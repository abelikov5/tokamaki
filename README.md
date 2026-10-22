# Paints by Numbers

Монорепозиторий веб-сервиса, который преобразует JPG/PNG в картину по номерам.

## Состав проекта

```text
apps/web/                 Laravel: web-интерфейс, задания и бизнес-логика
services/generator/       FastAPI: обработка изображения, SVG и PDF
legacy/next-prototype/    прежний Next.js-прототип для справки
infra/nginx/              конфигурация публичного web-сервера
scripts/                  локальная установка и запуск без Docker
```

Laravel является единственной публичной точкой входа. Браузер не обращается к Python напрямую: Laravel проверяет параметры, создаёт запись задания, вызывает внутренний FastAPI и проксирует готовые файлы.

## Локальный запуск без Docker (Windows)

Первичная установка:

```powershell
.\scripts\setup-local.ps1
```

Запуск Laravel на `http://127.0.0.1:8000` и Python на внутреннем порту `8001`:

```powershell
.\scripts\dev.ps1
```

Остановить оба процесса можно сочетанием `Ctrl+C`.

Ручной запуск, если нужны отдельные консоли:

```powershell
cd services\generator
.\.venv\Scripts\python.exe -m uvicorn app.main:app --reload --port 8001

cd apps\web
php artisan serve --port 8000
```

Для локальной разработки Laravel использует SQLite и не требует PostgreSQL или Redis.

## Запуск через Docker

```powershell
docker compose up --build
```

По умолчанию страница будет доступна только локально на `http://127.0.0.1:8081`. Compose поднимает Nginx, Laravel/PHP-FPM, Python-генератор, PostgreSQL и Redis.

## Production-деплой

Production использует отдельный Compose project `tokamaki` и публикует контейнерный Nginx только на `127.0.0.1:8081`. Системный Nginx проксирует домен `tokamaki.ru` на этот порт.

Первичная настройка на сервере:

```bash
git clone https://github.com/abelikov5/tokamaki.git /opt/tokamaki
cd /opt/tokamaki
cp .env.production.example .env
# Укажите APP_KEY и случайный DB_PASSWORD в .env
./scripts/deploy.sh
```

После первичной настройки push в `main` запускает `.github/workflows/deploy.yml`: сервер получает актуальный commit через Git и пересобирает только контейнеры проекта `tokamaki`. Секреты `DEPLOY_SSH_KEY` и `DEPLOY_KNOWN_HOSTS` хранятся в GitHub Environment `production`, а production `.env` остаётся только на VDS.

## Тесты

```powershell
cd apps\web
php artisan test

cd ..\..\services\generator
.\.venv\Scripts\python.exe -m pytest -q
```

## Python CLI

Алгоритм можно запускать без web-интерфейса:

```powershell
cd services\generator
.\.venv\Scripts\python.exe scripts\run_demo.py "C:\path\photo.jpg" `
  --colors 36 --min-region-size 150 --smoothing 2.5 `
  --min-region-width 4 --contour-smoothing 2.5 `
  --complexity simple --line-separation 3 --label-density 1
```

Результаты сохраняются в `services/generator/demo-output/`.

## Следующий этап архитектуры

Текущий вызов генератора синхронный, чтобы вертикальный прототип оставался простым. При переходе к фоновой обработке HTTP-контракт генератора сохранится, а Laravel будет ставить `generation_id` в Redis-очередь. Python workers будут сохранять результаты в S3/MinIO и возвращать подписанный callback. Таблица `generations` уже отделяет бизнес-состояние задания от файлов Python.
