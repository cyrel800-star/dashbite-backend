# DashBite Backend

Backend service para sa DashBite Food Delivery system. Sinusundan ang W2.01 tasks mula sa project plan.

**Stack:**
- Laravel 12 (PHP 8.2+)
- Sanctum (API tokens)
- SQLite (dev) / MySQL (prod-ready)
- Laravel RateLimiter (OTP throttling)

**Status:** W2.01 (Identity & Access) — **DONE**

---

## Setup (para sa Frontend Developer)

### Requirements
- PHP 8.2+
- Composer
- Git
- Node.js + npm (para sa frontend — hindi kailangan sa backend)

### Installation

```bash
# Clone repo
git clone <repo-url> dashbite-backend
cd dashbite-backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations (SQLite default)
php artisan migrate

# (Optional) Seed test users
php artisan db:seed

# Start server
php artisan serve
```

Backend API ay accessible sa: **`http://127.0.0.1:8000/api`**

---

## Base URL

```
Development: http://127.0.0.1:8000/api
```

Lahat ng endpoints ay naka-prefix sa `/api`.

---

## Authentication Flow

### Overview
1. User submits email + password → receives **Bearer token**
2. Frontend stores token (localStorage / sessionStorage)
3. Bawat request sa protected endpoint → include header:
   ```
   Authorization: Bearer <token>
   Accept: application/json
   ```

### Account States
- `pending` — bagong account, hindi pa approved ng admin. **Pwede pa ring mag-login**, pero **operational actions denied** (e.g., rider accept order).
- `approved` — full access sa role-specific features.
- `disabled` — restricted, parang pending pero mas malala (admin deliberately disabled).

### Roles
- `customer` — browse restaurants, order food
- `restaurant` — manage menu, accept/reject orders
- `rider` — go online, accept delivery offers
- `admin` — approve accounts, manage fees, monitor activity

---

## API Endpoints

### Auth (Public)

#### `POST /api/auth/login`
Login with email + password. Returns token.

**Request:**
```json
{
  "email": "rider@test.com",
  "password": "secret123"
}
```

**Response (200 OK):**
```json
{
  "token": "1|abc123xyz...",
  "user": {
    "id": 1,
    "name": "Test Rider",
    "email": "rider@test.com",
    "role": "rider",
    "account_status": "pending"
  }
}
```

**Response (422) — Invalid credentials:**
```json
{
  "message": "Invalid credentials. (and 1 more error)",
  "errors": {
    "email": ["Invalid credentials."]
  }
}
```

**Note:** Generic error message — walang account enumeration (hindi sinasabi kung email exists o hindi).

---

#### `POST /api/auth/otp/request`
Request OTP via email. **Rate limited: 5 requests per minute per email+IP.**

**Request:**
```json
{
  "email": "user@example.com"
}
```

**Response (200 OK):**
```json
{
  "message": "OTP sent.",
  "retry_after": 60
}
```

**Response (429 Too Many Requests) — Exceeded 5/min:**
```json
{
  "error": "otp_throttled",
  "message": "Too many OTP requests. Please try again shortly.",
  "retry_after": 60
}
```

