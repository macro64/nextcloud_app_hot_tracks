<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$useProxy = ($_['mode'] ?? '') === 'proxy';
$connected = !empty($_['connected']);
$configured = !empty($_['configured']);
$callbackDomain = (string)($_['callbackDomain'] ?? '');
?>
<div class="section" id="hot-tracks-personal">
	<h2><?php p($l->t('Hot Tracks')); ?></h2>
	<?php if (!empty($_['error'])): ?>
		<p class="hot-tracks-settings-banner hot-tracks-settings-banner--error"><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p class="hot-tracks-settings-banner"><?php p($_['notice']); ?></p>
	<?php endif; ?>

	<style>
		#hot-tracks-personal { max-width: 42rem; }
		.hot-tracks-settings-step { margin: 0 0 0.25rem; color: var(--color-text-maxcontrast); font-size: 0.85rem; letter-spacing: 0.04em; text-transform: uppercase; }
		.hot-tracks-settings-lead { margin: 0.35rem 0 1rem; max-width: 36rem; line-height: 1.45; }
		.hot-tracks-settings-muted { color: var(--color-text-maxcontrast); }
		.hot-tracks-settings-steps { margin: 0 0 1.25rem; padding-left: 1.25rem; line-height: 1.55; }
		.hot-tracks-settings-steps li { margin: 0.65rem 0; }
		.hot-tracks-settings-copyrow { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-top: 0.4rem; }
		.hot-tracks-settings-copyrow code { padding: 0.35rem 0.55rem; background: var(--color-background-dark, #eee); border-radius: 6px; }
		.hot-tracks-settings-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin: 1rem 0; }
		.hot-tracks-settings-banner { margin: 0 0 1rem; padding: 0.65rem 0.8rem; border-radius: 8px; background: var(--color-background-hover); }
		.hot-tracks-settings-banner--error { background: var(--color-error, #ffe8e8); }
		.hot-tracks-settings-status { margin-bottom: 1.5rem; }
		.hot-tracks-settings-disconnect { display: inline-flex; margin: 0; }
		.hot-tracks-settings-secondary { margin-top: 1.5rem; }
		#hot-tracks-auth-form:has(#hot-tracks-use-proxy:checked) #hot-tracks-direct-fields { display: none; }
		#hot-tracks-proxy-fields { display: none; }
		#hot-tracks-auth-form:has(#hot-tracks-use-proxy:checked) #hot-tracks-proxy-fields { display: block; }
	</style>

	<?php if ($connected): ?>
		<div class="hot-tracks-settings-status">
			<p>
				<?php if (!empty($_['athleteName'])): ?>
					<?php p($l->t('Connected as %1$s.', [$_['athleteName']])); ?>
				<?php else: ?>
					<?php p($l->t('Connected.')); ?>
				<?php endif; ?>
			</p>
			<p class="hot-tracks-settings-muted"><?php p($l->t('Your activities sync into the Hot Tracks folder in your files.')); ?></p>
			<p class="hot-tracks-settings-actions">
				<a class="button primary" href="<?php p($_['appUrl']); ?>"><?php p($l->t('Open Hot Tracks')); ?></a>
				<form method="post" action="<?php p($_['disconnectUrl']); ?>" class="hot-tracks-settings-disconnect">
					<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
					<button type="submit" class="button"><?php p($l->t('Disconnect Strava')); ?></button>
				</form>
			</p>
		</div>
	<?php else: ?>
		<div class="hot-tracks-settings-setup">
			<?php if (!$configured): ?>
				<p class="hot-tracks-settings-step"><?php p($l->t('Step 1 of 2')); ?></p>
				<h3><?php p($l->t('Add your Strava API keys')); ?></h3>
				<p class="hot-tracks-settings-lead"><?php p($l->t('Do this once. Afterwards you connect with one click.')); ?></p>
			<?php else: ?>
				<p class="hot-tracks-settings-step"><?php p($l->t('Step 2 of 2')); ?></p>
				<h3><?php p($l->t('Connect your Strava account')); ?></h3>
				<p class="hot-tracks-settings-lead"><?php p($l->t('Authorize Hot Tracks. Your activities stay private in this Nextcloud.')); ?></p>
				<p class="hot-tracks-settings-actions">
					<a href="<?php p($_['connectUrl']); ?>" class="hot-tracks-strava-connect" style="display: inline-block; line-height: 0;">
						<img
							src="<?php p($_['connectButtonSrc']); ?>"
							width="237"
							height="48"
							alt="<?php p($l->t('Connect with Strava')); ?>"
						>
					</a>
				</p>
			<?php endif; ?>

			<?php if (!$configured): ?>
				<ol class="hot-tracks-settings-steps">
					<li>
						<?php print_unescaped($l->t('Open %1$sstrava.com/settings/api%2$s and create an API application (Strava subscription required).', ['<a href="https://www.strava.com/settings/api" target="_blank" rel="noreferrer noopener">', '</a>'])); ?>
					</li>
					<li>
						<?php p($l->t('Copy this value into “Authorization Callback Domain” (no https://):')); ?>
						<span class="hot-tracks-settings-copyrow">
							<code id="hot-tracks-callback-domain"><?php p($callbackDomain); ?></code>
							<button type="button" id="hot-tracks-copy-domain" class="button"><?php p($l->t('Copy')); ?></button>
						</span>
					</li>
					<li><?php p($l->t('Paste Client ID and Client Secret below, then save.')); ?></li>
				</ol>

				<form method="post" action="<?php p($_['saveUrl']); ?>" id="hot-tracks-auth-form">
					<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">

					<div id="hot-tracks-direct-fields">
						<p>
							<label for="hot-tracks-client-id"><?php p($l->t('Client ID')); ?></label><br>
							<input id="hot-tracks-client-id" name="clientId" type="text" inputmode="numeric" pattern="[0-9]*" value="<?php p($useProxy ? '' : $_['clientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);" <?php if (!$useProxy): ?>required<?php endif; ?>>
						</p>
						<p>
							<label for="hot-tracks-client-secret"><?php p($l->t('Client secret')); ?></label><br>
							<input id="hot-tracks-client-secret" name="clientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && !$useProxy ? $l->t('Set — leave empty to keep it') : $l->t('Paste the Client Secret from Strava')); ?>" style="width: min(100%, 24rem);" <?php if (!$useProxy && empty($_['secretSet'])): ?>required<?php endif; ?>>
						</p>
					</div>

					<details id="hot-tracks-proxy-details" <?php if ($useProxy): ?>open<?php endif; ?> class="hot-tracks-settings-secondary">
						<summary style="cursor: pointer;"><?php p($l->t('Advanced: auth proxy')); ?></summary>
						<p style="margin-top: 0.75rem;"><?php p($l->t('Only if your admin told you to use a shared auth broker. Skip this otherwise.')); ?></p>
						<p>
							<label>
								<input id="hot-tracks-use-proxy" name="useProxy" type="checkbox" value="1" <?php if ($useProxy): ?>checked<?php endif; ?>>
								<?php p($l->t('Use auth proxy')); ?>
							</label>
						</p>
						<div id="hot-tracks-proxy-fields">
							<p>
								<label for="hot-tracks-proxy-url"><?php p($l->t('Auth service')); ?></label><br>
								<input id="hot-tracks-proxy-url" name="proxyUrl" type="text" inputmode="url" value="<?php p($_['proxyUrl']); ?>" autocomplete="off" style="width: min(100%, 36rem);">
							</p>
							<p>
								<label for="hot-tracks-proxy-client-id"><?php p($l->t('Client ID')); ?></label><br>
								<input id="hot-tracks-proxy-client-id" name="proxyClientId" type="text" value="<?php p($useProxy ? $_['clientId'] : ''); ?>" autocomplete="off" style="width: min(100%, 24rem);">
							</p>
							<p>
								<label for="hot-tracks-proxy-secret"><?php p($l->t('Client secret')); ?></label><br>
								<input id="hot-tracks-proxy-secret" name="proxyClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && $useProxy ? $l->t('Set — leave empty to keep it') : $l->t('OAuth client secret')); ?>" style="width: min(100%, 24rem);">
							</p>
						</div>
					</details>

					<p>
						<button type="submit" class="primary"><?php p($l->t('Save and continue')); ?></button>
					</p>
				</form>
			<?php else: ?>
				<details class="hot-tracks-settings-secondary">
					<summary style="cursor: pointer;"><?php p($l->t('Need to change the API keys?')); ?></summary>
					<form method="post" action="<?php p($_['saveUrl']); ?>" id="hot-tracks-auth-form" style="margin-top: 0.75rem;">
						<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
						<div id="hot-tracks-direct-fields">
							<p>
								<label for="hot-tracks-client-id"><?php p($l->t('Client ID')); ?></label><br>
								<input id="hot-tracks-client-id" name="clientId" type="text" inputmode="numeric" pattern="[0-9]*" value="<?php p($useProxy ? '' : $_['clientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);">
							</p>
							<p>
								<label for="hot-tracks-client-secret"><?php p($l->t('Client secret')); ?></label><br>
								<input id="hot-tracks-client-secret" name="clientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && !$useProxy ? $l->t('Set — leave empty to keep it') : $l->t('Paste the Client Secret from Strava')); ?>" style="width: min(100%, 24rem);">
							</p>
						</div>
						<details id="hot-tracks-proxy-details" <?php if ($useProxy): ?>open<?php endif; ?> style="margin: 1rem 0;">
							<summary style="cursor: pointer;"><?php p($l->t('Advanced: auth proxy')); ?></summary>
							<p>
								<label>
									<input id="hot-tracks-use-proxy" name="useProxy" type="checkbox" value="1" <?php if ($useProxy): ?>checked<?php endif; ?>>
									<?php p($l->t('Use auth proxy')); ?>
								</label>
							</p>
							<div id="hot-tracks-proxy-fields">
								<p>
									<label for="hot-tracks-proxy-url"><?php p($l->t('Auth service')); ?></label><br>
									<input id="hot-tracks-proxy-url" name="proxyUrl" type="text" inputmode="url" value="<?php p($_['proxyUrl']); ?>" style="width: min(100%, 36rem);">
								</p>
								<p>
									<label for="hot-tracks-proxy-client-id"><?php p($l->t('Client ID')); ?></label><br>
									<input id="hot-tracks-proxy-client-id" name="proxyClientId" type="text" value="<?php p($useProxy ? $_['clientId'] : ''); ?>" style="width: min(100%, 24rem);">
								</p>
								<p>
									<label for="hot-tracks-proxy-secret"><?php p($l->t('Client secret')); ?></label><br>
									<input id="hot-tracks-proxy-secret" name="proxyClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p($l->t('OAuth client secret')); ?>" style="width: min(100%, 24rem);">
								</p>
							</div>
						</details>
						<button type="submit"><?php p($l->t('Update API keys')); ?></button>
					</form>
				</details>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ($connected): ?>
		<details class="hot-tracks-settings-secondary">
			<summary style="cursor: pointer;"><?php p($l->t('Sports and folders')); ?></summary>
			<p style="margin-top: 0.75rem; max-width: 40rem; line-height: 1.5;">
				<?php p($l->t('Optional. Defaults already sync Run, Ride, Ski and Water.')); ?>
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
			<p style="max-width: 40rem; line-height: 1.5;">
				<?php print_unescaped($l->t('Extra GPX copy (optional). Leave empty to keep files only under Hot Tracks. Example: %1$sSport/GPX%2$s', ['<code>', '</code>'])); ?>
			</p>
			<form method="post" action="<?php p($_['copyFolderUrl']); ?>">
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
				<p>
					<label for="hot-tracks-copy-folder"><?php p($l->t('Folder in your files')); ?></label><br>
					<input id="hot-tracks-copy-folder" name="gpxCopyFolder" type="text" value="<?php p($_['gpxCopyFolder']); ?>" placeholder="<?php p($l->t('e.g. Sport/GPX')); ?>" style="width: min(100%, 24rem);">
				</p>
				<button type="submit"><?php p($l->t('Save folder')); ?></button>
			</form>
		</details>

		<details class="hot-tracks-settings-secondary">
			<summary style="cursor: pointer;"><?php p($l->t('Change API keys')); ?></summary>
			<form method="post" action="<?php p($_['saveUrl']); ?>" id="hot-tracks-auth-form-connected" style="margin-top: 0.75rem;">
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
				<div id="hot-tracks-direct-fields">
					<p>
						<label for="hot-tracks-client-id-connected"><?php p($l->t('Client ID')); ?></label><br>
						<input id="hot-tracks-client-id-connected" name="clientId" type="text" inputmode="numeric" pattern="[0-9]*" value="<?php p($useProxy ? '' : $_['clientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);">
					</p>
					<p>
						<label for="hot-tracks-client-secret-connected"><?php p($l->t('Client secret')); ?></label><br>
						<input id="hot-tracks-client-secret-connected" name="clientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && !$useProxy ? $l->t('Set — leave empty to keep it') : $l->t('Paste the Client Secret from Strava')); ?>" style="width: min(100%, 24rem);">
					</p>
				</div>
				<details <?php if ($useProxy): ?>open<?php endif; ?> style="margin: 1rem 0;">
					<summary style="cursor: pointer;"><?php p($l->t('Advanced: auth proxy')); ?></summary>
					<p>
						<label>
							<input name="useProxy" type="checkbox" value="1" <?php if ($useProxy): ?>checked<?php endif; ?> id="hot-tracks-use-proxy-connected">
							<?php p($l->t('Use auth proxy')); ?>
						</label>
					</p>
					<p>
						<label for="hot-tracks-proxy-url-connected"><?php p($l->t('Auth service')); ?></label><br>
						<input id="hot-tracks-proxy-url-connected" name="proxyUrl" type="text" inputmode="url" value="<?php p($_['proxyUrl']); ?>" style="width: min(100%, 36rem);">
					</p>
					<p>
						<label for="hot-tracks-proxy-client-id-connected"><?php p($l->t('Client ID')); ?></label><br>
						<input id="hot-tracks-proxy-client-id-connected" name="proxyClientId" type="text" value="<?php p($useProxy ? $_['clientId'] : ''); ?>" style="width: min(100%, 24rem);">
					</p>
					<p>
						<label for="hot-tracks-proxy-secret-connected"><?php p($l->t('Client secret')); ?></label><br>
						<input id="hot-tracks-proxy-secret-connected" name="proxyClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p($l->t('OAuth client secret')); ?>" style="width: min(100%, 24rem);">
					</p>
				</details>
				<button type="submit"><?php p($l->t('Update API keys')); ?></button>
			</form>
		</details>
	<?php endif; ?>
</div>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
(function () {
	var disconnectForm = document.querySelector('.hot-tracks-settings-disconnect');
	if (disconnectForm) {
		disconnectForm.addEventListener('submit', function (event) {
			if (!window.confirm(<?php echo json_encode($l->t('Disconnect Strava? GPX files in Hot Tracks stay in your files.')); ?>)) {
				event.preventDefault();
			}
		});
	}

	var copyBtn = document.getElementById('hot-tracks-copy-domain');
	var domain = document.getElementById('hot-tracks-callback-domain');
	if (copyBtn && domain && navigator.clipboard) {
		copyBtn.addEventListener('click', function () {
			navigator.clipboard.writeText(domain.textContent.trim()).then(function () {
				copyBtn.textContent = <?php echo json_encode($l->t('Copied')); ?>;
				window.setTimeout(function () {
					copyBtn.textContent = <?php echo json_encode($l->t('Copy')); ?>;
				}, 1500);
			});
		});
	}

	var form = document.getElementById('hot-tracks-auth-form');
	if (!form) return;
	var useProxy = document.getElementById('hot-tracks-use-proxy');
	var directId = document.getElementById('hot-tracks-client-id');
	var directSecret = document.getElementById('hot-tracks-client-secret');
	var proxyUrl = document.getElementById('hot-tracks-proxy-url');
	var proxyId = document.getElementById('hot-tracks-proxy-client-id');
	var proxySecret = document.getElementById('hot-tracks-proxy-secret');

	function sync() {
		var on = !!(useProxy && useProxy.checked);
		if (directId) directId.disabled = on;
		if (directSecret) directSecret.disabled = on;
		if (proxyUrl) proxyUrl.disabled = !on;
		if (proxyId) proxyId.disabled = !on;
		if (proxySecret) proxySecret.disabled = !on;
	}

	if (useProxy) useProxy.addEventListener('change', sync);
	form.addEventListener('submit', sync);
	sync();
})();
</script>
