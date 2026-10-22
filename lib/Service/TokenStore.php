<?php

declare(strict_types=1);

namespace OCA\Strava\Service;

use OCA\Strava\Db\Connection;
use OCA\Strava\Db\ConnectionMapper;
use OCP\Security\ICrypto;

class TokenStore {
	public function __construct(
		private ConnectionMapper $connections,
		private ICrypto $crypto,
		private AuthBroker $broker,
	) {
	}

	/**
	 * @param array{access_token: string, refresh_token: string, expires_at: int|string, scope?: string, athlete?: array{id: int|string, firstname?: string, lastname?: string}} $data
	 */
	public function saveFromTokenResponse(string $uid, array $data): void {
		$existing = $this->connections->findByUid($uid);
		$entity = $existing ?? new Connection();
		$entity->setUid($uid);
		if (isset($data['athlete']['id'])) {
			$entity->setAthleteId((int)$data['athlete']['id']);
			$firstname = trim((string)($data['athlete']['firstname'] ?? ''));
			$lastname = trim((string)($data['athlete']['lastname'] ?? ''));
			$name = trim($firstname . ' ' . $lastname);
			$entity->setAthleteName($name !== '' ? $name : null);
		}
		$this->applyTokens($entity, (string)$data['access_token'], (string)$data['refresh_token'], (int)$data['expires_at']);
		if (isset($data['scope']) && is_string($data['scope']) && $data['scope'] !== '') {
			$entity->setScope($data['scope']);
		}
		if ($existing === null) {
			$this->connections->insert($entity);
			return;
		}
		$this->connections->update($entity);
	}

	public function accessToken(Connection $connection): string {
		if ($connection->getExpiresAt() > time() + 120) {
			return $this->crypto->decrypt($connection->getAccessToken());
		}
		$refreshToken = $this->crypto->decrypt($connection->getRefreshToken());
		$refreshed = $this->broker->refresh($refreshToken);
		$this->applyTokens(
			$connection,
			$refreshed['access_token'],
			$refreshed['refresh_token'],
			$refreshed['expires_at'],
		);
		$this->connections->update($connection);
		return $refreshed['access_token'];
	}

	private function applyTokens(Connection $entity, string $accessToken, string $refreshToken, int $expiresAt): void {
		$entity->setAccessToken($this->crypto->encrypt($accessToken));
		$entity->setRefreshToken($this->crypto->encrypt($refreshToken));
		$entity->setExpiresAt($expiresAt);
	}
}
