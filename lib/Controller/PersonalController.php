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
use OCP\IL10N;
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
		private IL10N $l,
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
				$this->session->set('hot_tracks_personal_error', $this->l->t($urlError));
				return new RedirectResponse($target);
			}
			if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $clientId)) {
				$this->session->set('hot_tracks_personal_error', $this->l->t('The client ID may only contain letters, digits, underscore and hyphen.'));
				return new RedirectResponse($target);
			}
		} else {
			if (!preg_match('/^[0-9]{1,12}$/', $clientId)) {
				$this->session->set('hot_tracks_personal_error', $this->l->t('The Strava client ID must be a number (see Settings → API).'));
				return new RedirectResponse($target);
			}
			$proxyUrl = '';
		}

		if ($secret === '') {
			if (!$this->userAuth->secretIsSet($this->userId)) {
				$this->session->set('hot_tracks_personal_error', $this->l->t('Please enter the client secret.'));
				return new RedirectResponse($target);
			}
			// First save with admin fallback: copy secret, otherwise it stays instance-wide only.
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
		$this->session->set('hot_tracks_personal_notice', $this->l->t('Credentials saved. Then connect in the app.'));
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
		$labels = array_map(fn (string $id): string => $this->l->t(SportGroup::label($id)), $selected);
		$this->session->set(
			'hot_tracks_personal_notice',
			$this->l->t('Sports saved: %1$s. Sync and filters in the app use these groups.', [implode(', ', $labels)]),
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
			$this->session->set('hot_tracks_personal_error', $this->l->t($normalized['error']));
			return new RedirectResponse($target);
		}
		if ($normalized['path'] === '') {
			$this->copies->store($this->userId, '');
			$this->session->set('hot_tracks_personal_notice', $this->l->t('Extra folder turned off. New GPX files stay under Hot Tracks only. Already copied files are left as they are.'));
			return new RedirectResponse($target);
		}

		try {
			$this->files->ensureFolder($this->userId, $normalized['path']);
		} catch (\Throwable) {
			$this->session->set('hot_tracks_personal_error', $this->l->t('The folder could not be created.'));
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
				$this->logger->warning('Could not copy existing GPX file to the extra folder', [
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

		$notice = $this->l->t('Extra folder saved.');
		if ($pending) {
			$notice .= ' ' . $this->l->t('The first files were copied; the rest continue in the background.');
		} elseif ($copied === 1) {
			$notice .= ' ' . $this->l->n('%n existing GPX file was copied there.', '%n existing GPX files were copied there.', $copied);
		} elseif ($copied > 1) {
			$notice .= ' ' . $this->l->n('%n existing GPX file was copied there.', '%n existing GPX files were copied there.', $copied);
		} else {
			$notice .= ' ' . $this->l->t('Further syncs will also place the file there.');
		}
		if ($failed > 0) {
			$notice .= ' ' . $this->l->n('%n file could not be copied.', '%n files could not be copied.', $failed);
		}
		$this->session->set('hot_tracks_personal_notice', $notice);
		return new RedirectResponse($target);
	}

	#[NoAdminRequired]
	public function disconnect(): RedirectResponse {
		$target = $this->urlGenerator->getAbsoluteURL('/settings/user/hot_tracks');
		if ($this->userId !== null && $this->userId !== '') {
			$this->connections->deleteByUid($this->userId);
			$this->session->set('hot_tracks_personal_notice', $this->l->t('Disconnected. GPX files remain in the Hot Tracks folder.'));
		}
		return new RedirectResponse($target);
	}
}
