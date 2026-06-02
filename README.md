# LexCase — AI-Assisted Legal Case Management Platform

An enterprise-grade, **multi-tenant legal operating system** for Indian lawyers and law firms. It manages the full lifecycle of a matter — clients, cases, hearings, tasks, documents, evidence and team — and layers an **AI Case Assistant** on top that drafts case summaries, suggests applicable IPC/BNS sections, and anticipates cross-examination questions.

Built on **Laravel 12 · Inertia.js · Vue 3 · TypeScript · TailwindCSS**, with a clean, layered, strictly tenant-isolated architecture.

---

## Table of contents

- [Highlights](#highlights)
- [Tech stack](#tech-stack)
- [Features](#features)
  - [AI Case Assistant](#-ai-case-assistant)
  - [Cross-examination prep](#-cross-examination-prep)
  - [Cached AI insights & staleness](#-cached-ai-insights--staleness)
  - [Case management](#case-management)
  - [Other modules](#other-modules)
- [How it works (architecture)](#how-it-works-architecture)
- [Domain model](#domain-model)
- [Roles & permissions](#roles--permissions)
- [Local setup](#local-setup)
- [Configuration (environment)](#configuration-environment)
- [Demo logins](#demo-logins)
- [Testing](#testing)
- [Project structure](#project-structure)
- [Production notes](#production-notes)

---

## Highlights

- 🏢 **Multi-tenant** — every firm's data is isolated by a global team scope; cross-tenant access simply returns 404.
- ⚖️ **Case-centric** — a case ties together the client, assigned lawyers, hearings, tasks, documents, evidence, notes and a stage-by-stage tracking timeline.
- 🤖 **AI built in** — summarise a matter, suggest IPC/BNS sections, and anticipate the opponent's & judge's cross-examination, all grounded in the case facts **and** its tracking history.
- 💾 **AI results are cached per case** with a content **signature**, so revisiting a case is instant — and a *"regenerate"* alert appears automatically once the case (notably its tracking) has changed.
- 📊 **Analytics dashboard** — KPI cards, trend/donut/bar charts, win-rate, and per-lawyer workload.
- 🔍 **Command palette** (⌘K) for instant global search across cases, clients, hearings and tasks.
- 🔐 **RBAC** — five firm roles with a granular, per-ability permission catalogue, plus a full activity-log audit trail.

---

## Tech stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Vue 3 (Composition API + `<script setup>`), Inertia.js 2, TypeScript |
| UI | TailwindCSS 3, shadcn-vue / Radix Vue, Lucide icons — **light mode** |
| State / Forms | Pinia, VeeValidate + Zod |
| Build | Vite 6 |
| AI inference | **Groq** (OpenAI-compatible chat completions) — `llama-3.3-70b-versatile` |
| Auth | Session auth (Inertia) + Laravel Sanctum (API tokens) |
| Permissions | `spatie/laravel-permission` |
| Audit | `spatie/laravel-activitylog` |
| Search | Laravel Scout (database driver in dev) |
| Cache / Queue | Redis (prod) via Predis · database driver (dev) |
| Routing helper | Ziggy (named routes in JS) |
| Tests | Pest 3 |

---

## Features

### 🤖 AI Case Assistant

Powered by the [`CaseAiAssistant`](app/Services/CaseAiAssistant.php) service, which calls Groq's OpenAI-compatible API and returns **strict JSON**. From the case facts it produces:

- a clean **case summary** (3–5 sentences) and **key facts**,
- the **IPC sections** that most likely apply — each with its number, title and a one-line reason tied to the facts (the equivalent **Bharatiya Nyaya Sanhita (BNS) 2023** section is named where useful),
- a **suggested priority** (low / medium / high / urgent),
- a **disclaimer** (these are AI suggestions for a lawyer's review, not legal advice).

It runs in two modes:

| Mode | Where | Endpoint | Persisted? |
|------|-------|----------|-----------|
| **Draft** | Case create / edit form | `POST /cases/ai/analyze` | No — analyses the in-progress form fields and can apply the summary/priority back into the form |
| **Persisted** | Case detail page | `POST /cases/{case}/analyze` | Yes — reads the saved facts + tracking history, caches the result on the case |

The assistant is **history-aware**: it is fed the case's tracking timeline (oldest-first) so it reasons about the sections that apply *now*, reflecting how the matter evolved after investigation.

### 🎯 Cross-examination prep

A tabbed panel on the case detail page ([`CaseCrossExam.vue`](resources/js/components/cases/CaseCrossExam.vue)) that anticipates the hearing from two angles:

- **⚔️ Opponent** — questions opposing counsel would put to attack credibility, expose contradictions, the delay, motive and gaps in the evidence.
- **⚖️ Judge** — questions the bench is likely to ask to clarify facts, test the legal basis, maintainability and the sections invoked.

Each question carries a **category** (Credibility, Timeline/Delay, Documentary, Legal basis, …) and a short **prep strategy** for how to answer. Generated via `POST /cases/{case}/cross-questions`, grounded in the case facts and tracking history.

### 💾 Cached AI insights & staleness

Both AI panels persist their output to the [`case_ai_insights`](database/migrations/2026_06_02_120000_create_case_ai_insights_table.php) table (one row per `case` × `kind`, where kind is `analysis` or `cross_exam`). This means:

- **Instant on revisit** — the stored result is rendered immediately, no regeneration cost.
- **Staleness detection** — each result is stamped with a **SHA-256 signature** of the case facts + the full tracking timeline at generation time ([`CaseAiInsight::signatureFor()`](app/Models/CaseAiInsight.php)). On every page load the current signature is recomputed; if it differs, the panel shows an amber **"This case has changed since these were generated — Regenerate"** alert. Regenerating updates the same row and clears the flag.

> AI is optional. Without a `GROQ_API_KEY` the app runs fully; the AI panels simply show a clean "not configured" message.

### Case management

The reference module — full resourceful CRUD plus:

- **Case tracking timeline** — stage-by-stage updates ([`CaseEvent`](app/Models/CaseEvent.php)) across the lifecycle (FIR/Complaint → Investigation → Charge Sheet → Charges Framed → Trial → Final Arguments → Judgment → Appeal → Closed). Each entry snapshots the **applicable legal sections**, and the UI highlights sections *added* at each stage. An AI helper can suggest sections for an update from its title (`POST /cases/{case}/suggest-sections`).
- **Favorability score** (0–100) — a visual gauge of how strongly the matter is assessed to be in the firm's favour.
- **Rich sidebar** — client, court details, lead lawyer & assignees.
- Soft deletes (archive/restore), tags, priority & status badges, and full activity logging.

### Other modules

| Module | What it does |
|--------|--------------|
| **Dashboard** | KPI cards (cases, clients, tasks, hearings), 6-month case/hearing trend, cases by status/type/priority, task completion, win-rate, and per-lawyer workload. |
| **Command palette (⌘K)** | Type-as-you-go global search across cases, clients, hearings and tasks (`GET /search`). |
| **Clients** | Full CRUD with dedicated create/edit/show pages; linked to their cases. |
| **Hearings** | Calendar/agenda view; scheduled and managed via modals; status & outcome tracking. |
| **Tasks** | Board with filters and a view toggle, drag-reorder, per-task history, priorities & due dates. |
| **Documents & Evidence** | Versioned document register and an evidence locker (type + chain-of-custody status). |
| **Legal Notebook** | Read-only quick reference of Indian statutes & sections. |
| **Legal Library (Templates)** | Printable, editable & customizable document templates — create, edit, duplicate, delete. |
| **Team** | Add / edit / remove firm members and assign roles. |
| **Roles & rights** | Admins configure which abilities each role grants. |
| **Activity log** | Firm-wide audit trail of who changed what, when. |
| **Notifications** | In-app notification centre with read/read-all. |
| **Settings** | Profile, password and appearance. |

---

## How it works (architecture)

A clean, layered, modular design — controllers stay thin and delegate downward:

```
HTTP Request
  └─ Form Request (validation + authorization)     app/Http/Requests
       └─ Controller (thin)                         app/Http/Controllers
            └─ Service (orchestration)              app/Services
                 └─ Repository (queries, eager-loading)  app/Repositories
                      └─ Model (Eloquent + scopes + enums)  app/Models
  ├─ DTO            immutable payloads between layers     app/DTOs
  ├─ API Resource   response shaping for the frontend     app/Http/Resources
  ├─ Policy         authorization rules                   app/Policies
  └─ Enum           single source of truth (status/type/role/permission)  app/Enums
```

The frontend is **Inertia + Vue**: Laravel controllers `Inertia::render()` a page component under [`resources/js/pages`](resources/js/pages) and pass props as JSON (shaped by API Resources). No separate REST client — Inertia bridges server and SPA.

### Multi-tenancy

Every firm-owned model uses the [`BelongsToTeam`](app/Models/Concerns/BelongsToTeam.php) trait, which:
- applies a global [`TeamScope`](app/Models/Scopes/TeamScope.php) so all queries are constrained to the current firm, and
- auto-stamps `team_id` on create.

The "current team" is resolved by [`App\Support\TeamContext`](app/Support/TeamContext.php) (the authenticated user's team, overridable for jobs/console/tests). Result: **a query can never leak another firm's data**, and another firm's records resolve as 404.

### Security

- CSRF protection (Inertia / `X-XSRF-TOKEN`)
- Policy-based authorization on every action + Spatie RBAC
- **UUID route keys** — internal auto-increment IDs are never exposed in URLs or APIs
- Whitelisted mass-assignment and query-allowlisted sorting
- Rate-limited AI and auth endpoints
- Full activity-log audit trail

---

## Domain model

| Model | Notes |
|-------|-------|
| `Team` | The firm (tenant root). |
| `User` | Firm member with a role and designation. |
| `Client` | A firm's client (individual or company). |
| `LegalCase` | The central aggregate (table `cases`; `LegalCase` because `Case` is reserved in PHP). |
| `CaseEvent` | A tracking-timeline entry (stage + applicable sections snapshot). |
| `CaseAiInsight` | Cached AI result (`analysis` / `cross_exam`) + signature for staleness. |
| `Hearing` | A scheduled court hearing with purpose, judge, status & outcome. |
| `Task` | A work item (assignee, due date, priority, status, board position). |
| `Document` / `DocumentFolder` | Versioned documents organised in folders. |
| `Evidence` | Evidence items with type & status. |
| `CaseNote` | Free-form notes on a case. |
| `LegalTemplate` | Reusable document templates (Legal Library). |
| `LegalSection` | Indian statute sections (Legal Notebook). |

**Enums** (single source of truth): `CaseStatus`, `CasePriority`, `CaseType`, `CaseStage`, `HearingStatus`, `TaskStatus`, `TaskPriority`, `EvidenceType`, `EvidenceStatus`, `ClientType`, `RoleType`, `PermissionType` — each exposing `label()` / `color()` helpers consumed by the UI.

---

## Roles & permissions

Five seeded roles ([`RoleType`](app/Enums/RoleType.php)): **Firm Owner**, **Partner**, **Associate**, **Paralegal**, **Clerk** (Owner & Partner are administrative).

Granular abilities ([`PermissionType`](app/Enums/PermissionType.php)) are grouped by module and granted per role in the [`RolePermissionSeeder`](database/seeders/RolePermissionSeeder.php):

```
cases.view|create|update|delete|assign
clients.view|create|update|delete
hearings.view|manage     documents.view|manage     evidence.view|manage
tasks.view|manage        templates.view|manage
team.manage    audit.view    settings.manage
```

---

## Local setup

> **Windows / Laragon note:** the global `php`/`composer` may resolve to an older PHP (e.g. XAMPP's 8.2). This project's lockfile targets **PHP 8.4** — run through Laragon (which puts PHP 8.4 on PATH) or invoke Laragon's binary explicitly, e.g. `C:/laragon/bin/php/php-8.4.x/php.exe artisan ...`.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database — create the schema and seed a demo firm
php artisan migrate:fresh --seed
php artisan storage:link

# 4. (Optional) Enable AI — add your Groq key to .env
#    GROQ_API_KEY=gsk_...

# 5. Run everything (server + queue + Vite)
composer run dev
#   …or separately:  php artisan serve   +   npm run dev
```

Open the app at the URL Laravel prints (typically `http://127.0.0.1:8000`).

---

## Configuration (environment)

Key variables beyond the Laravel defaults:

| Variable | Purpose | Default |
|----------|---------|---------|
| `GROQ_API_KEY` | Enables the AI features. Without it the app runs; AI panels show a "not configured" message. | _(unset)_ |
| `GROQ_MODEL` | Groq model id. | `llama-3.3-70b-versatile` |
| `GROQ_BASE_URL` | OpenAI-compatible base URL. | `https://api.groq.com/openai/v1` |
| `GROQ_CA_BUNDLE` | Optional path to a TLS CA bundle (falls back to the one shipped in `storage/` so HTTPS verification works on Windows/Laragon). | _(unset)_ |
| `DB_CONNECTION` / `DB_DATABASE` | Database (dev uses MariaDB/MySQL). | per `.env.example` |
| `SCOUT_DRIVER` | Search engine (database in dev; Meilisearch/Algolia in prod). | `database` |

AI config lives in [`config/services.php`](config/services.php) under the `groq` key.

---

## Demo logins

`php artisan migrate:fresh --seed` creates the firm **Sterling & Associates** with sample clients, cases, hearings and tasks:

| Email | Password | Role |
|-------|----------|------|
| `admin@lexcase.test` | `password` | Firm Owner (all permissions) |
| `priya@lexcase.test` | `password` | Partner |
| `rohan@lexcase.test` | `password` | Associate |
| `sara@lexcase.test`  | `password` | Paralegal |

---

## Testing

```bash
php artisan test          # Pest — 95 passing
# or a focused subset:
php artisan test --filter=CaseCrossExam
```

AI tests **fake the Groq HTTP call** (`Http::fake`), so the suite never hits the network and needs no API key. Coverage includes matters CRUD, case tracking, the AI assistant / analyze / cross-exam endpoints (incl. caching & staleness), command-palette search, roles, team management, and the legal notebook.

---

## Project structure

```
app/
  Enums/            status / priority / type / stage / role / permission
  Http/
    Controllers/    thin controllers (Cases/, Dashboard, Search, …)
    Requests/       Form Request validation + authorization
    Resources/      JSON response shaping (CaseResource, …)
  Models/           Eloquent models + concerns (BelongsToTeam, HasUuid, Sortable)
  Policies/         authorization rules
  Repositories/     query/eager-load layer
  Services/         orchestration (CaseAiAssistant, CaseService, …)
  Support/          TeamContext and helpers
database/
  migrations/       schema
  seeders/          RolePermission, LegalLibrary, LegalTemplate, Demo
resources/js/
  pages/            Inertia pages (cases/, clients/, hearings/, tasks/, …)
  components/        cases/ (AI panels, forms), dashboard/ (charts), common/, ui/ (shadcn-vue)
  composables/ lib/ validation/   hooks, helpers, Zod schemas
routes/
  web.php           all application routes
tests/              Pest feature tests
```

---

## Production notes

- Switch `CACHE_STORE` & `QUEUE_CONNECTION` to `redis` and run a Redis server.
- Set `SCOUT_DRIVER` to a real engine (Meilisearch/Algolia) for large datasets.
- Run a queue worker (`php artisan queue:work`) for notifications and async side-effects.
- Keep `GROQ_API_KEY` server-side only; the AI endpoints are rate-limited (`throttle:20,1`).

---

> ⚖️ **Disclaimer:** the AI features generate suggestions for a qualified lawyer's review — they are **not legal advice**, and the IPC has largely been superseded by the **Bharatiya Nyaya Sanhita (BNS), 2023** for offences on/after 1 July 2024, so always verify the applicable code.