**Note:** Throttled requests are **logged in DB** for review (PDF AC #2 ng W2.01).

---

### Auth (Protected — need Bearer token)

#### `GET /api/auth/me`
Get current authenticated user.

**Headers:**
```
Authorization: Bearer <token>
```

**Response (200 OK):**
```json
{
  "id": 1,
  "name": "Test Rider",
  "email": "rider@test.com",
  "role": "rider",
  "account_status": "pending"
}
```

---

#### `POST /api/auth/logout`
Revoke current token.

**Headers:**
```
Authorization: Bearer <token>
```

**Response (200 OK):**
```json
{
  "message": "Logged out."
}
```

---

### Operational Routes (Role + Approval Required)

Ang mga routes na ito ay may **3 middleware**:
1. `auth:sanctum` — kailangan valid token
2. `role:<role>` — kailangan tama ang role
3. `approved` — kailangan `account_status = approved`

**Kung `pending` or `disabled`:**
```json
// 403 Forbidden
{
  "error": "account_pending",
  "account_status": "pending"
}
```

**Kung role mismatch (e.g., customer trying rider endpoint):**
```json
// 403 Forbidden
{
  "error": "forbidden_role"
}
```

#### Sample protected routes (stubs — real implementations sa susunod na tasks)

| Method | Endpoint | Required Role | Task |
|---|---|---|---|
| POST | `/api/rider/availability` | `rider` + `approved` | W5.01 |
| POST | `/api/restaurant/orders/{id}/accept` | `restaurant` + `approved` | W4.01 |
| POST | `/api/admin/users/{id}/approve` | `admin` | W6.05 |

**Note:** Ang mga endpoint na ito ay **stub pa lang** (returns `{ "ok": true }`). I-implement pa sa susunod na tasks (W4, W5, W6).

---

## Error Response Format

Lahat ng errors ay sumusunod sa Laravel standard:

**Validation error (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password field is required."]
  }
}
```

**Authentication error (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Authorization error (403):**
```json
{
  "error": "account_pending" | "account_disabled" | "forbidden_role"
}
```

**Not found (404):**
```json
{
  "message": "Resource not found."
}
```

**Rate limit (429):**
```json
{
  "error": "otp_throttled",
  "message": "Too many OTP requests. Please try again shortly.",
  "retry_after": 60
}
```

---

## Status Vocabulary

| Field | Values | Meaning |
|---|---|---|
| `user.role` | `customer`, `restaurant`, `rider`, `admin` | User type |
| `user.account_status` | `pending`, `approved`, `disabled` | Account approval state |
| `otp_requests.outcome` | `sent`, `throttled` | OTP request result |

---

## For Frontend Developers — Testing

### Test Accounts (seed after `php artisan db:seed`)

| Email | Password | Role | Status |
|---|---|---|---|
| `customer@test.com` | `password` | customer | approved |
| `restaurant@test.com` | `password` | restaurant | approved |
| `rider@test.com` | `password` | rider | approved |
| `rider.pending@test.com` | `password` | rider | **pending** |
| `restaurant.disabled@test.com` | `password` | restaurant | **disabled** |
| `admin@test.com` | `password` | admin | approved |

### Quick test with curl / PowerShell

```powershell
# Login as rider
$response = Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/login" `
    -Method POST `
    -ContentType "application/json" `
    -Body '{"email":"rider@test.com","password":"password"}'

$token = $response.token
Write-Host "Token: $token"

# Call protected endpoint
Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/me" `
    -Method GET `
    -Headers @{ "Authorization" = "Bearer $token" }
```

### Testing OTP Throttle

```powershell
# 6 requests in a row → 6th returns 429
1..6 | ForEach-Object {
    try {
        Invoke-RestMethod -Uri "http://127.0.0.1:8000/api/auth/otp/request" `
            -Method POST `
            -ContentType "application/json" `
            -Body '{"email":"test@test.com"}'
        Write-Host "Request $_ : OK"
    } catch {
        Write-Host "Request $_ : $($_.Exception.Response.StatusCode.value__) — throttled"
    }
}
```

---

## CORS Configuration

**NOTE**: Para ma-access ng frontend (different domain/port), kailangang i-configure ang CORS. Sa `config/cors.php`:

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:3000', 'http://127.0.0.1:5173'],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

I-update ang `allowed_origins` depende sa frontend dev URL.

---

## Development Notes

### Running Tests

```bash
php artisan test
```

Lahat ng tests ay dapat pumasa (9 tests for W2.01).

### Database Reset

```bash
php artisan migrate:fresh       # drop all tables + re-run migrations
php artisan migrate:fresh --seed # + seed test data
```

### Encoding Rule

**Lahat ng PHP files ay dapat UTF-8 WITHOUT BOM.** Kung may BOM, mag-fail ang PHP parsing. Use `Set-Content -Encoding ascii` sa PowerShell, o i-configure ang editor (VS Code, etc.) to save as UTF-8 without BOM.

---

## Roadmap (Tasks from PDF)

| Task | Repo | Status |
|---|---|---|
| **W2.01** — Account and role access service | `dashbite-backend` | ✅ Done |
| **W2.02** — Role-specific login screens | 3 frontend repos | ⏳ Next |
| **W2.03** — Publish API reference | `dashbite-docs` | ⏳ |
| **W3.01** — Restaurant catalog & availability | `dashbite-backend` | ⏳ |
| **W3.02** — Customer restaurant discovery | `dashbite-customer-pwa` | ⏳ |
| **W3.03** — Restaurant menu management | `dashbite-restaurant` | ⏳ |
| **W3.04** — Order submission & checkout | `dashbite-backend` + `dashbite-customer-pwa` | ⏳ |

---

## Support

Para sa questions tungkol sa API, tingnan:
- **API code**: `app/Http/Controllers/`
- **Routes**: `routes/api.php`
- **Tests**: `tests/Feature/` (halimbawa ng usage)
- **Enums**: `app/Enums/` (status vocabulary)

---

**Last updated:** 2026-11-02
**Version:** W2.01