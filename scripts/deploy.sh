#!/usr/bin/env bash
set -Eeuo pipefail

project_dir="${TOKAMAKI_PROJECT_DIR:-/opt/tokamaki}"
cd "$project_dir"

if [[ ! -f .env ]]; then
    echo "Missing $project_dir/.env. Copy .env.production.example and set production secrets first." >&2
    exit 1
fi

docker compose --project-name tokamaki config --quiet
docker compose --project-name tokamaki build --pull
docker compose --project-name tokamaki up -d --remove-orphans
docker compose --project-name tokamaki ps

for attempt in {1..30}; do
    if curl --fail --silent --show-error http://127.0.0.1:8081/healthz >/dev/null; then
        echo "Tokamaki deployment is healthy."
        exit 0
    fi
    sleep 2
done

echo "Tokamaki did not become healthy in time." >&2
docker compose --project-name tokamaki logs --tail=120
exit 1
