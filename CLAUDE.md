# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

A **PHP 8 / MariaDB 10 web application** implementing the DVE PPP Management System
(Thai vocational-education ↔ industrial-estate partnership tracking, under สอศ./OVEC),
built from the design handoff bundle that still lives in `project/`.

There is no Composer, no build step, no test suite, and **no JavaScript files** — it is plain
PHP with a hand-rolled autoloader, server-rendered forms, and one stylesheet. All user-facing
text is Thai.

`README.md` is the *design-tool handoff* README that shipped with the bundle, not documentation
for this app — it instructs a coding agent to build the prototype from scratch. That work is
done; ignore its instructions and read this file instead.

## Running it

Runs under XAMPP from the document root, so the app lives at `/vec.dve-ppp-next/`:

```bash
start "" http://localhost/vec.dve-ppp-next/install.php
```

For a quick local run without Apache (base path becomes `/`):

```bash
/c/xampp/php/php.exe -S 127.0.0.1:8765 _devrouter.php
```

`_devrouter.php` is not committed — it is three lines: serve real files, else `require index.php`.

`php` is not on PATH; the XAMPP binary is `C:\xampp\php\php.exe` (PHP 8.2). Syntax-check
everything before committing — this prints nothing when the tree is clean:

```bash
find . -name "*.php" -not -path "./project/*" -print0 | xargs -0 -n1 /c/xampp/php/php.exe -l | grep -v "^No syntax errors"
```

**Installer.** `install.php` is standalone (it requires `src/bootstrap.php`, never `index.php`)
and walks five steps: requirements → database → migrate → admin → done. It writes
`config/config.php` and `storage/installed.lock`; once the lock exists the installer demands the
admin password to re-enter. Deleting `storage/installed.lock` is the documented reset path.

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
Controller docblocks cite the numbered sections of `project/uploads/REDESIGNBRIEF.md`
(`/** 5.6 รายงานความคืบหน้า */`) — follow the number back to the spec before changing a screen.

### Adding a screen

Four edits, in this order, or the page will 404, be publicly reachable, or render without chrome:

1. Route in `index.php` — `$router->get('pveo/thing', [XController::class, 'thing'])`.
   Patterns take `{placeholders}`; handlers are `[Class, 'method']`.
2. Controller action — **first line is the guard**: `Auth::requireAdmin()` / `requirePveo()` /
   `requireLogin()`. The router has no middleware layer, so an action with no guard is public.
3. View under `views/`, rendered via `$this->view('pveo/thing', [...])` (not `View::display`,
   which skips the shell).
4. Sidebar entry in `Controller::sidebar()`, and pass a matching `'nav' => 'thing'` so the item
   highlights.

### Conventions that bite

- **Every POST needs `Csrf::field()`.** `Csrf::verify()` runs globally in `index.php` and answers
  419 otherwise — that includes logout, the theme toggle, and the year/estate switchers.
- **Never write a literal URL path.** The app is deployed in a sub-folder, so links go through
  `url('admin/estates')` / `asset('css/app.css')`, and `Url::redirect()` / `Url::back()` for
  responses. `Url::withQuery()` preserves the current query while changing one parameter.
- **Queries go through the `Database` statics** — `all()`, `first()`, `value()`, `int()`,
  `run()`. All are prepared; there is no query builder and no ORM.
- **No JavaScript.** `assets/` holds `css/app.css` and nothing else, and no view contains a
  `<script>` tag. Interactivity is POST forms; the most JS anything gets is
  `onchange="this.form.submit()"` on the top-bar pickers. Dark mode is a server-set cookie
  (`dveppp_theme`) rendered as `<html data-theme>`, not a client toggle. Keep it that way.
- **Public share links reuse the admin renderers read-only.** `AdminController::renderEstates()`
  and `renderUploads()` take `($template, $layout, $readOnly)`; `PublicController::shared()`
  calls them with the `public` layout after validating a `share_links` token. A new admin screen
  that should be shareable follows the same split rather than growing a public duplicate.
- Settings live in `app_settings` behind `Settings::get/int/bool`; `Settings::DEFAULTS` is the
  authoritative key list and `Settings::put()` invalidates the static cache.

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

Current set: `0001` reference tables → `0002` core domain → `0003` auth hardening + app tables →
`0004` two views (`v_estate_progress`, `v_pveo_progress`), two procedures
(`SyncPveoEstateAssignments`, `RecalcEnterpriseCompleteness`), three triggers on `surveys` /
`enterprise_completeness` → `0005` seed reference data.

Four states appear in the admin UI at `admin/migrations`: `applied`, `pending`,
`drifted` (file edited after it ran — resync accepts the new checksum), and `missing` (row with
no file). Admins can run all pending, run one, roll one back (typing the version to confirm),
and view the SQL first. Migration actions are blocked while impersonating.

MariaDB does not make DDL transactional, so a mid-file failure leaves earlier statements applied.
Each migration must therefore be self-contained. Prefer adding a new migration over editing one
that has already run.

### Sharing a database with the legacy system

**This app is deployed into the legacy system's own database.** Both run against the same
tables at the same time, so every migration is additive-only and must never touch an object the
legacy system owns. `Requirements::legacyData()` detects an existing legacy dataset and
`install.php` makes the operator confirm before installing into it.

Rules that keep the two systems from fighting:

