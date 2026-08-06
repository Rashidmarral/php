# BuildXact Saudi

A construction management & job costing SaaS platform for Saudi Arabia's contractors,
builders, and developers — inspired by [BuildXact](https://www.buildxact.com/), rebuilt from
scratch in plain PHP for the Saudi market (bilingual Arabic/English, SAR pricing, ZATCA-ready
invoice numbering).

The platform has three parts, all in this repository:

1. **Public marketing website** — home (with how-it-works, testimonials, FAQ), features, pricing
   (SAR, monthly/yearly), a public **Quick Estimate** calculator, about, contact. Bilingual
   (English/Arabic) with full right-to-left layout support.
2. **User panel** (`/app`) — the software each subscribing contractor company uses day to day:
   dashboard, projects, estimates (with line items), invoices (with line items), a **Digital
   Takeoff** tool, client CRM, scheduling, team management, billing/subscription, and company
   settings. Fully multi-tenant: every company only ever sees its own data.
3. **Admin panel** (`/admin`) — the platform owner's back office: revenue/MRR overview, company
   management (activate/suspend), subscription plan management, Quick Estimate pricing data
   (regions/foundation types/add-ons) and leads, payment ledger, admin user management, and
   platform-wide settings (free trial length, VAT rate, etc).

## Tech stack

Plain PHP 8.1+ with a small hand-rolled MVC core (router, PDO models, session auth, PHP-template
views) — no heavy framework dependency, so it runs anywhere PHP runs. Works against **SQLite**
out of the box for zero-setup local development/demo, or **MySQL** for production (switch with
one `.env` value). Three small Composer packages are used: `dompdf/dompdf` for PDF exports,
`khaled.alshamaa/ar-php` for Arabic PDF text shaping, and their transitive dependencies.

## Quick start

```bash
composer install                       # installs dompdf + Arabic PDF text shaping
cp .env.example .env
php database/migrate.php --seed-demo   # creates tables + seeds plans, admin, and a demo company
php -S localhost:8000 -t public public/router.php
```

Visit `http://localhost:8000`.

**Demo logins** (also shown on the login page):

| Role | Email | Password |
|---|---|---|
| Platform admin | `admin@buildxact-saudi.local` | `Admin@12345` |
| Demo company owner | `owner@buildxact-saudi.local` | `Demo@12345` |

The demo company ("Al Rashid Construction Co.") comes pre-loaded with a client, a project, an
accepted estimate, a paid invoice, and a schedule — so the user panel isn't empty on first login.

Drop `--seed-demo` if you only want the platform admin account and default plans (e.g. for a
fresh production database).

## Configuration (`.env`)

| Key | Purpose |
|---|---|
| `DB_DRIVER` | `sqlite` (default, zero setup) or `mysql` |
| `DB_SQLITE_PATH` | Path to the SQLite file when `DB_DRIVER=sqlite` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Used when `DB_DRIVER=mysql` |
| `TRIAL_DAYS` | Fallback trial length if the `trial_days` platform setting hasn't been set yet — once seeded, the admin panel (**Platform Settings**) controls this at runtime |
| `DEFAULT_CURRENCY` | Displayed currency (SAR by default) |
| `PAYMENT_GATEWAY*` | Placeholder for a real Saudi payment gateway integration (see below) |

Beyond `.env`, the platform admin can change the free trial length, VAT rate, site name, and
support contact info live from **Admin → Platform Settings** (`/admin/settings`) — no redeploy
needed.

To run on MySQL: create a database, set `DB_DRIVER=mysql` and the `DB_*` credentials in `.env`,
then run `php database/migrate.php`.

## Project structure

```
app/
  Core/         Router, PDO Database wrapper, Model base class, Auth, View, Csrf, Lang (i18n),
                Settings (DB-backed platform config), Pdf + ArabicText (PDF export)
  Controllers/
    Site/       Marketing site (home, features, pricing, about, contact) + public Quick Estimate
                calculator
    Auth/       Login / registration (register also creates the company + trial subscription)
    User/       Company subscriber panel (/app/...), including Digital Takeoff
    Admin/      Platform admin panel (/admin/...), including Quick Estimate data management
                and Platform Settings
  Models/       One thin PDO model per table (Company, User, Plan, Subscription, Payment,
                Client, Project, Estimate/EstimateItem, Invoice/InvoiceItem, Task,
                QuickEstimate + its Region/Foundation/Addon lookups, Takeoff/TakeoffMeasurement)
  Views/        PHP templates, split by layout: site / auth / user (app) / admin / pdf
  lang/         en.php / ar.php translation strings
  routes.php    All route definitions
  bootstrap.php Composer autoloader + app autoloader + env loader
database/
  migrate.php   Creates all tables (SQLite or MySQL) and seeds plans/admin/settings/
                Quick Estimate pricing data/demo data
public/
  index.php     Front controller
  router.php    Dev-server router (php -S ... public/router.php)
  assets/       Self-hosted CSS/JS (no external CDN dependency)
  uploads/      Takeoff plan images (gitignored — created at runtime)
storage/
  fonts/        Bundled Noto Naskh Arabic font used for Arabic PDF export
```

## Quick Estimate calculator & leads

`/quick-estimate` is a public, no-login-required cost calculator (region, foundation type, total
area, discount, add-ons) with a live-updating summary, matching the "quick estimate" reference
flow: pick a region and foundation type, enter area, toggle add-ons, and the sidebar recalculates
subtotal/discount/VAT/total instantly client-side. Submitting saves the estimate and optional
contact details as a **lead**, viewable and manageable — including a status pipeline
(new/contacted/converted/dismissed) — from **Admin → Quick Estimate Data → Leads**. The regions,
foundation types, and add-on catalog (bilingual names, pricing, PRO tags) are fully editable from
the same admin section, so the platform admin controls every number the public calculator uses
without touching code.

## PDF export

Estimates, invoices, and Quick Estimate results can all be downloaded as PDF (`App\Core\Pdf`,
built on `dompdf`), in the current viewing language and a choice of three visual templates
(Modern / Classic / Minimal) — see `app/Views/pdf/document.php`, the single shared template all
three document types render through. Arabic PDFs are pre-shaped with `khaled.alshamaa/ar-php`
(`App\Core\ArabicText`) before rendering, since dompdf itself doesn't perform Arabic letter
joining or bidi reordering — without this step Arabic text would render as disconnected,
misordered glyphs.

## Digital Takeoff ("AI Takeoff")

`/app/takeoffs` lets a company upload a plan/drawing image, calibrate its scale (click two points
of a known real-world length), then measure directly on the image with three tools — length
(polyline), area (polygon), and count (point markers) — using an HTML canvas overlay
(`app/Views/user/takeoffs/show.php`). Each measurement gets a label and a cost per unit; "Convert
to Estimate" turns every saved measurement into a line item on a new draft estimate in one click.
This mirrors how BuildXact's own takeoff tool works (measuring directly off an uploaded plan)
rather than being blueprint-reading computer vision — see the note below.

## Data model / multi-tenancy

Every subscriber is a **company**. A company has many **users** (the owner plus invited team
members), and owns its own **clients**, **projects**, **estimates** (→ `estimate_items`),
**invoices** (→ `invoice_items`), and **schedule_tasks**. All user-panel queries are scoped by
`company_id`, so companies can never see each other's data. The **platform admin** (`role =
super_admin`, no `company_id`) sits outside any company and manages the platform itself:
companies, plans, and payments.

Billing is modeled with **plans** (Starter / Professional / Enterprise, monthly+yearly SAR
pricing) and **subscriptions** (one company can have a history of subscriptions as it
upgrades/downgrades); **payments** records each charge. `BillingController::upgrade()` is where
plan changes happen today, and it simulates a successful charge — see below for wiring up a real
gateway.

## What's intentionally out of scope for this MVP

BuildXact itself is a mature, years-in-the-making product (detailed takeoffs, supplier price
lists, Gantt scheduling, mobile apps, accounting integrations, etc.). This build focuses on
standing up the three-part platform (marketing site → subscription → user panel; admin panel to
run it) with the core contractor workflow (estimate → project → schedule → invoice) fully
working end to end, so it's a real foundation to extend rather than a mockup. Notable gaps to be
aware of before going to production:

- **Payment gateway**: `PAYMENT_GATEWAY=manual` is a stub. For Saudi Arabia, wire up
  [Moyasar](https://moyasar.com), [HyperPay](https://hyperpay.com), [PayTabs](https://paytabs.com),
  or [Tap](https://tap.company) in `BillingController::upgrade()` (currently it just records a
  "paid" payment immediately).
- **ZATCA e-invoicing**: invoice numbering is sequential per company, but full ZATCA Phase 2
  compliance (QR codes, XML/UBL format, cryptographic stamps) is not implemented.
- **Email**: no transactional email (welcome email, invoice delivery, password reset) is wired
  up yet — plug in a provider (e.g. an SMTP relay) in `AuthController` and `TeamController`.
  Team-member invites currently show the temporary password directly in the UI as a placeholder
  for "send this by email."
- **File uploads / documents**: Digital Takeoff supports plan-image uploads; general document
  attachments on projects/estimates/invoices are not yet implemented.
- **Password reset flow**: not implemented; an admin can only re-invite via the Team page.
- **AI Takeoff**: "Digital Takeoff" is a manual measuring tool (calibrate scale, then click/trace
  on the uploaded plan) — the same interaction model BuildXact's own takeoff feature uses. It
  does not do automatic computer-vision quantity extraction from a blueprint image; wiring that
  up would require a vision-capable AI API and is a natural next step if wanted.
- **Uploaded files aren't per-tenant access-controlled**: takeoff plan images are served as plain
  static files under `public/uploads/`. Fine for a demo/foundation; add an authenticated
  file-serving script (or signed URLs) before treating uploaded plans as sensitive in production.

## Security notes

- Passwords are hashed with `password_hash()` (bcrypt by default in PHP).
- All state-changing POST requests are protected with a per-session CSRF token
  (`App\Core\Csrf`).
- All output is escaped via `View::e()` (`htmlspecialchars`).
- All queries use PDO prepared statements — no raw string interpolation of user input into SQL.
- Every user-panel query is scoped to `Auth::companyId()`, and every resource lookup
  (`findOwned()` in each controller) verifies the record belongs to the logged-in user's company
  before it's shown or modified.
