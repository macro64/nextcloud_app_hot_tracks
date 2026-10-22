<?php

declare(strict_types=1);

namespace OCA\Strava\Settings;

use OCA\Strava\AppInfo\Application;
use OCA\Strava\Service\AppConfig;
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
		$error = $this->session->get('strava_admin_error');
		$notice = $this->session->get('strava_admin_notice');
		$this->session->remove('strava_admin_error');
		$this->session->remove('strava_admin_notice');

		return new TemplateResponse(Application::APP_ID, 'admin', [
			'authServiceUrl' => $this->appConfig->authServiceUrl(),
			'authClientId' => $this->appConfig->clientId(),
			'secretSet' => $this->appConfig->clientSecret() !== '',
			'redirectUri' => $this->appConfig->redirectUri(),
			'saveUrl' => $this->urlGenerator->linkToRoute('strava.admin.save'),
			'error' => is_string($error) ? $error : '',
			'notice' => is_string($notice) ? $notice : '',
		], '');
	}

	public function getSection(): string {
		return 'strava';
	}

	public function getPriority(): int {
		return 50;
	}
}
