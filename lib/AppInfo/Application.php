<?php

declare(strict_types=1);

namespace OCA\DocSort\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap
{
    public const APP_ID = 'docsort';

    public function __construct()
    {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void
    {
        $context->registerSetupCheck(\OCA\DocSort\SetupChecks\ReaderCheck::class);
    }

    public function boot(IBootContext $context): void {}
}
