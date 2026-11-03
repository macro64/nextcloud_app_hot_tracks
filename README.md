# Hot Tracks for Nextcloud

Hot Tracks provides a sync with Strava via their API. Synced activities are stored in the Nextcloud as monthly folders. A small frontend-app displays basic data and a heatmap. Get your data on your storage.

Each person has their own Strava account. An admin sets up the shared login for the server. Each person connects under personal settings and enters their Strava ID there. GPX files stay in that person's `Hot Tracks/` folder, and the overview shows the last 7 days. The sync window can be the last month, the last year, five years, or the full history.

Tokens and files are bound to the Nextcloud user id. The app does not create shares.

## Settings

Nextcloud has two Hot Tracks pages. They do different jobs.

| Page | Who opens it | What belongs there |
| --- | --- | --- |
| **Administration settings → Hot Tracks** | Admins | Shared login for this server: login service URL, client id and secret. |
| **Personal settings → Hot Tracks** | Each person | Connect with Strava, that person's Strava ID, and disconnect. Optional: which sports to sync, and an extra folder for a GPX copy. |

Both pages show the same hint, so the split stays visible in the app.

## Setup

1. Place this folder in the Nextcloud `custom_apps/hot_tracks` directory (or symlink it as `hot_tracks`) and enable the app. Nextcloud 32 through 36.
2. **Administration settings → Hot Tracks.** Enter the login service URL, client id and secret. Several Nextcloud instances can share the OAuth client `nextcloud`. Each instance adds its callback URL to `NEXTCLOUD_REDIRECT_URIS` and, if used, its webhook URL to `NEXTCLOUD_WEBHOOK_FORWARD_URL`. Re-seed the auth service. `POST /oauth/token` must accept `grant_type=refresh_token`. When the webhook URL is set on the auth service, created, updated, and deleted activities arrive immediately as well. The Strava API application itself lives on that login service, not on this page.
3. **Personal settings → Hot Tracks.** Choose **Connect with Strava**, then enter the Strava ID. It is the number in the profile address, for example `strava.com/athletes/12345678`. Sports and an extra copy folder are optional.
4. In the app, pick a range (month / year / 5 years / all) and **Sync**. The page shows the last 7 calendar days in that person's time zone. Large ranges can hit the API limit. Sync the same range again; activities that are already stored are skipped. The hourly job only fetches activities that are new since the last successful run.

A person who is not an admin does not enter login-service keys. Once an admin has set up the shared login, each person connects with the Strava button under **Personal settings → Hot Tracks**. The same button is also in the app.

GPX files are stored under `Hot Tracks/YYYY/MM/`. The activity export and **Download all tracks** serve the files from Hot Tracks. Indoor activities without GPS appear in the list only.

## Rebuild the frontend

```bash
npm install
npm run build
```

The built files `js/hot_tracks-main.js` and `css/hot_tracks-main.css` are part of the app.
