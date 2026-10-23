<?php
/** @var array $_ */
$proxyConfigured = !empty($_['secretSet']) && $_['authServiceUrl'] !== '';
?>
<div class="section">
	<h2>Hot Tracks</h2>
	<?php if (!empty($_['error'])): ?>
		<p><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p><?php p($_['notice']); ?></p>
	<?php endif; ?>

	<p style="max-width: 40rem;">
		Standard ist direkter Strava-OAuth: Jede Person legt unter
		<a href="https://www.strava.com/settings/api" target="_blank" rel="noreferrer noopener">strava.com/settings/api</a>
		eine API-Anwendung an und trägt Client-ID sowie Secret in den
		<strong>persönlichen Einstellungen → Hot Tracks</strong> ein.
	</p>

	<details <?php if ($proxyConfigured): ?>open<?php endif; ?> style="max-width: 40rem; margin: 1rem 0;">
		<summary style="cursor: pointer;">Auth-Proxy (optional, Instanz-Fallback)</summary>
		<p style="margin-top: 0.75rem;">
			Wenn gesetzt, können Personen ohne eigene Strava-App den Proxy nutzen
			(oder ihn in den persönlichen Einstellungen überschreiben). Webhooks laufen nur über den Proxy.
		</p>
		<form method="post" action="<?php p($_['saveUrl']); ?>">
			<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
			<p>
				<label for="hot-tracks-auth-url">Auth-Dienst</label><br>
				<input id="hot-tracks-auth-url" name="authServiceUrl" type="url" value="<?php p($_['authServiceUrl']); ?>" style="width: min(100%, 36rem);">
			</p>
			<p>
				<label for="hot-tracks-client-id">Client-ID</label><br>
				<input id="hot-tracks-client-id" name="authClientId" type="text" value="<?php p($_['authClientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-client-secret">Client-Secret</label><br>
				<input id="hot-tracks-client-secret" name="authClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p($_['secretSet'] ? 'Gesetzt — leer lassen, um es zu behalten' : 'Secret des OAuth-Clients'); ?>" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-redirect">Redirect-URI für den Auth-Seed</label><br>
				<input id="hot-tracks-redirect" type="text" readonly value="<?php p($_['redirectUri']); ?>" style="width: min(100%, 36rem);">
			</p>
			<p>Diese URI muss exakt in <code>NEXTCLOUD_REDIRECT_URIS</code> stehen. Das Client-Secret der activity tracking app bleibt im Auth-Dienst.</p>
			<p>
				<label for="hot-tracks-webhook">Webhook-URL für den Auth-Seed</label><br>
				<input id="hot-tracks-webhook" type="text" readonly value="<?php p($_['webhookUrl']); ?>" style="width: min(100%, 36rem);">
			</p>
			<p>Diese URL in <code>NEXTCLOUD_WEBHOOK_FORWARD_URL</code> eintragen, mehrere Instanzen kommagetrennt, und den Auth-Dienst neu seeden. Sie enthält einen geheimen Token. Neue, geänderte und gelöschte Aktivitäten kommen dann direkt an. Der stündliche Abgleich bleibt das Auffangnetz.</p>
			<button type="submit">Proxy speichern</button>
		</form>
	</details>
</div>
