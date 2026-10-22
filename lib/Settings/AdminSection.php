<?php

declare(strict_types=1);

namespace OCA\Strava\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'strava';
	}

	public function getName(): string {
		return 'Strava';
	}

	public function getPriority(): int {
		return 80;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('strava', 'app.svg');
	}
}
