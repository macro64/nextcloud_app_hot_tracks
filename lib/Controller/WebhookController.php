<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Db\ConnectionMapper;
use OCA\HotTracks\Exception\RateLimitException;
use OCA\HotTracks\Service\AppConfig;
use OCA\HotTracks\Service\SyncService;
use OCA\HotTracks\Service\WebhookEvent;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class WebhookController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private AppConfig $appConfig,
		private ConnectionMapper $connections,
		private SyncService $sync,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function receive(string $token): JSONResponse {
		if (!$this->appConfig->webhookTokenMatches($token)) {
			return new JSONResponse(['error' => 'Nicht gefunden.'], Http::STATUS_NOT_FOUND);
		}

		$payload = $this->payload();
		try {
			$this->request->throwDecodingExceptionIfAny();
		} catch (\JsonException) {
			return new JSONResponse(['error' => 'Ungültiger Inhalt.'], Http::STATUS_BAD_REQUEST);
		}

		$event = WebhookEvent::parse($payload);
		if ($event === null) {
			return new JSONResponse(['ok' => true]);
		}

		foreach ($this->connections->findByAthleteId($event->ownerId) as $connection) {
			$uid = $connection->getUid();
			try {
				if ($event->deauthorized) {
					$this->connections->deleteByUid($uid);
					$this->logger->info('Verbindung nach Widerruf gelöst', [
						'app' => 'hot_tracks',
						'uid' => $uid,
					]);
					continue;
				}
				if ($event->objectType === 'activity' && $event->aspect === 'delete') {
					$this->sync->removeActivity($uid, $event->objectId);
					continue;
				}
				if ($event->objectType === 'activity') {
					$outcome = $this->sync->pullActivity($uid, $event->objectId);
					$this->logger->info('Aktivität aus Webhook übernommen', [
						'app' => 'hot_tracks',
						'uid' => $uid,
						'activity' => $event->objectId,
						'outcome' => $outcome,
					]);
				}
			} catch (RateLimitException) {
				$this->logger->info('Limit der activity tracking app erreicht, Webhook wartet auf den stündlichen Abgleich', [
					'app' => 'hot_tracks',
				]);
				return new JSONResponse(['ok' => true, 'rateLimited' => true]);
			} catch (\Throwable $e) {
				$this->logger->warning('Webhook konnte nicht verarbeitet werden', [
					'app' => 'hot_tracks',
					'exception' => $e,
				]);
				return new JSONResponse(['error' => 'Verarbeiten fehlgeschlagen.'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		return new JSONResponse(['ok' => true]);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		$updates = $this->request->getParam('updates');
		return [
			'object_type' => (string)$this->request->getParam('object_type', ''),
			'object_id' => $this->request->getParam('object_id', 0),
			'aspect_type' => (string)$this->request->getParam('aspect_type', ''),
			'owner_id' => $this->request->getParam('owner_id', 0),
			'updates' => is_array($updates) ? $updates : null,
		];
	}
}
