# Bruno API Tests — Souq Ecommerce

REST API test collection for the Souq ecommerce platform using [Bruno](https://www.usebruno.com/).

## Setup

1. **Install Bruno** from https://www.usebruno.com/
2. **Open this folder** in Bruno (`File > Open Collection`)
3. **Set environment variables** in `.env`:
   ```
   BASE_URL=http://localhost:8000
   ```

## Collection Structure

### `/Customers` — Customer Management API

#### Auth (`/auth`)
- **register.bru** — POST `/api/v1/auth/register` — Create new customer account
- **login.bru** — POST `/api/v1/auth/login` — Authenticate and receive token
- **me.bru** — GET `/api/v1/auth/me` — Get current authenticated user
- **logout.bru** — POST `/api/v1/auth/logout` — Revoke authentication token

#### Profile (`/profile`)
- **show.bru** — GET `/api/v1/customers/profile` — Get customer profile
- **update.bru** — PUT `/api/v1/customers/profile` — Update customer profile

#### Addresses (`/addresses`)
- **list.bru** — GET `/api/v1/customers/addresses` — List all customer addresses
- **create.bru** — POST `/api/v1/customers/addresses` — Add new address
- **update.bru** — PUT `/api/v1/customers/addresses/{id}` — Update address
- **delete.bru** — DELETE `/api/v1/customers/addresses/{id}` — Remove address

#### Wishlists (`/wishlists`)
- **show.bru** — GET `/api/v1/wishlist` — Get customer's wishlist
- **add-item.bru** — POST `/api/v1/wishlist/items` — Add product variant to wishlist
- **remove-item.bru** — DELETE `/api/v1/wishlist/items/{variantId}` — Remove from wishlist

## Usage

1. **Start the auth flow**: Run `register.bru` then `login.bru`
   - The `login.bru` endpoint automatically stores the `auth_token` in variables
   - This token is reused in all authenticated requests
2. **Run subsequent requests** in any order — they all use the stored token
3. **Test addresses**: Run `create.bru` first (stores `address_id`), then `update.bru` and `delete.bru`
4. **Test wishlists**: Run `add-item.bru` (stores `wishlist_variant_id`), then `remove-item.bru`

## Environment Variables

| Variable | Purpose |
|---|---|
| `BASE_URL` | API base URL (default: `http://localhost:8000`) |
| `auth_token` | Bearer token from login (auto-populated) |
| `address_id` | Address ID from create request (auto-populated) |
| `wishlist_variant_id` | Variant ID from wishlist add (auto-populated) |

## Assertions

All requests include assertions to verify:
- Response status codes (201 for creates, 200 for updates, 204 for deletes)
- Response data structure and field values
- Auto-population of variables for dependent requests

## Running via Claude Code

Bruno integrates with Claude Code for automated testing:

```bash
# Run all tests in a collection
bruno run ./bruno/Customers --env .env

# Run specific request
bruno run ./bruno/Customers/auth/login.bru --env .env

# With CI/CD (GitHub Actions, etc.)
npx @usebruno/cli run ./bruno --env .env
```

## Next Steps

- Add tests for Catalog, Orders, Payments modules
- Integrate with CI/CD pipeline
- Use Bruno's CLI agent for automated test reporting
