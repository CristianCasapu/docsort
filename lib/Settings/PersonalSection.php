<?php

declare(strict_types=1);

namespace OCA\DocSort\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final class PersonalSection implements IIconSection
{
    public function __construct(private IL10N $l, private IURLGenerator $urls) {}

    public function getID(): string
    {
        return 'docsort';
    }

    public function getName(): string
    {
        return $this->l->t('Document sorter');
    }

    public function getPriority(): int
    {
        return 62;
    }

    public function getIcon(): string
    {
        return $this->urls->imagePath('docsort', 'app-dark.svg');
    }
}
