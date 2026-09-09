<?php

declare(strict_types=1);

namespace OCA\DocSort\Settings;

use OCA\DocSort\AppInfo\Application;
use OCA\DocSort\Service\DefaultRules;
use OCA\DocSort\Service\PythonEnv;
use OCA\DocSort\Service\Settings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

final class Admin implements ISettings
{
    public function __construct(private IInitialState $state, private Settings $settings, private PythonEnv $env) {}

    public function getForm(): TemplateResponse
    {
        $this->state->provideInitialState('rules', $this->settings->rules());
        $this->state->provideInitialState('custom', $this->settings->customRules());
        $this->state->provideInitialState('categories', DefaultRules::CATEGORIES);
        $this->state->provideInitialState('python', $this->settings->pythonBinary());
        $this->state->provideInitialState('reader', ['reader' => $this->env->status(), 'external' => $this->settings->hasExternalReader(), 'python' => $this->settings->pythonBinary()]);

        return new TemplateResponse(Application::APP_ID, 'admin', [], '');
    }

    public function getSection(): string
    {
        return 'docsort';
    }

    public function getPriority(): int
    {
        return 10;
    }
}
