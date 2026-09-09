<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

use OCA\DocSort\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The app's own Python environment: a virtual environment in the data directory with the
 * document reader (RapidOCR, pypdfium2) installed into it from the administration page, so
 * that nobody has to touch a terminal. Nothing outside the data directory is changed.
 *
 * The state of an installation lives in the app config key "install" (JSON), which the
 * administration page polls while the background job runs.
 */
final class PythonEnv
{
    public const STATE_IDLE = 'idle';
    public const STATE_QUEUED = 'queued';
    public const STATE_RUNNING = 'running';
    public const STATE_DONE = 'done';
    public const STATE_FAILED = 'failed';

    /** What the environment must be able to import once it is ready */
    public const CHECK = "import cv2, numpy, pypdfium2\ntry:\n    import rapidocr_onnxruntime\nexcept ImportError:\n    import rapidocr\nprint('ok')\n";

    private const STALE_AFTER = 3 * 3600;

    public function __construct(
        private IConfig $config,
        private IAppConfig $appConfig,
        private IClientService $clients,
        private LoggerInterface $logger,
    ) {}

    /** Where the environment lives: <data directory>/appdata_<instance>/docsort/python (or the administrator's choice). */
    public function dir(): string
    {
        $own = trim($this->appConfig->getValueString(Application::APP_ID, 'envDir', ''));
        if ('' !== $own) {
            return rtrim($own, '/');
        }
        $data = rtrim((string) $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT.'/data'), '/');
        $instance = (string) $this->config->getSystemValue('instanceid', '');

