# LexCase — Legal Case Management Platform

An enterprise-grade, multi-tenant legal operating system for Indian lawyers and law firms, built on **Laravel 12 + Inertia.js + Vue 3 + TypeScript + Tailwind**.

> **Phase 1 — no AI.** This phase delivers the complete legal workflow platform (cases, clients, hearings, documents, evidence, tasks, teams, notifications, audit logs, legal library). The architecture is intentionally AI-ready for a later phase.

---

## Tech stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4 |
| Auth | Session auth (Inertia) + Laravel Sanctum (API tokens) |
| Permissions | spatie/laravel-permission (roles & granular abilities) |
| Audit | spatie/laravel-activitylog |
| Search | Laravel Scout (database driver in dev) |
| Cache / Queue | Redis (prod) via Predis · database driver (dev) |
| Frontend | Vue 3 (Composition API), Inertia.js, TypeScript |
| UI | TailwindCSS, shadcn-vue, Lucide icons — **light mode only** |
| State / Forms | Pinia, VeeValidate + Zod |

## Architecture

Clean, layered, modular — controllers stay thin:

```
HTTP Request
  └─ Form Request (validation + authorization)
       └─ Controller (thin)            app/Http/Controllers
            └─ Service (orchestration)  app/Services
                 ├─ Action (unit of work, transactional)  app/Actions
                 └─ Repository (queries, eager loading)    app/Repositories
                      └─ Model (Eloquent + scopes + enums)  app/Models
  └─ DTO  (app/DTOs)              immutable payloads between layers
  └─ API Resource (app/Http/Resources)   response shaping
  └─ Events / Listeners / Jobs / Notifications   async side-effects
  └─ Policies (app/Policies)     authorization
  └─ Enums (app/Enums)           single source of truth (status/priority/role/permission)
```

### Multi-tenancy
Every firm-owned model uses the `BelongsToTeam` trait, which applies a global
`TeamScope` (data isolation) and auto-stamps `team_id` on create. The current
team is resolved by `App\Support\TeamContext`. Cross-tenant access returns 404.

### Security
CSRF (Inertia), policy-based authorization, Spatie RBAC, query-allowlisted
sorting, validated/whitelisted mass assignment, UUID route keys (internal IDs
never exposed), activity-log audit trail, rate-limited auth routes.

## Local setup

> **Important (this machine):** the global `php`/`composer` resolve to XAMPP's PHP 8.2,
> which is too old. Use Laragon's PHP 8.4 explicitly, or run everything through Laragon
> (which puts PHP 8.4 on PATH). The database uses XAMPP's **MariaDB**.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
#   .env is preconfigured: DB_CONNECTION=mariadb, DB_DATABASE=ai_lawyer

# 3. Database (start MariaDB first), then:
php artisan migrate:fresh --seed
php artisan storage:link

# 4. Run (Vite + queue + server)
composer run dev          # or: npm run dev  +  php artisan serve
```

### Demo login
```
admin@lexcase.test  /  password      (Firm Owner — all permissions)
priya@lexcase.test  /  password      (Partner)
rohan@lexcase.test  /  password      (Associate)
sara@lexcase.test   /  password      (Paralegal)
```

## Roles & permissions
`firm_owner`, `partner`, `associate`, `paralegal`, `clerk` — see
`App\Enums\RoleType` / `PermissionType` and `RolePermissionSeeder`.

## Modules
Cases (full CRUD, reference implementation) · Clients · Hearings · Tasks ·
Documents · Evidence · Team · Activity Log · Legal Library (templates + statutes) ·
Notifications · Dashboard.

## Tests
```bash
php artisan test          # Pest — 33 passing
```

## Production notes
- Switch `CACHE_STORE` & `QUEUE_CONNECTION` to `redis`; run a Redis server.
- **Laravel Horizon** is the intended queue dashboard but requires `ext-pcntl`/`ext-posix`
  (Unix only) — install and run it on the Linux production host, not on Windows.
- Set `SCOUT_DRIVER` to a real engine (Meilisearch/Algolia) for large datasets.
