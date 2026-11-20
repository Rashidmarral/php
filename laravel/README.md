# BuildXact Saudi — Laravel conversion

This is the in-progress Laravel 11 (PHP ^8.2, MySQL) rewrite of the original vanilla-PHP
BuildXact Saudi codebase (the app at the repo root). It's being converted in phases so each
stage is runnable and verifiable rather than landing as one giant untested rewrite.

## Phase 1 — Foundation (done)

- Laravel 11 skeleton, PHP `^8.2`, MySQL as the database driver.
- Full schema ported as Laravel migrations (`database/migrations/2026_01_01_*`), grouped by
  domain (platform, companies, billing, CRM, projects, estimates, invoices, quick estimate,
  takeoffs, suppliers/materials, documents, business setup, consultations/audit). 32 domain
  tables, matching the original schema's final column set (all the incremental `ALTER TABLE`
  additions from `database/migrate.php` are baked directly into the create-table migrations).
- Eloquent models for every table, with relationships.
- Auth: company staff and platform admins share the `users` table/guard, differentiated by
  the `role` column (`owner`/`admin`/`estimator`/`accountant`/`viewer` vs `super_admin`/
  `support_admin`) — exactly like the original app. The client portal is a separate `client`
  guard/provider against the `clients` table.
- RBAC ported as Laravel Gates (`App\Providers\AppServiceProvider::boot()`), matching the
  original `App\Core\Auth::can()` ability matrix, with a `Gate::before` super-admin bypass.
- Route-group middleware (`company.user`, `admin.panel`, `admin.super`, `portal.client`)
  reproducing the original's trial-expired/past-due redirect and cross-panel bounce behavior.
- i18n: all ~1,300 translation keys ported from `app/lang/{en,ar}.php` to Laravel's native
  `resources/lang/{en,ar}.json` flat-key format. A `t()` helper (`app/Support/helpers.php`)
  overlays DB-editable overrides (`translations` table / `Translation` model) on top, exactly
  like the original `Lang::get()` — the admin Translations screen's DB-override behavior is
  preserved. `SetLocale` middleware ports the `?lang=en|ar` + session-persisted switch.
- Seeders (`database/seeders/`) reproduce `database/migrate.php`'s baseline: default plans
  (with feature flags/copy), platform settings, Quick Estimate calculator data, the 12-template
  estimate library (~95 line items), the super admin account, and a demo company — all
  idempotent, safe to re-run.
- Verified end-to-end against a real MySQL instance: `migrate:fresh && db:seed` from empty,
  then login → dashboard for both a company user and a platform admin, in both languages, plus
  every RBAC boundary (guest → login redirect, owner → 403 on `/admin`, admin → bounced from
  `/app` to `/admin`).

## Phase 2 — Admin panel (done)

Every `/admin` screen from the original app, fully ported and verified against real MySQL:
dashboard, reports, usage, audit log, companies (list/show/status/plan-override/profile-edit
incl. document uploads/impersonate/hard-delete/CSV export), ZATCA status view + environment
toggle, plans (CRUD incl. per-feature checkboxes), payments (list/show/edit/apply-plan/
approve/reject/CSV export), admin users, platform settings (all 7 tabs incl. logo/document
uploads), integrations status, pages/CMS (CRUD), translations editor (DB-override CRUD),
Quick Estimate data (regions/foundations/addons CRUD + leads), estimate template library
(CRUD + line items), consultations. RBAC (super_admin full access / support_admin read-only)
enforced via the `admin.super` middleware group exactly like the original's nested route
group. Every route smoke-tested end to end after a clean `migrate:fresh --seed`.

Conversion approach: each original view (already using a global `t()` translation helper and
a handful of small `View::`/`Csrf::` static helpers) was carried over largely as-is — inline
PHP control flow works unchanged inside a `.blade.php` file — with those static calls mapped
to small Laravel-native globals (`e()` is Laravel's own; `money()`, `local()`,
`passwordToggle()` added in `app/Support/helpers.php`) and wrapped in `@extends('layouts.admin')
/ @section('content')`. This kept ~20 view files faithful to the original pixel-for-pixel while
still being genuine Blade templates.

