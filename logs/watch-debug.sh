#!/usr/bin/env bash
# Watcher: emite un wake cuando aparece un error NUEVO en debug.log
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG="$ROOT/wordpress/wp-content/debug.log"
SESSION="$ROOT/logs/session-failures.log"
OFFSET_FILE="$ROOT/logs/.debug-offset"
mkdir -p "$ROOT/logs"
touch "$LOG" "$SESSION"
if [[ ! -f "$OFFSET_FILE" ]]; then
  wc -c < "$LOG" | tr -d ' ' > "$OFFSET_FILE"
fi
last=0
while true; do
  sleep 3
  [[ -f "$LOG" ]] || continue
  size=$(wc -c < "$LOG" | tr -d ' ')
  offset=$(cat "$OFFSET_FILE" 2>/dev/null || echo 0)
  if [[ "$size" -le "$offset" ]]; then
    continue
  fi
  python - "$LOG" "$offset" "$SESSION" "$OFFSET_FILE" <<'PY'
import sys, hashlib, datetime
from pathlib import Path
log, offset, session, offset_file = Path(sys.argv[1]), int(sys.argv[2]), Path(sys.argv[3]), Path(sys.argv[4])
data = log.read_bytes()
chunk = data[offset:].decode("utf-8", "replace")
offset_file.write_text(str(len(data)), encoding="utf-8")
keys = (
    "PHP Fatal", "PHP Parse", "PHP Warning", "PHP Notice", "PHP Deprecated",
    "Uncaught", "critical error", "Database error", "Allowed memory",
    "chilexpress", "Chilexpress", "woocommerce",
)
interesting = []
for line in chunk.splitlines():
    if any(k in line for k in ("PHP Fatal", "PHP Parse", "PHP Warning", "Uncaught", "Database error", "Allowed memory")):
        interesting.append(line)
    elif "PHP Notice" in line and "chilexpress" in line.lower():
        interesting.append(line)
if not interesting:
    sys.exit(0)
seen_path = Path(session).with_name(".seen-hashes")
seen = set(seen_path.read_text(encoding="utf-8").splitlines()) if seen_path.exists() else set()
new = []
for line in interesting:
    h = hashlib.sha1(line.encode("utf-8", "replace")).hexdigest()[:16]
    if h in seen:
        continue
    seen.add(h)
    new.append(line)
if not new:
    sys.exit(0)
seen_path.write_text("\n".join(sorted(seen)) + "\n", encoding="utf-8")
stamp = datetime.datetime.now().isoformat(timespec="seconds")
with session.open("a", encoding="utf-8") as f:
    f.write(f"\n## {stamp}\n")
    for line in new:
        f.write(line + "\n")
print("AGENT_LOOP_WAKE_logs " + '{"prompt":"Nuevos errores en debug.log. Lee logs/session-failures.log y wordpress/wp-content/debug.log (cola), reproduce, corrige bugs de la réplica (no parchees chilexpress-oficial). Luego re-arma el heartbeat."}')
PY
done
