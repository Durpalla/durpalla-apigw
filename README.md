# Durpalla API Gateway

Lightweight Laravel API service for customer app and related endpoints. Same database as the main Durpalla application — **no migrations** in this project. 

## Setup

- **Database:** Point `DB_*` in `.env` to the **same database** as the main app (e.g. `DB_DATABASE=durpalla`). Do not run `php artisan migrate` here; schema is managed only by the main Durpalla application.
- **Application key:** `php artisan key:generate`
- **Passport:** Use the **same** RSA key pair as the main Durpalla app (tokens must validate across services).

  **Production (Docker / CI deploy):** keys are stored on the `apigw-storage` volume as `storage/oauth-private.key` and `storage/oauth-public.key`. On each deploy, `script/ensure-passport-keys.php` runs before `config:cache`:

  1. If key files already exist on the volume, they are kept (never overwritten).
  2. Otherwise deploy runs `script/seed-passport-keys-volume.sh` (copies from `/var/www/html/durpalla/storage`, `DURPALLA_PASSPORT_KEYS_DIR`, or `/opt/durpalla/storage`).
  3. Otherwise keys are copied from `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` in `/opt/durpalla-apigw/.env`.

  Add multiline PEM values to `.env` (or copy key files from the main app):

  ```env
  PASSPORT_PRIVATE_KEY="-----BEGIN RSA PRIVATE KEY-----
  ...
  -----END RSA PRIVATE KEY-----"

  PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
  ...
  -----END PUBLIC KEY-----"
  ```

  After the first successful deploy, keys persist in the Docker volume even if `.env` entries are removed.

```bash
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD to match main app
php artisan key:generate
```

## Run

```bash
php artisan serve
# Or with Octane (recommended for production):
php artisan octane:start
```

## Structure

- **No modules** — flat Laravel: `app/Http`, `app/Models`, `app/Services`, `routes/`.
- **Small footprint** — API-only; minimal views and front-end assets.
- **Shared DB** — reads/writes the same MySQL as Durpalla; no migration files in this repo.

## Tests

Tests use a separate database (`apigw_test`). The test bootstrap creates the DB if missing; `RefreshDatabase` runs this project’s migrations (users, user_otps, customers, personal_access_tokens, Passport oauth_*) so the test DB has the required tables. Production continues to use the main app’s database without running these migrations.

```bash
php artisan test
```

## Load tests (booking ≥5 TPS)

k6 open-loop and closed-loop scripts live in [`loadtests/`](loadtests/README.md). Seed trip/seat inventory from the main Durpalla app:

```bash
cd ../durpalla && php artisan db:seed --class=TransportLoadTestSeeder
cd ../durpalla-apigw && ./loadtests/run.sh open
```

## Production deploy

CI (`.github/workflows/ci-deploy-server-build.yml`) SSHs, starts an async `nohup` build on each server, then exits (almost no Actions minutes). Build/roll continue on the VPS — see `/opt/durpalla-apigw/ci-runner/deploy.log`. It does **not** change host nginx, SSL, or `.env`. Legacy GHCR path (`ci-deploy.yml`) is manual `workflow_dispatch` only.

### One-time server bootstrap (per app server)

```bash
sudo mkdir -p /opt/durpalla-apigw
sudo chown -R $USER:$USER /opt/durpalla-apigw
# Copy .env.docker.example → /opt/durpalla-apigw/.env and fill in secrets

# Host nginx (HTTP proxy to Docker ports 8001–8004)
sudo bash script/setup-host-nginx.sh
```

### Cloudflare SSL (recommended — no certbot renewals)

1. Add `apigw.durpalla.com` in Cloudflare DNS → **Proxied** (orange cloud) → A record to your server IP.
2. Choose one mode:

**Option A — Flexible (simplest, no origin cert files)**

- Cloudflare → SSL/TLS → **Flexible**
- Origin stays HTTP on port 80 (`setup-host-nginx.sh` is enough)
- Laravel receives `X-Forwarded-Proto: https` via `cloudflare-real-ip.conf`

**Option B — Full (strict) with Cloudflare Origin Certificate**

Shared wildcard cert for all `*.durpalla.com` services on every server:

```text
/opt/durpalla/durpalla-cert.pem
/opt/durpalla/durpalla-key.pem
```

- Cloudflare Dashboard → SSL/TLS → **Origin Server** → **Create Certificate**
- Hostnames: `*.durpalla.com`, `durpalla.com`
- Copy the **Origin Certificate** and **Private Key** (shown once)

Install on one server:

```bash
sudo bash script/install-durpalla-cert.sh /path/to/cert.pem /path/to/key.pem
sudo bash script/setup-apigw-cloudflare-ssl.sh   # host nginx
# Docker host-network (.94): certs auto-mount when both files exist
sudo bash script/setup-host-network-nginx.sh
```

Sync to all servers from dev machine:

```bash
# Place files locally (gitignored):
#   .local/durpalla-cert.pem
#   .local/durpalla-key.pem
bash script/sync-durpalla-cert-to-servers.sh
```

- Cloudflare → SSL/TLS → **Full (strict)**

You cannot export Cloudflare’s edge certificate — only create **Origin Certificates** for your server. Edge HTTPS is automatic when DNS is proxied.

### Every deploy (automatic via GitHub Actions)

Push to `master` → Actions **only SSHs**, copies scripts, starts `nohup` on each host, then **exits** (~seconds of minutes). Docker build + roll continue on the VPS.

```bash
# Watch progress on a server
tail -f /opt/durpalla-apigw/ci-runner/deploy.log
cat /opt/durpalla-apigw/ci-runner/status   # state=queued|running|ok|failed
```

On finish (ok or failed), the server emails **jewelrana.dev@gmail.com** using `MAIL_*` from `/opt/durpalla-apigw/.env` (via Laravel inside a running `durpalla-apigw-*` container, with host-php / `php:cli` fallbacks). Check `email_notify=` in `ci-runner/status` and the end of `deploy.log` if mail is missing. SMTP must be real (`MAIL_MAILER=smtp`), not `log`.

**One-time per server** (if `src` is missing):

```bash
sudo mkdir -p /opt/durpalla-apigw && sudo chown -R "$USER:$USER" /opt/durpalla-apigw
# Ensure .env already exists at /opt/durpalla-apigw/.env
# Git access: default is HTTPS + GITHUB_TOKEN from Actions (no host key / deploy key needed).
# Optional: DEPLOY_GIT_SSH_KEY for git@ clone, or DEPLOY_GIT_TOKEN to override the token.
```

Optional Actions secrets: `DEPLOY_GIT_SSH_KEY`, `DEPLOY_GIT_TOKEN`, or `DEPLOY_GIT_REPO`.

Legacy GHCR build+pull: run workflow **CI — Build & Deploy (GHCR fallback)** manually.

To redeploy manually on a server from an existing checkout:

```bash
cd /opt/durpalla-apigw/src
git fetch && git checkout --detach origin/master
DOCKER_BUILDKIT=1 docker build -t durpalla-apigw-app:local .
cd /opt/durpalla-apigw
IMAGE=durpalla-apigw-app:local LOCAL_IMAGE_BUILD=1 \
  DEPLOY_SCRIPT_DIR=/opt/durpalla-apigw/ci-runner/scripts \
  bash /opt/durpalla-apigw/ci-runner/scripts/ci-deploy-remote.sh
```

