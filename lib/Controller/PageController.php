<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Server;
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
		$root = dirname(__DIR__, 2);
		$cssVersion = is_file($root . '/css/hot_tracks-main.css') ? filemtime($root . '/css/hot_tracks-main.css') : time();
		$url = Server::get(IURLGenerator::class);
		Util::addHeader('link', [
			'rel' => 'stylesheet',
			'href' => $url->linkTo(Application::APP_ID, 'css/hot_tracks-main.css') . '?v=' . $cssVersion,
		]);
		return $response;
	}
}
