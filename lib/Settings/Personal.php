<?php

declare(strict_types=1);

namespace OCA\DocSort\Settings;

use OCA\DocSort\AppInfo\Application;
use OCA\DocSort\Service\DefaultRules;
use OCA\DocSort\Service\Organizer;
use OCA\DocSort\Service\PythonEnv;
use OCA\DocSort\Service\Settings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IUserSession;
use OCP\Settings\ISettings;

final class Personal implements ISettings
{
    public function __construct(private IInitialState $state, private Settings $settings, private Organizer $organizer, private IUserSession $session, private PythonEnv $env) {}

    public function getForm(): TemplateResponse
    {
        $uid = (string) $this->session->getUser()?->getUID();
        $names = [];
        foreach ($this->settings->rules() as $rule) {
            $names[$rule['id']] = ['en' => $rule['en'], 'ro' => $rule['ro'], 'category' => $rule['category']];
        }
        $this->state->provideInitialState('config', $this->settings->forUser($uid));
        $this->state->provideInitialState('history', $this->organizer->history($uid));
        $this->state->provideInitialState('kinds', $names);
        $this->state->provideInitialState('categories', DefaultRules::CATEGORIES);
        $this->state->provideInitialState('readerReady', $this->env->isInstalled() || $this->settings->hasExternalReader());

        return new TemplateResponse(Application::APP_ID, 'personal', [], '');
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
