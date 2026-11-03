<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$connected = !empty($_['connected']);
$configured = !empty($_['configured']);
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
		.hot-tracks-settings-id { margin: 0.15rem 0 0.75rem; font-size: 1.6rem; font-weight: 600; letter-spacing: 0.02em; }
		.hot-tracks-settings-disconnect { display: inline-flex; margin: 0; }
		.hot-tracks-settings-secondary { margin-top: 1.5rem; }
		.hot-tracks-settings-note { margin: 0 0 1.25rem; padding: 0.75rem 0.9rem; border: 1px solid var(--color-border); border-radius: 8px; background: var(--color-background-hover); line-height: 1.45; }
		.hot-tracks-settings-note strong { display: block; margin-bottom: 0.2rem; }
	</style>

	<aside class="hot-tracks-settings-note" role="note">
		<strong><?php p($l->t('Hint')); ?></strong>
		<?php p($l->t('Enter your Strava ID here. The server connection is set up under Administration settings → Hot Tracks.')); ?>
	</aside>

	<form method="post" action="<?php p($_['saveAthleteUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
		<p>
			<label for="hot-tracks-athlete-id"><?php p($l->t('Strava ID')); ?></label><br>
			<input
				id="hot-tracks-athlete-id"
				name="athleteId"
				type="text"
				inputmode="numeric"
				pattern="[0-9]*"
				value="<?php p((int)$_['athleteId'] > 0 ? (string)(int)$_['athleteId'] : ''); ?>"
				placeholder="12345678"
				autocomplete="off"
				required
				style="width: min(100%, 16rem);"
			>
		</p>
		<p class="hot-tracks-settings-muted"><?php p($l->t('The number in your Strava profile address, for example strava.com/athletes/12345678.')); ?></p>
		<button type="submit" class="primary"><?php p($l->t('Save Strava ID')); ?></button>
	</form>

	<?php if ($connected): ?>
		<div class="hot-tracks-settings-status">
			<p class="hot-tracks-settings-muted">
				<?php if (!empty($_['athleteName'])): ?>
					<?php p($l->t('Connected as %1$s.', [$_['athleteName']])); ?>
				<?php else: ?>
					<?php p($l->t('Connected.')); ?>
				<?php endif; ?>
			</p>
			<p class="hot-tracks-settings-actions">
				<a class="button primary" href="<?php p($_['appUrl']); ?>"><?php p($l->t('Open Hot Tracks')); ?></a>
				<form method="post" action="<?php p($_['disconnectUrl']); ?>" class="hot-tracks-settings-disconnect">
					<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
					<button type="submit" class="button"><?php p($l->t('Disconnect Strava')); ?></button>
				</form>
			</p>
		</div>
	<?php elseif ($configured): ?>
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
	<?php else: ?>
		<p class="hot-tracks-settings-lead"><?php p($l->t('An admin sets up the Strava connection under Administration settings → Hot Tracks.')); ?></p>
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

})();
</script>
