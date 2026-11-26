# Coordina — Final Functional Audit (Pre-UI/UX Styling)

**Audit Date:** 2026-11-26  
**Branch:** `develop/coordina-revival`  
**Base Commit:** `bf5fe05` (`feat(phase-4): implement document generation, PDF lifecycle, revision management, and archive`) + post-audit & Phase 5 implementation (uncommitted)  
**Primary Requirement Baselines:**
- [PROJECT_AUDIT.md](file:///c:/laragon/www/coordina/docs/PROJECT_AUDIT.md)
- [BUSINESS_WORKFLOW.md](file:///c:/laragon/www/coordina/docs/BUSINESS_WORKFLOW.md)
- [DOCUMENT_WORKFLOW.md](file:///c:/laragon/www/coordina/docs/DOCUMENT_WORKFLOW.md)
- [TECHNICAL_IMPLEMENTATION_PLAN.md](file:///c:/laragon/www/coordina/docs/TECHNICAL_IMPLEMENTATION_PLAN.md)

> **Verification Scope Note:** Automated backend verification executed all 37 PHPUnit tests (496 assertions) on SQLite `:memory:` with `Storage::fake('local')` and mocked `PdfConverterInterface` stubs. Real `soffice` (LibreOffice) DOCX → PDF rendering could **not** be executed locally because LibreOffice is not installed on the Windows host and the Docker Desktop daemon is not running. Frontend behavior is verified via static inspection and `npm run build` compilation; no automated browser/E2E tests exist.

---

## 1. Requirement vs Implementation Matrix

| Domain / Requirement | Classification | Evidence & Notes |
|---|---|---|
| **Auth & Session Lifecycle** (`POST /api/login`, `POST /api/logout`, `GET /api/user`, login rate limit, Sanctum token expiration) | **PASS** | `AuthController.php`, `routes/api.php:19` (`throttle:login`), `config/sanctum.php:53` (`1440` mins), `frontend/app/stores/auth.js:61-85`. Verified in `Phase1SecurityAndStabilizationTest`. |
| **Two-Role Model & Legacy `user` Blocking** (`admin_mpk`, `owner_eskul`; legacy `user` denied) | **PASS** | `AuthController::login()` rejects non-`['admin_mpk', 'owner_eskul']` with `403`. Migration `2026_11_25_000002` upgrades only rows with non-null `organization_id`. Verified in PHPUnit. |
| **Fresh Database Seeding (`DatabaseSeeder.php`)** | **PASS** | `DatabaseSeeder.php:27-37` now explicitly sets `'is_mpk' => true` on `MPK / OSIS` and `'is_mpk' => false` on `Eskul Basket`, linking `admin@mpk.com` and `ketua@basket.com`. Verified in `Phase4PdfVerificationAndArchiveTest::test_database_seeder_sets_is_mpk_flag_and_supports_mpk_free_slot_signing`. |
| **Facility & Fixed Schedule Management + Conflict Prevention** | **PASS** | `FacilityController`, `FixedScheduleController`, `ScheduleConflictService`. Validates `end_time > start_time`, weekday enum, overlapping fixed schedules (`409`), overlapping approved bookings (`409` with `lockForUpdate()`), and multi-owner schedule overlaps (`422`). |
| **7-Day Schedule Board & Exact-Date Overrides** | **PARTIAL** | `ScheduleBoard.vue` supports all 7 days (`Monday`–`Sunday`) and displays exact `override.date` / `req.date` badges, but shows all future approved bookings per weekday without a week-picker filter as proposed in `TECHNICAL_IMPLEMENTATION_PLAN.md` §D.3. |
| **Workflow A (Inter-Eskul: 3 Signatures + MPK Screening + Verification)** | **PASS** | Enforces Requester Stage 1 → MPK Screening + Stage 2 → Owner Lending Decision + Stage 3 → MPK Verification & PDF finalization. Verified end-to-end in `Phase2`, `Phase3`, and `Phase4` tests. |
| **Workflow B (MPK Borrow: 2 Signatures, No Separate Screening)** | **PASS** | `admin_mpk` submission skips screening (`STATUS_PENDING_OWNER`), applies MPK Stage 1, awaits affected owner approval + Stage 2, and finalizes with 2-signature layout (omitting bottom 3rd block). Verified in PHPUnit. |
| **Free-Slot Borrowing (`owner_eskul` 2-sig & `admin_mpk` 1-sig)** | **PASS** | Both paths assign `letter_number`, require explicit signatures (`[1, 2]` for eskul, `[1]` for MPK), render clean DOCX layouts without fake empty signature blocks, and trigger PDF generation. *(Note: MPK `free_slot` assumes `organizations.is_mpk = true`.)* |
| **Rejection & Mandatory Written Reasons** | **PASS** | `ScreenBorrowRequest` and `OwnerDecisionRequest` require `rejection_reason` when `decision === 'reject'`. Rejected requests record history, notify requester, and never generate `letter_number`, DOCX, or PDF (`404`/`422`). |
| **Document Revisions (`Needs Correction`) & Signature Invalidation** | **PASS** | `BorrowWorkflowService::requestCorrection()` logs `DocumentRevision`, invalidates prior signatures (`is_valid = false`), bumps `current_revision`, notifies requester, and `revise()` re-checks conflicts, resets artifacts, and supports deferred Stage 1 `/sign`. |
| **Master DOCX Templates & Dynamic Org Branding/Snapshots** | **PASS** | `MasterTemplateGeneratorService`, `DocumentTemplateController`, `DocumentGenerationService::resolveActiveTemplate()`, and `TemplateManagerPanel.vue` support `shared`, `workflow_a`, and `workflow_b` template scopes, placeholder validation, and immutable signature snapshots (`Phase3` & `Phase4` tests). |
| **PDF Generation & Finalization Semantics** | **PARTIAL** | `DocumentGenerationService`, `GenerateFinalPermissionPdfJob`, `LibreOfficePdfConverter`, `LetterCounter`, and `borrow_requests_letter_number_unique` enforce `finalized` only when valid `%PDF-` bytes exist, clean up orphan files on failure, and handle sync/async failures (`pending_pdf_generation` / `pdf_generation_failed`). **However, real LibreOffice rendering has not been executed locally** (only mocked `PdfConverterInterface` tested). |
| **Archive, Print/Preview & Authorized Downloads** | **PASS** | `GET /api/documents/archive` lists only finalized official PDFs scoped by organization/MPK; `/document/pdf` (`?inline=1` or attachment) and `/document/docx` enforce `BorrowRequestPolicy::view` + `Cache-Control: no-store, private`; frontend includes popup-blocked download fallback. *(Archive query is unpaginated.)* |
| **Notifications & Activity History** | **PASS** | `ApprovalHistory` logs all transitions/signatures/corrections/revisions; database `notifications` notify MPK and affected/requesting owners (`GET /api/notifications`, `PATCH /api/notifications/{id}/read`). |
| **Organization Management (MPK CRUD for Orgs)** | **PARTIAL** | `GET /api/organizations` exists and `UserController` manages `owner_eskul` accounts (`create`, `reset-password` with token revocation, `delete`). There are no UI/API endpoints to create/edit/delete `organizations` records. |
| **Nuxt Route Middleware (`auth.ts`, `role.ts`, `guest.ts`)** | **PASS** | Implemented client-safe Nuxt route middleware (`frontend/app/middleware/auth.ts`, `role.ts`, `guest.ts`) wired into `dashboard.vue` (`auth`, `role` with `['admin_mpk', 'owner_eskul']`) and `login.vue` (`guest`), compiled cleanly into Nitro SSR/client chunks. *(No automated E2E browser run.)* |
| **Phase 5 Account Lifecycle Features** (`must_change_password`, `password_changed_at`, random password generator + copy handover, `toggle-active`, forced password change on first login, `POST /api/change-password`) | **PASS** | Implemented in `2026_11_26_000009_add_account_lifecycle_fields_to_users_table`, `AccountLifecycleService`, `EnsureAccountReady` middleware, `AuthController::changePassword`, `UserController::toggleActive`, `MpkMasterDataPanel.vue`, and `dashboard.vue`. Verified in `Phase5AccountLifecycleTest`. |
| **Legacy Stubs (`messages`, `negotiations`, `laravel/reverb`, `document_path`)** | **NOT IMPLEMENTED** | Empty DB tables/models and unused WebSocket dependencies preserved from initial prototype; dashboard uses 30-second polling. |

---

## 2. Verified Working Features

1. **Authentication & Security Boundaries:** Throttled `POST /api/login`, Sanctum `1440`-minute expiration, `POST /api/logout` token revocation, password reset revoking all active tokens (`UserController::resetPassword`), and removal of legacy `/api/test` & `/api/dns-test` routes.
2. **Role & Cross-Organization Isolation:** `BorrowRequestPolicy` (`view`, `screen`, `ownerDecision`, `finalize`), `OrganizationProfileController::streamSignature` (`403` for other orgs, `no-store, private` headers), and `BorrowRequestController::index()` prevent `null === null` bypasses and cross-organization leaks.
3. **Conflict Detection & Locking:** `ScheduleConflictService` + `DB::transaction()` + `lockForUpdate()` block overlapping `fixed_schedules` and overlapping `approved` bookings (`409 Conflict`), validate `end_time > start_time` and non-past dates, and flag multi-owner fixed-schedule overlaps (`422`).
4. **Workflow A, Workflow B, & Free-Slot State Machines:** Strict status synchronization between `borrow_requests.status` and `borrow_documents.document_status`, explicit signature stage enforcement via `SignatureService` + DB unique constraint (`doc_sigs_doc_rev_stage_unique`), and immutable signatory/image snapshots.
5. **Template Management, Revision Cycle & Sequential Letter Numbering:** Default SMKN 4 Padalarang DOCX generation, custom DOCX placeholder validation, `requestCorrection` → `needs_correction` → `revise` cycle with artifact reset, atomic `letter_counters` per year, and `borrow_requests_letter_number_unique` DB constraint.
6. **PDF Lifecycle, Archive & Frontend Build:** `DocumentGenerationService` + `GenerateFinalPermissionPdfJob` + `BorrowDocumentController` (`archive`, `downloadPdf`, `downloadDocx`, `regeneratePdf`) + modular Vue dashboard (`ScheduleBoard`, `OwnerInboxPanel`, `BorrowRequestForm`, `MyRequestsPanel`, `MpkWorkbenchPanel`, `DocumentArchivePanel`, `TemplateManagerPanel`, `OrganizationProfilePanel`, `MpkMasterDataPanel`).

---

## 3. Incomplete or Missing Features

1. **Real LibreOffice Execution in Local/CI Environment:** `LibreOfficePdfConverter` has not been exercised against an actual `soffice` binary; layout, Indonesian typography, dual logos, and single-page fit in Linux (`fonts-liberation`/`fonts-dejavu`) remain unverified at runtime.
2. **Deferred Phase 5 Account Lifecycle:** `must_change_password`, `password_changed_at`, random password generator UI, `is_active` toggle, and `PUT /api/facilities/{id}` are not implemented yet (per roadmap).
3. **Optional Post-MVP Polish:** `PUT /api/facilities/{id}` (edit facility inline), week-picker filtering on `ScheduleBoard.vue`, and pagination on `archive` / `borrow-requests`.
4. **Week-Picker Filter on `ScheduleBoard.vue` & Pagination on `archive` / `borrow-requests`:** Functional for MVP scale, but long-term datasets will benefit from week filtering and API pagination.
5. **Organization CRUD:** Organizations are seeded/queried read-only (`GET /api/organizations`).
6. **Frontend Automated Tests:** No Vitest or Playwright/Cypress tests exist in `frontend/`.

---

## 4. Bugs and Security Issues

### Resolved Post-Audit Bugs
1. **`DatabaseSeeder.php` `is_mpk` flag:** Fixed in [DatabaseSeeder.php](file:///c:/laragon/www/coordina/backend/database/seeders/DatabaseSeeder.php#L27-L37) (`'is_mpk' => true` for `MPK / OSIS`) and covered by automated PHPUnit test.
2. **Hardcoded `workflow_scope = 'shared'` in `TemplateManagerPanel.vue`:** Fixed in [TemplateManagerPanel.vue](file:///c:/laragon/www/coordina/frontend/app/components/dashboard/TemplateManagerPanel.vue#L19-L135) with explicit `shared` / `workflow_a` / `workflow_b` scope selection and scope badge display.
3. **Missing Nuxt Route Middleware:** Added [auth.ts](file:///c:/laragon/www/coordina/frontend/app/middleware/auth.ts), [role.ts](file:///c:/laragon/www/coordina/frontend/app/middleware/role.ts), and [guest.ts](file:///c:/laragon/www/coordina/frontend/app/middleware/guest.ts) wired into `dashboard.vue` and `login.vue`.

### Security Issues Assessed
- **No Critical or High active security vulnerabilities** were found in the implemented endpoints:
  - Protected storage (`local` disk under `storage/app/private`) is never exposed publicly; signatures, merged DOCX, and finalized PDFs stream only after auth + policy checks with `Cache-Control: no-store, private`.
  - Finalized PDFs and SHA-256 checksums are immutable against template or organization profile updates.
  - **Operational Caveat (Low/Info):** Because `admin_mpk` is a single shared account per `BUSINESS_WORKFLOW.md` §3, individual Komisi 2 operators cannot be distinguished at the auth layer beyond the active MPK profile snapshot and `signed_at` timestamp. Additionally, `local` disk storage on ephemeral containers (Railway) requires a persistent volume or S3 disk in production.

---

## 5. Test and Build Results (Observed During This Audit)

| Check | Command | Result | Scope & Conditions |
|---|---|---|---|
| **Database Migrations** | `cd backend; php artisan migrate:status` | **PASS** (22/22 `[Ran]`) | Local MySQL `coordina` database through batch 7 (`2026_11_26_000008`). |
| **API Route Table** | `cd backend; php artisan route:list --path=api` | **PASS** (37 API routes) | Confirmed zero debug routes (`/api/test`, `/api/dns-test` absent). |
| **Backend PHPUnit Suite** | `cd backend; php -d extension=pdo_sqlite -d extension=zip vendor/bin/phpunit --testdox` | **PASS** (`OK (37 tests, 496 assertions)`, `21.31s`) | SQLite `:memory:`, `Storage::fake('local')`, mocked `PdfConverterInterface`. Covers Example (2), Phase 1 (9), Phase 2 (4), Phase 3 (8), Phase 4 (11), Phase 5 (3). |
| **Frontend Nuxt Production Build** | `cd frontend; npm run build` | **PASS** (Exit code `0`, `5.39 MB` Nitro bundle) | Client (`41.97s`, 2500 modules) + SSR (`4.12s`, 132 modules) + Nitro server built cleanly with `auth`, `role`, and `guest` middleware chunks. |
| **Real LibreOffice PDF Conversion** | N/A | **NOT RUN** | `soffice` binary not installed locally; Docker Desktop daemon not running. |

---

## 6. Remaining Items Before UI/UX Styling / Production

1. **Execute 1 Real Docker/LibreOffice Smoke Test:**
   Build [Dockerfile](file:///c:/laragon/www/coordina/Dockerfile) in a Docker-enabled environment and verify one real Workflow A (3-signature) and one Workflow B (2-signature) DOCX → PDF output visually to confirm single-page fit, logo sizing, and Indonesian font rendering.
2. **Verify Existing Dev/Staging DB `is_mpk` Flag:**
   If an existing database was seeded prior to the `DatabaseSeeder.php` fix, ensure `MPK / OSIS` has `is_mpk = 1`.
3. **Optional Post-MVP Polish:**
   - Add pagination or limit controls to `BorrowDocumentController::archive()` and `BorrowRequestController::index()` before high-volume school usage.
   - Add week-picker filtering on `ScheduleBoard.vue`.

