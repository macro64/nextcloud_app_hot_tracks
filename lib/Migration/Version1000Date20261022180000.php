<?php

declare(strict_types=1);

namespace OCA\HotTracks\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261022180000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('hot_tracks_connections')) {
			$table = $schema->createTable('hot_tracks_connections');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('athlete_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('athlete_name', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('access_token', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('refresh_token', Types::TEXT, [
				'notnull' => true,
			]);
			$table->addColumn('expires_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('scope', Types::STRING, [
				'notnull' => false,
				'length' => 512,
			]);
			$table->addColumn('last_sync_after', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['uid'], 'hot_tracks_conn_uid');
		}

		if (!$schema->hasTable('hot_tracks_activities')) {
			$table = $schema->createTable('hot_tracks_activities');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
				'unsigned' => true,
			]);
			$table->addColumn('uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('activity_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('name', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('sport_type', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('start_time', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('distance', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('moving_time', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('elapsed_time', Types::INTEGER, [
				'notnull' => true,
			]);
			$table->addColumn('elevation', Types::FLOAT, [
				'notnull' => true,
			]);
			$table->addColumn('file_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->addColumn('synced_at', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['uid', 'activity_id'], 'hot_tracks_act_uid_id');
			$table->addIndex(['uid', 'start_time'], 'hot_tracks_act_uid_time');
		}

		return $schema;
	}
}
