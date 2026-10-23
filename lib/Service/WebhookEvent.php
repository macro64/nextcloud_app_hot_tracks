<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

/**
 * Ereignis, das der Auth-Dienst aus der activity tracking app weiterleitet.
 */
final class WebhookEvent {
	private function __construct(
		public readonly string $objectType,
		public readonly string $aspect,
		public readonly int $objectId,
		public readonly int $ownerId,
		public readonly bool $deauthorized,
	) {
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	public static function parse(array $payload): ?self {
		$objectType = strtolower(trim((string)($payload['object_type'] ?? '')));
		$aspect = strtolower(trim((string)($payload['aspect_type'] ?? '')));
		$objectId = (int)($payload['object_id'] ?? 0);
		$ownerId = (int)($payload['owner_id'] ?? 0);
		if (!in_array($objectType, ['activity', 'athlete'], true)) {
			return null;
		}
		if (!in_array($aspect, ['create', 'update', 'delete'], true)) {
			return null;
		}
		if ($ownerId <= 0) {
			return null;
		}
		if ($objectType === 'activity' && $objectId <= 0) {
			return null;
		}

		$deauthorized = false;
		$updates = $payload['updates'] ?? null;
		if (is_array($updates) && array_key_exists('authorized', $updates)) {
			$authorized = $updates['authorized'];
			$deauthorized = $authorized === false
				|| (is_string($authorized) && strtolower($authorized) === 'false');
		}
		if ($objectType === 'athlete' && !$deauthorized) {
			return null;
		}

		return new self($objectType, $aspect, $objectId, $ownerId, $deauthorized);
	}
}
