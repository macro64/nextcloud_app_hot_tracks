<?php

declare(strict_types=1);

namespace OCA\Strava\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setUid(string $uid)
 * @method string getUid()
 * @method void setAthleteId(int $athleteId)
 * @method int getAthleteId()
 * @method void setAthleteName(?string $athleteName)
 * @method string|null getAthleteName()
 * @method void setAccessToken(string $accessToken)
 * @method string getAccessToken()
 * @method void setRefreshToken(string $refreshToken)
 * @method string getRefreshToken()
 * @method void setExpiresAt(int $expiresAt)
 * @method int getExpiresAt()
 * @method void setScope(?string $scope)
 * @method string|null getScope()
 * @method void setLastSyncAfter(?int $lastSyncAfter)
 * @method int|null getLastSyncAfter()
 */
class Connection extends Entity {
	protected string $uid = '';
	protected int $athleteId = 0;
	protected ?string $athleteName = null;
	protected string $accessToken = '';
	protected string $refreshToken = '';
	protected int $expiresAt = 0;
	protected ?string $scope = null;
	protected ?int $lastSyncAfter = null;

	public function __construct() {
		$this->addType('athleteId', Types::BIGINT);
		$this->addType('expiresAt', Types::BIGINT);
		$this->addType('lastSyncAfter', Types::BIGINT);
	}
}
