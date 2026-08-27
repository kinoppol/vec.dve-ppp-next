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
/c/xampp2/php/php.exe -S 127.0.0.1:8765 _devrouter.php
```

`_devrouter.php` is not committed — it is three lines: serve real files, else `require index.php`.

`php` is not on PATH. This working copy sits in **xampp2's** document root, so use
`C:\xampp2\php\php.exe` (PHP 8.2.12) — an unrelated `C:\xampp\php\php.exe` also exists on this
machine. Syntax-check
everything before committing — this prints nothing when the tree is clean:

```bash
find . -name "*.php" -not -path "./project/*" -print0 | xargs -0 -n1 /c/xampp2/php/php.exe -l | grep -v "^No syntax errors"
```

**Installer.** `install.php` is standalone (it requires `src/bootstrap.php`, never `index.php`)
and walks five steps: requirements → database → migrate → admin → done. It writes
`config/config.php` and `storage/installed.lock`; once the lock exists the installer demands the
admin password to re-enter. Deleting `storage/installed.lock` is the documented reset path.

## Deployment

`.github/workflows/main.yml` deploys on **every push to `main`**: it SSHes to the production host
(`secrets.SERVER_HOST`) and runs `git fetch origin && git reset --hard origin/main` in
`/var/www/next`. No build, no test gate, no staging branch — a push is meant to be a release, and
any tracked file edited by hand on the server is discarded.

**As of 2026-08-27 this pipeline has never succeeded.** Both runs so far (the commit that added
the workflow, and the next one) fail in the SSH step within ~2 seconds, which is the signature of
missing `SERVER_HOST` / `SERVER_USER` / `SERVER_SSH_KEY` secrets. Until someone sets them,
production at `dve-ppp.vec.go.th/next/` is updated by hand and **pushing does not deploy** —
verify against the live page rather than assuming. Note also that the deploy would only move
code: migrations still have to be run from `admin/migrations` afterwards. Untracked paths survive the reset, which is what
keeps `config/config.php`, `storage/installed.lock` and `uploads/reports/` alive (all are in
`.gitignore`, along with `*.sql` — the real `ppp_db.sql` dump carries personal data and plaintext
passwords and must never be committed).

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
- **Login is two-step.** `AuthController::login()` requires an explicit `mode` of `pveo` or
  `admin` and never guesses; without it the POST bounces back to the type picker. The two roles
  authenticate against different tables (`admins` / `provincial_vocational_offices`), so guessing
  would hand the password to the wrong verifier.
- `Controller` gives every action `input($key)` (trimmed, GET then POST), `page()` and `perPage()`
  (clamped 10–100, defaulting to `app_settings.rows_per_page`) — use those rather than reaching
  into `$_GET` / `$_POST`.
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

Current set:

| | |
|---|---|
| `0001` | ตารางอ้างอิง — DDL จริงของ production ตรงตัว |
| `0002` | ตารางหลัก — DDL จริงของ production ตรงตัว |
| `0003` | คอลัมน์รหัสผ่าน + ตารางของแอปใหม่ (`app_settings`, `report_steps`, `share_links`, `ppp_enterprise_completeness`) |
| `0004` | view `v_ppp_*` และ procedure `Ppp*` — **ไม่มี trigger** |
| `0005` | ข้อมูลตั้งต้น เติมเฉพาะตอนตารางว่าง |
| `0006` | คอลัมน์ที่ production ไม่มี: `surveys.status` / `current_step` / `certifier_date`, `pveo_estate_assignments.is_manual` |

0001 and 0002 were generated from `ppp_db.sql` rather than hand-written, with `PRIMARY KEY` and
indexes folded **into** each `CREATE TABLE` — a separate `ALTER TABLE ADD PRIMARY KEY` fails the
moment the table already exists, which is the normal case here.

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

- **Everything this app creates carries a `ppp_` / `v_ppp_` / `Ppp` prefix.** Production owns
  `SyncPveoEstateAssignments` and `SyncReportCompletenessPveo` (procedures),
  `report_completeness_by_pveo` and `view_address` (views), and `enterprise_completeness`
  (table). 0004 creates its own `v_ppp_estate_progress`, `v_ppp_pveo_progress`,
  `PppSyncPveoEstateAssignments`, `PppRecountSurveyed`, `PppRecalcEnterpriseCompleteness`, and
  writes scores to `ppp_enterprise_completeness`. Never add a `DROP ... IF EXISTS` for an
  unprefixed name. Note production's `SyncPveoEstateAssignments` takes an `INT` year, and its
  completeness table uses `completeness_score` plus six sub-scores — a different formula from
  ours, which is exactly why the two must not share a table.
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

Verified against a database built from the real `ppp_db` DDL and carrying production's own
routines and triggers: the full migration set leaves every legacy object byte-identical, adds
only the columns above, and `PppRecountSurveyed` returns 1 (not 2) when the legacy
`after_survey_insert` trigger has already incremented the counter.

## Database constraints

The production schema is **frozen**. `migrations/0001` and `0002` are a verbatim copy of the real
`ppp_db` DDL — do not "tidy" the column names. `0003` and `0006` only *add* columns.

**The column names are not what the brief says.** The app was first written against the schema
*proposed* in `REDESIGNBRIEF.md` §8, which does not match production. The real names:

| ตาราง | PK | ชื่อที่ต้องใช้จริง |
|---|---|---|
| `provinces` | `province_id` | `province_name_th` / `province_name_en` |
| `geographies` | `geography_id` | `geography_name` |
| `provincial_vocational_offices` | `pveo_id` | **ไม่มี** `college_name` — join `college` ด้วย `college_code` |
| `admins` | `admin_id` | `admin_name` (ไม่มี `email` / `is_active`) |
| `industrial_estates` | `industrial_estate_id` | `industrial_estate_name`; จำนวนสถานประกอบการอยู่ที่ `industrial_estate_details.total_enterprises` |
| `pveo_estate_assignments` | `assignment_id` | `industrial_estate_id`, `survey_year` เป็น `int(4)` |
| `enterprises` | `id` | `name`, `contact_person`, `address_no`, `industrial_estate_id` |
| `surveys` | `id` | `operation_date` (ไม่ใช่ `survey_date`), `recorder_college_code` |
| `report_progress` | `id` | `academic_year`, `stage`, `status` enum('draft','submitted','done') |
| `report_files` | `id` | `file_type`, `uploaded_by_name` / `uploaded_by_code` |

`is_active` exists **only** on `industrial_estate_responsibility` — never filter the other tables
by it. Enum columns that trip people up: `survey_demands.disability_flag` is `enum('yes','no')`
(not 0/1), `survey_past_trainings.education_level` is `enum('vc','hvc')`, and
`surveys.teacher_training_status` is `enum('yes','no')`.

**SQL speaks the real schema; PHP and the templates keep the app's own vocabulary.** Every query
aliases back (`e.name AS enterprise_name`, `o.pveo_id AS id`), so adding a screen means writing
real column names in the SQL and aliasing — not renaming keys across the views.

`surveys` has four `NOT NULL` columns with no default — `recorder_college_code`,
`target_college_code`, `province_id_report`, `operation_date`. `SurveyController::findOrCreateDraft()`
fills all four, or the draft `INSERT` fails outright under `STRICT_TRANS_TABLES`.

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
