# API Endpoints Documentation

This document describes all API endpoints, authentication flow, request/response examples, and status codes for the wallet API.

## Base URL

```
http://localhost:8080
```

All API endpoints are prefixed with `/api/v1`.

## Authentication

### Token-Based Authentication

The API uses Laravel Sanctum for stateless token-based authentication.

#### Authentication Flow

1. **Register** (optional): Create a new user account and receive a bearer token
2. **Login**: Authenticate with email/password and receive a bearer token
3. **Authenticated Requests**: Include the token in the `Authorization` header
4. **Logout**: Revoke the current token

#### Authorization Header

```http
Authorization: Bearer <token>
```

#### Protected Endpoints

All endpoints except `register` and `login` require authentication.

---

## Endpoints

### Health Checks

#### GET `/`

Simple health check endpoint.

**Authentication:** None required

**Response:**
```json
{
  "service": "wallet-api",
  "status": "ok"
}
```

**Status Code:** `200 OK`

---

#### GET `/up`

Laravel's native health check endpoint.

**Authentication:** None required

**Response:** Empty `200 OK` (no response body)

**Status Code:** `200 OK`

---

### Registration

#### POST `/api/v1/auth/register`

Creates a new user account and issues a bearer token.

**Authentication:** None required

**Request Headers:**
```http
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "SecurePassword123",
  "password_confirmation": "SecurePassword123",
  "type": "common",
  "document_country": "BRA",
  "document_type": "br_cpf",
  "document_value": "12345678909"
}
```

**Request Body Fields:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | Yes | User's full name |
| `email` | string | Yes | User's email address (must be unique) |
| `password` | string | Yes | User's password (min 8 characters) |
| `password_confirmation` | string | Yes | Must match `password` |
| `type` | string | No | User type: `common` or `merchant` (default: `common`) |
| `document_country` | string | Yes | Country code (ISO alpha-3, e.g., `BRA`) |
| `document_type` | string | Yes | Document type (e.g., `br_cpf`, `br_cnpj`) |
| `document_value` | string | Yes | Document number |

**Response (201 Created):**
```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "type": "common",
    "token": "4|abcdefghijklmnopqrstuvwxyz123456",
    "token_type": "Bearer"
  }
}
```

**Status Codes:**
- `201 Created`: User created successfully
- `422 Unprocessable Entity`: Validation error or duplicate record (e.g., email already exists with code `duplicate_record`)

---

### Login

#### POST `/api/v1/auth/login`

Authenticates a user and issues a bearer token.

**Authentication:** None required

**Request Headers:**
```http
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "john@example.com",
  "password": "SecurePassword123"
}
```

**Request Body Fields:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | Yes | User's email address |
| `password` | string | Yes | User's password |

**Response (200 OK):**
```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "token": "4|abcdefghijklmnopqrstuvwxyz123456",
    "token_type": "Bearer"
  }
}
```

**Response (401 Unauthorized):**
```json
{
  "message": "The provided credentials are incorrect."
}
```

**Status Codes:**
- `200 OK`: Login successful
- `401 Unauthorized`: Invalid credentials
- `422 Unprocessable Entity`: Validation error

---

### Logout

#### POST `/api/v1/auth/logout`

Revokes the current bearer token.

**Authentication:** Required (`auth:sanctum`)

**Request Headers:**
```http
Authorization: Bearer <token>
Content-Type: application/json
```

**Response (200 OK):**
```json
{
  "message": "Token revoked successfully."
}
```

**Status Codes:**
- `200 OK`: Token revoked successfully
- `401 Unauthorized`: Invalid or missing token

---

### Transfer

#### POST `/api/v1/transfer`

Executes a transfer between wallets.

**Authentication:** Required (`auth:sanctum`)

**Request Headers:**
```http
Authorization: Bearer <token>
Idempotency-Key: <uuid>
Content-Type: application/json
```

**Request Body:**
```json
{
  "payer": 1,
  "payee": 2,
  "amount": 5000,
  "currency": "BRL"
}
```

**Request Body Fields:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `payer` | integer | Yes* | Payer user ID (auto-populated from authenticated user if omitted) |
| `payee` | integer | Yes | Payee user ID |
| `amount` | integer | Yes | Transfer amount in **cents** (must be > 0) |
| `currency` | string | Yes | Currency code (default: `BRL`) |

**Request Headers:**
| Header | Type | Required | Description |
|--------|------|----------|-------------|
| `Authorization` | string | Yes | Bearer token |
| `Idempotency-Key` | string | Yes | Unique identifier for idempotency |

**Response (201 Created):**
```json
{
  "data": {
    "id": 123,
    "status": "completed",
    "failure_reason": null
  }
}
```

**Response (422 Unprocessable Entity) - Authorizer Rejected:**
```json
{
  "code": "authorizer_rejected",
  "message": "Transfer not authorized by external service"
}
```

**Response (503 Service Unavailable) - Authorizer Unavailable:**
```json
{
  "code": "authorizer_unavailable",
  "message": "Authorizer temporarily unavailable. Please retry."
}
```

**Response (403 Forbidden) - Identity Mismatch:**
```json
{
  "message": "The provided credentials are incorrect."
}
```

