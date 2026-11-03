<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCP\Security\ICrypto;

/**
 * Encrypt / decrypt app secrets. Nextcloud ICrypto ciphertexts look like
 * hex|hex|hex|version — never treat those as legacy plaintext to re-encrypt.
 */
class SecretValue {
	public function __construct(
		private ICrypto $crypto,
	) {
	}

	public function seal(string $plain): string {
		return $this->crypto->encrypt($plain);
	}

	/**
	 * Returns the plaintext secret, or '' if missing / undecryptable ciphertext.
	 * Legacy plaintext values are returned as-is (and can be migrated by the caller).
	 */
	public function reveal(string $stored): string {
		if ($stored === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($stored);
		} catch (\Throwable) {
			if ($this->looksEncrypted($stored)) {
				return '';
			}
			return $stored;
		}
	}

	public function isLegacyPlaintext(string $stored): bool {
		return $stored !== '' && !$this->looksEncrypted($stored);
	}

	private function looksEncrypted(string $stored): bool {
		return (bool)preg_match('/^[0-9a-f]+\|[0-9a-f]+\|[0-9a-f]+\|\d+$/i', $stored);
	}
}
