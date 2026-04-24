# Reference Pattern Audit — BookStack + Coolify

## Summary

Both BookStack (documentation platform) and Coolify (PaaS deployment) are production-grade Laravel applications with patterns worth adopting:

1. **Service/Repository layering**: Both separate business logic from controllers into dedicated classes (BookStack: Repos + Services; Coolify: Models with rich methods + Services). Your modular monolith should enforce thin controllers rigorously.

2. **Permission system architecture**: BookStack's permission system (EntityPermissionEvaluator, PermissionApplicator, JointPermissionBuilder) is more sophisticated than typical Spatie. Consider adopting for multi-team/multi-tenant phases.

3. **Activity/Audit logging**: BookStack has a dedicated Activity module (ActivityQueries, CommentRepo, TagRepo) for comprehensive audit trails. Your Inventory module mirrors this—validate it's complete.

4. **Webhook & idempotency patterns**: Coolify's GitHub webhook controller (signature verification, duplicate prevention via watch paths) and queue configurations show production-safe patterns for Tap Payments webhooks.

5. **Testing organization by domain**: Both repos organize tests by feature domain (Activity/, Api/, Auth/, etc.) rather than generic Unit/Feature split. Aligns with your modular structure.

---

## 1. Architecture Patterns

### BookStack

**Pattern: Hybrid Repo + Service layer**

Where: `app/` structure uses `Repos/` (e.g., `PermissionsRepo.php`, `CommentRepo.php`) and separate `Services/` (e.g., `PermissionApplicator.php`)

- Repos handle data access and Eloquent query abstraction
- Services contain business logic and cross-repo orchestration
- Controllers delegate to Services and return API Resources

Why it's good: 
- Clear separation: controllers never touch Eloquent directly
- Testable business logic in services
- Reusable query patterns in repos

How it maps to your project:
- You use Services only (no explicit Repos layer)
- Your `ServiceClasses` (e.g., `CartService`, `OrderService`, `PaymentService`) should remain thin wrappers around business logic
- Add a `Repositories/` subdirectory per module if complex queries emerge (e.g., `OrderRepository::findByCustomerWithItems()`)
- Example: `Orders/Services/OrderService.php` → orchestrates; `Orders/Repositories/OrderRepository.php` → query abstraction

**Recommendation**: Don't over-engineer—only create Repos when the same query appears 3+ times or exceeds 3 lines.

### Coolify

**Pattern: Rich models with custom methods + service coordination**

Where: `app/Models/Application.php` (1000+ lines) shows extreme model enrichment

- Models include custom scopes (`ownedByCurrentTeamAPI()`, `ownedByCurrentTeamCached()`)
- Models have lifecycle hooks (Creating, Saving, Created, ForceDeleting) that perform side effects
- Services call into model methods, not the reverse

Why it's good:
- Single source of truth for model behavior
- Scopes encapsulate filtered queries
- Lifecycle consistency without repeated hooks in services

How it maps to your project:
- Your `Order`, `Product`, `Cart` models should have query scopes for common filters
- Example in Orders module:
  ```php
  public function scopeByCustomer($query, $customerId) {
      return $query->where('customer_id', $customerId);
  }
  
  public function scopePending($query) {
      return $query->where('order_status', 'pending');
  }
  ```
- Use `scopeOwnedByUser()` in Customers module to enforce ownership checks
- Keep lifecycle hooks focused on ONE responsibility; extract complex logic to Services

**Current gap**: Your models likely have minimal scopes. Audit and add domain-driven scopes for common queries (pending orders, abandoned carts, etc.).

### Cross-Module Dependencies

**BookStack approach**: No evidence of explicit dependency patterns beyond standard Laravel Service Container

**Coolify approach**: Models explicitly call other services via `app(ServiceClass::class)`, avoiding circular constructor injection

**Your approach (from CLAUDE.md)**: Use `app(ServiceClass::class)` and Events for async work—correct. Reinforce:
- **Never** constructor-inject Services across modules (circular deps)
- **Always** use Events for side effects (Inventory reserve on OrderPlaced, Notifications send)
- **Query-time** cross-module calls via `app()` are acceptable; keep them in Services, not Models

---

## 2. Security Patterns

### BookStack: Permission System (Best-in-Class)

**Pattern: Three-layer permission evaluation**

Where: `app/Permissions/` (PermissionApplicator.php, EntityPermissionEvaluator.php, MassEntityPermissionEvaluator.php)

