# Hot Tracks für Nextcloud

Private Nextcloud-App. Jede Person auf der Instanz verbindet ihr Konto bei der activity tracking app über [fluadrits-auth](https://auth.fluadrits.de). GPX-Dateien liegen im persönlichen Ordner `Hot Tracks/` dieser Person, die Übersicht zeigt ihre letzten 7 Tage.

Dieselbe App kann auf mehreren Nextclouds laufen. Alle Instanzen nutzen denselben OAuth-Client `nextcloud`. Pro Instanz steht ihre Callback-URL in `NEXTCLOUD_REDIRECT_URIS`. Tokens und Dateien bleiben an die Nextcloud-Benutzer-ID gebunden. Die App legt keine Freigaben an.

## Einrichten

1. Diesen Ordner nach `custom_apps/hot_tracks` der Nextcloud legen (oder als `hot_tracks` verlinken) und die App aktivieren. Nextcloud 31 bis 36.
2. Im Auth-Dienst die Variablen aus `NEXTCLOUD_*` setzen (dieselben Client-Daten der activity tracking app wie die anderen Clients, Scopes `read,activity:read_all`, keine Webhook-URL). `NEXTCLOUD_REDIRECT_URIS` ist eine kommagetrennte Liste. Jede Nextcloud trägt ihre eigene Callback-URL bei, abgelesen unter **Verwaltung → Hot Tracks**. Eine weitere Instanz ist ein weiterer Eintrag und ein erneuter Seed, kein neuer Client.
3. Auth-Dienst neu seeden und deployen. `POST /oauth/token` muss `grant_type=refresh_token` können, sonst läuft der Abgleich nach sechs Stunden nicht weiter.
4. In jeder Nextcloud unter **Verwaltung → Hot Tracks** Auth-URL `https://auth.fluadrits.de`, Client-ID `nextcloud` und dasselbe Client-Secret eintragen.
5. Jede Person öffnet die App, wählt **Mit der activity tracking app verbinden** und **Jetzt abgleichen**. Der erste Lauf holt 30 Tage dieser Person. Die Seite zeigt davon die letzten 7 Kalendertage in ihrer Zeitzone. Der stündliche Job geht alle verbundenen Konten dieser Instanz durch.
6. Der Hintergrundjob gleicht stündlich ab, sobald Nextcloud-Cron läuft.

GPX-Dateien liegen unter `Hot Tracks/JJJJ/MM/`. Der Export in der Aktivität und das ZIP der Woche liefern diese Dateien. Indoor-Einheiten ohne GPS erscheinen nur in der Liste.

## Frontend neu bauen

```bash
npm install
npm run build
```

Die gebauten Dateien `js/hot_tracks-main.js` und `css/hot_tracks-main.css` gehören zur App.
