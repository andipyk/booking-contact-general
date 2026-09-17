#!/usr/bin/env bash
# Import the credential and workflow into n8n, then activate the workflow.
#
# Run automatically by setup.sh, and safe to re-run: import overwrites by id,
# so editing the JSON and re-running is the way to update a workflow.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "    waiting for n8n to answer"
tries=0
until curl -fsS -o /dev/null "http://localhost:${N8N_PORT:-8103}/healthz" 2>/dev/null; do
  tries=$((tries + 1))
  if [ "$tries" -ge 45 ]; then
    echo "    n8n did not become reachable; check: docker compose logs n8n" >&2
    exit 1
  fi
  sleep 2
done

docker compose exec -T -u node n8n n8n import:credentials --input=/import/credentials/mailpit-smtp.json
docker compose exec -T -u node n8n n8n import:workflow   --input=/import/workflows/ess-booking.json

# Import preserves the `active` flag in the file but does not register the
# webhook until the workflow is published in this instance. n8n 2.x renamed
# `update:workflow` to `publish:workflow`; try the current name first and fall
# back so the script keeps working on either.
docker compose exec -T -u node n8n n8n publish:workflow --id=ess-booking-workflow 2>/dev/null \
  || docker compose exec -T -u node n8n n8n update:workflow --id=ess-booking-workflow --active=true 2>/dev/null \
  || echo "    (activate the workflow in the n8n UI; neither CLI command was available)"

docker compose restart n8n >/dev/null
echo "    n8n workflow imported and active"
