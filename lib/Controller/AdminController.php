<?php

declare(strict_types=1);

namespace OCA\DocSort\Controller;

use OCA\DocSort\BackgroundJobs\InstallJob;
use OCA\DocSort\Service\Classifier;
use OCA\DocSort\Service\DefaultRules;
use OCA\DocSort\Service\PythonEnv;
use OCA\DocSort\Service\Settings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;

final class AdminController extends Controller
{
    public function __construct(string $appName, IRequest $request, private Settings $settings, private PythonEnv $env, private IJobList $jobs)
    {
        parent::__construct($appName, $request);
    }

    /** The state of the document reader, polled by the administration page while it installs. */
    public function reader(): JSONResponse
    {
        return new JSONResponse($this->readerPayload());
    }

    /** Queue the installation; the background job does the long work. */
    public function installReader(): JSONResponse
    {
        $status = $this->env->status();
        if (!$status['canInstall']) {
            return new JSONResponse(['error' => $status['reason']] + $this->readerPayload(), 400);
        }
        if (\in_array($status['install']['state'], [PythonEnv::STATE_QUEUED, PythonEnv::STATE_RUNNING], true)) {
            return new JSONResponse($this->readerPayload());
        }
        $this->env->markQueued();
        $this->jobs->add(InstallJob::class);

        return new JSONResponse($this->readerPayload());
    }

    /** Throw the environment away (a fresh installation afterwards). */
    public function removeReader(): JSONResponse
    {
        $this->env->remove();

        return new JSONResponse($this->readerPayload());
    }

    /** @return array<string, mixed> */
    private function readerPayload(): array
    {
        return ['reader' => $this->env->status(), 'external' => $this->settings->hasExternalReader(), 'python' => $this->settings->pythonBinary()];
    }

    public function rules(): JSONResponse
    {
        return new JSONResponse(['rules' => $this->settings->rules(), 'custom' => $this->settings->customRules(), 'defaults' => DefaultRules::rules(), 'categories' => DefaultRules::CATEGORIES]);
    }

    /** @param array<int, mixed>|null $rules null or [] = back to the built-in rules */
    public function setRules(?array $rules = null): JSONResponse
    {
        $saved = $this->settings->setRules([] === $rules ? null : $rules);

        return new JSONResponse(['rules' => $saved, 'custom' => $this->settings->customRules()]);
    }

    /** What would this text be sorted as? */
    public function test(string $text = ''): JSONResponse
    {
        return new JSONResponse(Classifier::classify($text, $this->settings->rules()));
    }
}
