<?php

declare(strict_types=1);

namespace OCA\Strava\Service;

use OCA\Strava\Exception\RateLimitException;
use OCA\Strava\Exception\StravaApiException;
use OCP\Http\Client\IClientService;

class StravaClient {
	private const BASE = 'https://www.strava.com/api/v3';

	public function __construct(
		private IClientService $clientService,
	) {
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function listActivities(string $token, int $after): array {
		$all = [];
		for ($page = 1; $page <= 10; $page++) {
			$query = http_build_query([
				'after' => $after,
				'page' => $page,
				'per_page' => 50,
			]);
			$batch = $this->getJson($token, self::BASE . '/athlete/activities?' . $query);
			if (!array_is_list($batch)) {
				throw new StravaApiException('Strava hat keine Aktivitätsliste geliefert.');
			}
			foreach ($batch as $row) {
				if (is_array($row)) {
					/** @var array<string, mixed> $row */
					$all[] = $row;
				}
			}
			if (count($batch) < 50) {
				break;
			}
		}
		return $all;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function activity(string $token, int $stravaId): array {
		$data = $this->getJson($token, self::BASE . '/activities/' . $stravaId);
		if (array_is_list($data) || !isset($data['id'])) {
			throw new StravaApiException('Strava hat keine Aktivitätsdetails geliefert.');
		}
		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function streams(string $token, int $stravaId): array {
		$query = http_build_query([
			'keys' => 'latlng,altitude,time',
			'key_by_type' => 'true',
		]);
		$data = $this->getJson($token, self::BASE . '/activities/' . $stravaId . '/streams?' . $query);
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
			throw new StravaApiException('Strava-Anfrage fehlgeschlagen.', $status, $e);
		}

		$decoded = json_decode($response->getBody(), true);
		if (!is_array($decoded)) {
			throw new StravaApiException('Strava-Antwort ist kein JSON.');
		}
		return $decoded;
	}
}
