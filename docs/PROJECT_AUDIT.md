# Coordina — Comprehensive Project Audit

**Audit Date:** 2026-11-25  
**Repository Root:** `c:\laragon\www\coordina`  
**Scope:** Full-stack read-only audit of `backend/` (Laravel 13) and `frontend/` (Nuxt 4), architecture, database schema, business logic, security, testing, and technical debt.

---

## 1. Executive Summary

Coordina is an early-stage prototype of a school extracurricular facility scheduling and borrowing platform for SMKN 4. While core MVP scaffolding exists—authentication via Laravel Sanctum, facility and fixed-schedule listing, borrow request creation, and a single-page dashboard—the codebase contains **critical security, data integrity, and workflow gaps** that make it unsafe for production or multi-organization coordination without remediation.

### Key Findings at a Glance
- **Critical Authorization & Data Leak Bugs:** `BorrowRequestController::index` leaks all free-slot requests across users when `user.organization_id` is `null`. `BorrowRequestController::updateStatus` allows any ordinary `user` (with matching or `null` `organization_id`) to set *any* arbitrary `status` string (including `'approved'`), bypassing MPK final approval, while locking out the `owner_eskul` role entirely.
- **No Conflict Detection on Borrow Requests or Fixed Schedules:** Overlapping `borrow_requests` are never checked against existing approved/pending bookings, and `FixedScheduleController::store` performs no overlap validation.
- **Broken Facility Creation:** The frontend sends `{ name, type, description }`, and `FacilityController::store` runs `Facility::create($request->all())`, but the `facilities` table has no `description` column, causing a SQL error.
- **Incomplete Business Domain Features:** `messages` and `negotiations` migrations/models are empty stubs; `laravel/reverb`, `laravel-echo`, and `pusher-js` are installed but unused; `rejection_reason`, `priority`, and `document_path` exist in the DB schema but are unimplemented; there is no approval history or notification system.
- **Minimal Test Coverage:** Only Laravel's two default `ExampleTest` files exist (`2 passed`). Zero domain, authorization, or API tests exist.

---

## 2. Project Overview

Coordina is designed to coordinate shared school facilities (e.g., *Lapangan Basket*, *Aula Utama*, *Ruang Rapat MPK*, *Ruang Teori*) between extracurricular organizations (*eskul*) and the Student Council (*MPK*).

- **Current Layout (recently refactored into monorepo folders):**
  - `backend/` — Laravel API server (originally at repo root).
  - `frontend/` — Nuxt 4 SPA/SSR client (originally `coordina-frontend/`).
  - `Dockerfile` & `railway.json` — Root container build targeting Railway deployment.

---

## 3. Technology Stack

### Backend (`backend/composer.json`, `backend/package.json`)
- **Runtime / Framework:** PHP `^8.3`, Laravel Framework `^13.8`
- **Auth & API:** `laravel/sanctum` `^4.0`
- **Real-time (installed, unused):** `laravel/reverb` `^1.0`, `laravel-echo` `^2.3.4`, `pusher-js` `^8.5.0`
- **Tooling / Dev:** `laravel/pail` `^1.2.5` (incompatible with Windows due to `pcntl` requirement), `laravel/pint` `^1.27`, `phpunit/phpunit` `^12.5.12`, `mockery/mockery` `^1.6`, `vite` `^8.0.0`, `tailwindcss` `^4.0.0`

### Frontend (`frontend/package.json`)
- **Framework:** Nuxt `^4.4.5`, Vue `^3.5.34`, Vue Router `^5.0.6`
- **State Management:** Pinia `^3.0.4` (`@pinia/nuxt` `^0.11.3`)
- **UI & Styling:** `shadcn-nuxt` `^2.7.3`, `reka-ui` `^2.9.7`, `lucide-vue-next` `^1.0.0`, `@nuxtjs/tailwindcss` `^6.14.0`, `tailwindcss` `^3.4.19`, `tailwindcss-animate` `^1.0.7`
- **Utilities / Unused Real-time:** `@vueuse/core` `^14.3.0`, `laravel-echo` `^2.3.4`, `pusher-js` `^8.5.0`, `typescript` `^6.0.3`