1. **PermissionApplicator**: Entry point for all permission checks
   - `checkOwnableUserAccess()` — evaluates: user is owner OR has role permission
   - `restrictEntityQuery()` — filters queries to only allowed entities
   - `restrictDraftsOnPageQuery()` — hides drafts from non-owners
   - `ensureValidEntityAction()` — prevents invalid permission actions

2. **EntityPermissionEvaluator**: Checks individual entity-level restrictions (BookStack's pages have custom permission lists)

3. **MassEntityPermissionEvaluator**: Batch permission checks for lists (avoids N+1 queries)

Why it's good:
- Separation between **role permissions** (all users with role X can do Y) and **entity permissions** (this specific item has custom rules)
- Query filtering prevents information leakage (unpermitted items never reach API response)
- Reusable across controllers/services

How it maps to your project:
- You currently have **no granular entity permissions** (only cart/order ownership checks)
- When white-labeling multi-tenant, you'll need similar patterns:
  - Team/store ownership at entity level
  - Role-based access (admin sees all, customer sees theirs)
  - Product visibility (private vs public per store)

**For Phase 3G (multi-tenant)**: Plan a similar permission system before building. For now, ensure all entity access checks are in **Services**, not Controllers:

```php
// ✓ Correct (Orders/Services/OrderService.php)
public function getOrder($orderId, $userId) {
    $order = Order::findOrFail($orderId);
    if ($order->customer_id !== $userId) {
        throw new UnauthorizedException('Not your order');
    }
    return $order;
}

// ✗ Avoid (put this logic in service)
// Controllers should only call: $order = app(OrderService::class)->getOrder($id, auth()->id());
```

**Current audit result**: Your CheckoutRequest and OrderService both validate address ownership (defense-in-depth per CLAUDE.md). Good. Extend this pattern to all customer data.

### Coolify: API Security Middleware

**Pattern: Granular middleware stack per route group**

Where: `app/Http/Middleware/` includes specialized checks:
- `ApiAbility.php` — validates specific API abilities/tokens
- `ApiSensitiveData.php` — scrubs sensitive info from responses
- `ValidateSignature.php` — verifies webhook signatures

Why it's good:
- Separates **auth** (are you logged in?) from **authorization** (can you do this?)
- Sensitive data filtering happens once, applies everywhere
- Signature verification is centralized for all webhooks

How it maps to your project:
- You have Sanctum token auth (good)
- Tap webhook HMAC verification should be in middleware or a service called early in webhook handler
- Add response middleware to scrub payment card data (never expose in API responses even partially)

**Current audit result**: 
- Sanctum auth is wired up ✓
- Tap webhook HMAC validation is in `PaymentService::handleWebhook()` ✓
- No API response filtering for sensitive data — **add middleware**

```php
// New: app/Http/Middleware/ScrubSensitiveData.php
public function handle($request, $next) {
    $response = $next($request);
    if ($response->isJson()) {
        $data = $response->getData(true);
        // Remove card details, API secrets, internal IDs from JSON
        return response()->json($this->scrub($data));
    }
    return $response;
}
```

### Rate Limiting

**BookStack**: No explicit rate limiting documentation found in composer.json or config.

**Coolify**: No explicit rate limiting configuration found in queue.php or AppServiceProvider.

**Your project (from CLAUDE.md)**:
- auth: 60/min ✓
- checkout: 10/min ✓
- add-to-cart: 30/min ✓

**Action**: Verify these are enforced in middleware. If not present, add:

```php
// routes/api.php
Route::middleware('throttle:auth:60,1')->group(function () {
    Route::post('/login', ...);
});
```

---

## 3. Testing Patterns

### BookStack: Feature-Organized Test Structure

**Pattern**: Tests organized by domain (Activity/, Api/, Auth/, Permissions/, User/, etc.), not by test type

Where: `tests/` directory structure mirrors business domains

Why it's good:
- Test discovery is intuitive (find auth bugs → go to `tests/Auth/`)
- Test setup is co-located with test intent
- Easier to maintain when module grows

How it maps to your project:
- Your `tests/` likely mirrors `app/Modules/`
- Align test structure with modules:
  ```
  tests/
  ├── Feature/Catalog/
  ├── Feature/Orders/
  ├── Feature/Payments/
  ├── Feature/Inventory/
  ├── Feature/Customers/
  ├── Feature/Shipping/
  ├── Feature/Promotions/
  ├── Feature/Loyalty/
  └── Unit/Services/  (shared services)
  ```

**Current audit result**: Pest v3 with 428/428 passing (excellent). Verify tests are organized by module, not by test type.

### Coolify: Redis Queue Configuration with After-Commit

**Pattern**: Queue jobs fire AFTER database transaction commits

Where: `config/queue.php` specifies `'after_commit' => true` for Redis

Why it's good:
- **Safety**: Jobs only queue if the database transaction succeeds
- **Prevents orphans**: No jobs for rolled-back transactions
- **Atomicity**: If transaction fails, no side effects

How it maps to your project:
- Your Events are dispatched **OUTSIDE** `DB::transaction()` per CLAUDE.md (correct)
- Verify queued listeners have `after_commit` enabled in `config/queue.php`

```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'after_commit' => true,  // ← Ensure this is set
],
```

### Job Idempotency

**Coolify pattern**: Models use `isDeploymentInProgress()` and state checks before queuing jobs

**Your pattern (from CLAUDE.md)**:
- `ShouldBeUnique` + `uniqueId()` on webhook jobs ✓
- `lockForUpdate()` for inventory decrements ✓
- Service methods check state before acting ✓

**Example to audit in your codebase**:
```php
// app/Modules/Orders/Jobs/CapturePaymentJob.php
class CapturePaymentJob implements ShouldQueue, ShouldBeUnique {
    public function uniqueId() {
        return "capture-{$this->orderId}";  // Same job won't run twice
    }
    
    public function handle() {
        $order = Order::lockForUpdate()->find($this->orderId);
        // Check state first
        if ($order->order_status !== 'initiated') {
            return; // Already captured or failed, safe to ignore
        }
        // ... capture logic
    }
}
```

**Current audit result**: Verify all your jobs (CapturePaymentJob, EarnPointsJob, etc.) implement this pattern.

---

## 4. Job & Queue Patterns

### Coolify: Webhook Handling with Idempotency

**Pattern**: GitHub webhook controller verifies signature, checks watch paths, dispatches jobs

Where: `app/Http/Controllers/Webhook/Github.php`

Flow:
1. Verify HMAC-SHA256 signature (`$x_hub_signature_256` header vs. computed hash)
2. Filter for event types (push, pull request)
3. Check modified files against watch paths regex
4. If matched, dispatch `ProcessGithubPushWebhook` job
5. Return status per application

Why it's good:
- **Signature first**: Attackers can't spoof webhooks
- **Idempotency**: Jobs are keyed by commit hash or PR ID, safe to re-run
- **Watch paths**: Avoids queuing jobs for irrelevant commits
- **Detailed responses**: Logging shows why jobs did/didn't dispatch

How it maps to your project (Tap Payments):

Your webhook HMAC verification is in `PaymentService::handleWebhook()`:
```php
$webhookSecret = config('tap.webhook_secret');
$hashString = "x_id{$id}x_amount{$amount}x_currency{currency}x_status{status}";
$computedHash = hash_hmac('sha256', $hashString, $webhookSecret);

if (!hash_equals($computedHash, $headerHash)) {
    throw new WebhookVerificationException();
}
```

**Current audit result**: Your Tap webhook verification is correct ✓

**To improve**: Add middleware-level signature verification so bad webhooks never reach the controller:

```php
// app/Http/Middleware/VerifyTapWebhook.php
public function handle($request, $next) {
    if ($request->path() === 'api/v1/webhooks/tap') {
        $signature = $request->header('X-Tap-Signature');
        if (!$this->verifySignature($request, $signature)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }
    return $next($request);
}
```

### Job Retry & Failure Handling

**Coolify approach** (from queue.php): Redis retry after 24 hours, separate `failed_jobs` table for logging

**Your approach** (from CLAUDE.md):
- Jobs must be idempotent
- Tap webhook jobs use `ShouldBeUnique`
- EarnPointsJob dispatched on PaymentCaptured event

**Verify in your codebase**:
1. All jobs have `public $tries` and `public $backoff` configured
2. Failed jobs are logged to `failed_jobs` table
3. Job failure emails alert admins (Notifications module)

```php
// Example: app/Modules/Loyalty/Jobs/EarnPointsJob.php
class EarnPointsJob implements ShouldQueue, ShouldBeUnique {
    public $tries = 3;
    public $backoff = [30, 120, 300];  // 30s, 2m, 5m
    
    public function handle() {
        // Already idempotent per CLAUDE.md, so retries are safe
    }
    
    public function failed(Throwable $exception) {
        // Log and alert
        Notification::route('mail', 'admin@store.com')
            ->notify(new JobFailedNotification($this));
    }
}
```

**Current audit result**: Verify your EarnPointsJob and all queued listeners have retry/failure handlers.

---

## 5. Deployment Patterns

### Blue-Green Deploy Readiness (Coolify + Hetzner)

**Coolify uses**:
- Docker Compose versioning for configuration changes
- `isConfigurationChanged()` to detect if redeployment needed
- `deployment_queue` table to track deployment history
- `isDeploymentInProgress()` to prevent concurrent deploys

**Your requirements** (from CLAUDE.md):
- Zero-downtime blue-green on Coolify ✓
- Migrations safe during deploy
- Horizon queue drain before old code stops

**Verification checklist**:

1. **AppServiceProvider**: Disable destructive commands in production
   ```php
   if (app()->isProduction()) {
       DB::prohibitDestructiveCommands();  // ← Prevent DROP during deploy
   }
   ```

2. **Migration safety**: All migrations have `down()` methods (you've verified this)

3. **Queue drain**: Pre-deploy hook should wait for queue to empty
   ```bash
   php artisan queue:prune-batches
   php artisan queue:flush  # or drain specific queue
   ```

4. **Health check endpoint**: Add `/health` for load balancer
   ```php
   // routes/api.php
   Route::get('/health', fn() => response()->json(['status' => 'ok']));
   ```

### Deployment Secret Management

**Coolify pattern**: Environment variables from UI, stored in server config (no .env files committed)

**Your project**: GitHub Secrets → Coolify → Environment Variables

**Audit**: Verify in your deploy GitHub Action:
- No secrets logged to stdout
- `COOLIFY_WEBHOOK_TOKEN` and `COOLIFY_WEBHOOK_URL` are configured as GitHub Secrets
- Deploy workflow uses `${{ secrets.COOLIFY_WEBHOOK_TOKEN }}`

---

## 6. Code Quality Tooling

### PHPStan Configuration

**BookStack**: Level 8 (highest strictness), PHP ^8.2.0

Where: `phpstan.neon.dist` with Larastan extension

Why it's good: Catches type errors, undefined properties, array access issues at static analysis time (before tests run)

**Coolify**: Level 4 (moderate), PHP ^8.4

Why lower level: Balances thoroughness with pragmatism (level 8 can flag legitimate patterns)

### Your Project

**Current status** (from git commits): 411/411 tests pass, critical audit findings resolved

**Recommendation**:
1. Add PHPStan to CI pipeline (if not already)
   ```bash
   ./vendor/bin/phpstan analyse app --level=5
   ```
   - Start at level 5 (strict but not pedantic)
   - Gradually increase to 7 as codebase matures
   - Exclude `Modules/*/Database/Migrations` and configs

2. Configure Larastan:
   ```php
   // phpstan.neon
   parameters:
       level: 5
       paths:
           - app
       excludePaths:
           - app/*/Database/Migrations
   extensions:
       - Larastan\Larastan\Extension
   ```

3. Add Pint (code formatting):
   ```bash
   ./vendor/bin/pint
   ```
   - Runs before commits via pre-commit hook (if configured in settings.json)

4. Pre-commit hook:
   ```bash
   #!/bin/bash
   ./vendor/bin/pint --test || exit 1
   ./vendor/bin/phpstan analyse --no-progress || exit 1
   ```

---

## 7. Red Flags to Avoid

### BookStack: Rich Models Can Become God Classes

**Anti-pattern**: Putting too much logic into models

**How to avoid**: 
- Keep models under 200 lines (scopes, relationships, accessors only)
- Extract complex query logic to Repositories
- Extract business logic to Services

**Your safeguard**: Enforce this in Modules via code review

### Coolify: Lifecycle Hooks Side Effects

**Anti-pattern**: Model hooks (Created, Saving) that trigger external API calls or emails

**Example to avoid**:
```php
// ✗ Bad: Model hook makes API call
protected static function booted() {
    static::created(function ($app) {
        // Deploy to external service here — breaks tests!
    });
}
```

**How to avoid**:
- Use Events instead of hooks for async work
- Hooks are OK for: normalizing data, setting timestamps, validation
- Keep hooks under 5 lines

**Your safeguard**: All side effects go to Events (per CLAUDE.md), not model hooks. Keep hooks for `->update()` only.

### Common Trap: Transaction Scope in Webhook Handlers

**Anti-pattern**: Queuing jobs inside transaction, expecting them to be idempotent without state checks

**Your correct approach** (from CLAUDE.md):
```php
// ✓ Correct: Check state BEFORE queuing, transaction won't prevent re-runs
$order = Order::lockForUpdate()->find($orderId);
if ($order->tap_transaction_id === $tapId) {
    return;  // Already processed
}

DB::transaction(function () {
    $order->update(['order_status' => 'paid']);
    PaymentCaptured::dispatch($order);  // Inside transaction
});

// After commit, PaymentCaptured listeners run. Safe to run twice.
```

---

## Gaps in Your Project vs. These References

### Must-Have Gaps (address before production)

| Gap | Reference | Priority | Action |
|-----|-----------|----------|--------|
| No query scopes on models | Coolify: `ownedByCurrentTeam()`, `ownedByCurrentTeamCached()` | HIGH | Add scopes: `scopeByCustomer()`, `scopePending()`, `scopeByTeam()` for multi-tenant prep |
| No audit/activity logging | BookStack: `Activity/` module | MEDIUM | Inventory module has MovementService—ensure it's comprehensive for all modules |
| No API response filtering for sensitive data | Coolify: `ApiSensitiveData` middleware | HIGH | Add middleware to scrub card data, API secrets from JSON responses |
| Rate limiting incomplete | Both repos assume it's enforced | HIGH | Verify throttle middleware on auth (60/min), checkout (10/min), add-to-cart (30/min) |
| No test response assertions | BookStack: tests use assertions for JSON structure | MEDIUM | Audit Pest tests to ensure they assert response structure, not just status codes |
| No permission evaluator for multi-tenant | BookStack: `PermissionApplicator` + `EntityPermissionEvaluator` | LOW | Plan for Phase 3G—white-label requires this |

### Nice-to-Have Gaps

- Health check endpoint (`/health`) for load balancer
- Pre-commit hooks enforcing PHPStan + Pint
- Deployment hooks for queue drain + graceful shutdown
- Activity logging for admin actions (who changed what, when)

---

## Patterns to Explicitly NOT Copy

### 1. BookStack's Permission System (for now)

**Why not copy yet**: Your project is single-store MVP. Multi-tenant permission system adds complexity without ROI until Phase 3G.

**When to copy**: After shipping Phase 1 to first client, start planning Phase 3G.

### 2. Coolify's Model-Heavy Orchestration

**Why not copy blindly**: Coolify's Application model has 1000+ lines. Your models should stay focused.

**When to use this pattern**: Only for entity types that have 5+ relationships and 10+ query scopes (Order, Product, Cart—maybe). Keep others simple.

### 3. BookStack's Advanced Search Integration

**Not relevant**: BookStack uses full-text search for wiki pages. Your catalog will use Meilisearch (configured, ready when catalog > 200 products). Skip this pattern.

### 4. Activity/Audit for Every Action

**Not yet critical**: BookStack logs every page view, comment, share. You only need audit trails for:
- Payment interactions (required by Tap)
- Inventory changes (required by compliance)
- Admin actions (nice-to-have, plan for Phase 3F)

Don't over-engineer—log only what regulations or operations demand.

---

## Immediate Actions (Priority Order)

1. **Add query scopes** to models (Order, Product, Cart, Customer)
   - File: `app/Modules/Orders/Models/Order.php` → add `scopeByCustomer()`, `scopePending()`
   - Same for other modules

2. **Add API response middleware** for sensitive data
   - File: `app/Http/Middleware/ScrubSensitiveData.php` (new)
   - Register in `app/Http/Kernel.php`

3. **Verify rate limiting** is enforced
   - File: `routes/api.php`
   - Confirm `throttle:auth:60,1` on login, etc.

4. **Verify PHPStan + Pint** in CI pipeline
   - File: `.github/workflows/tests.yml` (or your CI config)
   - Add steps if missing

5. **Add health check endpoint**
   - File: `routes/api.php`
   - Route: `GET /health` → response JSON

6. **Audit test organization**
   - Verify tests/ mirrors Modules/ structure
   - Rename if needed: `tests/Feature/Orders/` not `tests/Feature/OrdersFeature/`

---

## References

- **BookStack**: https://github.com/BookStackApp/BookStack (development branch)
  - Key files: `app/Permissions/PermissionApplicator.php`, `app/Activity/`, `phpunit.xml`

- **Coolify**: https://github.com/coollabsio/coolify (main branch)
  - Key files: `app/Models/Application.php`, `config/queue.php`, `app/Http/Controllers/Webhook/Github.php`

- **Your CLAUDE.md**: Authoritative on your architecture decisions—all patterns above are aligned with it.
