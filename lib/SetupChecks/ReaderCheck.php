<?php

declare(strict_types=1);

namespace OCA\DocSort\SetupChecks;

use OCA\DocSort\Service\PythonEnv;
use OCA\DocSort\Service\Settings;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

/** Administration › Overview: is the document reader there? */
final class ReaderCheck implements ISetupCheck
{
    public function __construct(private IL10N $l, private PythonEnv $env, private Settings $settings) {}

    public function getCategory(): string
    {
        return 'system';
    }

    public function getName(): string
    {
        return $this->l->t('Document sorter reader');
    }

    public function run(): SetupResult
    {
        if ($this->env->isInstalled()) {
            return SetupResult::success($this->l->t('The document reader is installed (%s).', [$this->env->python()]));
        }
        if ($this->settings->hasExternalReader()) {
            return SetupResult::success($this->l->t('Documents are read with %s.', [$this->settings->pythonBinary()]));
        }
        $status = $this->env->status();
        if (!$status['canInstall']) {
            return SetupResult::warning($this->l->t('Pictures and scanned PDFs cannot be read: %s', [$status['reason']]));
        }

        return SetupResult::warning($this->l->t('The document reader is not installed, so pictures and PDFs are not read yet. Open Administration settings › Document sorter and press "Install the reader".'));
    }
}
