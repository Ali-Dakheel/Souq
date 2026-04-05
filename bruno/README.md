# Bruno API Tests — Souq Ecommerce

REST API test collection for the Souq ecommerce platform using [Bruno](https://www.usebruno.com/).

## Setup

### 1. Install Bruno
Download from https://www.usebruno.com/ (free, open-source)

### 2. Open as Collection
In Bruno:
- Click **Create New** or **Open Collection**
- Navigate to the `bruno/` folder in this repository
- Click **Open**

Bruno will recognize `bruno.json` and load the collection automatically.

### 3. Select Environment
Inside Bruno after opening:
- Look for **Environments** dropdown (top area, near the collection name)
- Select **development** (auto-loaded from `environments/development.bru`)
- This sets `BASE_URL=http://localhost:8000` and other variables

**Available environments:**
- **development** — Local testing (`http://localhost:8000`)
- **staging** — Staging server (`https://staging-api.souq.example.com`)
- **production** — Production server (`https://api.souq.example.com`)

### 4. Run Tests
1. Start with **Customers → auth → login.bru** (click blue **Send** button)
2. The login endpoint automatically stores `auth_token` in variables
3. Run other requests in any order — they all use the stored token
4. For addresses: run **create.bru** first (stores `address_id`), then **update.bru** and **delete.bru**
5. For wishlists: run **add-item.bru** first (stores `wishlist_variant_id`), then **remove-item.bru**

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

### Predefined Variables (in each environment)

| Variable | Purpose | Auto-populated? |
|---|---|---|
| `BASE_URL` | API base URL | ❌ Set per environment |
| `auth_token` | Bearer token from login endpoint | ✅ Yes (after login) |
| `address_id` | Address ID from create address request | ✅ Yes (after create) |
| `wishlist_variant_id` | Variant ID from add-to-wishlist request | ✅ Yes (after add-item) |

### Modify Environments

To change the BASE_URL or add new variables:

1. In Bruno: **Environments** dropdown → select an environment
2. Click the **pencil/edit icon** to edit
3. Modify or add variables under `vars { ... }`
4. Example:
   ```bru
   vars {
     BASE_URL: http://localhost:3000
     auth_token:
     API_KEY: my-secret-key
   }
   ```
5. Save and restart the request

### Add New Environment

1. In Bruno: **Create new environment file**
2. Or add manually: Create `environments/mycustom.bru`:
   ```bru
   vars {
     BASE_URL: https://my-custom-server.com
     auth_token:
     address_id:
     wishlist_variant_id:
   }
   ```
3. Refresh Bruno — it will appear in the environments dropdown

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
