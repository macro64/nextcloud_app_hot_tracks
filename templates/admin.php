<?php
/** @var array $_ */
?>
<div class="section">
	<h2>Strava</h2>
	<?php if (!empty($_['error'])): ?>
		<p><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p><?php p($_['notice']); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php p($_['saveUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
		<p>
			<label for="strava-auth-url">Auth-Dienst</label><br>
			<input id="strava-auth-url" name="authServiceUrl" type="url" value="<?php p($_['authServiceUrl']); ?>" style="width: min(100%, 36rem);" required>
		</p>
		<p>
			<label for="strava-client-id">Client-ID</label><br>
			<input id="strava-client-id" name="authClientId" type="text" value="<?php p($_['authClientId']); ?>" autocomplete="off" required>
		</p>
		<p>
			<label for="strava-client-secret">Client-Secret</label><br>
			<input id="strava-client-secret" name="authClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p($_['secretSet'] ? 'Gesetzt — leer lassen, um es zu behalten' : 'Secret des OAuth-Clients'); ?>">
		</p>
		<p>
			<label for="strava-redirect">Redirect-URI für den Auth-Seed</label><br>
			<input id="strava-redirect" type="text" readonly value="<?php p($_['redirectUri']); ?>" style="width: min(100%, 36rem);">
		</p>
		<p>Diese URI muss exakt in <code>NEXTCLOUD_REDIRECT_URIS</code> stehen. Das Strava-Client-Secret bleibt im Auth-Dienst.</p>
		<button type="submit">Speichern</button>
	</form>
</div>
