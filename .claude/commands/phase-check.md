---
description: >
  Check completion of the current build phase. Prevents advancing before
  foundation is solid. Usage: /phase-check
allowed-tools: Read, Bash, Glob
---

# Phase Completion Check

## Detect current phase
Read `CLAUDE.md` section 8. Identify active phase and checkbox status.

## Phase 1 checks

```bash
# shadcn init was run
test -f frontend/components.json && echo "✅ shadcn init" || echo "❌ shadcn init"

# RTL migration done
grep -q "rtl" frontend/src/app/layout.tsx 2>/dev/null && echo "✅ RTL" || echo "❌ RTL (check layout.tsx for dir attribute)"

# next-intl routing
test -d frontend/src/app/[locale] && echo "✅ locale routing" || echo "❌ locale routing"

# TypeScript strict
grep -q '"strict": true' frontend/tsconfig.json 2>/dev/null && echo "✅ TS strict" || echo "❌ TS strict"

# Laravel Modules structure
test -d backend/app/Modules && echo "✅ Laravel modules" || echo "❌ Laravel modules"

# Boost installed
test -f backend/composer.json && grep -q "laravel/boost" backend/composer.json && echo "✅ Boost" || echo "❌ Boost"

# PostgreSQL migrations exist
ls backend/database/migrations/ 2>/dev/null | grep -qE "products|variants|inventory" && echo "✅ DB schema" || echo "❌ DB schema"

# Docker Compose
test -f docker-compose.yml && grep -q "postgres" docker-compose.yml && echo "✅ Docker" || echo "❌ Docker"

# GitHub Actions CI
test -f .github/workflows/ci.yml && echo "✅ CI pipeline" || echo "❌ CI pipeline"

# Coolify connected (manual check)
echo "⚠️  Coolify deploy: verify manually in Coolify dashboard"
```

## Output format
```
PHASE 1 — Foundation Status
────────────────────────────
✅ shadcn init
✅ RTL migration
❌ locale routing — app/[locale]/ directory missing
...

COMPLETE: 7/9
STATUS: ⏳ IN PROGRESS

Remaining:
1. [item] — run: [command]
2. [item] — run: [command]
```

If all complete:
```
STATUS: ✅ PHASE 1 COMPLETE — ready for Phase 2
Update CLAUDE.md section 8 checkboxes.
```
