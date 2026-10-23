<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\AppInfo\Application;
use OCP\IConfig;

/**
 * Vom Benutzer gewählte Sportgruppen: Sync-Umfang und Filteroptionen in der App.
 */
class SportPreferences {
	private const KEY = 'heat_sports';

	public function __construct(
		private IConfig $config,
	) {
	}

	/**
	 * @return list<string>
	 */
	public function selected(string $uid): array {
		$raw = $this->config->getUserValue($uid, Application::APP_ID, self::KEY, '');
		if (!is_string($raw) || $raw === '') {
			return SportGroup::ALL_IDS;
		}
		$parts = array_values(array_filter(
			array_map('trim', explode(',', $raw)),
			static fn (string $id): bool => SportGroup::isValid($id),
		));
		return $parts !== [] ? $parts : SportGroup::ALL_IDS;
	}

	/**
	 * Filter für Liste/Heatmap: leerer Mode oder „all“ = alle in den Settings gewählten Gruppen.
	 * Ein einzelner Mode muss in den Settings aktiv sein.
	 *
	 * @return list<string>
	 */
	public function resolveFilter(string $uid, string $mode): array {
		return self::narrow($this->selected($uid), $mode);
	}

	/**
	 * @param list<string> $allowed
	 * @return list<string>
	 */
	public static function narrow(array $allowed, string $mode): array {
		$mode = trim($mode);
		if ($mode === '' || $mode === 'all') {
			return array_values($allowed);
		}
		if (SportGroup::isValid($mode) && in_array($mode, $allowed, true)) {
			return [$mode];
		}
		return array_values($allowed);
	}

	/**
	 * @param list<string>|array<int, string> $ids
	 * @return list<string>
	 */
	public function store(string $uid, array $ids): array {
		$clean = [];
		foreach ($ids as $id) {
			$id = trim((string)$id);
			if (SportGroup::isValid($id) && !in_array($id, $clean, true)) {
				$clean[] = $id;
			}
		}
		if ($clean === []) {
			$clean = SportGroup::ALL_IDS;
		}
		// Stabile Reihenfolge wie in SportGroup::ALL_IDS
		$ordered = [];
		foreach (SportGroup::ALL_IDS as $id) {
			if (in_array($id, $clean, true)) {
				$ordered[] = $id;
			}
		}
		$this->config->setUserValue($uid, Application::APP_ID, self::KEY, implode(',', $ordered));
		return $ordered;
	}

	public function allows(string $uid, string $sport): bool {
		foreach ($this->selected($uid) as $mode) {
			if (SportGroup::matches($mode, $sport)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return list<array{id: string, label: string, selected: bool}>
	 */
	public function formOptions(string $uid): array {
		$selected = $this->selected($uid);
		$options = [];
		foreach (SportGroup::ALL_IDS as $id) {
			$options[] = [
				'id' => $id,
				'label' => SportGroup::label($id),
				'selected' => in_array($id, $selected, true),
			];
		}
		return $options;
	}

	/**
	 * Optionen für die App-Selektoren (nur aktivierte Gruppen).
	 *
	 * @return list<array{id: string, label: string}>
	 */
	public function filterOptions(string $uid): array {
		$options = [];
		foreach ($this->selected($uid) as $id) {
			$options[] = [
				'id' => $id,
				'label' => SportGroup::label($id),
			];
		}
		return $options;
	}
}
