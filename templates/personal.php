<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$useProxy = ($_['mode'] ?? '') === 'proxy';
?>
<div class="section" id="hot-tracks-personal">
	<h2><?php p($l->t('Hot Tracks')); ?></h2>
	<?php if (!empty($_['error'])): ?>
		<p><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p><?php p($_['notice']); ?></p>
	<?php endif; ?>

	<?php if (!empty($_['connected'])): ?>
		<p>
			<?php if (!empty($_['athleteName'])): ?>
				<?php p($l->t('Connected as %1$s.', [$_['athleteName']])); ?>
			<?php else: ?>
				<?php p($l->t('Connected.')); ?>
			<?php endif; ?>
			<?php p($l->t('GPX files in the Hot Tracks folder are kept.')); ?>
		</p>
		<form method="post" action="<?php p($_['disconnectUrl']); ?>">
			<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
			<button type="submit"><?php p($l->t('Disconnect')); ?></button>
		</form>
		<hr>
	<?php endif; ?>

	<h3><?php p($l->t('Connect Strava (recommended)')); ?></h3>
	<ol style="max-width: 40rem; line-height: 1.5;">
		<li><?php print_unescaped($l->t('Create an API application at %1$sstrava.com/settings/api%2$s (Strava subscription required).', ['<a href="https://www.strava.com/settings/api" target="_blank" rel="noreferrer noopener">', '</a>'])); ?></li>
		<li><?php print_unescaped($l->t('Set the Authorization Callback Domain to exactly %1$s (without https://).', ['<code>' . \OCP\Util::sanitizeHTML($_['callbackDomain']) . '</code>'])); ?></li>
		<li><?php p($l->t('Save the client ID and client secret here, then connect.')); ?></li>
	</ol>

	<form method="post" action="<?php p($_['saveUrl']); ?>" id="hot-tracks-auth-form">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">

		<div id="hot-tracks-direct-fields" <?php if ($useProxy): ?>hidden<?php endif; ?>>
			<p>
				<label for="hot-tracks-client-id"><?php p($l->t('Client ID')); ?></label><br>
				<input id="hot-tracks-client-id" name="clientId" type="text" inputmode="numeric" pattern="[0-9]*" value="<?php p($useProxy ? '' : $_['clientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);" <?php if (!$useProxy): ?>required<?php endif; ?>>
			</p>
			<p>
				<label for="hot-tracks-client-secret"><?php p($l->t('Client secret')); ?></label><br>
				<input id="hot-tracks-client-secret" name="clientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && !$useProxy ? $l->t('Set — leave empty to keep it') : $l->t('Secret from the Strava API settings')); ?>" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-redirect"><?php p($l->t('Redirect URI (for verification only)')); ?></label><br>
				<input id="hot-tracks-redirect" type="text" readonly value="<?php p($_['redirectUri']); ?>" style="width: min(100%, 36rem);">
			</p>
		</div>

		<details id="hot-tracks-proxy-details" <?php if ($useProxy): ?>open<?php endif; ?> style="margin: 1rem 0; max-width: 40rem;">
			<summary style="cursor: pointer;"><?php p($l->t('Use auth proxy (optional)')); ?></summary>
			<p style="margin-top: 0.75rem;"><?php p($l->t('Use an auth broker instead of direct Strava OAuth (e.g. fluadrits-auth). Not needed for most people.')); ?></p>
			<p>
				<label>
					<input id="hot-tracks-use-proxy" name="useProxy" type="checkbox" value="1" <?php if ($useProxy): ?>checked<?php endif; ?>>
					<?php p($l->t('Auth proxy active')); ?>
				</label>
			</p>
			<div id="hot-tracks-proxy-fields" <?php if (!$useProxy): ?>hidden<?php endif; ?>>
				<p>
					<label for="hot-tracks-proxy-url"><?php p($l->t('Auth service')); ?></label><br>
					<input id="hot-tracks-proxy-url" name="proxyUrl" type="url" value="<?php p($_['proxyUrl']); ?>" style="width: min(100%, 36rem);" <?php if ($useProxy): ?>required<?php endif; ?>>
				</p>
				<p>
					<label for="hot-tracks-proxy-client-id"><?php p($l->t('Client ID')); ?></label><br>
					<input id="hot-tracks-proxy-client-id" name="proxyClientId" type="text" value="<?php p($useProxy ? $_['clientId'] : ''); ?>" autocomplete="off" style="width: min(100%, 24rem);" <?php if ($useProxy): ?>required<?php endif; ?>>
				</p>
				<p>
					<label for="hot-tracks-proxy-secret"><?php p($l->t('Client secret')); ?></label><br>
					<input id="hot-tracks-proxy-secret" name="proxyClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && $useProxy ? $l->t('Set — leave empty to keep it') : $l->t('OAuth client secret')); ?>" style="width: min(100%, 24rem);">
				</p>
			</div>
		</details>

		<button type="submit"><?php p($l->t('Save')); ?></button>
	</form>
	<?php if (!empty($_['configured']) && empty($_['connected'])): ?>
		<p style="margin-top: 1rem;">
			<a href="<?php p($_['connectUrl']); ?>" style="display: inline-block; line-height: 0;">
				<img
					src="<?php p($_['connectButtonSrc']); ?>"
					width="237"
					height="48"
					alt="<?php p($l->t('Connect with Strava')); ?>"
				>
			</a>
		</p>
	<?php endif; ?>

	<hr>
	<h3><?php p($l->t('Sports')); ?></h3>
	<p style="max-width: 40rem; line-height: 1.5;">
		<?php p($l->t('Controls what is fetched from Strava on sync and which groups appear under List and Heatmap in the app. Multiple groups at once are allowed.')); ?>
	</p>
	<form method="post" action="<?php p($_['sportsUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
		<p style="display: flex; flex-wrap: wrap; gap: 0.75rem 1.25rem; max-width: 40rem;">
			<?php foreach ($_['sportOptions'] as $option): ?>
				<label style="display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer;">
					<input
						type="checkbox"
						name="sports[]"
						value="<?php p($option['id']); ?>"
						<?php if (!empty($option['selected'])): ?>checked<?php endif; ?>
					>
					<?php p($option['label']); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<button type="submit"><?php p($l->t('Save sports')); ?></button>
	</form>

	<hr>
	<h3><?php p($l->t('Extra folder')); ?></h3>
	<p style="max-width: 40rem; line-height: 1.5;">
		<?php print_unescaped($l->t('Leave empty to keep GPX files only under Hot Tracks/year/month. A path like %1$sSport/GPX%2$s is relative to your files. Saving copies existing files there; with many files the rest continues in the background. Further fetches also place the same file there.', ['<code>', '</code>'])); ?>
	</p>
	<form method="post" action="<?php p($_['copyFolderUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
		<p>
			<label for="hot-tracks-copy-folder"><?php p($l->t('Folder in your files')); ?></label><br>
			<input id="hot-tracks-copy-folder" name="gpxCopyFolder" type="text" value="<?php p($_['gpxCopyFolder']); ?>" placeholder="<?php p($l->t('e.g. Sport/GPX')); ?>" style="width: min(100%, 24rem);">
		</p>
		<button type="submit"><?php p($l->t('Save folder')); ?></button>
	</form>