        return $data.'/appdata_'.$instance.'/'.Application::APP_ID.'/python';
    }

    public function python(): string
    {
        return $this->dir().'/venv/bin/python';
    }

    /** Ready = the environment exists and its last verification passed. */
    public function isInstalled(): bool
    {
        return is_executable($this->python()) && is_file($this->dir().'/ready');
    }

    /** @return array{state:string, step:string, log:string, started:int, finished:int, error:string} */
    public function installState(): array
    {
        $raw = json_decode($this->appConfig->getValueString(Application::APP_ID, 'install', '', true), true);
        $state = [
            'state' => self::STATE_IDLE,
            'step' => '',
            'log' => '',
            'started' => 0,
            'finished' => 0,
            'error' => '',
        ];
        if (\is_array($raw)) {
            foreach ($state as $k => $v) {
                if (\array_key_exists($k, $raw)) {
                    $state[$k] = \is_int($v) ? (int) $raw[$k] : (string) $raw[$k];
                }
            }
        }
        // a job that was killed (web cron, PHP time limit) must not look busy forever
        if (\in_array($state['state'], [self::STATE_QUEUED, self::STATE_RUNNING], true) && $state['started'] > 0 && time() - $state['started'] > self::STALE_AFTER) {
            $state['state'] = self::STATE_FAILED;
            $state['error'] = 'The installation did not finish; it was probably interrupted. Try again, or run occ docsort:install.';
            $this->saveState($state);
        }

        return $state;
    }

    /**
     * Everything the administration page and the setup check want to know.
     *
     * @return array{installed:bool, python:string, dir:string, systemPython:string, systemPythonVersion:string, canInstall:bool, reason:string, install:array}
     */
    public function status(): array
    {
        [$system, $version, $reason] = $this->systemPython();

        return [
            'installed' => $this->isInstalled(),
            'python' => $this->python(),
            'dir' => $this->dir(),
            'systemPython' => $system,
            'systemPythonVersion' => $version,
            'canInstall' => '' === $reason,
            'reason' => $reason,
            'install' => $this->installState(),
        ];
    }

    public function markQueued(): void
    {
        $this->saveState(['state' => self::STATE_QUEUED, 'step' => 'waiting for the background job', 'log' => '', 'started' => time(), 'finished' => 0, 'error' => '']);
    }

    /**
     * Builds the environment. Long: a virtual environment, then ~150 MB of packages.
     *
     * @param callable(string):void|null $progress every line of output
     *
     * @throws \RuntimeException with a message for the administrator
     */
    public function install(?callable $progress = null): void
    {
        $state = ['state' => self::STATE_RUNNING, 'step' => '', 'log' => '', 'started' => time(), 'finished' => 0, 'error' => ''];
        $lines = [];
        $note = function (string $line) use (&$state, &$lines, $progress): void {
            $line = rtrim($line);
            if ('' === $line) {
                return;
            }
            $lines[] = $line;
            $lines = \array_slice($lines, -60);
            $state['log'] = implode("\n", $lines);
            $this->saveState($state);
            if (null !== $progress) {
                $progress($line);
            }
        };
        $step = function (string $name) use (&$state, $note): void {
            $state['step'] = $name;
            $note('== '.$name);
        };

        try {
            [$system, $version, $reason] = $this->systemPython();
            if ('' !== $reason) {
                throw new \RuntimeException($reason);
            }
            $dir = $this->dir();
            $step('Preparing '.$dir);
            if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
                throw new \RuntimeException('Cannot create '.$dir.' — is the data directory writable?');
            }
            @unlink($dir.'/ready');
            foreach (['tmp', 'cache'] as $sub) {
                @mkdir($dir.'/'.$sub, 0770, true);
            }
            $env = $this->environment($dir);

            $step('Creating the virtual environment with '.$system.' ('.$version.')');
            $venv = $dir.'/venv';
            $create = new Process([$system, '-m', 'venv', '--clear', $venv], $dir, $env, null, 600);
            $create->run(fn ($type, $buffer) => $note($buffer));
            if (!$create->isSuccessful() || !is_executable($venv.'/bin/python')) {
                // Debian without python3-venv: the interpreter can make the environment but not put pip in it
                $note('venv with pip failed; making it without pip and fetching pip separately');
                $create = new Process([$system, '-m', 'venv', '--clear', '--without-pip', $venv], $dir, $env, null, 600);
                $create->run(fn ($type, $buffer) => $note($buffer));
                if (!$create->isSuccessful() || !is_executable($venv.'/bin/python')) {
                    throw new \RuntimeException('Python cannot create a virtual environment here: '.trim($create->getErrorOutput()));
                }
                $step('Fetching pip');
                $getPip = $dir.'/tmp/get-pip.py';
                $this->download('https://bootstrap.pypa.io/get-pip.py', $getPip);
                $pip = new Process([$venv.'/bin/python', $getPip, '--no-warn-script-location', '--disable-pip-version-check'], $dir, $env, null, 900);
                $pip->run(fn ($type, $buffer) => $note($buffer));
                @unlink($getPip);
                if (!$pip->isSuccessful()) {
                    throw new \RuntimeException('pip could not be installed: '.trim($pip->getErrorOutput()));
                }
            }

            $step('Installing the document reader (this downloads about 150 MB)');
            $setup = new Process([$venv.'/bin/python', '-u', \dirname(__DIR__, 2).'/src/setup_env.py'], $dir, $env, null, 3600);
            $setup->run(fn ($type, $buffer) => $note($buffer));
            if (!$setup->isSuccessful()) {
                throw new \RuntimeException('The installation failed; the last lines of its output are shown above.');
            }

            $step('Checking');
            $check = new Process([$venv.'/bin/python', '-c', self::CHECK], $dir, $env, null, 120);
            $check->run();
            if (!$check->isSuccessful()) {
                $err = trim($check->getErrorOutput());
                $hint = str_contains($err, 'failed to map segment') || str_contains($err, 'Operation not permitted')
                    ? ' The data directory seems to be mounted without execute permission (noexec); choose another directory for the environment in the settings.'
                    : '';
                throw new \RuntimeException('The reader was installed but cannot be loaded: '.$err.$hint);
            }
            file_put_contents($dir.'/ready', date('c')."\n".trim($check->getOutput())."\n");
            $this->deleteTree($dir.'/tmp');
            $state['state'] = self::STATE_DONE;
            $state['step'] = 'Installed';
            $state['finished'] = time();
            $this->saveState($state);
            $this->logger->info('docsort: the document reader was installed into '.$dir);
        } catch (\Throwable $e) {
            $state['state'] = self::STATE_FAILED;
            $state['error'] = $e->getMessage();
            $state['finished'] = time();
            $this->saveState($state);
            $this->logger->warning('docsort: installing the document reader failed: '.$e->getMessage());

            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /** Removes the environment (the administrator wants a clean start). */
    public function remove(): void
    {
        $this->deleteTree($this->dir());
        $this->saveState(['state' => self::STATE_IDLE, 'step' => '', 'log' => '', 'started' => 0, 'finished' => 0, 'error' => '']);
    }

    /**
     * The interpreter of the system, which makes the environment.
     *
     * @return array{0:string, 1:string, 2:string} path, version, reason why it cannot be used ('' = fine)
     */
    public function systemPython(): array
    {
        if (!\function_exists('proc_open')) {
            return ['', '', 'PHP cannot start programs here (proc_open is disabled), so the reader cannot be installed or used.'];
        }
        $own = trim($this->appConfig->getValueString(Application::APP_ID, 'systemPython', ''));
        $candidates = '' !== $own ? [$own] : [];
        $finder = new ExecutableFinder();
        foreach (['python3', 'python3.13', 'python3.12', 'python3.11', 'python3.10', 'python3.9', 'python3.8', 'python'] as $name) {
            $found = $finder->find($name, null, ['/usr/local/bin', '/usr/bin', '/bin', '/opt/homebrew/bin']);
            if (null !== $found && !\in_array($found, $candidates, true)) {
                $candidates[] = $found;
            }
        }
        $seen = '';
        foreach ($candidates as $candidate) {
            if (!is_executable($candidate)) {
                continue;
            }
            $probe = new Process([$candidate, '-c', 'import sys, venv; print("%d.%d" % sys.version_info[:2])'], null, null, null, 20);
            try {
                $probe->run();
            } catch (\Throwable) {
                continue;
            }
            $version = trim($probe->getOutput());
            if (!$probe->isSuccessful() || !preg_match('/^\d+\.\d+$/', $version)) {
                continue;
            }
            [$major, $minor] = array_map('intval', explode('.', $version));
            if ($major > 3 || ($major === 3 && $minor >= 8)) {
                return [$candidate, $version, ''];
            }
            $seen = $version;
        }

        return ['', $seen, '' === $seen
            ? 'No Python 3 was found on this server. Install it (for example "apt install python3 python3-venv") and come back here; nothing else is needed.'
            : 'Python '.$seen.' is too old; the reader needs Python 3.8 or newer.'];
    }

    /** @return array<string, string> */
    private function environment(string $dir): array
    {
        $env = [
            'HOME' => $dir,
            'TMPDIR' => $dir.'/tmp',
            'PIP_CACHE_DIR' => $dir.'/cache',
            'PIP_DISABLE_PIP_VERSION_CHECK' => '1',
            'PIP_NO_INPUT' => '1',
            'PYTHONUNBUFFERED' => '1',
            'PYTHONDONTWRITEBYTECODE' => '1',
            'LANG' => 'C.UTF-8',
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];
        $proxy = (string) $this->config->getSystemValue('proxy', '');
        if ('' !== $proxy) {
            $auth = (string) $this->config->getSystemValue('proxyuserpwd', '');
            $url = 'http://'.('' !== $auth ? $auth.'@' : '').$proxy;
            $env['HTTP_PROXY'] = $env['HTTPS_PROXY'] = $env['http_proxy'] = $env['https_proxy'] = $url;
        }

        return $env;
    }

    private function download(string $url, string $target): void
    {
        $response = $this->clients->newClient()->get($url, ['timeout' => 120]);
        if (200 !== $response->getStatusCode() || false === file_put_contents($target, $response->getBody())) {
            throw new \RuntimeException('Could not download '.$url);
        }
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $this->deleteTree($path.'/'.$entry);
        }
        @rmdir($path);
    }

    /** @param array<string, mixed> $state */
    private function saveState(array $state): void
    {
        $this->appConfig->setValueString(Application::APP_ID, 'install', (string) json_encode($state), true);
    }
}
