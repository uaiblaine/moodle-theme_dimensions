# Compiling the flavours' stylesheets ahead of time

Status: **S0-S3 done (S1 #52, S2 #57, S3 in review, 2026-10-29); S4, the release work, not started.** It replaces the shape proposed on 2026-10-27
(a scheduled detector queuing one adhoc builder per flavour): section 3 found that Boost Union
cannot compile two flavours in one PHP process. Section 8 lists the defaults this plan picked; each
can be overturned before its stage starts.

## 1. Goal

No visitor waits for a flavour's stylesheet to compile. After any event that resets the theme
caches (a flavour save, a theme setting change, a cache purge, an upgrade), the theme compiles
every flavour that can apply in the background, before the first visitor asks for it, and stores
the result where Boost Union's `flavours/styles.php` reads it.

This is the release prerequisite named by `docs/flavourchoice/PLAN.md` (sections 1 and 7.1): a
person's choice makes many flavours hot at once.

**Not in scope:**

- Changing how Boost Union compiles a flavour, or its stylesheet URL.
- The main theme's stylesheet. Core builds it (`upgrade_themes()`, `build_installed_themes_task`).
- The editor sheets (`editor`, `editor-rtl`). Boost Union compiles those per request without the
  lock, and they are small.

## 2. Why: the measured cost

| | Measure |
|---|---|
| Cold compile of one flavour (m503, CLI, Xdebug off) | ~7 s CPU, ~120 MB peak; 21-25 s with `xdebug.mode=debug` |
| First visit to a flavoured page after a purge (vitrine-react PLAN, R0 probe) | FCP **23.5 s**, against 252-368 ms warm |
| One flavour sheet | 2.84 MB, 431 KB gzip |

- **Nothing pre-builds flavours.** Core builds only the main sheet.
- **Boost Union's fallback sheet rarely helps a flavour.** `flavours/styles.php:166` serves the
  tempdir fallback only when `!$theme->has_css_cached_content()`, which reads the MAIN theme's key.
  The main sheet is usually built, so the flavour's first visitor waits for the full compile.
- **Cold flavours serialise.** `styles.php:189-190` takes one lock, `core_theme_get_css_content`
  keyed by the theme name, for every flavour and the main sheet. Waiters give up after 90-120 s and
  then compile anyway, in parallel, at 120 MB each.
- **Every reset starts over.** `flavours/edit.php` and the theme settings call
  `theme_reset_all_caches()` (core `lib/outputlib.php:269`), which bumps `themerev`, purges
  `core/postprocessedcss` (`:282`) and deletes every `localcache/theme/*` directory.

## 3. Facts the design rests on (read 2026-10-28, Boost Union `upgrade-503` 143e7497)

### 3.1 Where a flavour's sheet lives

`flavours/styles.php` reads three stores, in this order:

1. **The localcache file** `localcache/theme/<themerev>/dimensions/css/all_<subrev>_<flavourid>.css`
   (`styles.php:110-121`, name from `theme_boost_union_flavour_styles_get_filename()`, `:319`).
   On a hit it is sent with no bootstrap, no lock and no DB.
2. **The MUC entry** `core/postprocessedcss`, key `dimensions_<flavourid>_ltr`
   (`theme_boost_union_flavour_get_css_cache_key()`, `:373`). On a file miss, under the lock,
   `theme_boost_union_flavour_styles_generate_and_store()` (`:225`) uses it instead of compiling,
   then writes the file. **The key carries no revision**: it is correct only because
   `theme_reset_all_caches()` purges the whole definition.
3. **The tempdir fallback** `temp/theme/dimensions/all_<flavourid>.css`, unversioned (`:254-257`,
   `:291-308`).

`generate_and_store()` also deletes older `localcache/theme/<rev>` directories and older sub-revision
files (`:259-279`).

The localcache file is per web node; the MUC entry is shared (application cache). A builder that
fills both makes the cron node's file and every node's compile free: another node pays a lock, a MUC
read and a file write, about a second, never a compile.

### 3.2 The compile reads the flavour from a global

`styles.php:74-75` sets `$themeboostunionappliedflavour`; Boost Union's pre and extra SCSS callbacks
read it (`lib.php:409-414`, `:765-770`). A builder sets it, calls `theme_config::get_css_content()`
(which compiles fresh and writes no cache) and unsets it. It must never run inside a web request:
the global would re-flavour the page being served.

### 3.3 Boost Union cannot compile two flavours in one process

**Probe, m503, 2026-10-28.** `theme_boost_union_get_flavour_config_item_for_flavourid()`
(`flavours/flavourslib.php:332-352`) keeps the first record it fetches in a `static $flavourrecord`
and never compares the id again. Nine calls in `lib.php` and two in `locallib.php` read every look
setting through it during the compile.

```
id1: '#7a1f2b'
id999 (no such row): '#7a1f2b'
```

**Measured on m503b, 2026-10-28 (S0).** Two flavours, A `#7a1f2b` and B `#1f6a7a`, compiled by
`theme_config::get_css_content()` in the cron container (no Xdebug):

| Run | `--bs-primary` | A's colour | B's colour | Time | Peak |
|---|---|---|---|---|---|
| A, then B, one process: A | `#7a1f2b` | 91 | 0 | 9.1 s | 145 MB |
| A, then B, one process: **B** | **`#7a1f2b`** | **91** | **0** | 9.7 s | 150 MB |
| B alone, fresh process | `#1f6a7a` | 0 | 91 | 8.2 s | 145 MB |

So a second flavour compiled in the same process gets the first flavour's brand colour, raw SCSS
and every other look field, silently. Consequences:

- **Core runs adhoc tasks in the cron process itself** (`\core\cron::run_adhoc_tasks()`,
  `lib/classes/cron.php:232`, loops `run_inner_adhoc_task()`). Two builder tasks in one cron run
  would poison the second. This rules out the 2026-10-27 shape as it was written.
- PHPUnit runs every test in one process: a test that compiles a real flavour poisons the next one.
- The memory note "statics in Boost Union's locallib are not flavour-dependent" is true of
  `locallib.php` and false of `flavourslib.php`.

**Each flavour is therefore compiled in a PHP process of its own.** That also keeps the 120 MB peak
out of the cron process.

### 3.4 What changes the right answer

Every path that changes a flavour's CSS moves `themerev` or the theme sub-revision:
`theme_reset_all_caches()` (flavour save, settings callbacks, Boost Union's `purge_cache` task,
`purge_caches.php`, upgrades) moves `themerev`; `theme_build_css_for_themes()` (`outputlib.php:193`,
`build_theme_css.php`, upgrades) moves the sub-revision without purging MUC. **The localcache file
name encodes both**, so "the file for the current revision exists" is the one test that sees every
path. `theme_reset_all_caches()` has no hook or event, which is why the trigger polls.

## 4. Design

### 4.1 Pieces

| Piece | Path | Role |
|---|---|---|
| Builder | `classes/local/flavourcss/builder.php` | Compiles ONE flavour in the current process and fills the three stores |
| Planner | `classes/local/flavourcss/planner.php` | Lists the flavours that can apply and the ones whose sheet is missing; runs one child process per missing flavour |
| CLI | `cli/build_flavour_css.php` | `--flavourid=N`: the child, calls the builder. `--all`: the planner, for a deploy step. `--dry-run`: lists what would be built |
| Task | `classes/task/warm_flavour_css.php`, `db/tasks.php` | Scheduled every minute; calls the planner |

### 4.2 The builder, for one flavour

1. Refuse unless `CLI_SCRIPT`, `$CFG->theme === 'dimensions'` and `themedesignermode` is off
   (revision -1 disables every cache).
2. **Static guard.** Read Boost Union's static through
   `ReflectionFunction::getStaticVariables()`. If it holds a record for another flavour, throw:
   fail closed instead of writing another flavour's colours. This also makes an in-process test
   that forgets the rule fail loudly.
3. Read `themerev` and the sub-revision **from the database** (`$DB->get_field('config', ...)`,
   `get_config('theme_dimensions', 'themerev')`), not from `$CFG`.
4. Take Boost Union's lock: factory `core_theme_get_css_content`, key `dimensions`, timeout 120 s.
   No lock: exit "busy"; the next minute retries. Under the lock, re-check the file: a visitor may
   have built it while the builder waited.
5. Set the global, `theme_config::load('dimensions')`, `force_svg_use(true)`,
   `set_rtl_mode(<direction>)`, `get_css_content()`, unset the global in `finally`.
6. **Stale-write guard.** Re-read both revisions from the database. If either moved, discard the
   result and exit "stale": a MUC write now would outlive the purge under a revision-less key.
   (Boost Union's web path has the same window; stage U2 offers the fix upstream.)
7. Write, as `generate_and_store()` does: the MUC entry, the localcache file through
   `css_store_css()`, the tempdir fallback, then the older-revision cleanup.
8. Release the lock. Exit codes: 0 built, 3 already present, 4 busy, 5 stale, 1 failed.

The file names, the cache key and the fallback path are **copies of Boost Union's helpers**:
`styles.php` runs top-level code, so it cannot be included. A source test (stage S1) reads Boost
Union's `styles.php` and fails when any copied function's body changes.

### 4.3 The planner

- **Which flavours:** those that can apply today. A flavour with `applytocategories` and ids, or
  `applytocohorts` and ids. When the flavour choice ships (`docs/flavourchoice/PLAN.md`, F2), the
  offered list joins through one function, `planner::buildable_flavours()`. A flavour that applies
  to nothing is never requested; building it costs 7 s after every purge for nothing.
- **Which directions:** `ltr` always; `rtl` only when an installed language is right-to-left.
- **Missing** means: no localcache file for the current `themerev` and sub-revision (section 3.4).
  The check is one query for the flavours plus one `file_exists()` per flavour and direction.
- **Failure memo.** A child that exits 1 (a flavour's raw SCSS that does not compile) is recorded in
  `theme_dimensions/flavourcss_failed` as `<flavourid> => <themerev>_<subrev>`, and that flavour is
  skipped until either revision moves. Without it, one broken flavour costs 7 s of CPU every minute.
  The task's output names it.
- **One child at a time.** Parallel children would only queue on the theme's single lock.
- **Child process:** `proc_open([<php>, <dirroot>/theme/dimensions/cli/build_flavour_css.php,
  '--flavourid=N', '--direction=ltr'])`, where `<php>` is `PHP_BINARY` under `CLI_SCRIPT`, else
  `$CFG->pathtophp` as core's `task\manager::find_php_cli_path()` resolves it (`manager.php:1858`).
  Neither available, or `proc_open` disabled: the planner says so and builds nothing. The child's
  stdout and stderr go to the task log.
- **Order:** Boost Union's `sort`. Once the choice ships, the most chosen first.

### 4.4 The task

`warm_flavour_css`, scheduled `* * * * *`. A no-op while every buildable flavour has its file, which
is nearly always. Otherwise it runs the planner and logs one line per flavour with the exit code and
the time. Core's task lock keeps one run at a time; a run that takes more than a minute simply
makes the next one find nothing to do.

Why one scheduled task and not a detector plus adhoc builders (the 2026-10-27 shape):

- adhoc builders would run in the same cron process (section 3.3), so they would still need the
  child process;
- they would buy no parallelism, because every compile takes the same lock;
- a second moving part (queueing, deduplication by flavour and revision) for no gain.

The CLI `--all` runs the same planner, for a deploy script right after `upgrade.php` and
`purge_caches.php`.

### 4.5 What a visitor sees

- Within a minute of a reset plus about 7 s per flavour, every buildable flavour is warm.
- A visitor who arrives before that pays what they pay today, minus the queue: the builder releases
  the lock between flavours, and re-checks the file under the lock, so no flavour is compiled twice.
- In a cluster, other nodes fill their own file from MUC on the first request (section 3.1).

## 5. Stages

**S0. Probes, before any code. Done 2026-10-28 on m503b** (the test flavours were inserted with no
category or cohort, compiled from the CLI with no cache written, and deleted).
- **Poisoning:** measured, table in section 3.3. The compile itself is 8-10 s and 145-150 MB on
  m503b, above m503's ~7 s and ~120 MB; the stage S3 measurement settles the figure for the README.
- **Static guard:** `ReflectionFunction::getStaticVariables()` reads Boost Union's static. It is
  `NULL` in a fresh process and holds record 2 after a lookup of flavour 2, after which a lookup of
  flavour 1 answers flavour 2's colour. So step 2 of section 4.2 can be written as designed.
- **Shared directories:** the web and cron containers mount the same `moodledata` volume, and
  `localcachedir` and `tempdir` are its defaults (`moodledata/localcache`, `moodledata/temp`). A
  file the cron node writes is the file the web node serves.
- **PHP binary:** `PHP_BINARY` in the cron container is `/usr/local/bin/php`, `config.php` sets
  `$CFG->pathtophp` to the same path, and `disable_functions` is empty, so `proc_open` is available.
- **Overheads:** a child's bootstrap (`config.php` only) takes 0.5-1.0 s and 16 MB, about a tenth of
  a compile. The planner's no-op check (one query and one `file_exists()` per flavour) takes under
  1 ms, which is the task's whole cost per minute while everything is built.

**S1. Builder.**
- What: `builder.php`, the copied helpers, the lang strings for its errors.
- PHPUnit (no real compile: the compiler is a seam the tests replace):
  - the three stores receive the content under the right names, and the older revision is removed;
  - nothing is written when a revision moves during the compile (control: the same run without the
    move writes all three);
  - the static guard throws when Boost Union's static holds another flavour (control: same flavour
    passes);
  - the lock: a held lock exits "busy" and writes nothing;
  - refusal outside CLI, under designer mode, with another site theme;
  - the drift test against Boost Union's `styles.php`.
- Mutation gates: the stale guard, the static guard, the under-lock re-check, the `finally` unset.

**S2. Planner and CLI.**
- What: `planner.php`, `cli/build_flavour_css.php`.
- PHPUnit: which flavours are buildable (categories, cohorts, neither); missing versus present for
  the current revisions; the failure memo skips until a revision moves and then retries; `rtl` only
  with an RTL language installed; the PHP binary resolution and the refusal when none exists.
- One end-to-end check outside PHPUnit, on m503b: `--all` after a purge builds both flavours, each
  sheet carries its own brand colour, and a page of each flavour then returns its sheet from the
  file (Boost Union's `styles.php` never takes the lock).
- Mutation gates: the buildable filter, the missing test, the memo's revision comparison.

**S3. Task.**
- What: `warm_flavour_css.php`, `db/tasks.php`, the task name string, `version.php` bump.
- PHPUnit: the task is a no-op with everything built; it calls the planner when a sheet is missing.
- Measured on m503b, 2026-10-29, instead of after a purge (a purge on the shared stack would have reset every
  session's caches): two new flavours, one left to `styles.php`, one warmed by the task.
  - **Cold**, as today: the first request for its sheet waited **23.2 s** for `styles.php` to compile it, the
    23.5 s of the showcase measurement.
  - **Warmed**: the task built the other in its own child in 10.7 s, off the request path, and skipped the
    first because `styles.php` had stored it. The first request for the warmed sheet took **0.013 s**.
  - A run with nothing missing took 0.00 s.
  - After a purge of N flavours, the last one is warm within a minute plus about 8-11 s per flavour; a visitor
    who arrives before that pays what they pay today.

**S4. Release.**
- `README`: the task, the CLI and the deploy step, the failure memo, the cluster note;
  `marketplace_readiness_test` keeps it honest. `CHANGELOG.md`. The privacy provider is unchanged:
  nothing is stored about people.
- Gates: the `security-diff-read` skill (a subprocess, file writes under dataroot);
  `mdl ci moodle-theme_dimensions --matrix --behat`.
- `docs/flavourchoice/PLAN.md` sections 1 and 7.1 then point here as done.

**U. Upstream (optional, any time, the maintainer merges).**
1. Key `$flavourrecord` by flavour id in `flavourslib.php`. One line; it removes section 3.3's trap
   for anyone compiling flavours outside `styles.php`. The child process stays here regardless: it
   keeps the 120 MB out of the cron process.
2. In `generate_and_store()`, discard a compile whose revision moved before writing the
   revision-less MUC key.
3. In `styles.php:166`, test the flavour's own cache key instead of the main theme's, so the
   fallback can serve a flavour.

## 6. Error surface

| Failure | What happens |
|---|---|
| A flavour's SCSS does not compile | Child exits 1; memo skips it until the next revision; the visitor sees what Boost Union shows today |
| The lock is held for 120 s | Exit "busy"; retried next minute |
| A reset during a compile | Exit "stale"; nothing written; the next minute builds the new revision |
| No PHP binary or no `proc_open` | The task says so and builds nothing; the site behaves as today |
| Boost Union changes a copied helper | The drift test reddens on the next CI run |
| Boost Union renames the global or the static | The static guard or the end-to-end check fails before release |

Every failure falls back to today's behaviour, never to a wrong sheet.

## 7. Risks

- **A wrong colour is worse than a slow page.** The static guard and the stale-write guard are the
  two lines that keep it from happening; both are mutation-gated.
- **Cron must run.** Without cron the site behaves as today. The README says so.
- **CPU after a reset:** about 7 s per buildable flavour, one at a time, on the cron node instead of
  in a web worker.

## 8. Defaults picked here (overturn before the stage starts)

| # | Default | Alternative |
|---|---|---|
| D1 | Build only flavours that can apply (categories or cohorts set; later, the offered list) | Every flavour row |
| D2 | One child process per flavour | One flavour per cron process, guarded by the static check, no subprocess (a site with N flavours warms over N cron runs) |
| D3 | One scheduled task every minute | Detector plus adhoc builders (section 4.4 says why not) |
| D4 | `rtl` only when an RTL language is installed | Always both directions |
| D5 | A failing flavour is skipped until the revision moves | Retry every minute |
| D6 | The tempdir fallback is written, as Boost Union does | Skip it: it only helps a cold main sheet |
