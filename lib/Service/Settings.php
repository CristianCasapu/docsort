<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

use OCA\DocSort\AppInfo\Application;
use OCP\Config\IUserConfig;
use OCP\IAppConfig;

/** Per-person settings (what to sort, where to) and the administrator's (rules, Python). */
final class Settings
{
    public const USER_DEFAULTS = [
        'enabled' => false,
        // folders looked at, relative to the person's files; one per line
        'inbox' => 'Documente/De sortat',
        // where sorted documents go
        'destination' => 'Documente',
        // move | copy | tag (tag = leave the file where it is)
        'mode' => 'move',
        // Documents/<category>/<kind>/ (true) or Documents/<kind>/ (false)
        'byCategory' => true,
        // documents nobody recognised: leave (in the inbox) or unknown (into <destination>/<Unsorted>)
        'unknown' => 'leave',
        // ro | en for folder names and tags
        'language' => 'ro',
    ];

    public function __construct(private IAppConfig $config, private IUserConfig $userConfig, private PythonEnv $env) {}

    /** @return array<string, mixed> */
    public function forUser(string $uid): array
    {
        $out = [];
        foreach (self::USER_DEFAULTS as $key => $default) {
            $raw = $this->userConfig->getValueString($uid, Application::APP_ID, $key, \is_bool($default) ? ($default ? '1' : '0') : (string) $default);
            $out[$key] = \is_bool($default) ? ('1' === $raw || 'true' === $raw) : $raw;
        }

        return $out;
    }

    /** @param array<string, mixed> $values */
    public function setForUser(string $uid, array $values): array
    {
        foreach ($values as $key => $value) {
            if (!\array_key_exists($key, self::USER_DEFAULTS)) {
                continue;
            }
            $default = self::USER_DEFAULTS[$key];
            if (\is_bool($default)) {
                $raw = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } else {
                $raw = trim((string) $value);
                if ('mode' === $key && !\in_array($raw, ['move', 'copy', 'tag'], true)) {
                    $raw = 'move';
                }
                if ('unknown' === $key && !\in_array($raw, ['leave', 'unknown'], true)) {
                    $raw = 'leave';
                }
                if ('language' === $key && !\in_array($raw, ['ro', 'en'], true)) {
                    $raw = 'ro';
                }
                if (\in_array($key, ['inbox', 'destination'], true)) {
                    $raw = implode("\n", array_values(array_filter(array_map(static fn ($p) => trim(str_replace('\\', '/', $p), " /\t\r"), explode("\n", $raw)), static fn ($p) => '' !== $p && !str_contains($p, '..'))));
                    if ('destination' === $key) {
                        $raw = explode("\n", $raw)[0] ?? '';
                    }
                }
            }
            $this->userConfig->setValueString($uid, Application::APP_ID, $key, $raw);
        }

        return $this->forUser($uid);
    }

    /** People who switched sorting on. @return list<string> */
    public function enabledUsers(): array
    {
        return array_values(array_filter(
            $this->userConfig->searchUsersByValueString(Application::APP_ID, 'enabled', '1'),
            static fn ($uid) => \is_string($uid) && '' !== $uid,
        ));
    }

    /**
     * The rules in force: the administrator's, or the built-in ones.
     *
     * @return list<array{id:string, category:string, en:string, ro:string, min:int, keywords:array<string,int>}>
     */
    public function rules(): array
    {
        $raw = json_decode($this->config->getValueString(Application::APP_ID, 'rules', ''), true);
        if (\is_array($raw) && [] !== $raw) {
            $clean = self::cleanRules($raw);
            if ([] !== $clean) {
                return $clean;
            }
        }

        return DefaultRules::rules();
    }

    /** @param array<int, mixed> $raw */
    public function setRules(?array $raw): array
    {
        $clean = null === $raw ? [] : self::cleanRules($raw);
        $this->config->setValueString(Application::APP_ID, 'rules', [] === $clean ? '' : json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $this->rules();
    }

    public function customRules(): bool
    {
        return '' !== $this->config->getValueString(Application::APP_ID, 'rules', '');
    }

    /**
     * @param array<int, mixed> $raw
     *
     * @return list<array{id:string, category:string, en:string, ro:string, min:int, keywords:array<string,int>}>
     */
    private static function cleanRules(array $raw): array
    {
        $out = [];
        foreach ($raw as $rule) {
            if (!\is_array($rule) || !\is_string($rule['id'] ?? null) || !preg_match('/^[a-z0-9_]{1,64}$/', $rule['id'])) {
                continue;
            }
            $keywords = [];
            foreach ((array) ($rule['keywords'] ?? []) as $k => $w) {
                $k = Classifier::normalise((string) $k);
                if ('' !== $k) {
                    $keywords[$k] = max(1, min(20, (int) $w));
                }
            }
            if ([] === $keywords) {
                continue;
            }
            $category = (string) ($rule['category'] ?? 'other');
            $out[] = [
                'id' => $rule['id'],
                'category' => isset(DefaultRules::CATEGORIES[$category]) ? $category : 'other',
                'en' => mb_substr(trim((string) ($rule['en'] ?? $rule['id'])), 0, 64),
                'ro' => mb_substr(trim((string) ($rule['ro'] ?? $rule['en'] ?? $rule['id'])), 0, 64),
                'min' => max(1, min(100, (int) ($rule['min'] ?? 6))),
                'keywords' => $keywords,
            ];
        }

        return $out;
    }

    /**
     * The Python that reads the documents: the app's own environment (installed from the
     * administration page), else the administrator's choice, else the one of the Recognize
     * fork (RapidOCR lives there too), else whatever "python3" is.
     */
    public function pythonBinary(): string
    {
        if ($this->env->isInstalled()) {
            return $this->env->python();
        }
        $external = $this->externalReader();

        return '' !== $external ? $external : 'python3';
    }

    /** A reader that is not the app's own environment: the administrator's, or Recognize's. */
    public function hasExternalReader(): bool
    {
        return '' !== $this->externalReader();
    }

    /** Is there any Python to run the reader with? (whether it has RapidOCR shows only when it runs) */
    public function readerReady(): bool
    {
        if ($this->env->isInstalled() || $this->hasExternalReader()) {
            return true;
        }
        [$system] = $this->env->systemPython();

        return '' !== $system;
    }

    /** A writable HOME for the reader (the OCR package keeps a small cache there). */
    public function readerHome(): string
    {
        if ($this->env->isInstalled()) {
            return $this->env->dir();
        }
        $home = getenv('HOME');

        return \is_string($home) && '' !== $home && is_writable($home) ? $home : sys_get_temp_dir();
    }

    private function externalReader(): string
    {
        $own = trim($this->config->getValueString(Application::APP_ID, 'pythonBinary', ''));
        if ('' !== $own && is_executable($own)) {
            return $own;
        }
        $recognize = trim($this->config->getValueString('recognize', 'python_binary', '', lazy: true));
        if ('' !== $recognize && is_executable($recognize)) {
            return $recognize;
        }

        return '';
    }
}
