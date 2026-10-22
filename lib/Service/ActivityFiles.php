<?php

declare(strict_types=1);

namespace OCA\Strava\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;

class ActivityFiles {
	public function __construct(
		private IRootFolder $root,
	) {
	}

	public function writeGpx(string $uid, \DateTimeImmutable $startLocal, int $stravaId, string $name, string $gpx): int {
		$userFolder = $this->root->getUserFolder($uid);
		$folder = $this->childFolder($userFolder, 'Strava');
		$folder = $this->childFolder($folder, $startLocal->format('Y'));
		$folder = $this->childFolder($folder, $startLocal->format('m'));
		$filename = ActivityPath::filename($startLocal, $stravaId, $name);

		try {
			$node = $folder->get($filename);
			if (!$node instanceof File) {
				throw new \RuntimeException('Der GPX-Pfad ist keine Datei.');
			}
		} catch (NotFoundException) {
			$node = $folder->newFile($filename);
		}
		$node->putContent($gpx);
		$id = $node->getId();
		if ($id === null) {
			throw new \RuntimeException('Die GPX-Datei hat keine ID.');
		}
		return (int)$id;
	}

	public function readById(string $uid, int $fileId): string {
		$nodes = $this->root->getUserFolder($uid)->getById($fileId);
		foreach ($nodes as $node) {
			if ($node instanceof File && str_contains($node->getPath(), '/files/Strava/')) {
				return $node->getContent();
			}
		}
		throw new NotFoundException('GPX nicht gefunden');
	}

	private function childFolder(Folder $parent, string $name): Folder {
		try {
			$node = $parent->get($name);
		} catch (NotFoundException) {
			return $parent->newFolder($name);
		}
		if (!$node instanceof Folder) {
			throw new \RuntimeException('„' . $name . '“ ist kein Ordner.');
		}
		return $node;
	}
}
