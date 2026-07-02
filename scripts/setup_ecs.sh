#!/usr/bin/env bash

# Alibaba Cloud ECS Setup & Deployment Script for ElasticCost
set -e

echo "=== 1. Updating System & Installing Prerequisites ==="
sudo apt-get update -y
sudo apt-get install -y curl git ca-certificates gnupg lsb-release postgresql-client redis-tools

echo "=== 2. Installing Docker & Docker Compose ==="
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com -o get-docker.sh
    sudo sh get-docker.sh
    sudo usermod -aG docker $USER || true
    sudo systemctl enable --now docker
    rm get-docker.sh
fi

echo "=== Docker Version ==="
docker --version
docker compose version

echo "=== 3. Building and Starting Application Containers ==="
if [ ! -f .env ]; then
    echo "Copying .env.production.example to .env..."
    cp .env.production.example .env
    echo "Generating Application Key..."
fi

docker compose up -d --build

echo "=== 4. Running Post-Deployment Tasks ==="
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan storage:link --force
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

echo "=== Deployment Status ==="
docker compose ps
