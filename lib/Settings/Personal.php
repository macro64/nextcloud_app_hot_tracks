<?php

declare(strict_types=1);

namespace OCA\HotTracks\Settings;

use OCA\HotTracks\AppInfo\Application;
use OCA\HotTracks\Db\ConnectionMapper;
use OCA\HotTracks\Service\GpxCopyFolder;
use OCA\HotTracks\Service\SportPreferences;
use OCA\HotTracks\Service\UserAuthConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Personal implements ISettings {
	public function __construct(
		private IUserSession $userSession,
		private ConnectionMapper $connections,
		private UserAuthConfig $userAuth,
		private GpxCopyFolder $copies,
		private SportPreferences $sports,
		private ISession $session,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';
		$connection = $uid !== '' ? $this->connections->findByUid($uid) : null;
		$error = $this->session->get('hot_tracks_personal_error');
		$notice = $this->session->get('hot_tracks_personal_notice');
		$this->session->remove('hot_tracks_personal_error');
		$this->session->remove('hot_tracks_personal_notice');

		$state = $uid !== ''
			? $this->userAuth->formState($uid)
			: [
				'mode' => UserAuthConfig::MODE_DIRECT,
				'clientId' => '',
				'proxyUrl' => '',
				'secretSet' => false,
				'hasPersonal' => false,
			];

		return new TemplateResponse(Application::APP_ID, 'personal', [
			'connected' => $connection !== null,
			'athleteName' => $connection?->getAthleteName() ?? '',
			'configured' => $uid !== '' && $this->userAuth->isConfigured($uid),
			'mode' => $state['mode'],
			'clientId' => $state['clientId'],
			'proxyUrl' => $state['proxyUrl'],
			'secretSet' => $state['secretSet'],
			'hasPersonal' => $state['hasPersonal'],
			'redirectUri' => $this->userAuth->redirectUri(),
			'callbackDomain' => $this->userAuth->callbackDomain(),
			'connectUrl' => $this->urlGenerator->linkToRoute('hot_tracks.auth.start'),
			'connectButtonSrc' => $this->urlGenerator->imagePath(Application::APP_ID, 'btn_strava_connect_with_orange.svg'),
			'saveUrl' => $this->urlGenerator->linkToRoute('hot_tracks.personal.save'),
			'copyFolderUrl' => $this->urlGenerator->linkToRoute('hot_tracks.personal.saveCopyFolder'),
			'gpxCopyFolder' => $uid !== '' ? $this->copies->path($uid) : '',
			'sportsUrl' => $this->urlGenerator->linkToRoute('hot_tracks.personal.saveSports'),
			'sportOptions' => $uid !== '' ? $this->sports->formOptions($uid) : [],
			'disconnectUrl' => $this->urlGenerator->linkToRoute('hot_tracks.personal.disconnect'),
			'notice' => is_string($notice) ? $notice : '',
			'error' => is_string($error) ? $error : '',
		], '');
	}

	public function getSection(): string {
		return 'hot_tracks';
	}

	public function getPriority(): int {
		return 50;
	}
}
