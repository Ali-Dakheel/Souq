---
description: >
  Complete pre-deploy checklist before pushing to production.
  Catches zero-downtime violations, security issues, missing tests.
  Usage: /pre-deploy
allowed-tools: Read, Bash, Glob, Grep, Agent
---

# Pre-Deploy Checklist

## 1. Migration safety
```bash
git diff main --name-only -- 'backend/database/migrations/*.php'
```
For each new migration verify:
- [ ] No `renameColumn()` calls
- [ ] No non-nullable columns without defaults
- [ ] No `dropColumn()` on columns active code still reads
- [ ] `down()` method exists and is correct

## 2. Job idempotency
```bash
git diff main --name-only -- 'backend/app/Jobs/*.php'
```
Each job's `handle()` must:
- [ ] Check current model state BEFORE acting
- [ ] Return early (no-op) if already processed

## 3. BHD currency audit
```bash
git diff main -- '*.php' '*.ts' '*.tsx' | grep -E '(price|amount|total)\s*[=:]\s*[0-9]+\.[0-9]'
```
Any float price found is BLOCKING.

## 4. Run test suite
```bash
cd frontend && pnpm vitest run
cd ../backend && php artisan test --parallel
```
All tests must pass. Any failure is BLOCKING.

## 5. RTL audit on changed components
```bash
git diff main --name-only -- 'frontend/src/**/*.tsx'
```
If components changed: Use the rtl-auditor agent on changed files.

## 6. Security scan on changed API code
```bash
git diff main --name-only -- 'backend/app/**/*.php'
```
If payment/auth/webhook files changed: Use the security-reviewer agent.

## 7. Lint
```bash
cd frontend && pnpm lint
cd ../backend && ./vendor/bin/pint --test
```

## 8. Secrets check
```bash
git diff main -- '*.php' '*.ts' '*.tsx' | grep -E '(sk_live|pk_live)'
```
Any hardcoded production key is BLOCKING.

## Output

```
PRE-DEPLOY REPORT
─────────────────
✅ Migration safety
✅ Job idempotency
✅ BHD currency
✅ Test suite
✅ RTL audit
✅ Security scan
✅ Lint
✅ Secrets

BLOCKING ISSUES: 0
VERDICT: ✅ SAFE TO DEPLOY
```

Or: `VERDICT: 🚫 DO NOT DEPLOY — fix N blocking issues first`
