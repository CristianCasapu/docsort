<?php

declare(strict_types=1);

namespace OCA\DocSort\BackgroundJobs;

use OCA\DocSort\Service\PythonEnv;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/** Installs the document reader, queued from the administration page. */
final class InstallJob extends QueuedJob
{
    public function __construct(ITimeFactory $time, private PythonEnv $env, private LoggerInterface $logger)
    {
        parent::__construct($time);
        $this->setAllowParallelRuns(false);
    }

    protected function run($argument): void
    {
        @set_time_limit(0);

        try {
            $this->env->install();
        } catch (\Throwable $e) {
            // already recorded in the install state for the administration page
            $this->logger->debug('docsort: install job: '.$e->getMessage());
        }
    }
}
