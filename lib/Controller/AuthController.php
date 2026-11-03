<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Exception\AuthBrokerException;
use OCA\HotTracks\Service\AuthBroker;
use OCA\HotTracks\Service\OAuthStateStore;
use OCA\HotTracks\Service\TokenStore;
use OCA\HotTracks\Service\UserAuthConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AuthController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private UserAuthConfig $userAuth,
		private AuthBroker $broker,
		private TokenStore $tokens,
		private OAuthStateStore $oauthStates,
		private ISession $session,
		private IURLGenerator $urlGenerator,
		private IUserSession $userSession,
		private LoggerInterface $logger,
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
		$this->oauthStates->put($state, $this->userId);
		$this->logger->info('Hot Tracks OAuth start', [
			'app' => 'hot_tracks',
			'uid' => $this->userId,
			'redirect_uri' => $this->userAuth->redirectUri(),
			'proxy' => $this->userAuth->usesProxy($this->userId),
		]);
		return new RedirectResponse($this->broker->authorizationUrl($this->userId, $state));
	}

	/**
	 * Public so the callback still runs if the session cookie is missing after
	 * the round-trip through Strava / the auth broker. User binding comes from
	 * the one-time state stored in {@see OAuthStateStore}.
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	public function callback(): RedirectResponse {
		$page = $this->urlGenerator->linkToRoute('hot_tracks.page.index');
		$login = $this->urlGenerator->linkToRoute('core.login.showloginform', [
			'redirect_url' => $page,
		]);
		$state = (string)$this->request->getParam('state', '');
		$code = (string)$this->request->getParam('code', '');
		$error = (string)$this->request->getParam('error', '');

		if ($error !== '' || $code === '' || $state === '') {
			$this->logger->warning('Hot Tracks OAuth callback missing code/state', [
				'app' => 'hot_tracks',
				'error' => $error,
			]);
			return new RedirectResponse($page . '?error=oauth');
		}

		$uid = $this->oauthStates->consume($state);
		if ($uid === null) {
			$expected = (string)$this->session->get('hot_tracks_oauth_state');
			$this->session->remove('hot_tracks_oauth_state');
			$sessionUid = $this->userId;
			if (
				$expected !== ''
				&& hash_equals($expected, $state)
				&& is_string($sessionUid)
				&& $sessionUid !== ''
			) {
				$uid = $sessionUid;
			}
		} else {
			$this->session->remove('hot_tracks_oauth_state');
		}

		if ($uid === null) {
			$this->logger->warning('Hot Tracks OAuth state unknown or expired', [
				'app' => 'hot_tracks',
			]);
			return new RedirectResponse($page . '?error=oauth');
		}

		try {
			$tokens = $this->broker->exchangeCode($uid, $code);
			$this->tokens->saveFromTokenResponse($uid, $tokens);
		} catch (AuthBrokerException $e) {
			$this->logger->error('Hot Tracks OAuth token exchange failed', [
				'app' => 'hot_tracks',
				'uid' => $uid,
				'redirect_uri' => $this->userAuth->redirectUri(),
				'message' => $e->getMessage(),
			]);
			return new RedirectResponse($page . '?error=oauth');
		}

		$loggedIn = $this->userSession->isLoggedIn()
			&& $this->userSession->getUser()?->getUID() === $uid;
		if (!$loggedIn) {
			return new RedirectResponse($login);
		}

		return new RedirectResponse($page);
	}
}