---

## 4. Project Architecture

### Backend Architecture
- Standard Laravel 13 layout configured in `backend/bootstrap/app.php` (routing `web.php`, `api.php`, `console.php`, `channels.php`, health check `/up`).
- **No Service/Repository Layer or FormRequests:** All validation, authorization checks, and Eloquent queries live directly inside five controllers (`AuthController`, `FacilityController`, `BorrowRequestController`, `FixedScheduleController`, `UserController`).
- **API-First JSON:** Stateless Bearer token authentication via Sanctum (`HasApiTokens` on `User`).
- **CORS (`backend/config/cors.php`):** Allows `https://coordina-two.vercel.app`, plus `http://localhost:3000` and `http://127.0.0.1:3000` (added during local setup). `supports_credentials` is `false`.

### Frontend Architecture
- Nuxt 4 using `app/` directory convention (`frontend/nuxt.config.ts`).
- Runtime config exposes `config.public.apiUrl` from `process.env.NUXT_PUBLIC_API_URL`.
- **Single-page dashboard pattern:** All application logic after login lives in one 637-line component (`frontend/app/pages/dashboard.vue`), calling `$fetch` directly with Bearer tokens from `useAuthStore` (`frontend/app/stores/auth.js`).
- **Token Persistence:** `auth.js` stores `auth_token` in `localStorage` (client-only; no SSR auth or Nuxt route middleware).
- **Data Refresh:** `dashboard.vue` polls `refreshAllData()` every 30 seconds via `setInterval` (`frontend/app/pages/dashboard.vue:362-372`).

---

## 5. Database Schema

Inspecting `backend/database/migrations/` and `backend/app/Models/`:

### Tables & Columns
1. **`users`** (`0001_01_01_000000_create_users_table.php`, `2026_05_18_070000_add_organization_id_to_users_table.php`)
   - `id`, `name`, `email` (unique), `email_verified_at`, `password`, `year`, `major`, `class`, `role` (`enum('user', 'admin_mpk', 'owner_eskul')`, default `'user'`), `organization_id` (nullable FK -> `organizations.id`, `onDelete('set null')`), `remember_token`, `timestamps`.
   - *Model (`User.php`):* Uses `HasApiTokens`. Missing `organization()` `belongsTo` relation.
2. **`organizations`** (`2026_05_15_110157_create_organizations_table.php`)
   - `id`, `name`, `advisor_name` (nullable), `timestamps`.
   - *Model (`Organization.php`):* Completely empty class; no `$fillable` or relationships.
3. **`facilities`** (`2026_05_15_110157_create_facilities_table.php`)
   - `id`, `name`, `type`, `timestamps`. (No `description` column!)
   - *Model (`Facility.php`):* `protected $guarded = []`; has `fixedSchedules()` and `borrowRequests()`.
4. **`fixed_schedules`** (`2026_05_15_110158_create_fixed_schedules_table.php`)
   - `id`, `organization_id` (FK cascade), `facility_id` (FK cascade), `day` (`enum` Monday–Sunday), `start_time` (`time`), `end_time` (`time`), `timestamps`.
   - *Model (`FixedSchedule.php`):* `protected $guarded = []`; has `organization()`, missing `facility()` relation.
5. **`borrow_requests`** (`2026_05_15_110158_create_borrow_requests_table.php`, `2026_05_17_113528_add_owner_to_borrow_requests_table.php`)
   - `id`, `user_id` (FK cascade), `organization_id` (FK cascade, **NOT NULL**), `facility_id` (FK cascade), `owner_organization_id` (nullable FK -> `organizations.id`, `nullOnDelete`), `date` (`date`), `start_time` (`time`), `end_time` (`time`), `reason` (`text`), `priority` (`enum('low','medium','high')`, default `'low'`), `document_path` (nullable), `status` (`string`, default `'pending_mpk'`), `rejection_reason` (nullable `text`), `timestamps`.
   - *Model (`BorrowRequest.php`):* `$fillable` omits `priority`, `document_path`, `rejection_reason`. Missing `user()` `belongsTo` relation.
