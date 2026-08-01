# Zero-downtime deploy — apigw.durpalla.com

Production deploys use **rolling servers** (one host at a time) and **rolling container replacement** (one of four backends at a time on ports `8001`–`8004`).

## How it works

1. CI builds and pushes the image to GHCR.
2. Each server deploys **sequentially** (`max-parallel: 1`).
3. On each server, `script/ci-deploy-remote.sh`:
   - Pulls the new image and pins all containers to that **image digest** (not a mutable `:local` tag).
   - Removes orphan `durpalla-apigw*` containers that are not named `1`–`4`.
   - Replaces `durpalla-apigw-1` on port `8001`, warms Laravel caches on the shared bootstrap volume.
   - Replaces `durpalla-apigw-2`, `3`, `4` one at a time (`docker rm -f` then `docker run`) with `/up` health checks.
   - Reconciles any straggler still on an old image, then **fails the deploy** unless all four ports are healthy on the expected digest.
   - Nginx upstream (`apigw_durpalla`) always lists all four ports; `max_fails` skips a backend briefly during single-container swap.

CI never cancels an in-flight host deploy (`cancel-in-progress: false` on the workflow and deploy jobs). A newer push waits for the current roll to finish so hosts are never left half-updated.

There is **no** full stop of all four containers and **no** post-deploy `docker restart` of all containers.

## Cloudflare

Enable origin health monitors for `apigw.durpalla.com` on `/up` so restarting servers are drained from the pool during rolling deploy.

## Manual deploy on a server

```bash
export DEPLOY_PATH=/opt/durpalla-apigw
export IMAGE=ghcr.io/durpalla/durpalla-apigw:dev-latest
export GHCR_TOKEN_B64="$(echo -n "$TOKEN" | base64 -w0)"
export GHCR_USER=durpalla
export DEPLOY_SCRIPT_DIR=/opt/durpalla-apigw/script
bash /opt/durpalla-apigw/script/ci-deploy-remote.sh
```

## Rollback

Re-pull and run deploy with a known-good image tag, or on one server:

```bash
docker pull ghcr.io/durpalla/durpalla-apigw:dev-YYYYMMDD-<sha>
export IMAGE=ghcr.io/durpalla/durpalla-apigw:dev-YYYYMMDD-<sha>
# ... run ci-deploy-remote.sh
```

During rolling replace, at most one of four local backends is briefly unavailable; nginx `least_conn` routes to the other three.

## Scripts

| Script | Purpose |
|--------|---------|
| `script/ci-deploy-remote.sh` | Rolling 4-container deploy on one host |
| `.github/workflows/ci-deploy.yml` | CI: sequential multi-server deploy |
