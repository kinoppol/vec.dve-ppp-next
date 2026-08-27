# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

A **PHP 8 / MariaDB 10 web application** implementing the DVE PPP Management System
(Thai vocational-education ↔ industrial-estate partnership tracking, under สอศ./OVEC),
built from the design handoff bundle that still lives in `project/`.

There is no Composer, no build step, and no test suite — it is plain PHP with a hand-rolled
autoloader. All user-facing text is Thai.

## Running it

Runs under XAMPP from the document root, so the app lives at `/vec.dve-ppp-next/`:

```bash
start "" http://localhost/vec.dve-ppp-next/install.php
```

For a quick local run without Apache (base path becomes `/`):

```bash
php -S 127.0.0.1:8765 _devrouter.php
```

`_devrouter.php` is not committed — it is three lines: serve real files, else `require index.php`.

Syntax-check everything before committing:

```bash
find . -name "*.php" -not -path "./project/*" -exec php -l {} \;
```

## Architecture

**Front controller.** `index.php` loads `src/bootstrap.php` (autoloader, error handling,
`src/Support/helpers.php`), connects the DB, starts the session, verifies CSRF on every POST,
then dispatches through `src/Core/Router.php`. If `config/config.php` is missing it redirects
to `install.php`.

**Core** (`src/Core/`) — each class does one thing: `Config` (writes/reads the generated config),
`Database` (PDO singleton; `connect($cfg, asDefault: true)` is how the installer makes an
explicit connection the active one), `Migrator`, `Requirements`, `Auth`, `Session`, `Csrf`,
`Context`, `Settings`, `Url`, `View`, `Router`.

**Views** are plain PHP with one level of layout inheritance (`app`, `public`, `blank`).
`View::partial('partials/topbar')` takes **no data argument** — partials inherit the parent
template's variables from a stack. Never pass `get_defined_vars()` into a view: it also captures
`View::capture()`'s own locals and nests the data array into itself until memory is exhausted.

**Controllers** extend `Controller`, whose `view()` populates the shell (top bar, sidebar, year,
active estate) and force-redirects any user still on their initial password to `password/change`.

### Ambient state every screen filters by

- **Academic year** (พ.ศ.) — `Context::year()`, chosen in the top bar, defaulting to
  `app_settings.academic_year`. The old system hardcoded `'2568'`; never reintroduce a literal.
- **Active estate** — a PVEO office may be responsible for several estates but works one at a
  time. `Context::activeEstateId()` scopes nearly every PVEO query, and `setActiveEstate()`
  refuses estates the office is not assigned to.

## Migrations

`migrations/NNNN_name.sql`, applied in filename order and recorded in `schema_migrations` with a
SHA-256 checksum. A `-- @DOWN` line splits the file into up/rollback sections; everything below
it is the rollback. The `Migrator` honours `DELIMITER` blocks, so routines and triggers work.

Four states appear in the admin UI at `admin/migrations`: `applied`, `pending`,
`drifted` (file edited after it ran — resync accepts the new checksum), and `missing` (row with
no file). Admins can run all pending, run one, roll one back (typing the version to confirm),
and view the SQL first.

MariaDB does not make DDL transactional, so a mid-file failure leaves earlier statements applied.
Each migration must therefore be self-contained. Prefer adding a new migration over editing one
that has already run.

## Database constraints

The production schema is **frozen** — migration 0003 only *adds* columns
(`password_hash`, `must_change_password`, `last_login_at`) and never alters or drops existing
ones, so the legacy system can still read the same tables.

Things that will bite you:

- `provincial_vocational_offices.college_password` is plaintext and equals `college_code` in
  production. `Auth::attemptPveo()` accepts it once, then forces a password reset.
- `topics`/`replies` store `created_at` as **varchar** and images as base64.
- Estates may have a null `province_id` — display as "ไม่ระบุจังหวัด".
- **Progress can exceed 100%** (production shows 230.92%). Render the overflow as an explicit
  warning badge; never clamp the number itself, only the bar width.
- `SyncPveoEstateAssignments(year)` recomputes surveyed counts but must not overwrite quotas
  flagged `is_manual = 1` — that flag exists because several PVEOs can share one estate.
- `utf8mb4_thai_520_w2` is what production uses; `Requirements::pickCollation()` falls back
  through `utf8mb4_unicode_520_ci` → `unicode_ci` → `general_ci` on servers that lack it.

## UI rules (from the design brief, `project/uploads/REDESIGNBRIEF.md` §6)

All tokens live in `assets/css/app.css`; take every color from a CSS variable, never a literal.

- Oxblood brand ramp, primary `--brand-700` in light mode lifting to `--brand-400` in dark.
- **Status colors must stay visually distinct from the brand red** — never dark red for errors —
  and every status carries an icon, never color alone (color-blind users).
- Sarabun throughout including numerals; Thai body text needs `line-height` ≥ 1.6.
- Wide tables need the mobile card variant (`.table-cards` + `.card-list`); PVEO staff enter data
  on phones in the field.
- Print styles are a real requirement (A4 landscape) — users print reports for government
  submission. Use `.no-print` / `.print-only`.
- Buddhist-Era years, `dd/mm/yyyy` dates: use the `thai_date()` helper.

## Design source

`project/` is the original Claude Design handoff and is **reference only** — not part of the app.
`project/DVE PPP Redesign.dc.html` is the prototype (a `.dc.html` dialect: `{{ }}` interpolation,
`<sc-if>` / `<sc-for>`, a `class Component extends DCLogic` script block, all styling inline).
`project/uploads/REDESIGNBRIEF.md` is the authoritative spec for anything not yet built.
`project/_ds/modernist-*/` is an unused design system — the app deliberately does not follow it.
