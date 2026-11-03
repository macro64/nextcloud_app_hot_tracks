# Changelog

## 1.0.4

- OSM map tiles work under Nextcloud's `Referrer-Policy: no-referrer` via Leaflet `referrerPolicy`
- Empty-state markup under the heatmap and activity list no longer leaks Vue source into the UI

## 1.0.3

- Personal auth proxy settings save the proxy client id and secret directly, so the form no longer depends on the inline script

## 1.0.2

- README and store copy in English
- UI strings follow the Nextcloud l10n standard, with English as the source language and German in `l10n/de`

## 1.0.1

- The admin client secret and webhook token are stored encrypted with `ICrypto` (existing plaintext values are migrated on read)
- Auth proxy URLs reject private and reserved IP literals and embedded credentials
- The sync API no longer returns internal exception text to the client

## 1.0.0

- Per-person Strava connection, direct or through an auth proxy
- Sync for the last month, the last year, five years, or the full history
- Sport filters, a week overview with a heatmap, GPX export, and a copy into a chosen folder
- Webhooks for created, updated, and deleted activities
