<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Service\AppConfig;
use OCA\HotTracks\Service\AuthServiceUrl;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
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
		private IL10N $l,
	) {
		parent::__construct($appName, $request);
	}

	public function save(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/admin/hot_tracks');
		$url = trim((string)$this->request->getParam('authServiceUrl', ''));
		$clientId = trim((string)$this->request->getParam('authClientId', ''));
		$secret = (string)$this->request->getParam('authClientSecret', '');

		if ($url === '' && $clientId === '' && $secret === '' && $this->appConfig->clientSecret() === '') {
			$this->session->set('hot_tracks_admin_notice', $this->l->t('No proxy configured — people connect directly via Strava.'));
			return new RedirectResponse($target);
		}

		$urlError = AuthServiceUrl::validate($url);
		if ($urlError !== '') {
			$this->session->set('hot_tracks_admin_error', $this->l->t($urlError));
			return new RedirectResponse($target);
		}
		if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $clientId)) {
			$this->session->set('hot_tracks_admin_error', $this->l->t('The client ID may only contain letters, digits, underscore and hyphen.'));
			return new RedirectResponse($target);
		}
		if ($secret === '' && $this->appConfig->clientSecret() === '') {
			$this->session->set('hot_tracks_admin_error', $this->l->t('Please enter the client secret.'));
			return new RedirectResponse($target);
		}

		$this->appConfig->save($url, $clientId, $secret !== '' ? $secret : null);
		$this->session->set('hot_tracks_admin_notice', $this->l->t('Proxy saved.'));
		return new RedirectResponse($target);
	}
}
