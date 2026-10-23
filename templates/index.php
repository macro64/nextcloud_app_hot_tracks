<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$version = is_file(dirname(__DIR__) . '/js/hot_tracks-main.js') ? (string)filemtime(dirname(__DIR__) . '/js/hot_tracks-main.js') : '1';
$src = \OCP\Server::get(\OCP\IURLGenerator::class)->linkTo('hot_tracks', 'js/hot_tracks-main.js') . '?v=' . $version;
$nonce = \OCP\Server::get(\OC\Security\CSP\ContentSecurityPolicyNonceManager::class)->getNonce();
?>
<div id="hot-tracks-root"></div>
<script nonce="<?php p($nonce); ?>" src="<?php p($src); ?>"></script>
<noscript>
	<p><?php p($l->t('The Hot Tracks view requires JavaScript.')); ?></p>
</noscript>