6. **`messages`** & **`negotiations`** (`2026_05_15_110159_create_messages_table.php`, `2026_05_15_110159_create_negotiations_table.php`)
   - **Empty stub tables** containing only `id` and `timestamps()`. Models `Message.php` and `Negotiation.php` are empty stubs.
7. **`personal_access_tokens`**, **`cache`**, **`jobs`**, **`sessions`**, **`password_reset_tokens`**
   - Standard Laravel tables.

### Seeder (`backend/database/seeders/DatabaseSeeder.php`)
Seeds 4 facilities, 2 organizations (*Eskul Basket*, *MPK / OSIS*), and 3 users (`admin@mpk.com` [`admin_mpk`], `ketua@basket.com` [`owner_eskul`], `siswa@sekolah.com` [`user`]). **None of the seeded users have `organization_id` assigned**, which breaks borrow-request submission for seeded accounts (`organization_id` is NOT NULL on `borrow_requests`).

### Schema Weaknesses
- No composite indexes on `(facility_id, date, status)` in `borrow_requests` or `(facility_id, day)` in `fixed_schedules`.
- `borrow_requests.status` is an unconstrained `string` instead of an enum/check constraint.
- `borrow_requests.organization_id` is non-nullable even though `users.organization_id` is nullable.
- No `audit_logs` / `approval_histories` table to track who approved/rejected and when.

---

## 6. Backend Feature Inventory

| Feature | Endpoints / Files | Status | Notes |
|---|---|---|---|
| **Authentication (Login / User)** | `POST /api/login`, `GET /api/user` (`AuthController.php`, `api.php`) | **IMPLEMENTED BUT NOT VERIFIED** | Token issuance works per Sanctum setup; no automated tests; no rate limiting. |
| **Logout** | `POST /api/logout` (`AuthController.php:37-44`) | **PARTIALLY IMPLEMENTED** | Backend deletes current token, but frontend never calls this endpoint. |
| **List Facilities & Active Schedules** | `GET /api/facilities` (`FacilityController.php:10-24`) | **IMPLEMENTED BUT NOT VERIFIED** | Eager loads `fixedSchedules.organization` and future `approved` `borrowRequests`. |
| **Create Facility** | `POST /api/facilities` (`FacilityController.php:27-45`) | **BROKEN** | Uses `Facility::create($request->all())` while validating `description`; fails on SQL insert if `description` is sent because column doesn't exist. |
| **Delete Facility** | `DELETE /api/facilities/{id}` (`FacilityController.php:48-56`) | **IMPLEMENTED BUT NOT VERIFIED** | Restricted to `admin_mpk`; cascades to schedules and borrow requests. |
| **Create Fixed Schedule** | `POST /api/fixed-schedules` (`FixedScheduleController.php:11-28`) | **PARTIALLY IMPLEMENTED** | Missing `day` enum validation, `end_time > start_time` check, and overlap check. Uses `$request->all()`. |
| **Delete Fixed Schedule** | `DELETE /api/fixed-schedules/{id}` (`FixedScheduleController.php:31-39`) | **IMPLEMENTED BUT NOT VERIFIED** | Restricted to `admin_mpk`. |
| **Submit Borrow Request** | `POST /api/borrow-requests` (`BorrowRequestController.php:12-50`) | **PARTIALLY IMPLEMENTED** | Checks `FixedSchedule` overlap (`->first()` only); fails if user lacks `organization_id`; no overlap check with other `BorrowRequest`s; no time/date sanity validation. |
| **List Borrow Requests** | `GET /api/borrow-requests` (`BorrowRequestController.php:52-74`) | **BROKEN** | `orWhere('owner_organization_id', $user->organization_id)` leaks all `owner_organization_id IS NULL` requests to any user whose `organization_id` is `null`. |
| **Update Borrow Request Status** | `PATCH /api/borrow-requests/{id}/status` (`BorrowRequestController.php:76-110`) | **BROKEN** | No `status` enum validation; `null === null` bug lets org-less users modify `pending_owner` requests; locks out `owner_eskul` role; lets ordinary `user` approve directly without MPK final approval. |
| **User Management (Eskul Accounts)** | `GET/POST /api/users`, `PATCH /api/users/{id}/reset-password`, `DELETE /api/users/{id}` (`UserController.php`) | **PARTIALLY IMPLEMENTED** | Only manages `role = 'user'`; cannot manage `owner_eskul` or `admin_mpk` accounts or `year`/`major`/`class`. |
| **List Organizations** | `GET /api/organizations` (`api.php:61-65`) | **IMPLEMENTED BUT NOT VERIFIED** | Read-only closure; no CRUD endpoints for organizations. |
| **Negotiation & Messaging** | `Message.php`, `Negotiation.php`, migrations | **NOT IMPLEMENTED** | Empty tables and models; zero routes or controllers. |
| **Real-time Broadcasting / Notifications** | `reverb`, `channels.php` | **NOT IMPLEMENTED** | Packages installed; no Events, Notifications, or custom channels implemented. |
| **Document Upload / Priority / Rejection Reason** | `borrow_requests` columns | **NOT IMPLEMENTED** | Schema columns exist; completely unused in API and models. |

