#!/usr/bin/env bash
# Ruleaza testele si afiseaza un rezumat scurt. Folosire: tools/t.sh [--filter=Nume]
cd "$(dirname "$0")/.."
php artisan test "$@" 2>&1 | python3 -c '
import sys, json
raw = sys.stdin.read()
try:
    d = json.loads(raw[raw.index("{"):raw.rindex("}") + 1])
except Exception:
    print(raw[-1500:]); sys.exit(1)
print("rezultat:", d.get("result"), "| teste:", d.get("tests"), "| trecute:", d.get("passed"), "| aserțiuni:", d.get("assertions"), "| ms:", d.get("duration_ms"))
seen = set()
for key in ("error_details", "failure_details", "failures", "errors"):
    for e in (d.get(key) or []) if isinstance(d.get(key), list) else []:
        msg = (e.get("message") or "")[:260]
        if msg in seen: continue
        seen.add(msg); print(" -", e.get("test", "")[-70:], "|", msg, "|", (e.get("file") or "")[-40:], e.get("line"))
        if len(seen) >= 8: break
'
