# Hot Tracks für Nextcloud

Private Nextcloud-App. Jede Person verbindet ihr Strava-Konto per direktem OAuth (eigene API-App) oder optional über einen Auth-Proxy. GPX-Dateien liegen im persönlichen Ordner `Hot Tracks/`, die Übersicht zeigt die letzten 7 Tage. Beim Abgleich wählst du den Zeitraum: letzter Monat, Jahr, 5 Jahre oder alles.

Tokens und Dateien bleiben an die Nextcloud-Benutzer-ID gebunden. Die App legt keine Freigaben an.

## Einrichten

1. Diesen Ordner nach `custom_apps/hot_tracks` der Nextcloud legen (oder als `hot_tracks` verlinken) und die App aktivieren. Nextcloud 32 bis 36.
2. **Pro Person (Standard):** Unter [strava.com/settings/api](https://www.strava.com/settings/api) eine API-Anwendung anlegen (Strava-Abo nötig). Als *Authorization Callback Domain* den Hostnamen der Nextcloud eintragen (ohne `https://`). Client-ID und Client-Secret unter **Einstellungen → Hot Tracks** speichern und in der App **Mit Strava verbinden**.
3. Zeitraum wählen (Monat / Jahr / 5 Jahre / Alles) und **Abgleichen**. Die Seite zeigt die letzten 7 Kalendertage in der Zeitzone der Person. Große Zeiträume können am API-Limit scheitern – denselben Zeitraum erneut abgleichen; bereits gespeicherte Aktivitäten werden übersprungen. Der stündliche Job holt nur neue Aktivitäten seit dem letzten erfolgreichen Lauf.
4. **Optional Auth-Proxy:** In den persönlichen Einstellungen unter „Auth-Proxy verwenden“ oder als Instanz-Fallback unter **Verwaltung → Hot Tracks**. Mehrere Nextclouds können denselben OAuth-Client `nextcloud` nutzen; pro Instanz stehen Callback- und Webhook-URL in `NEXTCLOUD_REDIRECT_URIS` bzw. `NEXTCLOUD_WEBHOOK_FORWARD_URL`. Auth-Dienst neu seeden. `POST /oauth/token` muss `grant_type=refresh_token` können. Liegt die Webhook-URL im Auth-Dienst, kommen neue, geänderte und gelöschte Aktivitäten zusätzlich sofort an.

GPX-Dateien liegen unter `Hot Tracks/JJJJ/MM/`. In den persönlichen Einstellungen legen die Sportarten (Laufen, Rad, Ski, Wasser) fest, was beim Abgleich geholt wird und welche Filter in Liste und Heatmap zur Auswahl stehen. Zusätzlich kann ein eigener Ordner in den Dateien angegeben werden; dieselbe Datei wird dort mit abgelegt. Der Export in der Aktivität und **Alles als ZIP** liefern die Dateien aus Hot Tracks. Indoor-Einheiten ohne GPS erscheinen nur in der Liste.

## Frontend neu bauen

```bash
npm install
npm run build
```

Die gebauten Dateien `js/hot_tracks-main.js` und `css/hot_tracks-main.css` gehören zur App.
