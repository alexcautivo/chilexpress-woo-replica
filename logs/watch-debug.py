"""Watch WordPress debug.log and emit AGENT_LOOP_WAKE_logs on new failures."""
from __future__ import annotations

import hashlib
import time
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LOG = ROOT / "wordpress" / "wp-content" / "debug.log"
SESSION = ROOT / "logs" / "session-failures.log"
OFFSET_FILE = ROOT / "logs" / ".debug-offset"
SEEN_FILE = ROOT / "logs" / ".seen-hashes"

MARKERS = (
    "PHP Fatal",
    "PHP Parse",
    "PHP Warning",
    "Uncaught",
    "Database error",
    "Allowed memory",
    "There has been a critical error",
)


def interesting(line: str) -> bool:
    if any(m in line for m in MARKERS):
        return True
    if "PHP Notice" in line and "chilexpress" in line.lower():
        return True
    return False


def main() -> None:
    SESSION.parent.mkdir(parents=True, exist_ok=True)
    LOG.parent.mkdir(parents=True, exist_ok=True)
    LOG.touch(exist_ok=True)
    SESSION.touch(exist_ok=True)
    if not OFFSET_FILE.exists():
        OFFSET_FILE.write_text(str(LOG.stat().st_size), encoding="utf-8")
    seen = set(SEEN_FILE.read_text(encoding="utf-8").splitlines()) if SEEN_FILE.exists() else set()

    while True:
        time.sleep(3)
        size = LOG.stat().st_size
        offset = int(OFFSET_FILE.read_text(encoding="utf-8") or "0")
        if size <= offset:
            if size < offset:
                OFFSET_FILE.write_text(str(size), encoding="utf-8")
            continue
        chunk = LOG.read_bytes()[offset:].decode("utf-8", "replace")
        OFFSET_FILE.write_text(str(size), encoding="utf-8")
        new_lines = []
        for line in chunk.splitlines():
            if not interesting(line):
                continue
            digest = hashlib.sha1(line.encode("utf-8", "replace")).hexdigest()[:16]
            if digest in seen:
                continue
            seen.add(digest)
            new_lines.append(line)
        if not new_lines:
            continue
        SEEN_FILE.write_text("\n".join(sorted(seen)) + "\n", encoding="utf-8")
        stamp = datetime.now().isoformat(timespec="seconds")
        with SESSION.open("a", encoding="utf-8") as handle:
            handle.write(f"\n## {stamp}\n")
            for line in new_lines:
                handle.write(line + "\n")
        print(
            'AGENT_LOOP_WAKE_logs {"prompt":"Nuevos errores en debug.log. Lee logs/session-failures.log y la cola de wordpress/wp-content/debug.log, reproduce en http://127.0.0.1:8080, corrige bugs de la replica (no parchees chilexpress-oficial). Re-arma el heartbeat."}',
            flush=True,
        )


if __name__ == "__main__":
    main()
