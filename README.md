# UrSkool (Laravel 12 + React)

UrSkool is a Laravel 12 + React learning platform focused on code-related courses with interactive HTML/CSS/JS lessons.

## Stack

- Laravel 12 (API, auth, admin, migrations, seeders)
- React + TypeScript + React Router
- Tailwind CSS + shadcn/ui
- Interactive lesson runner (CodePen-style HTML/CSS/JS preview)
- MySQL (Docker) or SQLite/MySQL locally

## Docker Setup (Recommended)

1. Prepare environment:

```bash
cp .env.docker .env
```

2. Start containers:

```bash
docker compose up -d --build
```

3. Install dependencies and initialize app:

```bash
docker compose exec app composer install
docker compose exec node npm install
docker compose exec app php artisan key:generate
docker compose exec app php -r "if (file_exists('public/hot')) { unlink('public/hot'); }"
docker compose exec node npm run build
docker compose exec app php artisan migrate:fresh --seed
```

4. Open:

- App: [http://localhost:8080](http://localhost:8080)
- MySQL: `localhost:3307` (`urskool` / `urskool`, root password `root`)

Stop everything:

```bash
docker compose down
```

Optional Vite/HMR in Docker:

```bash
make docker-vite
```

## Local Setup (Without Docker)

1. Install dependencies:

```bash
composer install
npm install
```

2. Configure environment:

```bash
cp .env.example .env
php artisan key:generate
```

3. Initialize database:

```bash
php artisan migrate:fresh --seed
```

4. Run app:

```bash
php artisan serve
npm run dev
```

## Seeded Admin User

- Email: `admin@urskool.test`
- Password: `Admin@12345`
- Login URL: [http://localhost:8080/login](http://localhost:8080/login)

## Useful Commands

- `php artisan test`
- `npm test`
- `npm run build`
- `npm run lint`
- `make dev` (run Laravel + Vite together)
- `make use-build` (remove stale `public/hot` so app uses built assets)

## Blank Page Quick Fix

If the app shows a blank page, it is usually because `public/hot` exists but Vite is not running.

Use one of these:

```bash
# Option 1: run full dev stack (recommended for development)
make dev

# Option 2: use built assets without Vite
npm run build
make use-build

# Docker equivalent
make docker-use-build
make docker-build
```
