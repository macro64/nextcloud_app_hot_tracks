<?php

declare(strict_types=1);

namespace OCA\Strava\Controller;

use OCA\Strava\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$response = new TemplateResponse(Application::APP_ID, 'index');
		$policy = new ContentSecurityPolicy();
		$policy->addAllowedImageDomain('tile.openstreetmap.org');
		$response->setContentSecurityPolicy($policy);
		Util::addScript(Application::APP_ID, 'strava-main');
		Util::addStyle(Application::APP_ID, 'strava-main');
		return $response;
	}
}
