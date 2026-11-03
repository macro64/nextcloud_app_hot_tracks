<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCP\ICache;
use OCP\ICacheFactory;

/**
 * Persists OAuth CSRF state outside the PHP session so the callback still
 * works if the browser drops the Nextcloud session cookie on the way back
 * from Strava / the auth broker.
 */
class OAuthStateStore {
	private const TTL_SECONDS = 600;

	private ICache $cache;

	public function __construct(ICacheFactory $cacheFactory) {
		$this->cache = $cacheFactory->createDistributed('hot_tracks_oauth');
	}

	public function put(string $state, string $uid): void {
		$this->cache->set($state, $uid, self::TTL_SECONDS);
	}

	public function consume(string $state): ?string {
		$uid = $this->cache->get($state);
		$this->cache->remove($state);
		if (!is_string($uid) || $uid === '') {
			return null;
		}
		return $uid;
	}
}
