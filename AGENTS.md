# AGENTS.md — Bahrain Ecommerce Platform

Universal context file. Read by Claude Code, Cursor, Codex, OpenCode.

## Project
Production-ready bilingual (AR/EN) ecommerce template for Bahrain.
Next.js 16 frontend + Laravel 13 backend in one repo (two folders).
Payments: Tap Payments v2. Deploy: Coolify + Hetzner. Stack is final.

## Absolute rules
1. BHD stored as integer fils — 1 BHD = 1000 fils, NEVER floats
2. Migrations backward-compatible — no renames, no non-nullable without default
3. Queue jobs idempotent — check state before acting
4. Inventory decrements use lockForUpdate() inside DB transaction
5. All components work in LTR and RTL — logical CSS properties only
6. LazyMotion + domAnimation only — never full Framer Motion import
7. Tap webhooks verify HMAC-SHA256 before any processing
8. Controllers thin — all business logic in Service classes
9. Modules communicate through Events only — never cross-call Services
10. Zod validates ALL data at boundaries (API responses, form submissions)

## Stack decisions (final)
- REST not GraphQL
- PostgreSQL not MySQL (JSONB for product attributes)
- Modular monolith not microservices
- Laravel Reverb for WebSockets (not Node.js)
- Tap Payments covers all Bahrain payment methods
- TanStack Query for server state, Zustand for client state

## Build phases
Phase 1: Foundation (current) — scaffold, config, Docker, CI
Phase 2: Commerce — catalog, cart, checkout, payments, admin
Phase 3: Hardening — blue-green, load tests, security, Lighthouse
