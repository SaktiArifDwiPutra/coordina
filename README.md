# Coordina — School Facility Scheduling & Permission Document Platform

**Coordina** is a centralized school facility scheduling, extracurricular borrowing coordination, and official permission document platform built for **SMKN 4 Padalarang** (between extracurricular organizations and the Student Council / **MPK Komisi 2**).

---

## What Coordina Solves

Without a structured system, extracurricular facility borrowing relies on informal verbal agreements that cause double bookings and bypassed schedule owners. Coordina replaces verbal agreements with:

- **Recurring Schedule Ownership:** Each facility slot (`Lapangan Utama`, `Aula Serbaguna`, `Lapangan Upacara`, `Studio Musik`, etc.) is tied to an owning organization.
- **Role-Based Organizational Accounts:**
  - `admin_mpk` — Shared MPK Komisi 2 account for initial screening, administrative verification, official letter numbering, master DOCX templates, facilities, fixed schedules, and extracurricular account management.
  - `owner_eskul` — One organizational account per extracurricular for submitting borrowing requests, approving/rejecting incoming requests on their own schedule, and downloading/printing finalized PDFs.
- **Two Structured Approval Workflows:**
  - **Workflow A (Inter-Extracurricular Borrowing — 3 Signatures):** Requester submits + signs → MPK initial screening + signs → Schedule Owner decides + signs → MPK administrative verification & final PDF publication.
  - **Workflow B (MPK Borrowing — 2 Signatures):** MPK submits + signs directly to Schedule Owner → Schedule Owner decides + signs → MPK records & archives the final PDF (no separate MPK screening or second approval).
- **Automated Conflict Prevention & Traceability:** Prevents overlapping approved bookings, enforces mandatory written rejection reasons, and generates immutable revision-tracked PDF permission letters from dynamic DOCX templates.

---

## Repository Structure

```text
coordina/
├── backend/    # Laravel 12 REST API, Sanctum auth, PHPWord DOCX merge, LibreOffice PDF pipeline
├── frontend/   # Nuxt 4 + Vue 3 + Tailwind CSS production web application
├── demo/       # Standalone frontend-only interactive demo (mock data + localStorage, Vercel-ready)
├── docs/       # Business workflow, document lifecycle, technical plan, and audit reports
├── Dockerfile  # Containerized backend runtime (PHP + Node + LibreOffice)
└── railway.json
```

---

## Quick Start

### 1. Interactive Frontend Demo (No Backend Required)

```bash
cd demo
npm install
npm run dev
```

See [demo/README.md](demo/README.md) for static build and Vercel deployment instructions.

### 2. Full Application (`backend/` + `frontend/`)

**Backend (Laravel):**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

**Frontend (Nuxt 4):**
```bash
cd frontend
npm install
npm run dev
```
