---
name: zero-downtime
description: >
  Zero-downtime deployment patterns. Blue-green on Coolify + Hetzner,
  migration safety, Horizon graceful shutdown, health checks, smoke tests.
  Auto-referenced when working on deployment or CI/CD.
---

# Zero-Downtime Deployment Patterns

## The deploy sequence — memorize this

```
1. Push to main → GitHub Actions triggers
2. Full test suite runs (fail fast)
3. Build Docker images for frontend + backend
4. Deploy to GREEN (not live yet)
5. php artisan horizon:terminate on GREEN (graceful worker shutdown)
6. php artisan migrate --force on GREEN (backward-compat migrations only)
7. Health check passes on GREEN (/api/health → 200)
8. Smoke tests on GREEN (preview URL)
9. Switch Hetzner Load Balancer → GREEN (instant, 0 user impact)
10. Production smoke tests pass
11. BLUE goes idle, deploy complete
12. Any failure at 8–10 → auto-switch back to BLUE
```

**User-facing downtime: 0 seconds.**

---

## Health check endpoint (Laravel)

```php
// backend/routes/api.php
Route::get('/health', function () {
    DB::connection()->getPdo();           // DB connectivity
    Cache::store('redis')->get('ping');   // Redis connectivity

    return response()->json([
        'status'    => 'healthy',
        'timestamp' => now()->toISOString(),
    ]);
});
```

```yaml
# docker-compose.yml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:8000/api/health"]
  interval: 10s
  timeout: 5s
  retries: 3
  start_period: 30s
```

---

## GitHub Actions deploy step

```yaml
- name: Wait for Green health check
  run: |
    for i in {1..30}; do
      STATUS=$(curl -s -o /dev/null -w "%{http_code}" \
        "${{ secrets.GREEN_URL }}/api/health")
      if [ "$STATUS" = "200" ]; then echo "Green healthy"; break; fi
      sleep 10
    done
    [ "$STATUS" = "200" ] || exit 1

- name: Smoke tests on Green
  run: |
    curl -f "${{ secrets.GREEN_URL }}/" || exit 1
    curl -f "${{ secrets.GREEN_URL }}/api/health" || exit 1
    curl -f "${{ secrets.GREEN_URL }}/api/v1/products" || exit 1

- name: Switch load balancer to Green
  run: |
    curl -X POST \
      -H "Authorization: Bearer ${{ secrets.HETZNER_API_TOKEN }}" \
      -d '{"active_backend":"green"}' \
      "https://api.hetzner.cloud/v1/load_balancers/${{ secrets.LB_ID }}/actions/set_active_backend"

- name: Rollback on failure
  if: failure()
  run: |
    curl -X POST \
      -H "Authorization: Bearer ${{ secrets.HETZNER_API_TOKEN }}" \
      -d '{"active_backend":"blue"}' \
      "https://api.hetzner.cloud/v1/load_balancers/${{ secrets.LB_ID }}/actions/set_active_backend"
```

---

## Migration safety table

| Operation | Safe? | Notes |
|---|---|---|
| Add nullable column | ✅ | Old code ignores it |
| Add column with default | ✅ | Old code ignores it |
| Add index | ✅ | Use CONCURRENTLY for production |
| Drop unused column | ✅ | Only after code no longer references it |
| Rename column | ❌ | Multi-phase: add new → backfill → drop old |
| Add non-nullable, no default | ❌ | Breaks old inserts |
| Change column to smaller size | ❌ | Data loss risk |

---

## Horizon graceful shutdown

```bash
# Pre-deploy hook — before new containers start
php artisan horizon:terminate
# Waits for current jobs to finish, then exits cleanly
# New containers start fresh workers
```

```php
// config/horizon.php — wait per queue
'waits' => [
    'redis:default'       => 60,
    'redis:payments'      => 120,  // payment jobs get more time
    'redis:notifications' => 30,
],
```

---

## Infrastructure cost (zero-downtime setup)

| Component | Spec | Cost |
|---|---|---|
| Blue server | Hetzner CCX23 (4 vCPU, 16GB) | ~€30/mo |
| Green server | Hetzner CCX23 (4 vCPU, 16GB) | ~€30/mo |
| Load Balancer | Hetzner LB11 | €5.39/mo |
| **Total** | | **~€65/mo** |

For small clients (no blue-green needed yet): single CPX22 (~€8/mo) with
Coolify rolling restarts is near-zero-downtime and perfectly sufficient.
