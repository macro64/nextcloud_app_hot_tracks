<?php

declare(strict_types=1);

namespace OCA\HotTracks\Settings;

use OCA\HotTracks\AppInfo\Application;
use OCA\HotTracks\Service\AppConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private AppConfig $appConfig,
		private ISession $session,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getForm(): TemplateResponse {
		$error = $this->session->get('hot_tracks_admin_error');
		$notice = $this->session->get('hot_tracks_admin_notice');
		$this->session->remove('hot_tracks_admin_error');
		$this->session->remove('hot_tracks_admin_notice');

		return new TemplateResponse(Application::APP_ID, 'admin', [
			'authServiceUrl' => $this->appConfig->authServiceUrl(),
			'authClientId' => $this->appConfig->clientId(),
			'secretSet' => $this->appConfig->clientSecret() !== '',
			'redirectUri' => $this->appConfig->redirectUri(),
			'webhookUrl' => $this->appConfig->webhookUrl(),
			'saveUrl' => $this->urlGenerator->linkToRoute('hot_tracks.admin.save'),
			'error' => is_string($error) ? $error : '',
			'notice' => is_string($notice) ? $notice : '',
		], '');
	}

	public function getSection(): string {
		return 'hot_tracks';
	}

	public function getPriority(): int {
		return 50;
	}
}
