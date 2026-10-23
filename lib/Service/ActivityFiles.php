<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

class ActivityFiles {
	public function __construct(
		private IRootFolder $root,
		private GpxCopyFolder $copies,
		private LoggerInterface $logger,
	) {
	}

	public function writeGpx(string $uid, \DateTimeImmutable $startLocal, int $activityId, string $name, string $gpx): int {
		$userFolder = $this->root->getUserFolder($uid);
		$folder = $this->childFolder($userFolder, 'Hot Tracks');
		$folder = $this->childFolder($folder, $startLocal->format('Y'));
		$folder = $this->childFolder($folder, $startLocal->format('m'));
		$filename = ActivityPath::filename($startLocal, $activityId, $name);

		try {
			$node = $folder->get($filename);
			if (!$node instanceof File) {
				throw new \RuntimeException('The GPX path is not a file.');
			}
		} catch (NotFoundException) {
			$node = $folder->newFile($filename);
		}
		$node->putContent($gpx);
		$id = $node->getId();
		if ($id === null) {
			throw new \RuntimeException('The GPX file has no id.');
		}
		$this->writeConfiguredCopy($uid, $filename, $gpx);
		return (int)$id;
	}

	public function ensureFolder(string $uid, string $relative): void {
		$folder = $this->resolve($uid, $relative, true);
		if (!$folder instanceof Folder) {
			throw new \RuntimeException('The folder could not be created.');
		}
	}

	public function writeCopy(string $uid, string $filename, string $gpx): void {
		$this->assertFilename($filename);
		$relative = $this->copies->path($uid);
		if ($relative === '') {
			throw new \RuntimeException('No extra folder.');
		}
		$folder = $this->resolve($uid, $relative, true);
		if (!$folder instanceof Folder) {
			throw new \RuntimeException('The folder could not be created.');
		}
		$this->writeInto($folder, $filename, $gpx);
	}

	/**
	 * Copies an already stored GPX file into the extra folder
	 * without holding the file contents in PHP.
	 *
	 * @return 'copied'|'exists'|'missing'
	 */
	public function copyStored(string $uid, int $fileId): string {
		$relative = $this->copies->path($uid);
		if ($relative === '') {
			return 'missing';
		}
		$source = null;
		foreach ($this->root->getUserFolder($uid)->getById($fileId) as $node) {
			if ($node instanceof File && str_contains($node->getPath(), '/files/Hot Tracks/')) {
				$source = $node;
				break;
			}
		}
		if (!$source instanceof File) {
			return 'missing';
		}
		$filename = $source->getName();
		$this->assertFilename($filename);
		$folder = $this->resolve($uid, $relative, true);
		if (!$folder instanceof Folder) {
			throw new \RuntimeException('The folder could not be created.');
		}
		try {
			$existing = $folder->get($filename);
			if ($existing instanceof File) {
				return 'exists';
			}
		} catch (NotFoundException) {
		}
		$source->copy($folder->getPath() . '/' . $filename);
		return 'copied';
	}

	public function storedName(string $uid, int $fileId): ?string {
		foreach ($this->root->getUserFolder($uid)->getById($fileId) as $node) {
			if ($node instanceof File && str_contains($node->getPath(), '/files/Hot Tracks/')) {
				return $node->getName();
			}
		}
		return null;
	}

	public function deleteById(string $uid, int $fileId): void {
		$nodes = $this->root->getUserFolder($uid)->getById($fileId);
		foreach ($nodes as $node) {
			if ($node instanceof File && str_contains($node->getPath(), '/files/Hot Tracks/')) {
				$name = $node->getName();
				$node->delete();
				$this->deleteCopy($uid, $name);
				return;
			}
		}
	}

	public function readById(string $uid, int $fileId): string {
		$nodes = $this->root->getUserFolder($uid)->getById($fileId);
		foreach ($nodes as $node) {
			if ($node instanceof File && str_contains($node->getPath(), '/files/Hot Tracks/')) {
				return $node->getContent();
			}
		}
		throw new NotFoundException('GPX not found');
	}

	private function writeConfiguredCopy(string $uid, string $filename, string $gpx): void {
		if ($this->copies->path($uid) === '') {
			return;
		}
		try {
			$this->writeCopy($uid, $filename, $gpx);
		} catch (\Throwable $e) {
			$this->logger->warning('Extra GPX copy failed', [
				'app' => 'hot_tracks',
				'exception' => $e,
			]);
		}
	}

	private function deleteCopy(string $uid, string $filename): void {
		$relative = $this->copies->path($uid);
		if ($relative === '') {
			return;
		}
		try {
			$this->assertFilename($filename);
			$folder = $this->resolve($uid, $relative, false);
			if (!$folder instanceof Folder) {
				return;
			}
			$node = $folder->get($filename);
			if ($node instanceof File) {
				$node->delete();
			}
		} catch (NotFoundException) {
		} catch (\Throwable $e) {
			$this->logger->warning('Could not delete the extra GPX copy', [
				'app' => 'hot_tracks',
				'exception' => $e,
			]);
		}
	}

	private function writeInto(Folder $folder, string $filename, string $gpx): void {
		try {
			$node = $folder->get($filename);
			if (!$node instanceof File) {
				throw new \RuntimeException('The GPX path is not a file.');
			}
		} catch (NotFoundException) {
			$node = $folder->newFile($filename);
		}
		$node->putContent($gpx);
	}

	private function resolve(string $uid, string $relative, bool $create): ?Folder {
		$folder = $this->root->getUserFolder($uid);
		foreach (explode('/', $relative) as $segment) {
			if ($segment === '') {
				continue;
			}
			try {
				$node = $folder->get($segment);
			} catch (NotFoundException) {
				if (!$create) {
					return null;
				}
				$folder = $folder->newFolder($segment);
				continue;
			}
			if (!$node instanceof Folder) {
				throw new \RuntimeException('"' . $segment . '" is not a folder.');
			}
			$folder = $node;
		}
		return $folder;
	}

	private function assertFilename(string $filename): void {
		if (
			$filename === ''
			|| $filename === '.'
			|| $filename === '..'
			|| str_contains($filename, '/')
			|| str_contains($filename, '\\')
		) {
			throw new \RuntimeException('Invalid file name.');
		}
	}

	private function childFolder(Folder $parent, string $name): Folder {
		try {
			$node = $parent->get($name);
		} catch (NotFoundException) {
			return $parent->newFolder($name);
		}
		if (!$node instanceof Folder) {
			throw new \RuntimeException('"' . $name . '" is not a folder.');
		}
		return $node;
	}
}
