#!/usr/bin/env bash
# Dump n8n's execution history as committed evidence.
#
# The n8n UI needs an owner account before it will show anything, so this reads
# the instance's own SQLite database instead. It keeps the failed runs: the
# error rows are the debugging record, and hiding them would make the build look
# smoother than it was.
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p docs

docker compose exec -T -u node n8n node -e '
const { DatabaseSync } = require("node:sqlite");
const db = new DatabaseSync("/home/node/.n8n/database.sqlite", { readOnly: true });
const rows = db.prepare(`
  SELECT e.id, e.workflowId, e.status, e.mode, e.startedAt, e.stoppedAt
  FROM execution_entity e
  ORDER BY e.id ASC
`).all();
console.log(JSON.stringify(rows, null, 2));
' > docs/n8n-executions.json 2>/dev/null

python3 - <<'PY'
import json, datetime, pathlib
rows = json.loads(pathlib.Path("docs/n8n-executions.json").read_text())
print(f"{'run':>4}  {'status':<8} {'mode':<8} {'started':<21} {'ms':>6}")
print("  " + "-" * 52)
for r in rows:
    def p(s):
        return datetime.datetime.strptime(s[:23], "%Y-%m-%d %H:%M:%S.%f")
    ms = int((p(r["stoppedAt"]) - p(r["startedAt"])).total_seconds() * 1000) if r.get("stoppedAt") else 0
    print(f"{r['id']:>4}  {r['status']:<8} {r['mode']:<8} {r['startedAt'][:19]:<21} {ms:>6}")
ok = sum(1 for r in rows if r["status"] == "success")
print(f"\n  {ok}/{len(rows)} succeeded")
PY
