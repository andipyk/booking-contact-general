#!/usr/bin/env bash
# Measure the pages the case study makes claims about.
#
# Runs on the host against the host's Chrome rather than in a container. A
# container reaching the site by service name gets canonical-redirected to
# WP_HOME (localhost:8100), which it cannot resolve; rewriting WP_HOME to suit
# the audit would mean measuring a configuration the site never actually runs.
# Results land in docs/lighthouse/ and are committed, so the numbers in the case
# study can be checked rather than taken on trust.
set -euo pipefail
cd "$(dirname "$0")/.."

BASE="http://localhost:${WP_PORT:-8100}"
OUT="docs/lighthouse"
mkdir -p "$OUT"

PAGES=(
  "home:/"
  "projects:/projects/"
  "project:/projects/navy-yard-lab/"
  "services:/services/"
  "booking:/book-a-consultation/"
  "contact:/contact/"
)

echo "==> Auditing ${#PAGES[@]} pages at $BASE"
for entry in "${PAGES[@]}"; do
  name="${entry%%:*}"
  path="${entry#*:}"
  echo "    $name  ($path)"
  npx --yes lighthouse@12 "${BASE}${path}" \
    --quiet \
    --output=json --output=html \
    --output-path="${OUT}/${name}" \
    --only-categories=performance,accessibility,best-practices,seo \
    --preset=desktop \
    --chrome-flags="--headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage" \
    >/dev/null 2>&1 || { echo "      audit failed for $name"; continue; }
done

echo
echo "==> Scores"
python3 - <<'PY'
import json, pathlib
rows = []
for f in sorted(pathlib.Path("docs/lighthouse").glob("*.report.json")):
    d = json.loads(f.read_text())
    c = d["categories"]
    a = d["audits"]
    rows.append((
        f.name.replace(".report.json", ""),
        round(c["performance"]["score"] * 100),
        round(c["accessibility"]["score"] * 100),
        round(c["best-practices"]["score"] * 100),
        round(c["seo"]["score"] * 100),
        a["largest-contentful-paint"]["displayValue"],
        a["cumulative-layout-shift"]["displayValue"],
        a["total-blocking-time"]["displayValue"],
    ))
hdr = ("page", "perf", "a11y", "best", "seo", "LCP", "CLS", "TBT")
print(f"    {hdr[0]:<10}{hdr[1]:>5}{hdr[2]:>6}{hdr[3]:>6}{hdr[4]:>5}   {hdr[5]:<9}{hdr[6]:<7}{hdr[7]}")
for r in rows:
    print(f"    {r[0]:<10}{r[1]:>5}{r[2]:>6}{r[3]:>6}{r[4]:>5}   {r[5]:<9}{r[6]:<7}{r[7]}")
PY
