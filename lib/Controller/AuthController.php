<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Exception\AuthBrokerException;
use OCA\HotTracks\Service\AuthBroker;
use OCA\HotTracks\Service\TokenStore;
use OCA\HotTracks\Service\UserAuthConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;

class AuthController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private UserAuthConfig $userAuth,
		private AuthBroker $broker,
		private TokenStore $tokens,
		private ISession $session,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function start(): RedirectResponse {
		$page = $this->urlGenerator->linkToRoute('hot_tracks.page.index');
		if ($this->userId === null || $this->userId === '') {
			return new RedirectResponse($page);
		}
		if (!$this->userAuth->isConfigured($this->userId)) {
			return new RedirectResponse($page . '?error=config');
		}
		$state = bin2hex(random_bytes(16));
		$this->session->set('hot_tracks_oauth_state', $state);
		return new RedirectResponse($this->broker->authorizationUrl($this->userId, $state));
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function callback(): RedirectResponse {
		$page = $this->urlGenerator->linkToRoute('hot_tracks.page.index');
		$expected = (string)$this->session->get('hot_tracks_oauth_state');
		$this->session->remove('hot_tracks_oauth_state');
		$state = (string)$this->request->getParam('state', '');
		$code = (string)$this->request->getParam('code', '');

		if (
			$this->userId === null
			|| $this->userId === ''
			|| $expected === ''
			|| $state === ''
			|| !hash_equals($expected, $state)
			|| $code === ''
		) {
			return new RedirectResponse($page . '?error=oauth');
		}

		try {
			$tokens = $this->broker->exchangeCode($this->userId, $code);
			$this->tokens->saveFromTokenResponse($this->userId, $tokens);
		} catch (AuthBrokerException) {
			return new RedirectResponse($page . '?error=oauth');
		}

		return new RedirectResponse($page);
	}
}
