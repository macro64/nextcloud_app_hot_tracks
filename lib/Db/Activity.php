<?php

declare(strict_types=1);

namespace OCA\Strava\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setUid(string $uid)
 * @method string getUid()
 * @method void setStravaId(int $stravaId)
 * @method int getStravaId()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setSportType(string $sportType)
 * @method string getSportType()
 * @method void setStartTime(int $startTime)
 * @method int getStartTime()
 * @method void setDistance(float $distance)
 * @method float getDistance()
 * @method void setMovingTime(int $movingTime)
 * @method int getMovingTime()
 * @method void setElapsedTime(int $elapsedTime)
 * @method int getElapsedTime()
 * @method void setElevation(float $elevation)
 * @method float getElevation()
 * @method void setFileId(?int $fileId)
 * @method int|null getFileId()
 * @method void setSyncedAt(int $syncedAt)
 * @method int getSyncedAt()
 */
class Activity extends Entity {
	protected string $uid = '';
	protected int $stravaId = 0;
	protected string $name = '';
	protected string $sportType = '';
	protected int $startTime = 0;
	protected float $distance = 0.0;
	protected int $movingTime = 0;
	protected int $elapsedTime = 0;
	protected float $elevation = 0.0;
	protected ?int $fileId = null;
	protected int $syncedAt = 0;

	public function __construct() {
		$this->addType('stravaId', Types::BIGINT);
		$this->addType('startTime', Types::BIGINT);
		$this->addType('distance', Types::FLOAT);
		$this->addType('movingTime', Types::INTEGER);
		$this->addType('elapsedTime', Types::INTEGER);
		$this->addType('elevation', Types::FLOAT);
		$this->addType('fileId', Types::BIGINT);
		$this->addType('syncedAt', Types::BIGINT);
	}
}
