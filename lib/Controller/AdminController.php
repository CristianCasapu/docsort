<?php

declare(strict_types=1);

namespace OCA\DocSort\Controller;

use OCA\DocSort\Service\Classifier;
use OCA\DocSort\Service\DefaultRules;
use OCA\DocSort\Service\Settings;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

final class AdminController extends Controller
{
    public function __construct(string $appName, IRequest $request, private Settings $settings)
    {
        parent::__construct($appName, $request);
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
