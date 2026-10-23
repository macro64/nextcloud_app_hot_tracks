<?php
/** @var array $_ */
$useProxy = ($_['mode'] ?? '') === 'proxy';
?>
<div class="section" id="hot-tracks-personal">
	<h2>Hot Tracks</h2>
	<?php if (!empty($_['error'])): ?>
		<p><strong><?php p($_['error']); ?></strong></p>
	<?php endif; ?>
	<?php if (!empty($_['notice'])): ?>
		<p><?php p($_['notice']); ?></p>
	<?php endif; ?>

	<?php if (!empty($_['connected'])): ?>
		<p>
			Verbunden<?php if (!empty($_['athleteName'])): ?> als <?php p($_['athleteName']); ?><?php endif; ?>.
			Die GPX-Dateien im Ordner Hot Tracks bleiben erhalten.
		</p>
		<form method="post" action="<?php p($_['disconnectUrl']); ?>">
			<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
			<button type="submit">Trennen</button>
		</form>
		<hr>
	<?php endif; ?>

	<h3>Strava verbinden (empfohlen)</h3>
	<ol style="max-width: 40rem; line-height: 1.5;">
		<li>Unter <a href="https://www.strava.com/settings/api" target="_blank" rel="noreferrer noopener">strava.com/settings/api</a> eine API-Anwendung anlegen (Strava-Abo nötig).</li>
		<li>Als <em>Authorization Callback Domain</em> genau <code><?php p($_['callbackDomain']); ?></code> eintragen (ohne <code>https://</code>).</li>
		<li>Client-ID und Client-Secret hier speichern, danach verbinden.</li>
	</ol>

	<form method="post" action="<?php p($_['saveUrl']); ?>" id="hot-tracks-auth-form">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">

		<div id="hot-tracks-direct-fields" <?php if ($useProxy): ?>hidden<?php endif; ?>>
			<p>
				<label for="hot-tracks-client-id">Client-ID</label><br>
				<input id="hot-tracks-client-id" name="clientId" type="text" inputmode="numeric" pattern="[0-9]*" value="<?php p($useProxy ? '' : $_['clientId']); ?>" autocomplete="off" style="width: min(100%, 24rem);" <?php if (!$useProxy): ?>required<?php endif; ?>>
			</p>
			<p>
				<label for="hot-tracks-client-secret">Client-Secret</label><br>
				<input id="hot-tracks-client-secret" name="clientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && !$useProxy ? 'Gesetzt — leer lassen, um es zu behalten' : 'Secret aus den Strava-API-Einstellungen'); ?>" style="width: min(100%, 24rem);">
			</p>
			<p>
				<label for="hot-tracks-redirect">Redirect-URI (nur zur Kontrolle)</label><br>
				<input id="hot-tracks-redirect" type="text" readonly value="<?php p($_['redirectUri']); ?>" style="width: min(100%, 36rem);">
			</p>
		</div>

		<details id="hot-tracks-proxy-details" <?php if ($useProxy): ?>open<?php endif; ?> style="margin: 1rem 0; max-width: 40rem;">
			<summary style="cursor: pointer;">Auth-Proxy verwenden (optional)</summary>
			<p style="margin-top: 0.75rem;">Statt direktem Strava-OAuth über einen Auth-Broker (z.&nbsp;B. fluadrits-auth). Für die meisten Personen nicht nötig.</p>
			<p>
				<label>
					<input id="hot-tracks-use-proxy" name="useProxy" type="checkbox" value="1" <?php if ($useProxy): ?>checked<?php endif; ?>>
					Auth-Proxy aktiv
				</label>
			</p>
			<div id="hot-tracks-proxy-fields" <?php if (!$useProxy): ?>hidden<?php endif; ?>>
				<p>
					<label for="hot-tracks-proxy-url">Auth-Dienst</label><br>
					<input id="hot-tracks-proxy-url" name="proxyUrl" type="url" value="<?php p($_['proxyUrl']); ?>" style="width: min(100%, 36rem);" <?php if ($useProxy): ?>required<?php endif; ?>>
				</p>
				<p>
					<label for="hot-tracks-proxy-client-id">Client-ID</label><br>
					<input id="hot-tracks-proxy-client-id" name="proxyClientId" type="text" value="<?php p($useProxy ? $_['clientId'] : ''); ?>" autocomplete="off" style="width: min(100%, 24rem);" <?php if ($useProxy): ?>required<?php endif; ?>>
				</p>
				<p>
					<label for="hot-tracks-proxy-secret">Client-Secret</label><br>
					<input id="hot-tracks-proxy-secret" name="proxyClientSecret" type="password" value="" autocomplete="new-password" placeholder="<?php p(!empty($_['secretSet']) && $useProxy ? 'Gesetzt — leer lassen, um es zu behalten' : 'Secret des OAuth-Clients'); ?>" style="width: min(100%, 24rem);">
				</p>
			</div>
		</details>

		<button type="submit">Speichern</button>
	</form>
	<?php if (!empty($_['configured']) && empty($_['connected'])): ?>
		<p style="margin-top: 1rem;">
			<a href="<?php p($_['connectUrl']); ?>" style="display: inline-block; line-height: 0;">
				<img
					src="<?php p($_['connectButtonSrc']); ?>"
					width="237"
					height="48"
					alt="Connect with Strava"
				>
			</a>
		</p>
	<?php endif; ?>

	<hr>
	<h3>Sportarten</h3>
	<p style="max-width: 40rem; line-height: 1.5;">
		Bestimmt, was beim Abgleich von Strava geholt wird und welche Gruppen in der App unter Liste und Heatmap zur Auswahl stehen.
		Mehrere Gruppen gleichzeitig möglich.
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
		<button type="submit">Sportarten speichern</button>
	</form>

	<hr>
	<h3>Zusätzlicher Ordner</h3>
	<p style="max-width: 40rem; line-height: 1.5;">
		Leer lassen, dann liegen GPX-Dateien nur unter Hot Tracks/Jahr/Monat.
		Ein Pfad wie <code>Sport/GPX</code> ist relativ zu deinen Dateien.
		Beim Speichern werden bereits vorhandene Dateien dorthin kopiert. Bei vielen Dateien läuft der Rest im Hintergrund weiter.
		Weitere Abrufe legen dieselbe Datei zusätzlich dort ab.
	</p>
	<form method="post" action="<?php p($_['copyFolderUrl']); ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
		<p>
			<label for="hot-tracks-copy-folder">Ordner in deinen Dateien</label><br>
			<input id="hot-tracks-copy-folder" name="gpxCopyFolder" type="text" value="<?php p($_['gpxCopyFolder']); ?>" placeholder="z. B. Sport/GPX" style="width: min(100%, 24rem);">
		</p>
		<button type="submit">Ordner speichern</button>
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
