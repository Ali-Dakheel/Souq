---
description: >
  Verify Tap Payments integration for a specific flow.
  Usage: /tap-verify [checkout|webhook|refund|benefitpay]
allowed-tools: Read, Grep, Glob, Agent
---

# Tap Payments Verification: $ARGUMENTS

## Step 1: Locate files
```bash
find backend/app/Modules/Payments -type f -name "*.php"
```

## Step 2: Webhook security
- [ ] HMAC-SHA256 verified BEFORE any processing
- [ ] Raw request body used for hash (not parsed JSON)
- [ ] Amount normalized before hash comparison
- [ ] Returns 200 immediately, processes in queued job
- [ ] Duplicate charge_id is a no-op (idempotent)
- [ ] Failed signature returns 400

## Step 3: Charge creation
- [ ] Currency set to "BHD"
- [ ] Amount: `number_format($fils / 1000, 3, '.', '')` → `"10.500"`
- [ ] Amount derived from DB — never from frontend POST body
- [ ] `source.id` is `src_all` (redirect) or token (embedded)
- [ ] `redirect.url` and `post.url` both set
- [ ] `tap_charge_id` stored with UNIQUE constraint

## Step 4: Return URL handler
- [ ] `tap_id` from query string → verify via `GET /v2/charges/{id}` server-side
- [ ] Status NOT trusted from query string alone
- [ ] Order updated from verified API response only

## Step 5: BenefitPay (if applicable)
- [ ] Domain registered with Tap support (documented)
- [ ] Hash string calculated server-side
- [ ] `source.id` is `src_benefit_pay`

## Step 6: Refunds (if applicable)
- [ ] `POST /v2/refunds/` with charge_id and decimal amount
- [ ] Partial refund tracking in `refunds` table
- [ ] Total refunded never exceeds original charge
- [ ] `OrderRefunded` event fired after success

## Step 7: Security review
Use the security-reviewer agent to audit backend/app/Modules/Payments/

## Verdict
PAYMENT INTEGRATION VERIFIED | NEEDS FIXES
