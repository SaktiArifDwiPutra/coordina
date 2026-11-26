# Coordina — Technical Implementation Plan

**Document Date:** 2026-11-25  
**Status:** Architecture & Implementation Roadmap (Pre-Implementation)  
**Project:** Coordina (Extracurricular Scheduling, Borrowing Coordination & Permission Document Platform)  
**Related Docs:** [PROJECT_AUDIT.md](file:///c:/laragon/www/coordina/docs/PROJECT_AUDIT.md) | [BUSINESS_WORKFLOW.md](file:///c:/laragon/www/coordina/docs/BUSINESS_WORKFLOW.md) | [DOCUMENT_WORKFLOW.md](file:///c:/laragon/www/coordina/docs/DOCUMENT_WORKFLOW.md)

> **Limitation Note:** No example `.docx` permission letter is present in the workspace at the time of writing. Exact XML structure, font names, margins, and table cell dimensions of the school's official template must be verified once an example `.docx` is provided.

---

## A. Current Architecture Assessment

### 1. Existing Laravel Backend Architecture (`backend/`)
- **Framework:** PHP `^8.3`, Laravel `^13.8`, Laravel Sanctum `^4.0`, Laravel Reverb `^1.0` (installed, unused).
- **Routing (`backend/routes/api.php`):** 14 application API routes under `/api/*`, plus public debug endpoints (`GET /api/test`, `GET /api/dns-test`).
- **Controllers:** All validation, authorization checks, and database queries reside directly inside 5 fat controllers (`AuthController`, `FacilityController`, `BorrowRequestController`, `FixedScheduleController`, `UserController`). No `FormRequest`, `Policy`, `Service`, `Event`, or `Job` classes exist.
- **Broadcasting/Queues:** `config/queue.php` defaults to `database` (`jobs` table migrated); `config/reverb.php` and `routes/channels.php` are untouched defaults.

### 2. Existing Nuxt Frontend Architecture (`frontend/`)
- **Framework:** Nuxt `^4.4.5`, Vue `^3.5.34`, Pinia `^3.0.4`, Tailwind CSS `^3.4.19`, `shadcn-nuxt` `^2.7.3`, `reka-ui`.
- **Pages & State:** 3 pages (`index.vue`, `login.vue`, `dashboard.vue`) and 1 store (`stores/auth.js`).
- **Monolithic UI:** `dashboard.vue` (637 lines) mixes 4 modals, 5 API fetchers, 7 mutation handlers, 30-second `setInterval` polling, and all role views in one component. No route middleware (`middleware/`) or API composable layer exists.
- **Known Broken Flows:** `login.vue` never resets `loading` on error and references undefined debug variables; `dashboard.vue` sends `description` on facility creation (causing SQL 500), lacks Sat/Sun tabs and booking dates, and lacks a "My Requests" status view.

### 3. Existing Authentication & Role Implementation
- **Token Auth:** Sanctum Bearer tokens persisted in `localStorage` (`auth.js`). `logout()` does not call `POST /api/logout`, and `config/sanctum.php` sets `'expiration' => null`.
- **Roles:** DB `users.role` enum defines `['user', 'admin_mpk', 'owner_eskul']` (default `'user'`).
- **Critical Authorization Bugs:**
  - `BorrowRequestController::updateStatus()` checks `$user->role === 'user'` (locking out `owner_eskul`), allows `null === null` bypass, and accepts arbitrary `status` strings without state-transition checks.
  - `BorrowRequestController::index()` leaks `owner_organization_id IS NULL` requests when `$user->organization_id` is `null`.
  - `UserController` only creates/lists/deletes `role = 'user'`.

### 4. Existing Database Schema
- 12 migrations (`backend/database/migrations/`), all currently run (`php artisan migrate:status`).
- Key tables: `users`, `organizations`, `facilities`, `fixed_schedules`, `borrow_requests`, `personal_access_tokens`, plus empty stub tables `messages` and `negotiations`.
- Key schema gaps: `facilities` lacks `description`; `borrow_requests` has unused `priority`, `document_path`, `rejection_reason` and unconstrained `status` string; `users` lacks `organization()` relation; `Organization` model is empty; no signature, template, document, revision, audit, or notification tables exist.

### 5. Deployment & Storage Configuration
- **Container (`Dockerfile`):** `dunglas/frankenphp:php8.3` with `pdo_pgsql` + Node.js/npm; runs `php artisan serve` on `$PORT`. **No LibreOffice or font packages are installed** for DOCX-to-PDF conversion.
- **Storage (`backend/config/filesystems.php`):** `local` (`storage/app/private`), `public` (`storage/app/public`), and `s3` disks configured. Note that Railway containers have ephemeral local storage unless a persistent volume or S3-compatible bucket is attached.

---

## B. Target Database Architecture

### Design Principles
- **Non-Destructive Evolution:** Do **not** drop legacy columns (`users.year`, `users.major`, `users.class`) or forcibly strip `'user'` from the DB enum in ways that risk data loss. Instead, migrate legitimate org rows (`role = 'user'` + non-null `organization_id`) to `owner_eskul`, deprecate legacy columns safely, and enforce the 2-role model (`admin_mpk`, `owner_eskul`) in application code/policies.
- **Separation of Concerns:** Separate **business status** (`borrow_requests.business_status`), **document/verification status** (`borrow_documents.document_status`), **signature events** (`document_signatures`), and **revision snapshots** (`document_revisions`).

### 1. Updates to Existing Tables (Additive Migrations)

- **`organizations`**
  - Add: `slug` (string, unique), `is_mpk` (boolean, default `false`), `logo_path` (nullable string), `chairperson_name` (nullable string), `chairperson_title` (nullable string), `chairperson_id_number` (nullable string), `signature_image_path` (nullable string), `signature_updated_at` (nullable timestamp).
  - Purpose: Stores MPK and each extracurricular's branding logo and reusable signatory profile.

- **`users`** *(Organizational Accounts)*
  - Add: `must_change_password` (boolean, default `false`), `password_changed_at` (nullable timestamp), `is_active` (boolean, default `true`). *(Supports future account lifecycle requirements without altering existing behavior until enabled.)*
  - Data migration: Link seeded/existing MPK account to the `MPK / OSIS` organization (`is_mpk = true`); convert any existing `role = 'user'` accounts that represent an organization (`organization_id IS NOT NULL`) to `owner_eskul`.
  - Constraint consideration: Enforce at most one active `owner_eskul` account per organization at the application layer (and optionally via a partial unique index on PostgreSQL `WHERE role = 'owner_eskul' AND is_active = true`; avoid a global `UNIQUE(organization_id)` if multiple MPK admins ever share the MPK org or during soft-deactivation).

- **`facilities`**
  - Add: `description` (nullable text), `is_active` (boolean, default `true`).

- **`fixed_schedules`**
  - Add index: `INDEX (facility_id, day, start_time, end_time)` for fast overlap queries.

- **`borrow_requests`**
  - Add:
    - `workflow_type` (`string` / enum: `'inter_eskul'`, `'mpk_borrow'`, `'free_slot'`, indexed).
    - `business_status` (`string`, indexed; e.g., `submitted`, `pending_mpk_screening`, `rejected_by_mpk`, `pending_owner`, `rejected_by_owner`, `approved`, `cancelled`). *(Kept alongside or migrated safely from `status` with a data backfill.)*
    - `letter_number` (nullable string, unique when assigned).
    - `letter_date` (nullable date).
    - `mpk_screened_at` / `owner_decided_at` / `finalized_at` (nullable timestamps).
  - Add composite index: `INDEX (facility_id, date, business_status)`.
  - Ensure `BorrowRequest::$fillable` excludes protected status fields (managed only via Service methods).

### 2. New Tables

1. **`document_templates`**
   - `id`, `name` (string), `workflow_scope` (`'workflow_a'`, `'workflow_b'`, or `'shared'`), `file_path` (string, private disk), `placeholders_json` (json), `version` (integer), `is_active` (boolean), `uploaded_by_user_id` (FK `users`), `created_at`, `updated_at`.
   - Purpose: Versioned master DOCX templates. Replacing a template inserts a new version and marks older versions `is_active = false`, preserving historical template references.

2. **`borrow_documents`**
   - `id`, `borrow_request_id` (FK `borrow_requests`, unique 1:1 with active record), `document_template_id` (FK `document_templates`), `current_revision` (integer, default `1`), `document_status` (`string`, indexed: `draft`, `waiting_mpk_signature`, `waiting_owner_signature`, `pending_mpk_verification`, `needs_correction`, `finalized`, `voided`), `final_pdf_path` (nullable string, private disk), `final_pdf_sha256` (nullable string, integrity hash), `verified_by_user_id` (nullable FK `users`), `verified_at` (nullable timestamp), `published_at` (nullable timestamp), `created_at`, `updated_at`.

3. **`document_signatures`**
   - `id`, `borrow_document_id` (FK `borrow_documents`), `revision_number` (integer), `signer_role` (`'requester_eskul'`, `'mpk'`, `'owner_eskul'`), `stage_order` (`1`, `2`, `3`), `organization_id` (FK `organizations`), `signed_by_user_id` (FK `users`), `signer_name_snapshot` (string), `signer_title_snapshot` (string), `signer_id_number_snapshot` (nullable string), `signature_image_path_snapshot` (string), `is_valid` (boolean, default `true` — set `false` if invalidated by `Needs Correction`), `signed_at` (timestamp), `created_at`.
   - Index: `INDEX (borrow_document_id, revision_number, stage_order)`.

4. **`document_revisions`**
   - `id`, `borrow_document_id` (FK `borrow_documents`), `revision_number` (integer), `triggered_by_user_id` (FK `users`), `reason` (text), `snapshot_json` (json — stores booking/letter fields at that revision), `created_at`.
   - Purpose: Traceable correction history whenever MPK flags `Needs Correction` (prevents silent post-signature edits).

5. **`approval_histories`** *(Audit Trail)*
   - `id`, `borrow_request_id` (FK `borrow_requests`), `actor_user_id` (FK `users`), `actor_role` (string), `action` (string: `submitted`, `signed_requester`, `screened_accepted`, `screened_rejected`, `signed_mpk`, `owner_approved`, `owner_rejected`, `signed_owner`, `flagged_correction`, `verified_published`, `cancelled`), `from_status` (nullable string), `to_status` (string), `note` (nullable text), `metadata_json` (nullable json), `created_at`.
   - Index: `INDEX (borrow_request_id, created_at)`.

6. **`notifications`**
   - Standard Laravel `notifications` table (`uuid id`, `type`, `notifiable` morphs, `data` text/json, `read_at`, `created_at`, `updated_at`) for in-app alerts on screening, owner decisions, correction requests, and finalized PDFs.

---

## C. Backend Architecture

### 1. Layering Strategy
Move all domain logic out of controllers into clean, single-responsibility layers:
- **`app/Http/Requests/` (Form Requests):** Strict input validation (`StoreBorrowRequest`, `ScreenBorrowRequest`, `OwnerDecisionRequest`, `VerifyDocumentRequest`, `StoreFacilityRequest`, `StoreFixedScheduleRequest`, `StoreEskulAccountRequest`, `UploadTemplateRequest`, `UpdateOrgProfileRequest`).
- **`app/Policies/` (Authorization):** `BorrowRequestPolicy`, `BorrowDocumentPolicy`, `FacilityPolicy`, `FixedSchedulePolicy`, `OrganizationPolicy`, `UserPolicy`.
- **`app/Services/` (Domain Logic):**
  - `ScheduleConflictService` — Overlap checks for `FixedSchedule` vs. `FixedSchedule`, `BorrowRequest` vs. `FixedSchedule` (returning *all* overlapping owners, not just `->first()`), and `BorrowRequest` vs. `approved` bookings inside `DB::transaction()`.
  - `BorrowWorkflowService` — State-machine transitions for Workflow A and Workflow B, recording `ApprovalHistory` on every transition.
  - `SignatureService` — Validates signatory profile completeness, captures immutable identity/image snapshots into `document_signatures`, and enforces strict signature ordering (1 → 2 → 3 for Workflow A; 1 → 2 for Workflow B).
  - `DocumentGenerationService` — Merges master DOCX placeholders + dynamic logos + signature snapshots via PHPWord, triggers PDF conversion, stores SHA-256 hash, and manages `Needs Correction` revisions.
  - `AccountLifecycleService` — Manages `owner_eskul` creation, password resets, and Sanctum token revocation.
- **`app/Jobs/`:** `GenerateFinalPermissionPdfJob` (queued job to render DOCX → PDF asynchronously or via dedicated worker).

### 2. Planned API Endpoints (RESTful, Sanctum-Protected)

| Domain | Method & Route | Purpose |
|---|---|---|
| **Auth** | `POST /api/login` *(throttled)*, `POST /api/logout`, `GET /api/user` | Login, revoke current token on logout, return user + `organization` |
| **Organizations & Profiles** | `GET /api/organizations`, `GET /api/organization/profile`, `PUT /api/organization/profile`, `POST /api/organization/assets` | List orgs; update chairperson identity, logo, and signature image |
| **Accounts (MPK)** | `GET /api/eskul-accounts`, `POST /api/eskul-accounts`, `PATCH /api/eskul-accounts/{id}/reset-password`, `PATCH /api/eskul-accounts/{id}/toggle-active` | Manage `owner_eskul` accounts (`admin_mpk` only) |
| **Facilities & Fixed Schedules** | `GET/POST/PUT/DELETE /api/facilities`, `POST/DELETE /api/fixed-schedules` | Facility & recurring schedule management with overlap checks |
| **Borrow Requests (Workflow)** | `GET /api/borrow-requests`, `GET /api/borrow-requests/{id}`, `POST /api/borrow-requests` | Submit Workflow A (`owner_eskul`) or Workflow B (`admin_mpk`); scoped visibility |
| | `POST /api/borrow-requests/{id}/screen` | `admin_mpk` screens Workflow A (accept + MPK sign, or reject with reason) |
| | `POST /api/borrow-requests/{id}/owner-decision` | Affected `owner_eskul` approves + signs, or rejects with reason |
| | `POST /api/borrow-requests/{id}/verify` | `admin_mpk` verifies Workflow A or finalizes Workflow B -> triggers PDF |
| | `POST /api/borrow-requests/{id}/request-correction` | `admin_mpk` flags `Needs Correction` with note |
| | `PUT /api/borrow-requests/{id}/revise` | Authorized party updates fields during `Needs Correction` (creates revision) |
| **Documents & Templates** | `GET/POST /api/document-templates`, `GET /api/borrow-requests/{id}/document/preview`, `GET /api/borrow-requests/{id}/document/pdf` | Upload/list master DOCX; stream draft preview or final PDF securely |
| **Notifications** | `GET /api/notifications`, `PATCH /api/notifications/{id}/read` | In-app workflow notifications |

*(Remove `/api/test` and `/api/dns-test` immediately.)*

---

## D. Frontend Architecture (`frontend/`)

Replace the monolithic `dashboard.vue` with Nuxt route middleware, composables, and role-focused pages/components:

1. **Auth & Middleware (`app/middleware/auth.ts`, `role.ts`, `stores/auth.js`):**
   - Call `POST /api/logout` on logout; reset `loading` properly in `login.vue`; remove dead debug template (`pending`, `data`, `error`) and `/api/test` call.
   - Route guards redirecting unauthenticated users to `/login` and separating MPK vs. Eskul views.
2. **API & Domain Composables (`app/composables/`):**
   - `useApiFetch.ts` (centralized `$fetch` wrapper handling `Authorization` header, 401 auto-logout, and Laravel 422 validation error extraction).
   - `useFacilities.ts`, `useBorrowRequests.ts`, `useDocuments.ts`, `useOrganizationProfile.ts`.
3. **Separate Role Experiences:**
   - **Shared Schedule Board:** 7-day tabs (Mon–Sun) + week/date picker displaying recurring schedules and exact-date approved overrides.
   - **Extracurricular Dashboard (`owner_eskul`):**
     - **Submit Borrow Request:** Form with client-side time/date checks + explicit **"Confirm Chairperson Signature"** step before submitting.
     - **My Requests & Document Tracker:** Live view of submitted requests, current step (Screening / Owner / Verification / Finalized), rejection reasons, correction prompts, and **Download/Print PDF** button.
     - **Incoming Owner Requests:** Requests affecting this org's schedule (from Workflow A and Workflow B) with explicit **"Approve & Sign"** and **"Reject (with mandatory reason)"** modals.
     - **Organization Profile:** Manage logo, chairperson name/title/ID, and signature image.
   - **MPK Dashboard (`admin_mpk`):**
     - **Screening Queue (Workflow A):** Review reason/conflicts; **"Accept & Apply MPK Signature"** or **"Reject with Reason"**.
     - **MPK Borrow Request (Workflow B):** Submit request + **Apply MPK Signature** directly to affected owner.
     - **Verification & Finalization Queue:** Review 3-signature Workflow A documents (preview + signature checklist), **"Verify & Publish PDF"** or **"Flag Needs Correction"**; finalize 2-signature Workflow B approvals.
     - **Final Document Archive:** Searchable list of all finalized PDFs, rejections, and audit trails.
     - **Master Template Manager, Facility/Fixed-Schedule Manager, & Eskul Account Manager** (with future password copy/reset UI hooks).

---

## E. Document Generation Architecture

### 1. Recommended Libraries & Conversion Pipeline
- **DOCX Template Merging:** **`phpoffice/phpword`** (`\PhpOffice\PhpWord\TemplateProcessor`)
  - Supports `${text_placeholder}` substitution (`setValue`) and image replacement (`setImageValue` with explicit width/height constraints) for MPK logo, requesting eskul logo, and signature PNGs.
- **DOCX-to-PDF Conversion:** **Headless LibreOffice (`soffice --headless --convert-to pdf`)** invoked via `Symfony\Component\Process\Process` with strict timeout and isolated temp directory.
  - *Why LibreOffice over pure PHP (Dompdf/TCPDF):* Pure PHP HTML-to-PDF libraries cannot read `.docx` templates directly; LibreOffice preserves Word tables, headers, logos, and side-by-side signature blocks faithfully from the single master DOCX.
  - *Container requirement:* Add `libreoffice-writer` and required TrueType fonts (e.g., `fonts-liberation`, `fonts-dejavu`, or the template's exact font family) to [Dockerfile](file:///c:/laragon/www/coordina/Dockerfile).

### 2. Supporting Workflow A (3 Signatures) vs. Workflow B (2 Signatures)
- **Workflow A (3 Signatures):** Top row places `${mpk_signature}` (left) and `${requester_signature}` (right) side by side; bottom row places `${owner_signature}` centered below.
- **Workflow B (2 Signatures):** Two viable template options (to finalize once sample DOCX is inspected):
  - *Option 1 (Recommended for single-template policy):* Keep one master DOCX or a dedicated `workflow_b` template variant in `document_templates` (`workflow_scope = 'workflow_b'`). If using a single master DOCX, either use a clean 2-party section or omit the 3rd signatory block via PHPWord `cloneBlock`/`deleteBlock` (`${block_third_signatory}...${/block_third_signatory}`).
  - *Option 2:* Allow MPK to upload one Workflow A master DOCX and one optional Workflow B master DOCX in `document_templates`.

### 3. Immutability, Revisions & Worker Strategy
- **Worker vs. Synchronous API Request:** Running LibreOffice takes ~1–4 seconds and consumes significant CPU/RAM. **Use a queued Laravel Job (`GenerateFinalPermissionPdfJob` on the `database` queue)** triggered upon final verification/recording, returning a `generating` / `finalized` status to the UI (or synchronous fallback only in local dev if `QUEUE_CONNECTION=sync`). This prevents HTTP worker timeouts under concurrent requests.
- **Immutability:** Once `final_pdf_path` is written and `final_pdf_sha256` is recorded, the PDF is never regenerated when a master template is replaced or when an organization updates its chairperson/signature profile.
- **Revisions (`Needs Correction`):** Editing fields creates a new `document_revisions` snapshot, increments `current_revision`, marks prior `document_signatures` as `is_valid = false`, and requires explicit re-signing before final PDF generation.

---

## F. Security and Reliability

1. **Shared `admin_mpk` Account Limitations:** Because Komisi 2 shares one `admin_mpk` credential set, individual MPK members cannot be cryptographically distinguished at the account level. Mitigate by: (a) recording the configured MPK representative identity snapshot on `document_signatures`, (b) requiring explicit confirmation clicks before signing, and (c) rotating the shared password when Komisi 2 membership changes.
2. **Organization-Level Authorization:** Enforce `BorrowRequestPolicy` and `BorrowDocumentPolicy` strictly on every endpoint. Never match `organization_id` when `null`.
3. **Private Storage & Authorized Streaming:** Store master DOCX templates, signature PNGs, and finalized PDFs on the **`local` (`storage/app/private`)** or private **`s3`** disk—never in `public/storage`. Serve downloads/previews only via authenticated controller endpoints (`Storage::disk(...)->download(...)`) after policy checks.
4. **Token Lifecycle & Revocation:** Configure `sanctum.expiration` (e.g., 1440 minutes / 24h), call `POST /api/logout` from frontend, throttle `POST /api/login`, and revoke all user tokens (`$user->tokens()->delete()`) on password reset.
5. **Race Conditions & State Transitions:** Wrap booking submission, conflict checks, and final approval in `DB::transaction()` with `lockForUpdate()` on overlapping `borrow_requests` / `fixed_schedules`. Reject invalid state transitions with HTTP `422`/`409`.

---

## G. Testing Strategy

Target comprehensive PHPUnit Feature & Unit tests (`backend/tests/`) plus frontend E2E/component checks:

1. **Authentication & Session Tests:** Valid/invalid login, login throttling, token revocation on logout, password reset revoking prior tokens.
2. **Role & Cross-Organization Isolation Tests:** `admin_mpk` vs. `owner_eskul` permissions; legacy `user` role denied; Eskul A cannot view, sign, or decide Eskul B's incoming requests; `null` `organization_id` cannot leak or mutate requests.
3. **Workflow A End-to-End Tests:** Submit (with Signature 1) → MPK screen reject (with reason, no PDF) → MPK screen accept + Signature 2 → Owner reject (with reason, no PDF) → Owner approve + Signature 3 → MPK verify (`Needs Correction` invalidating signatures + revision log) → MPK verify & publish (`Finalized / Published`).
4. **Workflow B End-to-End Tests:** `admin_mpk` submit (with Signature 1, skipping screening) → Owner reject vs. Owner approve + Signature 2 → MPK administrative recording & final PDF archival (verifying no 3rd signature or 2nd approval required).
5. **Document & File Access Tests:** Template upload placeholder validation; PDF generation output non-empty & SHA-256 recorded; replacing master template does not alter archived PDFs; unauthorized org cannot download another org's private PDF or signature image (HTTP 403).
6. **Schedule Conflict & Concurrency Tests:** Overlapping `fixed_schedules` rejected; overlapping `borrow_requests` against `approved` bookings rejected; `end_time <= start_time` and past dates rejected; multi-owner overlaps handled cleanly.

---

## H. Phased Implementation Roadmap

### Phase 1 — Security Stabilization & Critical Bug Fixes
- **Objectives:** Close critical security holes and fix broken existing endpoints before building new features.
- **Backend Changes:** Remove `/api/test` & `/api/dns-test`; add login rate limiting; fix `BorrowRequestController::index` `null` leak & `updateStatus` `user`/`null === null` bypass; replace `$request->all()` with `FormRequest` validation; fix `FacilityController::store` (`description` column mismatch); add `User::organization()` & `Organization` relationships; set `sanctum.expiration`.
- **Frontend Changes:** Fix `login.vue` loading freeze & dead template; call `POST /api/logout` in `auth.js`; remove `/api/test` fetch and `'admin'` role alias.
- **Database Changes:** Additive migration adding `facilities.description`; update `DatabaseSeeder.php` to link seeded accounts to organizations and replace `user` with `owner_eskul`.
- **Dependencies:** None.
- **Acceptance Criteria:** Existing broken flows pass manual & PHPUnit feature tests; `owner_eskul` can log in and act only for its own org; unauthorized transitions return 403/422.
- **Risks:** Existing DB rows with `role = 'user'` need safe data migration to `owner_eskul` before removing `user` access.

### Phase 2 — Core Workflow State Machine, Conflict Detection & Audit Trail
- **Objectives:** Implement Workflow A & B business state machines, conflict prevention, rejection reasons, and audit logging.
- **Backend Changes:** Create `BorrowWorkflowService`, `ScheduleConflictService`, `BorrowRequestPolicy`, and `FormRequest` classes; enforce Workflow A (screening → owner → verification) and Workflow B (MPK request → owner → recording) transitions; require written `rejection_reason`; record `approval_histories`.
- **Frontend Changes:** Split `dashboard.vue` into modular components (`ScheduleBoard`, `MpkWorkbench`, `EskulRequests`, `OwnerInbox`); add 7-day tabs, exact dates on overrides, "My Requests" tracker, and rejection reason modal.
- **Database Changes:** Migrate `borrow_requests` (`workflow_type`, `business_status`, composite index); create `approval_histories` and `notifications` tables.
- **Dependencies:** Phase 1 complete.
- **Acceptance Criteria:** Double-bookings are blocked atomically; Workflow A and B transitions are enforced end-to-end with full `approval_histories` logs; all feature tests pass.
- **Risks:** Concurrent pending requests policy (block vs. allow until finalized) must be confirmed before finalizing conflict checks.

### Phase 3 — Organization Profiles, Signatures & Master DOCX Template
- **Objectives:** Enable organization branding, signature assets, master DOCX template upload, and explicit multi-stage signing.
- **Backend Changes:** Install `phpoffice/phpword`; build `SignatureService` and template validation; add private storage & authorized streaming endpoints for logos, signatures, and templates.
- **Frontend Changes:** Add Organization Profile settings (logo, chairperson identity, signature upload), explicit "Confirm & Sign" step in submission/screening/owner-approval modals, and MPK Template Manager.
- **Database Changes:** Extend `organizations` with branding/chairperson/signature columns; create `document_templates`, `borrow_documents`, `document_signatures`, and `document_revisions` tables.
- **Dependencies:** Phase 2 complete + school's sample `.docx` template inspected.
- **Acceptance Criteria:** MPK can upload/validate a master DOCX; signatures record immutable actor/timestamp/identity snapshots; silent post-signature edits are blocked.
- **Risks:** Unclean Word XML in school's DOCX template could break placeholder replacement without template preparation.

### Phase 4 — PDF Generation, Verification Queue & Archive
- **Objectives:** Render final PDFs for Workflow A (3 signatures) and Workflow B (2 signatures), support `Needs Correction` loops, and archive finalized PDFs.
- **Backend Changes:** Add LibreOffice + fonts to `Dockerfile`; implement `DocumentGenerationService` and `GenerateFinalPermissionPdfJob`; add MPK verification/correction endpoints and authorized PDF download endpoint; configure persistent/S3 storage for production.
- **Frontend Changes:** Add MPK Verification Queue (preview + "Verify & Publish" / "Needs Correction"), revision edit flow, and Final Document Archive with PDF download/print.
- **Database Changes:** None (uses Phase 3 schema).
- **Dependencies:** Phase 3 complete; PDF infrastructure & letter numbering decision finalized.
- **Acceptance Criteria:** Completed Workflow A (3 sigs) and Workflow B (2 sigs) generate immutable, verifiable PDFs; rejected requests never generate PDFs; template replacement leaves archived PDFs intact.
- **Risks:** Ephemeral Railway filesystem without S3/volume would lose generated PDFs on redeploy.

### Phase 5 — Future Account Lifecycle Enhancements *(Post-MVP / Deferred)*
- **Objectives:** Implement leadership-handover password lifecycle features.
- **Backend & Frontend Changes:** Random password generator + copy-to-clipboard handover UI, token revocation on password reset (`$user->tokens()->delete()`), and forced password change on first login (`must_change_password`).
- **Database Changes:** Add `must_change_password` and `password_changed_at` to `users`.
- **Dependencies:** Phases 1–4 stable.

