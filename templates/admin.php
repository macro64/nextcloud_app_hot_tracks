<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
$proxyConfigured = !empty($_['secretSet']) && $_['authServiceUrl'] !== '';
?>
<div class="section">
	<h2><?php p($l->t('Hot Tracks')); ?></h2>
	<?php if (!empty($_['error'])): ?>
		<p><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p><?php p($_['notice']); ?></p>
	<?php endif; ?>

	<p style="max-width: 40rem;">
		<?php print_unescaped($l->t('By default each person uses direct Strava OAuth: create an API application at %1$sstrava.com/settings/api%2$s and enter the client ID and secret under Personal settings → Hot Tracks.', ['<a href="https://www.strava.com/settings/api" target="_blank" rel="noreferrer noopener">', '</a>'])); ?>
	</p>

	<details <?php if ($proxyConfigured): ?>open<?php endif; ?> style="max-width: 40rem; margin: 1rem 0;">
		<summary style="cursor: pointer;"><?php p($l->t('Auth proxy (optional, instance fallback)')); ?></summary>
		<p style="margin-top: 0.75rem;">
			<?php p($l->t('When set, people without their own Strava app can use the proxy (or override it in personal settings). Webhooks only work through the proxy.')); ?>
		</p>
		<form method="post" action="<?php p($_['saveUrl']); ?>">
			<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
			<p>
				<label for="hot-tracks-auth-url"><?php p($l->t('Auth service')); ?></label><br>
				<input id="hot-tracks-auth-url" name="authServiceUrl" type="url" value="<?php p($_['authServiceUrl']); ?>" style="width: min(100%, 36rem);">
			</p>
			<p>
				<label for="hot-tracks-client-id"><?php p($l->t('Client ID')); ?></label><br>
				<input id="hot-tracks-client-id" name="authClientId" type="text" value="<?php p($_['authClientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-client-secret"><?php p($l->t('Client secret')); ?></label><br>
				<input id="hot-tracks-client-secret" name="authClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) ? $l->t('Set — leave empty to keep it') : $l->t('OAuth client secret')); ?>" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-redirect"><?php p($l->t('Redirect URI for the auth seed')); ?></label><br>
				<input id="hot-tracks-redirect" type="text" readonly value="<?php p($_['redirectUri']); ?>" style="width: min(100%, 36rem);">
			</p>
			<p><?php print_unescaped($l->t('This URI must appear exactly in %1$s. The activity tracking app client secret stays in the auth service.', ['<code>NEXTCLOUD_REDIRECT_URIS</code>'])); ?></p>
			<p>
				<label for="hot-tracks-webhook"><?php p($l->t('Webhook URL for the auth seed')); ?></label><br>
				<input id="hot-tracks-webhook" type="text" readonly value="<?php p($_['webhookUrl']); ?>" style="width: min(100%, 36rem);">
			</p>
			<p><?php print_unescaped($l->t('Put this URL in %1$s (comma-separated for multiple instances) and re-seed the auth service. It contains a secret token. New, updated and deleted activities then arrive immediately. The hourly sync remains the safety net.', ['<code>NEXTCLOUD_WEBHOOK_FORWARD_URL</code>'])); ?></p>
			<button type="submit"><?php p($l->t('Save proxy')); ?></button>
		</form>
	</details>
</div>
