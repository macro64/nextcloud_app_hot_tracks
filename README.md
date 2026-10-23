# Hot Tracks for Nextcloud

Hot Tracks provides a sync with Strava via their API. Synced activities are stored in the Nextcloud as monthly folders. A small frontend-app displays basic data and a heatmap. Get your data on your storage.

Each person connects their own Strava account, either with direct OAuth (their own API application) or through an optional auth proxy. GPX files stay in that person's `Hot Tracks/` folder, and the overview shows the last 7 days. The sync window can be the last month, the last year, five years, or the full history.

Tokens and files are bound to the Nextcloud user id. The app does not create shares.

## Setup

1. Place this folder in the Nextcloud `custom_apps/hot_tracks` directory (or symlink it as `hot_tracks`) and enable the app. Nextcloud 32 through 36.
2. **Per person (default):** Create an API application at [strava.com/settings/api](https://www.strava.com/settings/api) (a Strava subscription is required). Set the *Authorization Callback Domain* to the Nextcloud hostname, without `https://`. Save the client id and client secret under **Settings → Hot Tracks**, then choose **Connect with Strava** in the app.
3. Pick a range (month / year / 5 years / all) and **Sync**. The page shows the last 7 calendar days in that person's time zone. Large ranges can hit the API limit. Sync the same range again; activities that are already stored are skipped. The hourly job only fetches activities that are new since the last successful run.
4. **Optional auth proxy:** Enable **Use auth proxy** in the personal settings, or set an instance fallback under **Administration → Hot Tracks**. Several Nextcloud instances can share the OAuth client `nextcloud`. Each instance adds its callback URL to `NEXTCLOUD_REDIRECT_URIS` and, if used, its webhook URL to `NEXTCLOUD_WEBHOOK_FORWARD_URL`. Re-seed the auth service. `POST /oauth/token` must accept `grant_type=refresh_token`. When the webhook URL is set on the auth service, created, updated, and deleted activities arrive immediately as well.

GPX files are stored under `Hot Tracks/YYYY/MM/`. Personal settings choose which sports (run, ride, ski, water) are synced and which filters appear in the list and the heatmap. An extra folder in Files can be set, and the same GPX file is copied there. The activity export and **Download all as ZIP** serve the files from Hot Tracks. Indoor activities without GPS appear in the list only.

## Rebuild the frontend

```bash
npm install
npm run build
```

The built files `js/hot_tracks-main.js` and `css/hot_tracks-main.css` are part of the app.
