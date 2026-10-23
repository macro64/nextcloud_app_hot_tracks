<?php

declare(strict_types=1);

namespace OCA\HotTracks\Controller;

use OCA\HotTracks\Db\ActivityMapper;
use OCA\HotTracks\Db\ConnectionMapper;
use OCA\HotTracks\Service\ActivityFiles;
use OCA\HotTracks\Service\AuthServiceUrl;
use OCA\HotTracks\Service\GpxCopyFolder;
use OCA\HotTracks\Service\GpxCopyPath;
use OCA\HotTracks\Service\SportGroup;
use OCA\HotTracks\Service\SportPreferences;
use OCA\HotTracks\Service\UserAuthConfig;
use OCA\HotTracks\BackgroundJob\CopyGpxJob;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class PersonalController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private ConnectionMapper $connections,
		private UserAuthConfig $userAuth,
		private GpxCopyFolder $copies,
		private SportPreferences $sports,
		private ActivityFiles $files,
		private ActivityMapper $activities,
		private IJobList $jobList,
		private LoggerInterface $logger,
		private ISession $session,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function save(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/user/hot_tracks');
		if ($this->userId === null || $this->userId === '') {
			return new RedirectResponse($target);
		}

		$useProxy = $this->request->getParam('useProxy') === '1'
			|| $this->request->getParam('useProxy') === 'on';
		$mode = $useProxy ? UserAuthConfig::MODE_PROXY : UserAuthConfig::MODE_DIRECT;
		$clientId = trim((string)$this->request->getParam('clientId', ''));
		$secret = (string)$this->request->getParam('clientSecret', '');
		$proxyUrl = trim((string)$this->request->getParam('proxyUrl', ''));

		if ($mode === UserAuthConfig::MODE_PROXY) {
			$urlError = AuthServiceUrl::validate($proxyUrl);
			if ($urlError !== '') {
				$this->session->set('hot_tracks_personal_error', $urlError);
				return new RedirectResponse($target);
			}
			if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $clientId)) {
				$this->session->set('hot_tracks_personal_error', 'Die Client-ID darf nur Buchstaben, Ziffern, Unterstrich und Bindestrich enthalten.');
				return new RedirectResponse($target);
			}
		} else {
			if (!preg_match('/^[0-9]{1,12}$/', $clientId)) {
				$this->session->set('hot_tracks_personal_error', 'Die Strava-Client-ID muss eine Zahl sein (steht unter Einstellungen → API).');
				return new RedirectResponse($target);
			}
			$proxyUrl = '';
		}

		if ($secret === '') {
			if (!$this->userAuth->secretIsSet($this->userId)) {
				$this->session->set('hot_tracks_personal_error', 'Bitte das Client-Secret eintragen.');
				return new RedirectResponse($target);
			}
			// Erstes Speichern mit Admin-Fallback: Secret übernehmen, sonst bleibt es nur instanzweit.
			if (!$this->userAuth->hasPersonalConfig($this->userId)) {
				$secret = $this->userAuth->clientSecret($this->userId);
			}
		}

		$this->userAuth->save(
			$this->userId,
			$mode,
			$clientId,
			$secret !== '' ? $secret : null,
			$proxyUrl,
		);
		$this->session->set('hot_tracks_personal_notice', 'Zugangsdaten gespeichert. Danach in der App verbinden.');
		return new RedirectResponse($target);
	}

	#[NoAdminRequired]
	public function saveSports(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/user/hot_tracks');
		if ($this->userId === null || $this->userId === '') {
			return new RedirectResponse($target);
		}

		$raw = $this->request->getParam('sports', []);
		if (!is_array($raw)) {
			$raw = [];
		}
		$selected = $this->sports->store($this->userId, array_map('strval', $raw));
		$labels = array_map(static fn (string $id): string => SportGroup::label($id), $selected);
		$this->session->set(
			'hot_tracks_personal_notice',
			'Sportarten gespeichert: ' . implode(', ', $labels) . '. Abgleich und Filter in der App nutzen diese Gruppen.',
		);
		return new RedirectResponse($target);
	}

	#[NoAdminRequired]
	public function saveCopyFolder(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/user/hot_tracks');
		if ($this->userId === null || $this->userId === '') {
			return new RedirectResponse($target);
		}

		$normalized = GpxCopyPath::normalize((string)$this->request->getParam('gpxCopyFolder', ''));
		if ($normalized['error'] !== '') {
			$this->session->set('hot_tracks_personal_error', $normalized['error']);
			return new RedirectResponse($target);
		}
		if ($normalized['path'] === '') {
			$this->copies->store($this->userId, '');
			$this->session->set('hot_tracks_personal_notice', 'Zusätzlicher Ordner aus. Neue GPX-Dateien bleiben nur unter Hot Tracks. Bereits kopierte Dateien bleiben liegen.');
			return new RedirectResponse($target);
		}

		try {
			$this->files->ensureFolder($this->userId, $normalized['path']);
		} catch (\Throwable) {
			$this->session->set('hot_tracks_personal_error', 'Der Ordner konnte nicht angelegt werden.');
			return new RedirectResponse($target);
		}

		$this->copies->store($this->userId, $normalized['path']);
		$copied = 0;
		$failed = 0;
		$pending = false;
		foreach ($this->activities->findByUid($this->userId, 10000) as $activity) {
			$fileId = $activity->getFileId();
			if ($fileId === null) {
				continue;
			}
			try {
				$status = $this->files->copyStored($this->userId, (int)$fileId);
			} catch (\Throwable $e) {
				$failed++;
				$this->logger->warning('Vorhandene GPX-Datei konnte nicht zusätzlich abgelegt werden', [
					'app' => 'hot_tracks',
					'exception' => $e,
				]);
				continue;
			}
			if ($status !== 'copied') {
				continue;
			}
			$copied++;
			if ($copied >= 30) {
				$pending = true;
				break;
			}
		}
		if ($pending) {
			$this->jobList->add(CopyGpxJob::class, ['uid' => $this->userId]);
		}

		$notice = 'Zusätzlicher Ordner gespeichert.';
		if ($pending) {
			$notice .= ' Die ersten Dateien sind kopiert, der Rest folgt im Hintergrund.';
		} elseif ($copied === 1) {
			$notice .= ' 1 vorhandene GPX-Datei dorthin kopiert.';
		} elseif ($copied > 1) {
			$notice .= ' ' . $copied . ' vorhandene GPX-Dateien dorthin kopiert.';
		} else {
			$notice .= ' Weitere Abrufe legen die Datei dort zusätzlich ab.';
		}
		if ($failed > 0) {
			$notice .= ' ' . $failed . ' konnten nicht kopiert werden.';
		}
		$this->session->set('hot_tracks_personal_notice', $notice);
		return new RedirectResponse($target);
	}

	#[NoAdminRequired]
	public function disconnect(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/user/hot_tracks');
		if ($this->userId !== null && $this->userId !== '') {
			$this->connections->deleteByUid($this->userId);
			$this->session->set('hot_tracks_personal_notice', 'Verbindung getrennt. Die GPX-Dateien bleiben im Ordner Hot Tracks.');
		}
		return new RedirectResponse($target);
	}
}