---

## 7. Frontend Feature Inventory

| Feature | Location | Status | Notes |
|---|---|---|---|
| **Root Redirect** | `frontend/app/pages/index.vue` | **IMPLEMENTED BUT NOT VERIFIED** | Redirects `/` to `/login`. |
| **Login Page** | `frontend/app/pages/login.vue`, `frontend/app/stores/auth.js` | **PARTIALLY IMPLEMENTED** | Contains dead debug template (`pending`, `data`, `error` undefined at lines 37-47); `loading` never resets on error, freezing the button. |
| **Auth State & Persistence** | `frontend/app/stores/auth.js` | **PARTIALLY IMPLEMENTED** | Persists token in `localStorage`; `logout()` does not call `/api/logout`; no route middleware. |
| **Facility & Weekly Schedule Board** | `frontend/app/pages/dashboard.vue:433-482` | **PARTIALLY IMPLEMENTED** | Only Mon–Fri tabs (missing Sat/Sun); displays approved `borrowRequests` by weekday name without showing actual `req.date` or filtering by current week. |
| **Create Facility Form (MPK)** | `frontend/app/pages/dashboard.vue:556-564` | **BROKEN** | Sends `description` field rejected by DB schema. |
| **Fixed Schedule Management (MPK)** | `frontend/app/pages/dashboard.vue:567-588` | **PARTIALLY IMPLEMENTED** | Only allows Mon–Fri selection; lacks edit capability or conflict feedback. |
| **Submit Borrow Request Form (Eskul)** | `frontend/app/pages/dashboard.vue:505-519` | **PARTIALLY IMPLEMENTED** | No client-side check that `end_time > start_time` or `date >= today`; swallows detailed validation errors if `error.response?._data` differs. |
| **My Submitted Requests Status View (Eskul)** | `frontend/app/pages/dashboard.vue` | **NOT IMPLEMENTED** | Eskul users cannot see their own submitted requests (`pending_mpk`, `pending_owner`, `approved`, `rejected`) in the UI. |
| **Incoming Owner Permission Inbox** | `frontend/app/pages/dashboard.vue:486-503` | **PARTIALLY IMPLEMENTED** | Visible to `owner_eskul` in UI, but `owner_eskul` gets 403 from backend; directly sets status to `approved` instead of returning to MPK. |
| **MPK Approval Workbench** | `frontend/app/pages/dashboard.vue:526-550` | **PARTIALLY IMPLEMENTED** | Can forward to owner or approve/reject, but lacks rejection reason input, filtering, pagination, or conflict warnings against other approved bookings. |
| **Eskul User Account Management (MPK)** | `frontend/app/pages/dashboard.vue:593-632` | **IMPLEMENTED BUT NOT VERIFIED** | Create user, reset password modal, delete user modal. |
| **Debug `/api/test` Fetch** | `frontend/app/pages/dashboard.vue:12-15` | **TECHNICAL DEBT** | Fires unnecessary `GET /api/test` on every dashboard mount. |

