.PHONY: help install env env-docker key migrate seed fresh test lint build dev use-build \
	docker-up docker-down docker-logs docker-shell docker-node-shell docker-init docker-fresh docker-test docker-build docker-use-build docker-vite

# Local shortcuts
help:
	@echo "Available targets:"
	@echo "  make install        Install PHP and Node dependencies"
	@echo "  make env            Create .env from .env.example if missing"
	@echo "  make env-docker     Copy .env.docker to .env"
	@echo "  make key            Generate Laravel app key"
	@echo "  make migrate        Run database migrations"
	@echo "  make seed           Seed database"
	@echo "  make fresh          Fresh migrate + seed"
	@echo "  make dev            Run Laravel + Vite dev stack (composer run dev)"
	@echo "  make use-build      Force production assets (removes stale public/hot)"
	@echo "  make test           Run Laravel tests"
	@echo "  make lint           Run frontend lint"
	@echo "  make build          Build frontend assets"
	@echo ""
	@echo "Docker targets:"
	@echo "  make docker-up      Build and start containers"
	@echo "  make docker-down    Stop containers"
	@echo "  make docker-logs    Follow container logs"
	@echo "  make docker-shell   Open shell in PHP app container"
	@echo "  make docker-node-shell Open shell in Node container"
	@echo "  make docker-init    One-time Docker init (env + deps + key + fresh seed)"
	@echo "  make docker-fresh   Fresh migrate + seed in Docker"
	@echo "  make docker-test    Run Laravel tests in Docker"
	@echo "  make docker-build   Run frontend build in Docker"
	@echo "  make docker-use-build Remove public/hot in Docker (force built assets)"
	@echo "  make docker-vite    Start Vite dev server in Docker (optional)"

install:
	composer install
	npm install

env:
	@if [ ! -f .env ]; then cp .env.example .env; echo ".env created"; else echo ".env already exists"; fi

env-docker:
	cp .env.docker .env

key:
	php artisan key:generate

migrate:
	php artisan migrate --force

seed:
	php artisan db:seed --force

fresh:
	php artisan migrate:fresh --seed --force

dev:
	composer run dev

test:
	php artisan test

lint:
	npm run lint

build:
	npm run build

use-build:
	@php -r "if (file_exists('public/hot')) { unlink('public/hot'); echo 'Removed public/hot\n'; } else { echo 'public/hot not present\n'; }"
	@echo "Using built assets from public/build."

# Docker shortcuts
docker-up:
	docker compose up -d --build

docker-down:
	docker compose down

docker-logs:
	docker compose logs -f

docker-shell:
	docker compose exec app sh

docker-node-shell:
	docker compose exec node sh

docker-init: env-docker docker-up
	docker compose exec app composer install
	docker compose exec node npm install
	docker compose exec app php artisan key:generate
	docker compose exec app php -r "if (file_exists('public/hot')) { unlink('public/hot'); echo 'Removed public/hot\n'; }"
	docker compose exec node npm run build
	docker compose exec app php artisan migrate:fresh --seed --force

docker-fresh:
	docker compose exec app php artisan migrate:fresh --seed --force

docker-test:
	docker compose exec app php artisan test

docker-build:
	docker compose exec node npm run build

docker-use-build:
	docker compose exec app php -r "if (file_exists('public/hot')) { unlink('public/hot'); echo 'Removed public/hot\n'; } else { echo 'public/hot not present\n'; }"

docker-vite:
	docker compose exec node npm run dev -- --host 0.0.0.0 --port 5173