- **Everything this app creates carries a `ppp_` / `v_ppp_` / `Ppp` prefix.** The legacy system
  already owns `v_estate_progress`, `SyncPveoEstateAssignments`, `RecalcEnterpriseCompleteness`
  and `enterprise_completeness`; 0004 creates its own `v_ppp_estate_progress`,
  `PppSyncPveoEstateAssignments`, `PppRecalcEnterpriseCompleteness` and writes scores to
  `ppp_enterprise_completeness` instead. Never add a `DROP ... IF EXISTS` for an unprefixed name.
- **This app creates no triggers.** Production already has `after_survey_insert`,
  `after_survey_delete` and `after_enterprise_completeness_update`. Replacing them destroys
  legacy behaviour, and adding same-event triggers under new names double-counts, because
  MariaDB 10.2.3+ runs every trigger registered for an event.
- **Recompute, never increment.** `PppRecountSurveyed` recounts `surveyed_count` from `surveys`
  rather than adding one, so the result is correct whether or not a legacy trigger already
  incremented it, and calling it repeatedly is a no-op. `SurveyController::refreshCounters()` is
  the only caller. Any new counter must follow the same rule.
- **Rollbacks must not destroy legacy-owned data.** 0001 and 0002 are deliberately irreversible —
  their `@DOWN` holds only an explanatory comment, because a `DROP TABLE` there would let an
  admin wipe the legacy dataset from a web button. A down section with no executable statement
  counts as non-reversible (`Migrator::isReversible()`), so the UI shows it as such and
  `rollback()` refuses. Don't "fix" that by adding statements.
- **Seed only into empty tables.** 0005 fills `geographies` / `vec_region` / `college_types`
  only when they are empty (`JOIN (SELECT COUNT(*) ...) ON n = 0`), so a shared database keeps
  the legacy reference rows untouched. Its rollback deletes `app_settings` rows only.
- 0003 alters two legacy tables, and that is the one accepted intrusion: it *adds* nullable /
  defaulted columns with `ADD COLUMN IF NOT EXISTS` and leaves `college_password` in place, so
  legacy logins and legacy `INSERT`s that name their columns keep working.

Verified against a simulated legacy database carrying its own views, routines and triggers: the
full migration set changes nothing except adding those columns and the app's own objects.

## Database constraints

The production schema is **frozen** — migration 0003 only *adds* columns
(`password_hash`, `must_change_password`, `last_login_at`) and never alters or drops existing
ones, so the legacy system can still read the same tables.

Things that will bite you:

- `provincial_vocational_offices.college_password` is plaintext and equals `college_code` in
  production. `Auth::attemptPveo()` accepts it once, then forces a password reset. Admin rows
  may hold bcrypt, md5, sha1, or plaintext; `Auth::verifyAgainst()` covers all four and rehashes
  to `PASSWORD_DEFAULT` on successful login.
- `topics`/`replies` store `created_at` as **varchar** and images as base64.
- Estates may have a null `province_id` — display as "ไม่ระบุจังหวัด".
- **Progress can exceed 100%** (production shows 230.92%). Render the overflow as an explicit
  warning badge; never clamp the number itself, only the bar width (`min(100, $p)`).
- `SyncPveoEstateAssignments(year)` recomputes surveyed counts but must not overwrite quotas
  flagged `is_manual = 1` — that flag exists because several PVEOs can share one estate.
- `utf8mb4_thai_520_w2` is what production uses; `Requirements::pickCollation()` falls back
  through `utf8mb4_unicode_520_ci` → `unicode_ci` → `general_ci` on servers that lack it.

## UI rules (from the design brief, `project/uploads/REDESIGNBRIEF.md` §6)

All tokens live in `assets/css/app.css`; take every color from a CSS variable, never a literal.

- Oxblood brand ramp, primary `--brand-700` in light mode lifting to `--brand-400` in dark.
- **Status colors must stay visually distinct from the brand red** — never dark red for errors —
  and every status carries an icon, never color alone (color-blind users). Use the
  `--ok` / `--warn` / `--err` / `--info` variables with their `-bg` companions.
- Sarabun throughout including numerals; Thai body text needs `line-height` ≥ 1.6.
- Wide tables need the mobile card variant (`.table-cards` + `.card-list`); PVEO staff enter data
  on phones in the field.
- Print styles are a real requirement (A4 landscape) — users print reports for government
  submission. Use `.no-print` / `.print-only`.
- Buddhist-Era years, `dd/mm/yyyy` dates: use the `thai_date()` helper.

**Template helpers** (`src/Support/helpers.php`): `e()` escape, `url()`, `asset()`,
`num()` thousands separator, `pct()` (returns null on an unusable denominator — render `—`),
`thai_date()`, `be_year()`, `file_size_human()`, `str_excerpt()`. Missing values render as `—`,
not `0` or blank.

## Design source

`project/` is the original Claude Design handoff and is **reference only** — not part of the app.
`project/DVE PPP Redesign.dc.html` is the prototype (a `.dc.html` dialect: `{{ }}` interpolation,
`<sc-if>` / `<sc-for>`, a `class Component extends DCLogic` script block, all styling inline).
`project/uploads/REDESIGNBRIEF.md` is the authoritative spec for anything not yet built.
`project/_ds/modernist-*/` is an unused design system — the app deliberately does not follow it.