CSR generation and compliance/production CSID issuance on the ZATCA screen were stubbed here
pending the real crypto/API client — fully wired in Phase 4 below.

## Phase 3 — User (company) panel (done)

Every `/app` screen from the original app, fully ported and verified against real MySQL:
dashboard (trial banner, KPIs, expiring-documents alert), projects (change orders, site-diary
photos), clients (portal access), estimates (blank/template-gallery/template-configured
creation, status workflow, client share link), invoices (VAT, retention withholding, WhatsApp
notification), schedule, team (invite with temp password + optional SMTP email), billing (plan
checkout, bank transfer with receipt upload, Moyasar card scaffold), company settings (profile,
logo, legal documents, ZATCA address, self-service password change), business setup (lookup
tables + units of measure + tax rates), compliance documents (expiry tracking), leads CRM
(status pipeline, lead-to-client conversion), suppliers, materials (CSV import + Google Sheets
price sync), documents, integrations (Google Sheets link, per-company Moyasar keys), live
expert consultations (monthly quota), quick estimate (in-app calculator + saved quotes),
business reports (performance/profit/tax/retention), digital takeoff (canvas measurement tool),
and admin impersonation hand-back.

Three self-contained integrations were fully ported rather than stubbed, since none need
external API credentials to build correctly: `WhatsApp` (wa.me deep links + the Cloud API
call), `Mailer` (the raw-socket SMTP client), and `Moyasar` (the REST client for verifying
payments and charging saved cards — only the platform/company *credentials* to actually use it
are still unconfigured in a fresh install). `Billing::activatePlan()` was extracted into
`App\Support\Billing` and now backs both the user-panel checkout flow and the two admin
override paths that duplicated it in Phase 2.

Two systemic bugs were caught by testing every module end-to-end and fixed at the root rather
than per-screen: date-only columns were serializing with a timestamp instead of a plain date,
silently blanking `<input type="date">` fields on every edit (fixed via explicit `date:Y-m-d`
casts); and flash messages were never cleared from the session, so they piled up and
re-displayed on every page load forever (fixed in both layouts). A new `App\Models\Model` base
class now gives every model a consistent `Y-m-d H:i:s` timestamp format by default, matching
the original app's raw MySQL strings, so this class of bug shouldn't recur module-by-module.

PDF export, ZATCA UBL/XML export and Phase 2 submission, and the AI estimate generator were
stubbed here pending their respective engines — all fully wired in Phase 4 below.

## Phase 4 — Business logic, integrations, public site & client portal (done)

