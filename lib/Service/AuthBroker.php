<?php

declare(strict_types=1);

namespace OCA\Strava\Service;

use OCA\Strava\Exception\AuthBrokerException;
use OCP\Http\Client\IClientService;

class AuthBroker {
	public function __construct(
		private AppConfig $appConfig,
		private IClientService $clientService,
	) {
	}

	public function authorizationUrl(string $state): string {
		$query = http_build_query([
			'client_id' => $this->appConfig->clientId(),
			'redirect_uri' => $this->appConfig->redirectUri(),
			'state' => $state,
		], '', '&', PHP_QUERY_RFC3986);
		return $this->appConfig->authServiceUrl() . '/oauth/strava?' . $query;
	}

	/**
	 * @return array{access_token: string, refresh_token: string, expires_at: int, scope?: string, athlete: array{id: int, firstname?: string, lastname?: string}}
	 */
	public function exchangeCode(string $code): array {
		$data = $this->post([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->appConfig->clientId(),
			'client_secret' => $this->appConfig->clientSecret(),
			'redirect_uri' => $this->appConfig->redirectUri(),
		]);
		if (!isset($data['access_token'], $data['refresh_token'], $data['expires_at'], $data['athlete']['id'])) {
			throw new AuthBrokerException('Der Auth-Dienst hat keine vollständigen Tokens geliefert.');
		}
		return $data;
	}

	/**
	 * @return array{access_token: string, refresh_token: string, expires_at: int}
	 */
	public function refresh(string $refreshToken): array {
		$data = $this->post([
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id' => $this->appConfig->clientId(),
			'client_secret' => $this->appConfig->clientSecret(),
		]);
		if (!isset($data['access_token'], $data['refresh_token'], $data['expires_at'])) {
			throw new AuthBrokerException('Der Auth-Dienst hat keine neuen Tokens geliefert.');
		}
		return [
			'access_token' => (string)$data['access_token'],
			'refresh_token' => (string)$data['refresh_token'],
			'expires_at' => (int)$data['expires_at'],
		];
	}

	/**
	 * @param array<string, string> $payload
	 * @return array<string, mixed>
	 */
	private function post(array $payload): array {
		$client = $this->clientService->newClient();
		try {
			$response = $client->post($this->appConfig->authServiceUrl() . '/oauth/token', [
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
				],
				'body' => json_encode($payload, JSON_THROW_ON_ERROR),
				'timeout' => 20,
			]);
		} catch (\Throwable $e) {
			throw new AuthBrokerException('Der Auth-Dienst ist nicht erreichbar.', (int)$e->getCode(), $e);
		}

		$decoded = json_decode($response->getBody(), true);
		if (!is_array($decoded)) {
			throw new AuthBrokerException('Der Auth-Dienst hat kein JSON geliefert.');
		}
		/** @var array<string, mixed> $decoded */
		return $decoded;
	}
}
