<?php

declare(strict_types=1);

namespace OCA\DocSort\Controller;

use OCA\DocSort\Service\Organizer;
use OCA\DocSort\Service\Settings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

final class PersonalController extends Controller
{
    public function __construct(string $appName, IRequest $request, private Settings $settings, private Organizer $organizer, private IUserSession $session)
    {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function config(): JSONResponse
    {
        return new JSONResponse($this->settings->forUser($this->uid()));
    }

    /** @param array<string, mixed> $values */
    #[NoAdminRequired]
    public function setConfig(array $values = []): JSONResponse
    {
        return new JSONResponse($this->settings->setForUser($this->uid(), $values));
    }

    /** Sort now, a handful of files, and tell what happened. */
    #[NoAdminRequired]
    public function scan(bool $dryRun = false): JSONResponse
    {
        $results = [];
        $stats = $this->organizer->scanUser($this->uid(), $dryRun, 15, static function (array $r) use (&$results): void {
            $results[] = $r;
        });

        return new JSONResponse(['stats' => $stats, 'results' => $results]);
    }

    #[NoAdminRequired]
    public function history(): JSONResponse
    {
        return new JSONResponse($this->organizer->history($this->uid()));
    }

    private function uid(): string
    {
        return (string) $this->session->getUser()?->getUID();
    }
}
