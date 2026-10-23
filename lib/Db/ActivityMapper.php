<?php

declare(strict_types=1);

namespace OCA\HotTracks\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @extends QBMapper<Activity> */
class ActivityMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'hot_tracks_activities', Activity::class);
	}

	public function findByActivityId(string $uid, int $activityId): ?Activity {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('activity_id', $qb->createNamedParameter($activityId, IQueryBuilder::PARAM_INT)));
		try {
			/** @var Activity $entity */
			$entity = $this->findEntity($qb);
			return $entity;
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return list<Activity>
	 */
	public function findBetween(string $uid, int $from, int $to): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('start_time', $qb->createNamedParameter($from, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('start_time', $qb->createNamedParameter($to, IQueryBuilder::PARAM_INT)))
			->orderBy('start_time', 'DESC');
		/** @var list<Activity> $rows */
		$rows = $this->findEntities($qb);
		return $rows;
	}

	/**
	 * Neueste Aktivitäten zuerst. Die Übersicht listet damit den gespeicherten Verlauf,
	 * nicht nur das 7-Tage-Fenster.
	 *
	 * @return list<Activity>
	 */
	public function findByUid(string $uid, int $limit = 1000): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->orderBy('start_time', 'DESC')
			->setMaxResults(max(1, $limit));
		/** @var list<Activity> $rows */
		$rows = $this->findEntities($qb);
		return $rows;
	}

	public function countByUid(string $uid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*'))
			->from($this->getTableName())
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));
		$result = $qb->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}
}
