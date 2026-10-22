<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Service\AppConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;

class AdminController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppConfig $appConfig,
		private ISession $session,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	public function save(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/admin/hot_tracks');
		$url = trim((string)$this->request->getParam('authServiceUrl', ''));
		$clientId = trim((string)$this->request->getParam('authClientId', ''));
		$secret = (string)$this->request->getParam('authClientSecret', '');

		if (!preg_match('#^https://#', $url) && !preg_match('#^http://(localhost|127\.0\.0\.1)(:\d+)?(/|$)#', $url)) {
			$this->session->set('hot_tracks_admin_error', 'Die Auth-URL muss HTTPS sein.');
			return new RedirectResponse($target);
		}
		if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $clientId)) {
			$this->session->set('hot_tracks_admin_error', 'Die Client-ID darf nur Buchstaben, Ziffern, Unterstrich und Bindestrich enthalten.');
			return new RedirectResponse($target);
		}

		$this->appConfig->save($url, $clientId, $secret !== '' ? $secret : null);
		$this->session->set('hot_tracks_admin_notice', 'Gespeichert.');
		return new RedirectResponse($target);
	}
}
