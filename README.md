# LendTrack

Laravel 12 backend and Flutter Android app for LendTrack. The backend remains at the repository root for the existing Render deployment. The mobile app is in [`mobile/`](mobile/README.md). Database records and credentials are not included.

## Repository layout

- Root: Laravel API, migrations, tests, Dockerfile, and Render startup configuration.
- `mobile/`: Flutter source, Android project, font assets, dependency lockfile, and tests.

Open `mobile/` in Android Studio to work on the phone app. Render continues to build the root Dockerfile; mobile files are excluded from the Docker image.

## Render setup

1. Create an external PostgreSQL database (for example Aiven's free PostgreSQL plan). Keep its credentials private. The PostgreSQL migrations are checked by GitHub Actions.
2. Create a Render Web Service connected to this repository, branch `main`, runtime Docker, Free instance. Dockerfile is at repository root. Health check: `/up`.
3. Copy the variable names from `.env.render.example` into Render's Environment settings and fill in their values. Use the database provider's exact host, port and database. Use SSL. Set `APP_URL` and `ASSET_URL` to the actual HTTPS Render URL.
4. Generate APP_KEY locally with `php artisan key:generate --show`. Put its output ONLY in Render. Keep this key unchanged across redeployments.
5. For the first login, set `DEPLOY_ADMIN_EMAIL` and a unique `DEPLOY_ADMIN_PASSWORD` of at least 12 characters in Render. Startup creates this admin only if that email does not exist. After successful creation remove both variables. Startup never overwrites an existing password or role.
6. Deploy. Startup runs non-destructive pending migrations; it never runs migrate:fresh or the demo seeder. New databases start empty except for the configured admin.
7. Build Flutter with `--dart-define=API_BASE_URL=https://YOUR-SERVICE.onrender.com/api` and install the new APK.

## Boundaries

- The free Render server sleeps when unused; the first request may need time to wake up. Existing mobile timeout/retry behavior still needs a live cold-start test.
- Do not use Render's expiring free PostgreSQL offering for lasting data. Use an external persistent database.
- Existing local MySQL records have NOT been imported. Migration of those records requires a separate, verified transfer; do not upload SQL dumps to this repository.
- Container files are temporary. User uploads need persistent object storage before relying on uploads in production. Sessions and cache use the database.
- Public website registration is disabled in production because it creates staff accounts. Use admin-managed accounts.
- No queue worker is deployed: QUEUE_CONNECTION=sync processes jobs during requests. Configure real mail delivery before using password-reset email.
- Database migrations alone do not prove every lending calculation works on PostgreSQL. Run live login, borrower, loan, payment retry/overpayment, collections and reports checks before real use.

## Checks

GitHub Actions runs PHP syntax checks, the supplied mobile API tests with isolated SQLite, fresh PostgreSQL migrations, and a Docker build. No PHP or Docker runtime was available in the preparation workspace; remote results must be checked before deployment.

Secrets belong in Render environment settings, never GitHub. `.env`, database dumps, dependencies and runtime files are excluded.
