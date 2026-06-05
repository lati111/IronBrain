# IronBrain

The publicly available repository for [ironbrain.io](https://ironbrain.io) — a Laravel 12 web application hosting a suite of useful webtools. IronBrain uses a modular architecture, meaning new feature sets can be added as self-contained modules with their own routes, views, models, and services.

## Table of Contents

- [Modules](#modules)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Deployment](#deployment)
  - [Development](#development)
- [Configuration](#configuration)
- [Architecture](#architecture)
  - [Project Structure](#project-structure)
  - [Authentication & Authorization](#authentication--authorization)
  - [Dataprovider System](#dataprovider-system)
  - [Dual Interface Pattern](#dual-interface-pattern)
- [API Reference](#api-reference)
  - [Authentication Endpoints](#authentication-endpoints)
  - [Config Endpoints](#config-endpoints)
  - [PKSanc Endpoints](#pksanc-endpoints)
- [Dataprovider Endpoints](#dataprovider-endpoints)
- [Custom Validators](#custom-validators)
- [Database](#database)
- [Testing](#testing)

---

## Modules

| Module | Description |
|--------|-------------|
| **PKSanc** | Pokémon Save Collection manager. Upload Pokémon from save files via CSV, browse your collection, track Pokédex completion, and manage game data. |

---

## Requirements

| Dependency | Version |
|-----------|---------|
| PHP | ^8.3 |
| Composer | ^2.0 |
| Node.js | ^22.12.0 |
| NPM | ^10.0 |
| MySQL | ^8.0 |
| Google Chrome | Latest (browser tests only) |

---

## Installation

### Deployment

1. Download and extract the IronBrain zip file.
2. Copy `.env.example` to `.env` and configure it (see [Configuration](#configuration)).
3. Run `composer install --no-dev` to install PHP dependencies.
4. Run `npm install` to install Node dependencies.
5. Run `php artisan key:generate` to generate the application key.
6. Run `php artisan migrate` to create the database schema.
7. Run `php artisan db:seed --class=CoreSeeder` to seed default modules and permissions.
8. Run `php artisan import:all` to populate Pokémon, type, nature, move, ability, and game data.
9. Run `npm run build` to compile frontend assets.
10. Run `php artisan optimize` to cache config, routes, and views.
11. Run `php artisan storage:link` to create the public storage symlink.

### Development

1. Download and extract the IronBrain zip file.
2. Copy `.env.example` to `.env` and configure it (see [Configuration](#configuration)).
3. Run `composer install` to install all PHP dependencies (including dev tools).
4. Run `npm install` to install Node dependencies.
5. Run `php artisan key:generate` to generate the application key.
6. Copy `.env` to `.env.testing` and set a **separate** database in the testing file.
7. Run `php artisan migrate` to set up the main database.
8. Run `php artisan migrate --env=testing` to set up the test database.
9. Run `php artisan db:seed --class=CoreSeeder` to seed the main database.
10. Run `php artisan db:seed --class=AuthSeeder` to seed test users and roles.
11. Run `php artisan import:all` to populate reference data.
12. Run `php artisan storage:link` to create the public storage symlink.
13. In one terminal, run `php artisan serve` to start the development server.
14. In another terminal, run `npm run dev` to start the Vite dev server with HMR.

---

## Configuration

Key `.env` values:

| Variable | Description |
|----------|-------------|
| `APP_NAME` | Application display name |
| `APP_URL` | Full URL including protocol (e.g. `http://localhost:8000`) |
| `APP_KEY` | Generated application encryption key |
| `DB_CONNECTION` | Database driver (default: `mysql`) |
| `DB_HOST` / `DB_PORT` | Database host and port |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` / `DB_PASSWORD` | Database credentials |
| `SANCTUM_STATEFUL_DOMAINS` | Comma-separated domains allowed to use SPA cookie auth |
| `SESSION_DRIVER` | Session storage backend (default: `file`) |

---

## Architecture

### Project Structure

```
app/
├── Console/Commands/          # Artisan commands (data import, etc.)
├── Enum/                      # String constants and enumerations
│   ├── Auth/                  # UserEnum, RoleEnum, PermissionEnum
│   └── PKSanc/                # PKSanc strings, storage paths, CSV versions
├── Exceptions/                # Custom exception classes
│   └── Modules/PKSanc/        # ImportException, ImportValidationException
├── Http/
│   ├── Api/                   # JSON API handlers (extend AbstractApi)
│   │   ├── Auth/
│   │   ├── Config/
│   │   └── Modules/PKSanc/
│   ├── Controllers/           # Web view controllers (extend Controller)
│   │   ├── Auth/
│   │   ├── Config/
│   │   └── Modules/PKSanc/
│   ├── Dataproviders/         # Paginated data providers
│   │   ├── Config/
│   │   ├── Filters/           # Custom filter implementations
│   │   ├── Interfaces/
│   │   ├── Modules/PKSanc/
│   │   └── Traits/
│   └── Middleware/            # HTTP middleware
├── Models/
│   ├── Auth/                  # User, Role, Permission, RolePermission
│   └── PKSanc/                # Pokemon, StoredPokemon, ImportCsv, Game, etc.
├── Providers/
│   └── AppServiceProvider.php # Rate limiting, route macros, custom validators
└── Service/
    ├── AvatarGeneratorService.php
    └── PKSanc/
        ├── CsvHydrator/       # Version-specific CSV row parsers
        ├── DepositService.php
        └── ImportService.php

routes/
├── web.php          # HTML view routes
├── api.php          # JSON API routes (prefixed /api)
├── dataprovider.php # Data provider routes (prefixed /data)
├── console.php      # Artisan closure commands
└── channels.php     # Broadcast channel authorization

database/
├── migrations/      # Chronological schema migrations
├── seeders/         # CoreSeeder (modules/permissions), AuthSeeder (test users)
└── factories/       # Model factories for testing

resources/
├── css/             # Stylesheets
├── ts/              # TypeScript source files
├── views/           # Blade templates
└── lang/            # Localization strings

tests/
├── Browser/         # Laravel Dusk browser tests
├── Feature/         # HTTP feature tests
└── Unit/            # Unit tests
```

---

### Authentication & Authorization

IronBrain uses **Laravel Sanctum** for authentication, supporting two flows:

- **SPA / Session-based**: Web routes use `auth:sanctum` with cookie sessions. Add the app domain to `SANCTUM_STATEFUL_DOMAINS` in `.env`.
- **Token-based**: API clients authenticate via a bearer token returned after a successful login.

#### Permission System

Permissions are stored in `auth__permission` and linked to roles via `auth__role_permission`. The `PermissionGuard` middleware enforces them at the route level.

```
User → Role → RolePermission[] → Permission[]
```

**Applying permission middleware to a route:**

```php
// Single permission
Route::get('/example')->middleware('auth.permission:some.permission');

// Multiple permissions — user must have ALL of them
Route::get('/example')->middleware('auth.permission:permission.one,permission.two');
```

**Middleware behaviour:**

| Situation | Result |
|-----------|--------|
| User's role has `is_admin = true` | All checks bypassed |
| Permission name not found in database | **403 Forbidden** (fail-secure) |
| User not authenticated | **401 Unauthorized** |
| User lacks the permission | **403 Forbidden** |
| All checks pass | Request continues |

#### Seeded Test Accounts (development only, via `AuthSeeder`)

| Username | Password | Role |
|----------|----------|------|
| `Tester` | `Password123` | Tester (has `has.permission`) |
| `Admin` | `Password123` | Admin (`is_admin = true`, bypasses all permission checks) |

---

### Dataprovider System

Dataproviders serve paginated, searchable, sortable, and filterable data to frontend components. They are registered using the `Route::dataprovider()` macro and mounted under the `/data` prefix with the `web` middleware.

**A single `Route::dataprovider()` call registers up to three endpoints:**

```php
Route::dataprovider('/example', 'data.example', ExampleDataprovider::class);
// GET /data/example         → ExampleDataprovider@data    — paginated records
// GET /data/example/pages   → ExampleDataprovider@count   — total record count
// GET /data/example/filters → ExampleDataprovider@filters — available filter options (if method exists)
```

**Supported query parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | integer | Page number (1-indexed) |
| `per_page` | integer | Records per page |
| `search` | string | Full-text search applied to configured fields |
| `sort` | JSON array | e.g. `[{"column":"name","direction":"asc"}]` |
| `filters` | JSON object | e.g. `{"type":"fire"}` |

**Creating a new dataprovider:**

1. Create a class in `app/Http/Dataproviders/`.
2. Extend `AbstractCardlist` or `AbstractDatalist`.
3. Add traits from `lati111/laravel_dataproviders` as needed: `Dataprovider`, `Paginatable`, `Searchable`, `Sortable`, `Filterable`, `HasPages`, `HasFilters`.
4. Implement `getContent(Request $request, bool $dataQuery): Builder` — return the base Eloquent query.
5. Implement `getSearchFields(): array` if using `Searchable`.
6. Register a route in `routes/dataprovider.php`.

---

### Dual Interface Pattern

Each feature typically has both a **web controller** (renders Blade views) and an **API handler** (returns JSON). Business logic lives in shared service classes.

```
Browser request
    │
    ├─ GET  ──► Web Controller ──► View (Blade)
    └─ POST ──► Web Controller ──► Redirect
                API Handler    ──► JSON response
                      │
                      └─ Service class (business logic)
                                │
                                └─ Eloquent models
```

**API response envelope** (all `AbstractApi` subclasses):

```json
{
    "code": 200,
    "message": "data retrieved",
    "data": { },
    "errors": null
}
```

---

## API Reference

### Authentication Endpoints

#### `POST /api/auth/login`

Authenticate a user and start a session.

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `username` | string | Yes | |
| `password` | string | Yes | |
| `remember_me` | boolean/string | No | Accepts `true`, `false`, `on`, `off` |

**Responses:**

| Code | Meaning |
|------|---------|
| `200` | Login successful. `Location` header points to home. |
| `401` | Invalid credentials. |
| `422` | Validation error. |

---

#### `POST /api/auth/signup`

Create a new user account.

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `username` | string | Yes | Unique, max 28 characters |
| `email` | string | No | Unique |
| `password` | string | Yes | Min 8 chars, mixed case + numbers required |
| `password_confirmation` | string | Yes | Must match `password` |

**Responses:**

| Code | Meaning |
|------|---------|
| `201` | Account created. `Location` header points to home. |
| `422` | Validation error (duplicate username, weak password, etc.). |

---

### Config Endpoints

All config endpoints require `auth:sanctum`.

#### `POST /api/config/user/{user_uuid}/set_role/{role_id}`

Assign a role to a user.

**Required permissions:** `config.user.edit`, `config.user.role`

| Code | Meaning |
|------|---------|
| `200` | Role assigned. |
| `404` | User or role not found. |

---

#### `POST /api/config/role/{role_id}/permission/{permission_id}/add`

Grant a permission to a role.

**Required permission:** `config.role.permissions`

| Code | Meaning |
|------|---------|
| `200` | Permission granted. |
| `404` | Role or permission not found. |

---

#### `POST /api/config/role/{role_id}/permission/{permission_id}/remove`

Revoke a permission from a role.

**Required permission:** `config.role.permissions`

| Code | Meaning |
|------|---------|
| `200` | Permission revoked. |
| `404` | Role or permission not found. |

---

### PKSanc Endpoints

All PKSanc endpoints require `auth:sanctum`.

#### `POST /api/pksanc/deposit/staging`

Upload a Pokémon CSV to begin the staging workflow.

**Request body (multipart/form-data):**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | Yes | 4–255 chars, alphanumeric, spaces, hyphens, and underscores only |
| `csv` | file | Yes | `.csv` or `.txt`, max 480 KB |
| `game` | string | Yes | Must match a game code in the database |

**Responses:**

| Code | Meaning |
|------|---------|
| `302` | Staged successfully. Redirects to the staging review page. |
| `422` | Validation error. |

---

#### `POST /api/pksanc/deposit/staging/{staging_uuid}/confirm`

Confirm staged Pokémon and move them to the permanent collection.

> The `staging_uuid` must belong to the authenticated user.

**Request body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `excluded_uuids` | string[] | No | UUIDs of staged Pokémon to skip |

**Responses:**

| Code | Meaning |
|------|---------|
| `200` | Confirmed. `Location` header points to the collection. |
| `404` | Import not found or not owned by the current user. |
| `422` | Validation error. |

---

#### `POST /api/pksanc/romhacks/add`

Add a ROM hack as a new game entry.

**Request body:**

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `name` | string | Yes | Max 255 characters |
| `original_game` | string | No | Game code of the base game |

**Responses:**

| Code | Meaning |
|------|---------|
| `201` | ROM hack added. |
| `422` | Validation error. |

---

#### `POST /api/pksanc/pokedex/mark`

Add a Pokédex marking for the authenticated user.

**Request body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `pokedex_id` | integer | Yes | National Pokédex number |
| `form_index` | integer | Yes | Form index (0 = base form) |
| `marking` | string | Yes | Marking type (e.g. `caught`, `seen`) |

**Responses:**

| Code | Meaning |
|------|---------|
| `201` | Marking added. |
| `208` | Marking already exists. |

---

#### `POST /api/pksanc/pokedex/unmark`

Remove a Pokédex marking for the authenticated user.

**Request body:** Same fields as `/mark`.

| Code | Meaning |
|------|---------|
| `201` | Marking removed. |
| `208` | Marking did not exist. |

---

## Dataprovider Endpoints

All endpoints support `page`, `per_page`, `search`, `sort`, and `filters` query parameters. Each listed route also has a `/pages` variant that returns the total record count for pagination.

### Home

| Endpoint | Description | Auth |
|----------|-------------|------|
| `GET /data/home/overview` | Navigation module cards | None |

### Config

| Endpoint | Description | Required Permission |
|----------|-------------|---------------------|
| `GET /data/config/users/overview` | User management table | `config.user.view` |
| `GET /data/config/role/overview` | Role management table | `config.role.view` |
| `GET /data/config/role/dataselect` | Role dropdown options | `config.role.view` |
| `GET /data/config/role/{role_id}/permissions` | Permissions assigned to a role | `config.role.permissions` |

### PKSanc

| Endpoint | Description | Auth |
|----------|-------------|------|
| `GET /data/pksanc/data/overview` | User's stored Pokémon collection | `auth:sanctum` |
| `GET /data/pksanc/data/pokedex` | Pokédex completion status with markings | `auth:sanctum` |
| `GET /data/pksanc/data/staging/{import_uuid}` | Staged Pokémon from a pending import | `auth:sanctum` |
| `GET /data/pksanc/data/games/dataselect` | Game dropdown options | `auth:sanctum` |
| `GET /data/pksanc/data/saves/dataselect` | Save file dropdown options | `auth:sanctum` |
| `GET /data/pksanc/data/owned-species/dataselect` | Owned species dropdown | `auth:sanctum` |

---

## Custom Validators

Registered in `AppServiceProvider` and available in any `Validator::make()` call.

| Rule | Usage | Accepts |
|------|-------|---------|
| `checkbox` | `'field' => 'checkbox'` | `true`, `false`, `null`, `'True'`, `'False'`, `'on'`, `'off'` |
| `csv_boolean` | `'field' => 'csv_boolean'` | `'True'` or `'False'` (CSV import strings) |
| `pksanc_pokemon_exists` | `'pokemon' => 'pksanc_pokemon_exists:form_index_field'` | Pokémon name that exists for the given form index |
| `pksanc_move_exists` | `'move' => 'pksanc_move_exists'` | Move name in database, or `'none'` |
| `pksanc_pokemon_gender` | `'gender' => 'pksanc_pokemon_gender'` | `M`, `F`, or `-` |
| `pksanc_trainer_gender` | `'gender' => 'pksanc_trainer_gender'` | Valid trainer gender values |

---

## Database

### Auth Tables (prefix `auth__`)

| Table | Description |
|-------|-------------|
| `auth__user` | Users; UUID primary key; soft-deletable; username-based auth |
| `auth__role` | Roles; optional `is_admin` flag grants full access |
| `auth__permission` | Permission definitions (code, display name, group) |
| `auth__role_permission` | Many-to-many role ↔ permission assignments |

### Config Tables (prefix `config__`)

| Table | Description |
|-------|-------------|
| `config__module` | Top-level navigation modules |
| `config__submodule` | Sub-items within a module |

### PKSanc Tables (prefix `pksanc__`)

| Table | Description |
|-------|-------------|
| `pksanc__pokemon` | Pokémon species/form definitions (species, types, stats, sprites) |
| `pksanc__stored_pokemon` | User-owned Pokémon; UUID PK; soft-delete; version-tracked |
| `pksanc__staged_pokemon` | Pending import records awaiting confirmation |
| `pksanc__import_csv` | CSV upload sessions; tracks uploader, game, and validation status |
| `pksanc__game` | Supported games and user-submitted ROM hacks |
| `pksanc__type` | Pokémon types |
| `pksanc__nature` | Natures with stat multipliers |
| `pksanc__ability` | Abilities |
| `pksanc__pokeball` | Poké Ball variants |
| `pksanc__move` | Moves (type, power, accuracy, priority) |
| `pksanc__stats` | Individual EV/IV values per stored Pokémon |
| `pksanc__contest_stats` | Contest stat values per stored Pokémon |
| `pksanc__moveset` | Four move slots per stored Pokémon |
| `pksanc__ribbon` | Ribbon definitions |
| `pksanc__pokemon_ribbon` | Per-Pokémon ribbon assignments |
| `pksanc__origin` | Origin data (OT name, met location, met level, ID number) |
| `pksanc__pokedex_marking` | Per-user Pokédex markings (caught, seen, etc.) |

### Migrations & Seeders

```bash
# Run all migrations
php artisan migrate

# Roll back the last batch
php artisan migrate:rollback

# Seed core data (modules, permissions) — run on all environments
php artisan db:seed --class=CoreSeeder

# Seed test users and roles — development only
php artisan db:seed --class=AuthSeeder
```

---

## Testing

### Running Tests

```bash
# Unit tests
php artisan test --testsuite=Unit

# Feature (HTTP) tests
php artisan test --testsuite=Feature

# Browser tests (requires Google Chrome)
php artisan dusk
```

### Test Setup

Browser and feature tests use a separate database defined in `.env.testing`. Ensure it is migrated before running tests:

```bash
php artisan migrate --env=testing
```

### Test Structure

```
tests/
├── Browser/           # Dusk end-to-end tests
│   ├── Auth/          # Login, signup, logout flows
│   ├── Config/        # User and role management UI
│   └── Modules/       # PKSanc module UI tests
├── Feature/           # HTTP-level API tests
│   └── Api/           # Auth and PKSanc API endpoints
└── Unit/              # Isolated logic tests
    ├── Controller/    # Controller unit tests
    ├── DataProvider/  # Dataprovider output tests
    └── Service/       # Service class unit tests
```