</div>
<script>
(function () {
	var form = document.getElementById('hot-tracks-auth-form');
	if (!form) return;
	var useProxy = document.getElementById('hot-tracks-use-proxy');
	var details = document.getElementById('hot-tracks-proxy-details');
	var direct = document.getElementById('hot-tracks-direct-fields');
	var proxyFields = document.getElementById('hot-tracks-proxy-fields');
	var directId = document.getElementById('hot-tracks-client-id');
	var directSecret = document.getElementById('hot-tracks-client-secret');
	var proxyUrl = document.getElementById('hot-tracks-proxy-url');
	var proxyId = document.getElementById('hot-tracks-proxy-client-id');
	var proxySecret = document.getElementById('hot-tracks-proxy-secret');

	function sync() {
		var on = !!(useProxy && useProxy.checked);
		if (direct) direct.hidden = on;
		if (proxyFields) proxyFields.hidden = !on;
		if (details && on) details.open = true;
		if (directId) {
			directId.required = !on;
			directId.disabled = on;
		}
		if (directSecret) directSecret.disabled = on;
		if (proxyUrl) {
			proxyUrl.required = on;
			proxyUrl.disabled = !on;
		}
		if (proxyId) {
			proxyId.required = on;
			proxyId.disabled = !on;
		}
		if (proxySecret) proxySecret.disabled = !on;
	}

	if (useProxy) useProxy.addEventListener('change', sync);
	if (details) {
		details.addEventListener('toggle', function () {
			if (!details.open && useProxy && useProxy.checked) {
				useProxy.checked = false;
				sync();
			}
		});
	}

	form.addEventListener('submit', function () {
		if (useProxy && useProxy.checked) {
			if (directId && proxyId) directId.value = proxyId.value;
			if (directSecret && proxySecret && proxySecret.value !== '') {
				directSecret.value = proxySecret.value;
			}
			directSecret && (directSecret.disabled = false);
			directId && (directId.disabled = false);
		} else {
			if (proxyUrl) proxyUrl.disabled = true;
			if (proxyId) proxyId.disabled = true;
			if (proxySecret) proxySecret.disabled = true;
		}
	});

	sync();
})();
</script>
