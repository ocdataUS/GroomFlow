#!/usr/bin/env bash
set -euo pipefail
OUT="docs/context/context-pack.json"
mkdir -p docs/context
jq -n   --arg spec "$(sed 's/\r$//' SPEC.md)"   --arg roadmap "$(sed 's/\r$//' docs/ROADMAP.md)"   --arg api "$(sed 's/\r$//' docs/API.md)"   --arg db "$(sed 's/\r$//' docs/DB_SCHEMA.md)"   --arg sec "$(sed 's/\r$//' docs/SECURITY.md)"   --arg bind "$(sed 's/\r$//' docs/FRONTEND_BINDINGS.md)"   '{spec:$spec, roadmap:$roadmap, api:$api, db:$db, security:$sec, bindings:$bind}' > "$OUT"
echo "🧳 Wrote $OUT"
