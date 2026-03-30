---
description: >
  Scaffold a complete new Laravel module with architecture design first.
  Usage: /new-module Inventory
allowed-tools: Read, Write, Edit, Bash, Glob, Agent
---

# New Module: $ARGUMENTS

## Step 1: Architecture design first
```
Use the laravel-architect agent to design the $ARGUMENTS module.
```
Present the design. Wait for confirmation before creating any files.

## Step 2: Scaffold files (after confirmation)

Create `backend/app/Modules/$ARGUMENTS/` with:
- `Controllers/$ARGUMENTSController.php` — thin, delegates to service
- `Models/$ARGUMENTS.php` — Eloquent model
- `Services/$ARGUMENTSService.php` — all business logic here
- `Events/$ARGUMENTSCreated.php` — domain event
- `Resources/$ARGUMENTSResource.php` — JSON transformation
- `Requests/Store$ARGUMENTSRequest.php` — validation
- `Requests/Update$ARGUMENTSRequest.php` — validation
- `Exceptions/$ARGUMENTSException.php` — domain exception
- `routes.php` — API routes for this module

## Step 3: Register routes
Add to `backend/routes/api.php`:
```php
require __DIR__.'/../app/Modules/$ARGUMENTS/routes.php';
```

## Step 4: Write migration
```
Use the migration-writer agent to create the initial migration for
the $ARGUMENTS module based on the architecture design above.
```

## Step 5: Confirm
Output the complete file tree created and confirm routes are registered.
