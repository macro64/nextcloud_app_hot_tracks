<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\Exception\RateLimitException;
use OCA\HotTracks\Exception\TrackingApiException;
use OCP\Http\Client\IClientService;

class TrackingClient {
	private const BASE = 'https://www.strava.com/api/v3';

	public function __construct(
		private IClientService $clientService,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listActivities(string $token, int $after, int $maxPages = 10): array {
		$all = [];
		for ($page = 1; $page <= $maxPages; $page++) {
			$batch = $this->listActivitiesPage($token, $after, $page);
			foreach ($batch as $row) {
				$all[] = $row;
			}
			if (count($batch) < 50) {
				break;
			}
		}
		return $all;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listActivitiesPage(string $token, int $after, int $page): array {
		$query = http_build_query([
			'after' => $after,
			'page' => $page,
			'per_page' => 50,
		]);
		$batch = $this->getJson($token, self::BASE . '/athlete/activities?' . $query);
		if (!array_is_list($batch)) {
			throw new TrackingApiException('Die activity tracking app hat keine Aktivitätsliste geliefert.');
		}
		$out = [];
		foreach ($batch as $row) {
			if (is_array($row)) {
				/** @var array<string, mixed> $row */
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function activity(string $token, int $activityId): array {
		$data = $this->getJson($token, self::BASE . '/activities/' . $activityId);
		if (array_is_list($data) || !isset($data['id'])) {
			throw new TrackingApiException('Die activity tracking app hat keine Aktivitätsdetails geliefert.');
		}
		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function streams(string $token, int $activityId): array {
		$query = http_build_query([
			'keys' => 'latlng,altitude,time',
			'key_by_type' => 'true',
		]);
		$data = $this->getJson($token, self::BASE . '/activities/' . $activityId . '/streams?' . $query);
		return $this->normalizeStreams($data);
	}

	/**
	 * @param array<mixed> $payload
	 * @return array<string, mixed>
	 */
	public function normalizeStreams(array $payload): array {
		if (isset($payload['latlng']) || isset($payload['altitude']) || isset($payload['time'])) {
			/** @var array<string, mixed> $payload */
			return $payload;
		}
		$out = [];
		foreach ($payload as $stream) {
			if (is_array($stream) && isset($stream['type']) && is_string($stream['type'])) {
				$out[$stream['type']] = $stream;
			}
		}
		return $out;
	}

	/**
	 * @return array<mixed>
	 */
	private function getJson(string $token, string $url): array {
		$client = $this->clientService->newClient();
		try {
			$response = $client->get($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Accept' => 'application/json',
				],
				'timeout' => 30,
			]);
		} catch (\Throwable $e) {
			$status = (int)$e->getCode();
			if ($status === 429) {
				throw new RateLimitException();
			}
			throw new TrackingApiException('Anfrage an die activity tracking app fehlgeschlagen.', $status, $e);
		}

		$decoded = json_decode($response->getBody(), true);
		if (!is_array($decoded)) {
			throw new TrackingApiException('Antwort der activity tracking app ist kein JSON.');
		}
		return $decoded;
	}
}
