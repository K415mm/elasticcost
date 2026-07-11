---
name: sweinstruction
description: "Deploy the elasticcost Laravel app on Alibaba Cloud using Docker Compose."
license: MIT
---

# Alibaba Cloud Docker Compose Deployment

Use this when deploying the `elasticcost` Laravel app to the Alibaba Cloud server at `47.251.180.213`.

## 1. Commit and push locally

```bash
# from s:\elasticcost
git status --short
git add app/Services/AiConfigHelper.php config/ai.php tests/Feature/AiConfigHelperEmbeddingsTest.php
git commit -m "feat: configure alternative embedding providers via AiConfigHelper"
git pull origin main
git push origin main
```

## 2. SSH to the remote server

```powershell
ssh -i s:\elasticcost\key\ali1.pem -o StrictHostKeyChecking=accept-new -o ServerAliveInterval=60 -o ServerAliveCountMax=60 root@47.251.180.213
```

## 3. Pull latest code on the server

```bash
cd /var/www/elasticcost
git pull origin main
```

## 4. Deploy with Docker Compose

The server uses the `docker compose` plugin. The standalone `docker-compose` binary is not installed.

If only PHP code changed, the container image does not need rebuilding because the source is mounted at `.:/var/www`:

```bash
cd /var/www/elasticcost
docker compose up -d
```

If `Dockerfile`, `Dockerfile.octane`, `composer.json`, `composer.lock`, `package.json`, or `package-lock.json` changed, rebuild the images:

```bash
cd /var/www/elasticcost
docker compose -f docker-compose.yml up -d --build
```

## 5. Run Laravel post-deployment commands

```bash
cd /var/www/elasticcost

# Run migrations
docker compose exec app php artisan migrate --force

# Cache config, events, routes, and views
docker compose exec app php artisan optimize

# Restart long-running services so they pick up code changes
docker compose restart app worker reverb pulse
```

## 6. Verify the deployment

```bash
cd /var/www/elasticcost

# Check container status
docker compose ps

# Tail logs
docker compose logs -f
```

## Containers involved

- `elasticcost-app` — PHP-FPM application
- `elasticcost-web` — nginx reverse proxy
- `elasticcost-db` — pgvector (Postgres 17) database
- `elasticcost-redis` — Redis cache/queue
- `elasticcost-worker` — Laravel Horizon queue worker
- `elasticcost-reverb` — Laravel Reverb WebSocket server
- `elasticcost-pulse` — Laravel Pulse worker
