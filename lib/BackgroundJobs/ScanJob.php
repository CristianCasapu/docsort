<?php

declare(strict_types=1);

namespace OCA\DocSort\BackgroundJobs;

use OCA\DocSort\Service\Organizer;
use OCA\DocSort\Service\Settings;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/** Every quarter of an hour: the inbox of everybody who switched sorting on, a few files at a time. */
final class ScanJob extends TimedJob
{
    public function __construct(ITimeFactory $time, private Settings $settings, private Organizer $organizer, private LoggerInterface $logger)
    {
        parent::__construct($time);
        $this->setInterval(15 * 60);
        $this->setAllowParallelRuns(false);
    }

    protected function run($argument): void
    {
        foreach ($this->settings->enabledUsers() as $uid) {
            try {
                $stats = $this->organizer->scanUser($uid, false, 25);
                if ($stats['files'] > 0) {
                    $this->logger->info('docsort: '.$uid.': '.json_encode($stats));
                }
            } catch (\Throwable $e) {
                $this->logger->error('docsort: '.$uid.': '.$e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
