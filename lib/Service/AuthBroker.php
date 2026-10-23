<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCA\HotTracks\Exception\AuthBrokerException;
use OCP\Http\Client\IClientService;

class AuthBroker {
	private const STRAVA_AUTHORIZE = 'https://www.strava.com/oauth/authorize';
	private const STRAVA_TOKEN = 'https://www.strava.com/oauth/token';
	private const SCOPE = 'read,activity:read_all';

	public function __construct(
		private UserAuthConfig $userAuth,
		private IClientService $clientService,
	) {
	}

	public function authorizationUrl(string $uid, string $state): string {
		$clientId = $this->userAuth->clientId($uid);
		$redirect = $this->userAuth->redirectUri();
		if ($this->userAuth->usesProxy($uid)) {
			$query = http_build_query([
				'client_id' => $clientId,
				'redirect_uri' => $redirect,
				'state' => $state,
			], '', '&', PHP_QUERY_RFC3986);
			return $this->userAuth->proxyUrl($uid) . '/oauth/strava?' . $query;
		}

		$query = http_build_query([
			'client_id' => $clientId,
			'redirect_uri' => $redirect,
			'response_type' => 'code',
			'approval_prompt' => 'auto',
			'scope' => self::SCOPE,
			'state' => $state,
		], '', '&', PHP_QUERY_RFC3986);
		return self::STRAVA_AUTHORIZE . '?' . $query;
	}

	/**
	 * @return array{access_token: string, refresh_token: string, expires_at: int, scope?: string, athlete: array{id: int, firstname?: string, lastname?: string}}
	 */
	public function exchangeCode(string $uid, string $code): array {
		$data = $this->tokenRequest($uid, [
			'grant_type' => 'authorization_code',
			'code' => $code,
			'client_id' => $this->userAuth->clientId($uid),
			'client_secret' => $this->userAuth->clientSecret($uid),
			'redirect_uri' => $this->userAuth->redirectUri(),
		]);
		if (!isset($data['access_token'], $data['refresh_token'], $data['expires_at'], $data['athlete']['id'])) {
			throw new AuthBrokerException('Incomplete tokens returned.');
		}
		return $data;
	}

	/**
	 * @return array{access_token: string, refresh_token: string, expires_at: int}
	 */
	public function refresh(string $uid, string $refreshToken): array {
		$data = $this->tokenRequest($uid, [
			'grant_type' => 'refresh_token',
			'refresh_token' => $refreshToken,
			'client_id' => $this->userAuth->clientId($uid),
			'client_secret' => $this->userAuth->clientSecret($uid),
		]);
		if (!isset($data['access_token'], $data['refresh_token'], $data['expires_at'])) {
			throw new AuthBrokerException('No new tokens returned.');
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
	private function tokenRequest(string $uid, array $payload): array {
		$client = $this->clientService->newClient();
		$usesProxy = $this->userAuth->usesProxy($uid);
		$url = $usesProxy
			? $this->userAuth->proxyUrl($uid) . '/oauth/token'
			: self::STRAVA_TOKEN;

		try {
			if ($usesProxy) {
				$response = $client->post($url, [
					'headers' => [
						'Content-Type' => 'application/json',
						'Accept' => 'application/json',
					],
					'body' => json_encode($payload, JSON_THROW_ON_ERROR),
					'timeout' => 20,
				]);
			} else {
				$response = $client->post($url, [
					'headers' => [
						'Accept' => 'application/json',
					],
					'body' => $payload,
					'timeout' => 20,
				]);
			}
		} catch (\Throwable $e) {
			throw new AuthBrokerException(
				$usesProxy ? 'The auth service is unreachable.' : 'Strava is unreachable.',
				(int)$e->getCode(),
				$e,
			);
		}

		$decoded = json_decode($response->getBody(), true);
		if (!is_array($decoded)) {
			throw new AuthBrokerException('The response was not JSON.');
		}
		/** @var array<string, mixed> $decoded */
		return $decoded;
	}
}