---

## 8. User Roles and Permissions

| Role (DB Enum) | Intended Purpose | Actual Backend Enforcement | Actual Frontend UI |
|---|---|---|---|
| `admin_mpk` | Student Council administrator | Full access to facilities, fixed schedules, all borrow requests, and `user` account CRUD (`UserController`). | Checked as `['admin', 'admin_mpk'].includes(role)` (`'admin'` is non-existent). Shows MPK workbench, facility/schedule forms, user management. |
| `owner_eskul` | Extracurricular owner/leader (seeded in `DatabaseSeeder`) | **Locked out** of `UserController`, `FacilityController`, `FixedScheduleController`, and **cannot approve** in `BorrowRequestController::updateStatus` (checks `$user->role === 'user'`). | Treated identically to `user` (`!['admin', 'admin_mpk']`), showing approval buttons that fail with `403`. |
| `user` | Ordinary member / borrower | Can submit requests; **wrongly allowed** to approve/reject `pending_owner` requests if `organization_id` matches (or both are `null`); sees leaked `null`-owner requests in `index()`. | Shows borrow form and `pending_owner` inbox if `organization_id` matches. |

---

## 9. Business Workflow Analysis

Evaluating the core coordination scenario (*Futsal wants to borrow Friday slot normally owned by Basketball, requiring formal request and authorized MPK approval*):

1. **Who can submit requests?** Any authenticated user—even `admin_mpk` or users without an `organization_id` (which crashes SQL). There is no validation that the requester belongs to an organization.
2. **Who can approve or reject requests?**
   - `admin_mpk` can set **any** status at **any** time (even bypassing `pending_owner` when an owner organization exists).
   - Any ordinary `user` in the owning organization can transition `pending_owner` directly to `approved`, **bypassing final MPK authorization**! Meanwhile, `owner_eskul` is blocked from doing so.
   - An informal/ordinary member (`role = 'user'`) with the same `organization_id` *can* approve, violating the requirement that ordinary members cannot grant official approval.
3. **Is a pending request treated as an approved booking?**
   - `FacilityController::index` correctly filters `where('status', 'approved')` when displaying official schedules.
   - However, because `updateStatus` has no status-transition state machine or validation, invalid statuses can be injected.
4. **Are approved bookings reflected in the official schedule?**
   - Partially: `FacilityController::index` loads future `approved` requests, and `dashboard.vue` renders them under weekday tabs—**but without dates**, causing future-week bookings to visually collide with this week's schedule.
5. **Is there an activity or approval history?**
   - **No.** Only `created_at` and `updated_at` exist on `borrow_requests`. Neither `approved_by`, `owner_approved_by`, `approved_at`, nor an audit log exists.
6. **Are users informed when request statuses change?**
   - **No.** There are no notifications (email, in-app, or WebSocket), and non-admin requesters cannot even view their own submitted request statuses on the dashboard.

---

## 10. Booking and Schedule Conflict Analysis

- **Fixed Schedule vs. Borrow Request:** `BorrowRequestController::store` checks `FixedSchedule` overlap using `start_time < $request->end_time && end_time > $request->start_time` and attaches the **first** matching `FixedSchedule`'s `organization_id` as `owner_organization_id` (`BorrowRequestController.php:26-38`). If two fixed schedules somehow overlapped, only the first owner is recorded.
- **Borrow Request vs. Borrow Request:** **Completely unimplemented.** Two organizations (e.g., Futsal and Choir) can submit overlapping requests for the same facility, date, and time, and MPK can approve **both** without any error or warning.
- **Fixed Schedule vs. Fixed Schedule:** `FixedScheduleController::store` does not check for overlapping fixed schedules on the same facility and day.
- **Race Conditions / Transactions:** Neither `store()` nor `updateStatus()` uses `DB::transaction()` or row-level locking (`lockForUpdate()`).
- **Time Ordering:** Neither `BorrowRequestController::store` nor `FixedScheduleController::store` validates `end_time > start_time`.

