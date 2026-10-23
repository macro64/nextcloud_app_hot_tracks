<?php

declare(strict_types=1);

namespace OCA\HotTracks\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<Connection> */
class ConnectionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'hot_tracks_connections', Connection::class);
	}

	public function findByUid(string $uid): ?Connection {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		try {
			/** @var Connection $entity */
			$entity = $this->findEntity($qb);
			return $entity;
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return list<Connection>
	 */
	public function findByAthleteId(int $athleteId): array {
		if ($athleteId <= 0) {
			return [];
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('athlete_id', $qb->createNamedParameter($athleteId, IQueryBuilder::PARAM_INT)));
		/** @var list<Connection> $rows */
		$rows = $this->findEntities($qb);
		return $rows;
	}

	/**
	 * @return list<Connection>
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		/** @var list<Connection> $rows */
		$rows = $this->findEntities($qb);
		return $rows;
	}

	public function deleteByUid(string $uid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_STR)));
		$qb->executeStatement();
	}
}
