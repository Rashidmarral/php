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

## Not yet ported (later phases)

- Admin panel controllers/views (companies, plans, payments, reports, settings, translations
  editor UI, quick-estimate data admin, estimate template admin, pages/CMS, audit log, usage,
  consultations).
- User panel controllers/views (projects, clients, estimates, invoices, billing, business
  setup, schedule, team, leads, quick estimate, reports, takeoffs, consultations,
  integrations, materials, suppliers, documents, settings) and the client portal.
- Business logic: multi-template PDF generation, ZATCA Phase 1 QR + Phase 2 XML/UBL signing,
  Moyasar payment gateway, WhatsApp integration, the AI estimate generator, subscription
  renewal cron, transactional emails, and the public marketing site.

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