**Response (422 Unprocessable Entity) - Business Rule Violation:**
```json
{
  "message": "Validation error"
}
```

**Status Codes:**
- `201 Created`: Transfer successful
- `422 Unprocessable Entity`: Validation error, authorizer rejection, or business rule violation
- `403 Forbidden`: Payer ID does not match authenticated user
- `503 Service Unavailable`: External authorizer temporarily unavailable

---

## Idempotency

### Idempotency Key

All `POST` requests that modify data (e.g., transfer) require an `Idempotency-Key` header.

**Purpose:** Prevents duplicate processing of the same request.

**Behavior:**
1. First request: Processes normally and stores the response
2. Subsequent requests with the same key: Returns the cached response without re-processing

**Idempotency Key Format:**
- Must be a non-empty string
- Typically a UUID or similar unique identifier
- Should be unique per request

**Example:**
```http
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
```

**Cache Duration:** Idempotency keys are stored permanently in the database.

---

## Request Examples

### Register User

```bash
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePassword123",
    "type": "common",
    "document_country": "BRA",
    "document_type": "cpf",
    "document_value": "12345678909"
  }'
```

### Login

```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "SecurePassword123"
  }'
```

### Transfer (with Authentication)

```bash
curl -X POST http://localhost:8080/api/v1/transfer \
  -H "Authorization: Bearer 4|abcdefghijklmnopqrstuvwxyz123456" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -H "Content-Type: application/json" \
  -d '{
    "payer": 1,
    "payee": 2,
    "amount": 5000,
    "currency": "BRL"
  }'
```

### Transfer (Auto-populated Payer)

```bash
curl -X POST http://localhost:8080/api/v1/transfer \
  -H "Authorization: Bearer 4|abcdefghijklmnopqrstuvwxyz123456" \
  -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000" \
  -H "Content-Type: application/json" \
  -d '{
    "payee": 2,
    "amount": 5000,
    "currency": "BRL"
  }'
```

### Logout

```bash
curl -X POST http://localhost:8080/api/v1/auth/logout \
  -H "Authorization: Bearer 4|abcdefghijklmnopqrstuvwxyz123456"
```

---

## Status Codes Reference

| Code | Meaning | Common Scenarios |
|------|---------|------------------|
| `200 OK` | Request succeeded | Health check, logout |
| `201 Created` | Resource created | Registration, successful transfer |
| `401 Unauthorized` | Authentication required/failed | Invalid credentials, missing token |
| `403 Forbidden` | Access denied | Identity mismatch (payer != authenticated user) |
| `422 Unprocessable Entity` | Validation error | Invalid input, business rule violation, authorizer rejection |
| `409 Conflict` | Resource conflict | Email already exists |
| `503 Service Unavailable` | Service temporarily unavailable | External authorizer unavailable |

---

## Error Responses

### Validation Error (422)

```json
{
  "message": "Validation error",
  "errors": {
    "field": [
      "The field is required."
    ]
  }
}
```

### Authentication Error (401)

```json
{
  "message": "The provided credentials are incorrect."
}
```

### Authorization Error (403)

```json
{
  "message": "The provided credentials are incorrect."
}
```

### Service Unavailable (503)

```json
{
  "code": "authorizer_unavailable",
  "message": "Authorizer temporarily unavailable. Please retry."
}
```

---

## Business Rules

### Transfer Rules

The transfer enforces these business rules:

1. **Same User Restriction:** Payer and payee cannot be the same user
2. **Amount Validation:** Amount must be greater than 0
3. **Wallet Status:** Both payer and payee wallets must be active
4. **Merchant Restriction:** Merchant users cannot initiate transfers (only receive)
5. **Currency Match:** Payer and payee must use the same currency
6. **Sufficient Balance:** Payer must have sufficient balance
7. **External Authorization:** Transfer must be authorized by external service
8. **Identity Verification:** Payer ID must match authenticated user ID

### User Types

| Type | Can Pay | Can Receive |
|------|---------|-------------|
| `common` | Yes | Yes |
| `merchant` | No | Yes |

---

## Testing the API

### Using cURL

```bash
# Register
curl -X POST http://localhost:8080/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "type": "common",
    "document_country": "BRA",
    "document_type": "cpf",
    "document_value": "12345678909"
  }'

# Login
TOKEN=$(curl -X POST http://localhost:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123"
  }' | jq -r '.data.access_token')

# Transfer
curl -X POST http://localhost:8080/api/v1/transfer \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: test-uuid-$(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "payee": 2,
    "amount": 5000,
    "currency": "BRL"
  }'
```

### Using Docker Compose

```bash
# Run a test request
docker compose run --rm app curl http://localhost:8080/

# Start the stack
docker compose up -d

# Run migrations
docker compose run --rm app php artisan migrate --force
```

---

## Rate Limiting

Currently, the API does not implement rate limiting. All requests are processed without rate limits.

---

## CORS

The API is designed for backend-to-backend communication. CORS is not configured for public browser access.

---

## Versioning

The API uses URL path versioning (`/api/v1/`). Future breaking changes will be released under `/api/v2/`.