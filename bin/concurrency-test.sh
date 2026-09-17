#!/usr/bin/env bash
# Prove the double-booking guard holds under concurrency.
#
# Fires N simultaneous bookings at one slot and asserts that exactly one is
# created. Correctness here comes from the UNIQUE index on wp_ess_slot_locks,
# so this test is what turns that design claim into a measurement.
set -euo pipefail
cd "$(dirname "$0")/.."

REQUESTS="${1:-12}"
API="http://localhost:${WP_PORT:-8100}/wp-json/ess/v1"
MU="wp-content/mu-plugins/ess-test-ratelimit.php"

cleanup() { [ -f "$MU" ] && mv "$MU" "$MU.disabled" || true; }
trap cleanup EXIT

echo "==> Raising the rate limit for the duration of the test"
mv "$MU.disabled" "$MU"

echo "==> Clearing rate-limit counters"
./bin/wp cache flush >/dev/null 2>&1 || true

echo "==> Fetching a free slot, a nonce and a signed form stamp"
./bin/wp eval-file bin/e2e-booking.php 2>/dev/null | grep -o '{.*}' > /tmp/ess-conc.json
NONCE=$(python3 -c "import json;print(json.load(open('/tmp/ess-conc.json'))['nonce'])")
STAMP=$(python3 -c "import json;print(json.load(open('/tmp/ess-conc.json'))['stamp'])")
SLOT=$(python3 -c "import json;print(json.load(open('/tmp/ess-conc.json'))['slot'])")
LABEL=$(python3 -c "import json;print(json.load(open('/tmp/ess-conc.json'))['label'])")

if [ -z "$SLOT" ]; then
  echo "No free slot available to test against." >&2
  exit 1
fi
echo "    contested slot: $SLOT ($LABEL)"

# The time trap rejects anything submitted within three seconds of render.
echo "==> Waiting out the anti-spam time trap"
sleep 4

OUT=$(mktemp -d)
echo "==> Firing $REQUESTS simultaneous bookings at that one slot"
for i in $(seq 1 "$REQUESTS"); do
  (
    body=$(python3 -c "
import json
print(json.dumps({
  'slot_start': '$SLOT',
  'name': 'Racer $i',
  'email': 'racer$i@example.test',
  'phone': '',
  'company': '',
  'project_type': 'feasibility_review',
  'message': 'Concurrency probe $i',
  'website': '',
  'rendered_at': '$STAMP',
}))")
    curl -s -o "$OUT/body.$i" -w '%{http_code}' \
      -X POST "$API/booking" \
      -H 'Content-Type: application/json' \
      -H "X-WP-Nonce: $NONCE" \
      -d "$body" > "$OUT/code.$i"
  ) &
done
wait

echo
echo "==> Results"
created=0; conflict=0; other=0
for i in $(seq 1 "$REQUESTS"); do
  code=$(cat "$OUT/code.$i")
  case "$code" in
    201) created=$((created + 1)) ;;
    409) conflict=$((conflict + 1)) ;;
    *)   other=$((other + 1)); echo "    unexpected $code: $(head -c 160 "$OUT/body.$i")" ;;
  esac
done

printf "    201 Created  : %d\n" "$created"
printf "    409 Conflict : %d\n" "$conflict"
printf "    other        : %d\n" "$other"

echo
echo "==> Rows actually written for that slot"
ROWS=$(./bin/wp db query \
  "SELECT COUNT(*) FROM wp_ess_slot_locks WHERE slot_start = '$SLOT';" --skip-column-names 2>/dev/null \
  | tr -dc '0-9')
BOOKINGS=$(./bin/wp db query \
  "SELECT COUNT(*) FROM wp_postmeta WHERE meta_key='ess_slot_start' AND meta_value='$SLOT';" --skip-column-names 2>/dev/null \
  | tr -dc '0-9')
printf "    lock rows    : %s\n" "$ROWS"
printf "    booking posts: %s\n" "$BOOKINGS"

echo
if [ "$created" -eq 1 ] && [ "$conflict" -eq $((REQUESTS - 1)) ] && [ "$ROWS" = "1" ] && [ "$BOOKINGS" = "1" ]; then
  echo "PASS — one booking created, $conflict rejected, one lock row, no orphans."
  exit 0
fi
echo "FAIL — expected exactly 1 created and $((REQUESTS - 1)) conflicts with a single lock row."
exit 1
