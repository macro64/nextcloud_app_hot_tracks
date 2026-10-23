# Changelog

## 1.0.1

- Admin-Client-Secret und Webhook-Token werden mit `ICrypto` verschlüsselt gespeichert (bestehende Klartextwerte werden beim Lesen migriert)
- Auth-Proxy-URLs lehnen private/reservierte IP-Literale und eingebettete Zugangsdaten ab
- Sync-API gibt bei Fehlern keine internen Exception-Texte mehr an den Client

## 1.0.0

- Strava-Verbindung pro Person, direkt oder über einen Auth-Proxy
- Abgleich für den letzten Monat, das letzte Jahr, fünf Jahre oder den gesamten Verlauf
- Sportarten filtern, Wochenübersicht mit Heatmap, GPX-Export und Kopie in einen eigenen Ordner
- Webhooks für neue, geänderte und gelöschte Aktivitäten