---

## 11. Security Assessment

| Severity | Finding | Location |
|---|---|---|
| **CRITICAL** | **Authorization bypass & arbitrary status injection:** `updateStatus` accepts any string for `status`; `null === null` lets org-less users update `pending_owner` requests with `owner_organization_id = null`; ordinary `user`s can directly set `status = 'approved'` without MPK sign-off. | `backend/app/Http/Controllers/BorrowRequestController.php:76-110` |
| **CRITICAL** | **Cross-user data leak in `BorrowRequestController::index`:** When `$user->organization_id` is `null`, `orWhere('owner_organization_id', null)` returns every user's free-slot borrow requests. | `backend/app/Http/Controllers/BorrowRequestController.php:67-70` |
| **HIGH** | **Mass assignment via `$request->all()` with `$guarded = []`:** `FacilityController::store` and `FixedScheduleController::store` pass unvalidated `$request->all()` into unguarded models. | `FacilityController.php:39`, `FixedScheduleController.php:25`, `Facility.php:12`, `FixedSchedule.php:12` |
| **HIGH** | **Unrevoked Sanctum tokens on logout & infinite token lifetime:** Frontend `auth.logout()` never calls `POST /api/logout`, and `config/sanctum.php:53` has `'expiration' => null`. | `frontend/app/stores/auth.js:61-68`, `backend/config/sanctum.php:53` |
| **MEDIUM** | **Debug route exposing internal DB hostname:** `GET /api/dns-test` is publicly accessible without auth and resolves a Neon PostgreSQL host. | `backend/routes/api.php:23-25` |
| **MEDIUM** | **No login rate limiting:** `POST /api/login` has no throttle middleware. | `backend/routes/api.php:15` |
| **LOW** | **Role string mismatch:** Frontend checks `'admin'` alongside `'admin_mpk'`, though `'admin'` does not exist in DB schema. | `frontend/app/pages/dashboard.vue` (multiple lines) |

---

## 12. Testing Assessment

- **Backend (`php artisan test`):** Executed on 2026-11-25 via `BypassSandbox` (required by Laragon PHP NTFS checks in sandbox):
  - **Result:** `2 passed` (`tests/Unit/ExampleTest.php`, `tests/Feature/ExampleTest.php`), `2 assertions`, `2424ms`.
  - **Coverage:** Effectively **0%** of Coordina's actual business logic, authentication, authorization, or conflict checks.
- **Database Migrations (`php artisan migrate:status`):** All 12 migrations ran cleanly.
- **Frontend (`npm run build`):** Exited with code 0 (`✨ Build complete!`, Client built in 11.91s / 769 modules, Server built in 2.20s / 116 modules, Nitro total size 4.38 MB, with Windows symlink `EPERM` warnings in `.output/server/node_modules`). `nuxi typecheck` timed out in sandbox because `vue-tsc` is not installed in `frontend/package.json`.
- **Frontend Tests:** None exist (no Vitest/Playwright/Cypress setup).

---

## 13. Known Issues

1. **Broken Facility Creation (`POST /api/facilities`):** Frontend submits `description`, backend passes `$request->all()` to `Facility::create()`, DB lacks `description` column -> HTTP 500.
2. **Broken Seeded Borrow Requests:** Seeded users lack `organization_id`, while `borrow_requests.organization_id` is NOT NULL -> HTTP 500 on submit.
3. **Login Button Permanent Freeze on Error (`login.vue:18-33`):** `loading.value` is never reset to `false` when `auth.login()` throws.
4. **Dead Template Variables (`login.vue:37-47`):** References undefined `pending`, `data`, `error`.
5. **Windows Dev Script Failure (`backend/composer.json:47`):** `composer run dev` runs `php artisan pail` with `--kill-others`, which immediately crashes on Windows (`pcntl` missing) and kills `php artisan serve`, `queue:listen`, and `vite`.
6. **`owner_eskul` Role Broken:** Seeded in `DatabaseSeeder.php`, defined in `users.role` enum, shown approval UI in `dashboard.vue`, but rejected with HTTP 403 by `BorrowRequestController::updateStatus`.

