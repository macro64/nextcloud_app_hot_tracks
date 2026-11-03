<?php

declare(strict_types=1);

namespace OCA\HotTracks\Service;

class GpxBuilder {
	private const NS = 'http://www.topografix.com/GPX/1/1';
	private const TPX = 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1';

	/**
	 * @param list<mixed> $latlng
	 * @param list<mixed> $altitude
	 * @param list<mixed> $timeOffsets seconds from the activity start
	 * @param list<mixed> $heartRate beats per minute, aligned with $latlng
	 */
	public function build(
		string $name,
		string $sport,
		int $startTimestamp,
		array $latlng,
		array $altitude,
		array $timeOffsets,
		array $heartRate = [],
	): string {
		$doc = new \DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;
		$gpx = $doc->createElementNS(self::NS, 'gpx');
		$gpx->setAttribute('version', '1.1');
		$gpx->setAttribute('creator', 'Hot Tracks');
		$storeHeartRate = $this->hasHeartRate($heartRate);
		if ($storeHeartRate) {
			$gpx->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:gpxtpx', self::TPX);
		}
		$doc->appendChild($gpx);

		$metadata = $this->element($doc, 'metadata');
		$metadata->appendChild($this->element($doc, 'name', $name));
		$metadata->appendChild($this->element($doc, 'time', gmdate('Y-m-d\TH:i:s\Z', $startTimestamp)));
		$gpx->appendChild($metadata);

		$trk = $this->element($doc, 'trk');
		$trk->appendChild($this->element($doc, 'name', $name));
		$trk->appendChild($this->element($doc, 'type', $sport));
		$seg = $this->element($doc, 'trkseg');
		$points = 0;

		foreach ($latlng as $index => $pair) {
			if (!is_array($pair) || !isset($pair[0], $pair[1]) || !is_numeric($pair[0]) || !is_numeric($pair[1])) {
				continue;
			}
			$pt = $this->element($doc, 'trkpt');
			$pt->setAttribute('lat', sprintf('%.7F', (float)$pair[0]));
			$pt->setAttribute('lon', sprintf('%.7F', (float)$pair[1]));
			if (isset($altitude[$index]) && is_numeric($altitude[$index])) {
				$pt->appendChild($this->element($doc, 'ele', sprintf('%.1F', (float)$altitude[$index])));
			}
			$offset = isset($timeOffsets[$index]) && is_numeric($timeOffsets[$index]) ? (int)$timeOffsets[$index] : 0;
			$pt->appendChild($this->element($doc, 'time', gmdate('Y-m-d\TH:i:s\Z', $startTimestamp + $offset)));
			if ($storeHeartRate && isset($heartRate[$index]) && is_numeric($heartRate[$index]) && (int)$heartRate[$index] > 0) {
				$extensions = $this->element($doc, 'extensions');
				$trackPoint = $doc->createElementNS(self::TPX, 'gpxtpx:TrackPointExtension');
				$trackPoint->appendChild($doc->createElementNS(self::TPX, 'gpxtpx:hr', (string)(int)$heartRate[$index]));
				$extensions->appendChild($trackPoint);
				$pt->appendChild($extensions);
			}
			$seg->appendChild($pt);
			$points++;
		}

		if ($points === 0) {
			return '';
		}

		$trk->appendChild($seg);
		$gpx->appendChild($trk);
		$xml = $doc->saveXML();
		return $xml === false ? '' : $xml;
	}

	/**
	 * @param list<mixed> $heartRate
	 */
	private function hasHeartRate(array $heartRate): bool {
		foreach ($heartRate as $value) {
			if (is_numeric($value) && (int)$value > 0) {
				return true;
			}
		}
		return false;
	}

	private function element(\DOMDocument $doc, string $name, ?string $text = null): \DOMElement {
		$element = $doc->createElementNS(self::NS, $name);
		if ($text !== null) {
			$element->appendChild($doc->createTextNode($text));
		}
		return $element;
	}
}
