<?php

declare(strict_types=1);

namespace OCA\HotTracks\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l,
	) {
	}

	public function getID(): string {
		return 'hot_tracks';
	}

	public function getName(): string {
		return $this->l->t('Hot Tracks');
	}

	public function getPriority(): int {
		return 80;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('hot_tracks', 'app.svg');
	}
}
