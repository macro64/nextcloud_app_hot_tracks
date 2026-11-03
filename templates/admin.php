<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$proxyConfigured = !empty($_['secretSet']) && $_['authServiceUrl'] !== '';
?>
<div class="section" id="hot-tracks-admin">
	<h2><?php p($l->t('Hot Tracks')); ?></h2>
	<?php if (!empty($_['error'])): ?>
		<p class="hot-tracks-admin-banner hot-tracks-admin-banner--error"><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p class="hot-tracks-admin-banner"><?php p($_['notice']); ?></p>
	<?php endif; ?>

	<style>
		#hot-tracks-admin { max-width: 42rem; }
		.hot-tracks-admin-lead { margin: 0.35rem 0 0.5rem; max-width: 36rem; line-height: 1.45; }
		.hot-tracks-admin-muted { color: var(--color-text-maxcontrast); margin: 0 0 1.25rem; }
		.hot-tracks-admin-banner { margin: 0 0 1rem; padding: 0.65rem 0.8rem; border-radius: 8px; background: var(--color-background-hover); }
		.hot-tracks-admin-banner--error { background: var(--color-error, #ffe8e8); }
		.hot-tracks-admin-status { margin: 0 0 1.25rem; }
		.hot-tracks-admin-disconnect { margin: 0 0 1.25rem; }
		.hot-tracks-admin-secondary { margin-top: 0.5rem; }
		.hot-tracks-admin-copyrow { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-top: 0.35rem; }
		.hot-tracks-admin-copyrow code,
		.hot-tracks-admin-copyrow input[readonly] {
			flex: 1 1 16rem;
			min-width: 0;
			padding: 0.35rem 0.55rem;
			background: var(--color-background-dark, #eee);
			border: 1px solid var(--color-border, transparent);
			border-radius: 6px;
			font: inherit;
			font-family: var(--font-face-monospace, monospace);
			font-size: 0.85rem;
		}
		.hot-tracks-admin-hint { margin: 0.35rem 0 1rem; color: var(--color-text-maxcontrast); line-height: 1.45; font-size: 0.9rem; }
	</style>

	<p class="hot-tracks-admin-lead">
		<?php p($l->t('Nothing to set up here for normal use.')); ?>
	</p>
	<p class="hot-tracks-admin-muted">
		<?php p($l->t('Everyone connects Strava under Personal settings → Hot Tracks.')); ?>
	</p>

	<?php if (!empty($_['connected'])): ?>
		<p>
			<?php if (!empty($_['athleteName'])): ?>
				<?php p($l->t('Connected as %1$s.', [$_['athleteName']])); ?>
			<?php else: ?>
				<?php p($l->t('Connected.')); ?>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php p($_['disconnectUrl']); ?>" class="hot-tracks-admin-disconnect">
			<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
			<button type="submit" class="button"><?php p($l->t('Disconnect Strava')); ?></button>
		</form>
	<?php endif; ?>

	<?php if ($proxyConfigured): ?>
		<p class="hot-tracks-admin-status">
			<?php p($l->t('Shared login is on. People can connect without their own Strava API app.')); ?>
		</p>
	<?php endif; ?>

	<details class="hot-tracks-admin-secondary" <?php if ($proxyConfigured): ?>open<?php endif; ?>>
		<summary style="cursor: pointer;"><?php p($l->t('Shared login for the whole server')); ?></summary>
		<p style="margin-top: 0.75rem; line-height: 1.45;">
			<?php p($l->t('Only if one Strava app should serve every user on this Nextcloud. Skip this otherwise.')); ?>
		</p>
		<form method="post" action="<?php p($_['saveUrl']); ?>" style="margin-top: 1rem;">
			<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
			<p>
				<label for="hot-tracks-auth-url"><?php p($l->t('Login service URL')); ?></label><br>
				<input id="hot-tracks-auth-url" name="authServiceUrl" type="url" value="<?php p($_['authServiceUrl']); ?>" placeholder="https://…" style="width: min(100%, 36rem);" autocomplete="off">
			</p>
			<p>
				<label for="hot-tracks-client-id"><?php p($l->t('Client ID')); ?></label><br>
				<input id="hot-tracks-client-id" name="authClientId" type="text" value="<?php p($_['authClientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-client-secret"><?php p($l->t('Client secret')); ?></label><br>
				<input id="hot-tracks-client-secret" name="authClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) ? $l->t('Set — leave empty to keep it') : $l->t('From your login service')); ?>" style="width: min(100%, 24rem);">
			</p>
			<button type="submit" class="primary"><?php p($l->t('Save shared login')); ?></button>
		</form>

		<details style="margin-top: 1.5rem;">
			<summary style="cursor: pointer;"><?php p($l->t('URLs for the login service operator')); ?></summary>
			<p class="hot-tracks-admin-hint" style="margin-top: 0.75rem;">
				<?php p($l->t('Copy these into the login service config, then restart or re-seed that service.')); ?>
			</p>
			<p>
				<label for="hot-tracks-redirect"><?php p($l->t('Callback URL')); ?></label>
				<span class="hot-tracks-admin-copyrow">
					<input id="hot-tracks-redirect" type="text" readonly value="<?php p($_['redirectUri']); ?>">
					<button type="button" class="button hot-tracks-admin-copy" data-copy-target="hot-tracks-redirect"><?php p($l->t('Copy')); ?></button>
				</span>
			</p>
		</details>
	</details>
</div>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
(function () {
	var copied = <?php echo json_encode($l->t('Copied')); ?>;
	var copyLabel = <?php echo json_encode($l->t('Copy')); ?>;
	var disconnectForm = document.querySelector('.hot-tracks-admin-disconnect');
	if (disconnectForm) {
		disconnectForm.addEventListener('submit', function (event) {
			if (!window.confirm(<?php echo json_encode($l->t('Disconnect Strava? GPX files in Hot Tracks stay in your files.')); ?>)) {
				event.preventDefault();
			}
		});
	}

	document.querySelectorAll('.hot-tracks-admin-copy').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var input = document.getElementById(btn.getAttribute('data-copy-target'));
			if (!input || !navigator.clipboard) return;
			navigator.clipboard.writeText(input.value).then(function () {
				btn.textContent = copied;
				window.setTimeout(function () { btn.textContent = copyLabel; }, 1500);
			});
		});
	});
})();
</script>