---

## 14. Technical Debt

- **Monolithic `dashboard.vue` (637 lines):** Mixes 4 modals, 5 API fetchers, 7 mutation handlers, polling, and 3 role views in one file with no reusable components or composables.
- **Unused Dependencies & Stubs:** `laravel/reverb`, `laravel-echo`, `pusher-js`, `messages` table/model, `negotiations` table/model, `priority`/`document_path`/`rejection_reason` columns.
- **Missing Eloquent Relationships & Mass-Assignment Discipline:** `User::organization()`, `BorrowRequest::user()`, `FixedSchedule::facility()`, and `Organization` relations/fillables are missing; `Facility` and `FixedSchedule` use `$guarded = []`.
- **Hardcoded Polling & Debug Requests:** 30-second `setInterval` polling and `/api/test` fetch on every dashboard load.
- **Inconsistent Error Handling:** Mix of `error.response?._data?.message`, `err?.data?.message`, and swallowed errors showing generic alerts.

---

## 15. Recommended Fixes

### Immediate (Critical / High)
1. **Fix `BorrowRequestController` Authorization & State Machine:**
   - Validate `status` against allowed transitions (`pending_mpk` -> `pending_owner` | `approved` | `rejected`; `pending_owner` -> `owner_approved` | `rejected` -> `admin_mpk` final `approved`).
   - Require `$user->organization_id !== null` before matching `owner_organization_id` in both `index()` and `updateStatus()`.
   - Enforce role checks using `owner_eskul` (or explicit org-leader permission) instead of ordinary `user`, and require final `admin_mpk` approval before a booking becomes `approved`.
2. **Implement Conflict Detection & Transactions:**
   - Reject overlapping `BorrowRequest`s against existing `approved` (and optionally `pending_*`) requests on the same `facility_id` + `date` inside `DB::transaction()`.
   - Validate `end_time > start_time`, `date >= today`, and valid `day` enum values; add overlap checks to `FixedScheduleController::store`.
3. **Fix Facility Creation & Mass Assignment:**
   - Either add `description` to `facilities` or remove it from validation/frontend; replace `$request->all()` with `$request->validated()`.
4. **Fix Auth Lifecycle & Remove Debug Routes:**
   - Call `POST /api/logout` in `auth.js`, configure `sanctum.expiration`, remove `/api/dns-test` and `/api/test`, and add login rate limiting.
5. **Fix `login.vue` & `dashboard.vue` Bugs:**
   - Add `finally { loading.value = false }` and remove dead debug template in `login.vue`.
   - Display actual `req.date` on approved bookings, add Sat/Sun tabs, and add a "My Requests" status table for non-admin users.

---

## 16. Recommended Development Priorities

1. **Phase A — Stabilize Security, Auth & Core Data Integrity:** Fix `BorrowRequestController` permissions, `null` `organization_id` leaks, `FacilityController` 500 bug, `login.vue` loading freeze, and `logout` token revocation.
2. **Phase B — Implement Real Conflict Detection & Formal Approval Workflow:** Define explicit state transitions (`pending_mpk` -> `pending_owner` -> `owner_approved` -> `approved`/`rejected`), overlap checks, `rejection_reason`, and an `approval_histories` audit trail.
3. **Phase C — Refactor & Add Automated Tests:** Extract FormRequests, Policy/Service classes, and Nuxt composables/components; write PHPUnit Feature tests covering every role, conflict, and edge case.
4. **Phase D — Complete Missing Coordination Features:** Build `negotiations`/`messages` or notifications (leveraging the already-installed Reverb/Echo stack or clean REST + polling) once the core booking state machine is rock-solid.