- **PDF generation**: `App\Support\Pdf\Pdf` ports the original's dompdf wrapper (chroot'd to the
  project root, no remote resources), with `App\Support\Pdf\ArabicText` (glyph shaping via
  `ar-php`, since dompdf doesn't shape Arabic natively) and `App\Support\Pdf\NumberToWords`. All
  6 visual templates (modern/classic/minimal/bold/elegant/saudi) work for estimates, invoices,
  and quick estimates, in both languages, via `Controller::streamPdf()`.
- **ZATCA Phase 1**: `App\Support\Zatca\Phase1Qr` generates the TLV-encoded, Base64 QR payload
  (seller name, VAT number, timestamp, total, VAT total) rendered as an inline SVG data URI —
  embedded on every invoice PDF and share page once a company's VAT number is set.
- **ZATCA Phase 2**: `App\Support\Zatca\{CsrGenerator,ApiClient,UblInvoice}` port the EC
  (secp256k1) CSR generation, the Fatoora gateway REST client (compliance/production CSID
  issuance, invoice reporting), and UBL 2.1 XML generation with the PIH hash chain.
  `InvoiceController::store()` chains every new invoice (UUID v4, incrementing ICV, SHA-256
  hash linked to the company's previous invoice hash); `xml()`/`submitZatca()` export the UBL
  document and report it to ZATCA's gateway. The admin ZATCA screen's CSR/compliance-CSID/
  production-CSID actions are fully wired (previously stubbed in Phase 2 of this conversion).
- **AI estimate generator**: `App\Support\AiEstimateGenerator` ports both paths — a real
  Anthropic API call when a key is configured, and a zero-config template-keyword-matching
  fallback otherwise, so the feature is useful out of the box.
- **Subscription lifecycle**: `App\Console\Commands\RunDailyTasks` (`php artisan app:daily-tasks`,
  scheduled daily via `routes/console.php`) handles auto-renewal against saved Moyasar card
  tokens with retry/past-due escalation, trial-ending reminders, and compliance-document expiry
  reminders — all idempotent, safe to run more than once a day.
- **Transactional email**: `App\Support\Notifications` ports all 6 triggers (invoice paid,
  estimate signed, trial ending, subscription renewed, renewal failed, compliance doc expiring),
  wired into the cron above and the client-facing share-link actions below.
- **Public marketing site**: home, features, pricing (live from the `plans` table), about,
  contact (with a working contact form), privacy/terms, and admin-managed CMS pages (`/p/{slug}`)
  — all in `App\Http\Controllers\Site\{Home,Page}Controller` + `resources/views/site/*.blade.php`
  under a new `layouts/site.blade.php` (bilingual nav, admin-configurable footer/social links,
  CMS page links).
- **Client portal / share links**: `App\Http\Controllers\Site\ShareController` — public,
  token-based (unauthenticated, 160-bit unguessable tokens) pages for estimate review + canvas
  e-signature, invoice viewing/PDF download with the ZATCA QR embedded, and per-company Moyasar
  card payment with a server-verified payment callback. `App\Http\Controllers\Site\
  QuickEstimateController` ports the public Quick Estimate calculator (region/foundation/add-on
  pricing, PDF export, lead capture).

Every piece above was verified against a real MySQL instance after a clean `migrate:fresh --seed`:
ZATCA hash-chain correctness (verified the chain increments and links correctly across multiple
invoices), a real generated CSR validated with `openssl req -text`, well-formed UBL XML, PDF
structural validation, and a live end-to-end run of the estimate-signing and invoice-payment
flows through the actual public routes.

## Phase 5 — Final verification & demo data (done)

- Full regression pass: every `/app`, `/admin`, and public/share route smoke-tested end to end
  against a clean `migrate:fresh --seed`, across the owner, estimator, and platform-admin roles.
- `DemoCompanySeeder` substantially enriched so a fresh seed produces a realistic, fully
  populated demo company rather than a bare-minimum fixture: 2 projects, 2 clients, 2 estimates,
  2 invoices (one with a real ZATCA hash chain), a full schedule, 2 change orders, project
  photos, 2 suppliers with 5 materials, 2 documents, 3 compliance documents (including one
  expiring soon, to exercise the reminder banner), 3 CRM leads across different pipeline stages,
  a converted quick estimate, a digital takeoff with measurements, a completed consultation, and
  all 5 business-setup lookup tables (building/contact/client types, units of measure, tax
  rates) — plus two additional team members (`estimator@`/`accountant@buildxact-saudi.local`,
  same password) to exercise role-based access with real distinct accounts.

## Local setup

```bash
composer install
cp .env.example .env   # fill in DB_PASSWORD
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeded accounts:
- Platform admin: `admin@buildxact-saudi.local` / `Admin@12345`
- Demo company owner: `owner@buildxact-saudi.local` / `Demo@12345`
- Demo company estimator: `estimator@buildxact-saudi.local` / `Demo@12345`
- Demo company accountant: `accountant@buildxact-saudi.local` / `Demo@12345`

To run the subscription/reminder cron manually: `php artisan app:daily-tasks`.
