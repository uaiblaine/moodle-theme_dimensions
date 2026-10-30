# The category showcase: the round-by-round record

This is the history of the category showcase: the sections the repository's `CLAUDE.md` carried for
each stage and round, from the decision layer (stage 2) to the removal of the old AMD listing (R4c),
moved here whole, word for word and in their order, by stage R5 of `docs/vitrine-react/PLAN.md`. Each
holds the decisions, the measurements, the incidents and the rules of its round, as they were written
at the time.

**This file is not auto-loaded.** `CLAUDE.md` keeps what is true now in its one "The category
showcase" section and points here. Read this file by section, on demand, as the fleet's long-form file
is read: find the round in the list below (or with `rg -n '^##' docs/category-showcase/history.md`)
and read that section alone, never the whole file.

Read it as a record, not as a description of the code:

- "This file" inside a section means the `CLAUDE.md` it was written in; "below" and "above" mean its
  neighbours here.
- The AMD modules many sections name were deleted in R4c (`categorylisting`, `categorypublic`,
  `categoryvitrine`, `categorypanel`, `categoryline`, `categorypreview`, `categoryareapanel`,
  `categoryposters`, `daterange.js`, `categorymark`) and the star left AMD in R5 (`categorystar`). Their
  rules live on in the ES tree (`js/esm/src`), and the sections from R1 on say where each one went.
- A later section supersedes an earlier one where they disagree, and `CLAUDE.md` and the code win
  over both. The decision ids (D1-D39, OD-n, the round letters) name entries of the original design
  record, which is not part of this repository.

The sections, in order (each heading below is the section's own):

- where the record is
- the decision layer (stage 2)
- the listing and the logged-in shell (stage 3)
- the hero, the tabs and the branding (stage 4)
- the anonymous shell (stage 5)
- the visual refinement (between stages 5 and 6)
- the web services and the client (stage 6)
- the owner's round after the browser session (stage 6b)
- the owner's round B, the editor half (2026-09-17)
- the crests and the card geometry (round E1, 2026-09-17)
- the toolbar, server side (round D1, 2026-09-18)
- the editor's two columns (round F, 2026-09-18)
- the toolbar's client half (round D2b, 2026-09-18)
- the tree as a filter, and the listing's controls (round D3, 2026-09-18)
- the card's cover and its hover film (round G, 2026-09-19)
- the editor's file fields and the public text (round H, 2026-09-19)
- the posters and the panel between rows (round EL-1, 2026-09-24)
- search and filters on the posters, and the toolbar's order (round EL-2, 2026-09-25)
- the toolbar's controls in the design's skin (round EL-S, 2026-09-26)
- search at the root (round EL-3, 2026-09-27)
- the pages strip (round T2, 2026-09-27)
- the visitor's live client (stages S1 to S4, 2026-10-04 and 05)
- the state left per category, the open area's colour, the folded crests (round I, lot C1, 2026-10-11)
  - Round I, lot C2: the course dialogue (2026-10-11)
- the filter line (T1 to T4, 2026-10-16 and 17)
- first paint and first use (P0, 2026-10-26)
- The performance probe (R0)
- the server render of the first paint (R0, 2026-10-27)
- the calendar as a React component (R1, 2026-10-27)
- the filter line as a React component (R2, 2026-10-27)
- the panel and the posters as React components (R3, 2026-10-28)
- the visitor's shell on the React components (R4a, 2026-10-28)
- the shared listing in TypeScript (R4b1, 2026-10-29)
- the store and the visitor's shell (R4b2, 2026-10-29)
- the account's shell (R4b3, 2026-10-29)
- the old listing removed (R4c, 2026-10-30)

## The category showcase: where the record is

Twenty-one sections of this file carry the traps, one per stage, in the order they were paid for:
the decision layer (stage 2), the listing and the logged-in shell (stage 3), the hero and the
branding (stage 4), the anonymous shell (stage 5), the visual refinement, the web services and
the client (stage 6), the owner's round (stage 6b), the owner's round B (2026-09-17), the
crests and card geometry of round E1 (2026-09-17), the toolbar's server half (round D1,
2026-09-18), the editor's two columns (round F, 2026-09-18), the toolbar's client half (round
D2b, 2026-09-18 - the partition's server half is filed with the decision layer's sections it
changed, and the SVG revert with round E1, which is the decision it reverses), the tree as a
filter (round D3, 2026-09-18), the card's cover and its hover film (round G, 2026-09-19), the
editor's file fields with the public text's multimedia button (round H, 2026-09-19), the
posters with the panel between their rows (round EL-1, 2026-09-24), search and filters on the
posters (round EL-2, 2026-09-25), the toolbar's controls in the design's skin (round EL-S,
2026-09-26), search at the root (round EL-3, 2026-09-27), the pages strip (round T2,
2026-09-27) and the visitor's live client with its web service and the cache decision (stages S1
to S4, 2026-10-04 and 05); the enrolment matrix's state area on the card and the landing page (stage 5b,
2026-10-06) has a section of its own after them. They hold what a maintainer has to know before touching
the code, and nothing else: read the section for the layer you are in. **The sections are by LAYER, not by round**, which is why round E2 - the
public root of the course index and the directory's cut at twenty-five (2026-09-18) - has no section
of its own: its facts are filed with the anonymous shell (stage 5), which is the code they are
about. The same is why round B's own section carries its editor half alone: the four traps its
listing half paid - the non-bubbling
`toggle`, the peek that eats its own card's disclosure, the offline `grunt` rebuilding every
module, and judging a JavaScript mutation by whether the build changed - are filed with the client
(stage 6), because that is the code they are about.

The original design record (not part of this repository) is the other half and is not a duplicate of them. It holds the plan
(sections 1 to 8), the decisions D1-D39 with the alternatives each one rejected, and a **status
block per stage** - the visual refinement, the owner's round 6b, the free hero colours 6c, the
visitor's card cover, stage 7, round B, and on to stages S1 to S4 - each recording what was built
file by file, every number that was measured, the departures from that round's blueprint with their reasons, and what the
round did NOT verify. When a question is "why is it like this", the answer is a decision in that record; when
it is "what breaks if I change this", the answer is a section here and a gate in
`mutations/gates.conf`. The residue - what is deliberately unproven, and why - lives only in the
status blocks, and the newest one supersedes the older ones where they disagree. A session without
access to that record works from this file, the docblocks and the gates; the decision ids cited
here (D1-D39, OD-n, the round letters) are the record's and stay as names.

## The category showcase: the decision layer (stage 2)

The plan is in the original design record (not part of this repository); stage 2 built `classes/local/category/` and nothing that
renders. The facts the code rests on, in the order they bite:

- **Two gates, core first, on every entry.** `population::scope()` asks core about the root
  (`core_course_category::can_view_category()`) and then the plugin (`category_access`), and
  refuses both with ONE `unavailable_exception` - the same one a nonexistent id and, for a
  visitor, a non-public category get. `local_unlistedcourses` is a layer on top of core
  visibility, never a replacement: its predicates answer "true" wholesale when nothing is
  unlisted and for a site admin, so a core-hidden course sails through them. The population
  tests seed a core-hidden course and a core-hidden category as controls beside the unlisted ones.
- **A category that fails a gate is CLAMPED, not dropped.** It stays in the population statement
  and each of its courses is kept only by the rescue that clamp allows: an active enrolment for a
  core clamp (what `get_course_records()`'s `$mycourses` bypass does), an enrolment, a pending
  application or staff standing (`is_viewing()` / `viewhiddencourses` at the preloaded course
  context) for a plugin clamp (what `access::filter_courses()`'s relationship rescue does). A
  hidden category clamps its whole branch - stricter than core's recursive listing, on purpose.
- **Nothing is ever driven by the population's course ids.** `get_in_or_equal()` emits one
  placeholder per item and never chunks, so every statement is driven by the admitted category
  ids, the viewer's id or the page slice. The plugin's `get_states()` is an IN list too, which
  is why the population reads each course's state through the plugin's `state_sql()` join in
  its own statement (D23), and why `filter_courses()` is handed only the unlisted-state courses -
  related ones all (answered from the primed memo), unrelated ones up to `PROBE_CAP` (50), the
  rest withheld unasked - and `filter_courses_public()` only the page slice.
- **Three context-cache rules.** Category contexts arrive in the subtree statement's own preload
  columns (`preload_contexts_by_id()` wants CONTEXT ids); a course context is preloaded only
  when a capability is asked of it (the cache holds `CONTEXT_CACHE_MAX_SIZE` = 2 500 entries);
  and rows are preloaded from a CLONE, because `preload_from_record()` strips the columns it
  reads, so `population::page_contexts()` can rebuild the page's contexts with zero statements.
  `cards::values()` takes ROWS, not ids, for that reason: the first draft took ids and paid one
  context statement per card, which the P-slope budget test caught (20 reads at P=4, 64 at P=48).
- **A filtered `format_text()` costs statements per new context, and three landing page fields are
  textareas.** The first `format_text()` in a context runs `filter_get_active_in_context()` (a
  recordset: 3 reads on PostgreSQL) and then every active filter, `filter_activitynames` reading
  the course record per context; a page of 48 cards paid 94 reads against 18 for a page of 4, and
  a fixture without a textarea value never shows it. So a listing asks `values_many(..., false)`:
  the textareas are rewritten and cleaned exactly as core's `export_value()` does but with the
  filter pass off, and only the landing page (`values()`) runs the filters. `fixture::reads()` resets
  `$FILTERLIB_PRIVATE` and `filter_manager` before metering, as core's PHPUnit reset does; a
  budget test that skips that reset measures a warm cache, not the code.
- **A chip is a filter; a link only where the page exists.** Under D20 the anonymous shell names
  every admitted child holding a public course and marks `linkable` only for a PUBLIC child;
  the plan's §1.3 was corrected to say so after the critic round read it the other way.
- **Core lists the newest course of a category first.** The generator's creation order is the
  reverse of core order; four tests were written the wrong way round before this was measured.
  And `create_course()` HIDES a course created inside a hidden category, so a fixture testing the
  category clamp passes `visible => 1` explicitly.
- **The classifier may only err towards "open".** Its docblock names the per-plugin methods it
  must agree with; the superset test runs it beside `landing\resolver` shape by shape. The one
  grouped count uses enrol_apply's shape (non-expired rows), which can only make a full
  enrol_self instance look open, never an open one look full - pinned by a test of its own.
- **Guest access behind a key reads key, for a real user and for the guest account alike** (enrolment
  matrix, stage 5b: stage 0's interim "open", and "none" for the guest account, are gone). enrol_paypal is
  read as enrol_fee is; enrol_autoenrol keeps only its SQL half (enabled, window, `customint4`, no own row).
  The resolver asks autoenrol's own `enrol_allowed()` and paypal the fee checks, and lists both as
  fallbacks only where those pass, so the superset test can hold them; a plain fallback would read open
  for every enabled instance. enrol_coursecompleted reads conditional for a viewer actively enrolled in
  the course `customint1` names, read off the viewer's own enrolments map at no statement, and nothing
  for anybody else; see the stage 5b section below.
- **The landing page's pending application is enrol_apply's queue predicate** (`ue.status` not active, and
  `timeend` 0 or ahead) over the viewer's rows on every apply instance of the course, enabled or not,
  with the plugin enabled or not; the queue reads neither. The card's pending and waitlisted come from
  `access::classify_enrolment()` in local_unlistedcourses, which applies the same rule since its 5a
  release, and the landing page tells the waiting list apart through the same plugin's relationship. The
  design record is the fleet's `moodle-dev/docs/enrolment-status-matrix/` (findings M4, M6, M8, M9).
- **Facets are counted in PHP over one O(N)-row statement (D22)**, each group with the other
  groups' filters applied; the `GROUP BY` with capped exception lists survives only in the
  SQL-cut hatch (`categorystatesort = 0`), which also gives up the state chips and the per-state
  counts. Only an explicitly stored `'0'` switches the hatch on.
- **Sixteen gates**, listed at the tail of `mutations/gates.conf`; every population gate runs
  in both page-cutting modes through a data provider. The anonymous page-slice replay is
  belt-and-braces and NOT a gate: the statement withholds the same rows, so deleting the replay
  reddens nothing while the two agree.
- **The load probe is an operational check, not a one-off.** The load probe of the original design record (not part of this repository)
  (5 000 courses on a 5.2 stack) is re-run and its numbers re-recorded in the plan's §3.3 whenever a
  production subtree materially exceeds the last measured size, and `budget_test` writes each
  run's numbers to `/tmp/theme_dimensions-vitrine-budget.json` inside the container.

## The category showcase: the listing and the logged-in shell (stage 3)

Stage 3 built `classes/output/{category_listing,category_toolbar,category_card,category_actions}.php`,
the three templates, `scss/category.scss`, the settings tab and the `course_category()` override.
What the code rests on, beyond the stage-2 facts above:

- **`course_category()` is the whole logged-in shell, and any miss is the parent.** The guard chain
  is the plan's: kill switch, `core_course_category::get($id, IGNORE_MISSING, true)` then
  `is_uservisible()` (core's gate, asked FIRST - the plugin answers "yes" wholesale for a hidden
  category), then `category_access::is_category_discoverable()`. A miss returns
  `parent::course_category()`, so the switched-off page, the site root, a hidden category and a
  withheld category render exactly what Boost Union rendered before, with core's own exception
  where core throws one. The test captures that exception's message from core itself rather than
  typing it; a mutation dropping the core gate reddens it because the population then refuses with
  the theme's own message.
- **The listing costs nothing per card after four bulk reads.** `category_listing` reads
  `cards::records/values/images/contacts` once and builds a `category_card` per row from data in
  hand; the card's context comes from the population's preload columns, its subcategory name from
  `page_result::$categorynames` (filled from the scope's rows in both page-cutting modes), the
  summary excerpt and the programme lines from a light tag-to-lines reduction with the text
  filters OFF - the same rule as the landing page textareas of a listing. The render-level budget in
  `course_renderer_test` is 18 statements flat in N and in P; the two above the decision layer's
  16 are the root's context before the population preloads it and the filter load of the
  category description.
- **The toolbar is a real GET form and the chips are radios.** Native radio inputs behind
  pill-shaped labels give the arrow keys and the group announcement for free; the "Filter" submit
  carries `dim-nojs-only` and the star `dim-js-only`, so the client module of stage 6 only has to
  add `dim-js` to the body. The view toggle is two submit buttons carrying `view=`, written as a
  user preference for a logged-in person by `category_listing::view()`; the guest account gets it
  for one page. Filter selections are stored nowhere (A8).
- **One markup, two views, the star last.** `category_card.mustache` serves cards and the dense
  list; the view is one class on the container. The star is the last child of
  `.dim-card__actions` (amendment 1) and `card_markup_test` renders the template from its own
  example context to assert it, so a re-ordering of the partial reddens a test rather than a
  screenshot. The details disclosure is native, its panel anchored to the CARD under it (so
  opening it never resizes a row), inline below 576px.
- **Every badge background carries its text utility, read from the class.** `category_card`
  keeps the state table as constants; the markup test reads all eight badges through reflection
  and pairs `bg-success/primary/danger/info/dark` with `text-white`, `bg-secondary/warning` with
  `text-dark`. A rendered example shows one state at a time, which is why the table is what is
  checked.
- **A clamped category is never named, on any surface.** The chips withhold it and so does the
  card's overline: `population::category_names()` skips a category `is_open()` refuses, because a
  viewer rescued into a course of an unlisted subcategory would otherwise read its name on the
  card. The critic round caught this after every test was green; the test seeds exactly that
  viewer and checks both surfaces, and the gate is `pop_category_names_open`.
- **The first submit of the toolbar form is name-less.** Enter in the search box presses the
  form's first submit button, name and value included; with the card view toggle first, a
  list-view reader who searched was flipped to cards and had it remembered. Keep the hidden
  default submit before every named one (`toolbar_default_submit` reddens the markup test). The
  test finds that button by its CLASS and asserts it is the first submit: "the first submit has no
  name" went silent once round D1 put the sort row's name-less submit second, and only the full
  sweep of 2026-09-19 noticed. An assertion of the shape "the first X is Y" stops guarding the day
  a second X is also Y.
- **The render budget is the learner's page on a site without filterall.** With `filterall` on,
  the first `format_string()` in each course context loads that context's filters (a recordset
  per card); the budget tests assert the precondition rather than pretend the number holds
  everywhere. Do not pass `filter => false` on a course name: that kills multilang.
- **A held event is "open" to the classifier and "held" on the card.** Its instance is still
  enabled and the classifier reads instances; the chips are the classifier's numbers, and it may
  only err towards open. Do not make the card's rule leak back into the counts.
- **Core's tree tests switch the showcase off.** `course_renderer_test`'s four tree tests call
  `core_tree()` first: the tree overrides remain the safety net for the front page listings, the
  AJAX expander and a switched-off site, and on the category page the showcase replaced the tree.
- **Behat reaches a second page through the resolver, not through two dozen courses.** The page
  type `theme_dimensions > Category showcase` takes `IDNUMBER|perpage=1`; the
  scenarios assert English strings (the Behat site runs in English) and scope every
  `I should see` to `.dim-vitrine__grid` or `.dim-vitrine__subcats`, because a course name is
  also its own overline and chip.

## The category showcase: the hero, the tabs and the branding (stage 4)

Stage 4 built `classes/local/category/branding.php`, `classes/output/category_hero.php` and its
template, the `full_header()` override, the editor (`categoryhero.php` + `classes/form/`), the
theme's first table and its cache, observer, event and uninstall. What the code rests on:

- **The hero is armed by the listing and drawn by the header, and core fixes the order.**
  `course/index.php` calls the course renderer's `course_category()` BEFORE `$OUTPUT->header()`,
  so that method arms a `category_hero` in a static registry with the counts it already knows,
  and the core renderer's `full_header()` renders it while the layout runs. Any miss of the
  showcase's guards resets the registry first, so the parent's header follows the parent's page.
  The registry is static like the Open Graph one: `fixture::forget()` resets it, and any test
  rendering the showcase outside that trait resets it in `tearDown()`.
- **The band below the header is switched off by SHAPE.** `render_from_template()` sets
  `secondarymoremenu` to false in any array context carrying that key while a hero is armed -
  never by template name. The drift test in `core_renderer_test` renders the real page through
  Boost Union's layout and asserts each tab row by its component: on 5.3 the primary navigation is
  `core/primarymoremenu` (a React `PrimaryNav` mount around a `moremenu observed navigation`
  fallback) and the band `core/secondarymoremenu` (a React `Nav` mount around a plain
  `moremenu navigation` fallback, the same nav the hero's `core/moremenu` draws). With a hero: the
  PrimaryNav mount, the hero's more menu once inside `.dim-hero__tabs`, no `secondary-navigation`
  and no `Nav` mount; with the showcase off, the band's wrapper, its mount and its fallback. If
  Boost Union renames the key the band comes back on the hero's page, and that is the test to read
  first after a Boost Union update.
- **The tab labels are on their own lines.** Core's `moremenu_children` renders the text with
  whitespace around it, so `'>Settings<'` never matches; assert a tab by its node key
  (`data-key="edit"`, `data-key="categorymain"`, `data-key="categoryappearance"`), on the page
  and in Behat.
- **A table name may run to 53 characters on 5.2**, not the 28 of older releases:
  `xmldb_table::NAME_MAX_LENGTH` is `63 - PREFIX_MAX_LENGTH(10)`. The first draft of this stage
  named the table after the component alone on the old figure; the critic round measured the
  real one and the table is the plan's `theme_dimensions_cat`. `db/upgrade.php` builds
  the same definition as `db/install.xml`, and `xmllint --noout --schema
  /Users/uaiblaine/dev/moodle-502/public/lib/xmldb/xmldb.xsd db/install.xml` is the check.
- **`theme_config::setting_file_url()` returns a protocol-relative address** (`//host/...`), and
  `core\url` prefixes `wwwroot` to anything starting with a slash, doubling the host. The
  fallback hero image was linked that way and a test matching a substring of the mangled string
  passed; `branding::fallback_url()` now returns an absolute string and the test counts hosts.
- **"Hidden from students" is a staff number.** The population admits a hidden course to a
  student actively enrolled in it, so `page_result::$hidden` is not zero for that student; the
  hero prints the count behind `moodle/course:viewhiddencourses`, never from the count alone.
- **The colour is a FREE pair, and the hero's inline style attribute is the feature's one
  CSS-context sink** (stage 6c, D29 - this replaced the palette KEY of stage 4, whose classes
  meant there was no sink at all). `category_hero::style()` is the only builder of that string,
  and every value in it comes out of `branding::hex()`, `stops()` or `rgba()`, each of which
  parses its input into integer channels and prints them back, so the attribute can only ever
  match `^(--dim-hero-[a-z-]+:\s*(#[0-9a-f]{6}|rgba\([0-9, .]+\));\s*)+$`. **Mustache's
  escaping does not protect a CSS context**: the guard is that vocabulary, not the template, and
  it is pinned on a real export AND on a poisoned row (`#fff;background:url(x)`) by
  `category_hero_test`, with the gates `hero_style_sanitised` (hand values back unparsed),
  `hero_stops_from_bg` (pin the gradient to the default) and `hero_contrast_floor` (accept a pair
  under AA). The directory card's band is the same sink once more, as `--dim-card-band`, with
  `directory_band_from_child` over it. `branding::hex()` is the ONE sanitiser, applied on the way
  IN and on the way OUT (`record()`, `for_category()`), and the leading `#` is required.
- **The AA floor is a refusal, not a warning** (D28): `branding::validate()` throws for a pair
  under `branding::FLOOR` = 4.5 and the form reports it on the text-colour field with the
  measured ratio and the minimum, both printed by `format_ratio()`. It certifies the OPAQUE pair
  only - the image layout paints it translucent over a photograph and reads lower, which D31
  measures, records and deliberately does not refuse (a worst-case refusal would make three of
  the four quick colours unsaveable at the 60 % overlay).
- **The default pair is a dark blue, `#0a4a85`, with white ink** (`branding::DEFAULT_BG`, the column
  default in `install.xml`, the fixture's first pair; 2026100507, replacing Boost's primary `#0f6cbf`
  of 2026100503, itself a replacement of the institutional navy). It is a hex and not
  `var(--bs-primary)` because the stops, the watermark's alpha and the contrast audit are computed
  from its channels. It reads 9.01:1 opaque and, over a WHITE photograph, 5.08:1 at the default 78 %
  overlay and 6.97:1 at 90 % (pinned in `branding_test`); the folder watermark on it gets the full
  .20. The 60 % overlay reads 3.27:1 and no colour can reach 4.5 there, so it stays a manager's
  explicit choice that D31 measures and does not refuse. The stylesheet's own defaults
  (`--dim-hero-*` in `category.scss`, for a header drawn without the attribute) are still the
  design system's navy; `#0f6cbf` survives there only as Bootstrap's `var(--bs-primary, #0f6cbf)` fallback.
- **Two implementations of the same maths, held together by one fixture.**
  `tests/fixtures/contrast_pairs.json` is read by `branding_test` against `branding::contrast()`
  AND by `categoryhero_source_test` against the WCAG constants read out of
  `amd/src/categoryhero.js`: five measured pairs (one at 4.607518, just over the floor) plus a
  `spellings` table that pins the two PARSERS - a hash made optional on one side alone is a panel
  grading a value the form refuses, or the reverse, and no other test can see it.
- **The showcase has TWO stylesheets, each scoped under its own body class.** `scss/category.scss`
  under `body.dim-category` and `scss/categoryhero.scss` (the editor: swatches, quick colours,
  the contrast panel) under `body.dim-categoryhero`, both concatenated in `lib.php`.
  `category_scss_test` walks both, ends the scope check at a word boundary (`body.dim-category`
  is a PREFIX of `body.dim-categoryhero`), scans `amd/src/category*.js` for Bootstrap 4 names
  because the modules write class names themselves, and asserts that each scope really is a class
  a page adds - deleting `categoryhero.php`'s `add_body_class()` unstyles the whole editor and
  fails nothing else (gate `categoryhero_body_class`).
- **Behind the organisation's network proxy `mdl grunt` must not be run**: its npm postinstall fetches React
  through Node's fetch, which ignores the proxy, and on failure it DELETES
  `lib/js/bundles/react*` in `~/dev/moodle-502`. The offline substitute is a `node:22-bookworm`
  container over the 5.2 checkout's own `node_modules` (`npx grunt amd/stylelint/gherkinlint
  --root=public/theme/dimensions`), and the build is judged by the map's
  `sourcesContent`. `mdl ci` on 5.02 cannot install here either; the static gates run through
  `--branch MOODLE_501_STABLE --only phpdoc,validate,savepoints,leftover`.
- **Under a photograph the three gradient stops turn translucent**; the tab strip reads
  `--dim-hero-strip`, the opaque middle stop, or white tabs vanish on a pale strip - the first
  demo render had exactly that. The focus ring inside the hero is `--dim-hero-ring`, which is the
  INK: a fixed white ring is invisible on any light band a manager may now pick.
- **The route re-asks both gates, core's first, for whoever asks.** `branding::resolve()` mirrors
  `publicfiles::resolve()`: context level, id match, `viewer_may_see()` (`is_uservisible()` then
  `category_access::filter_categories()`), hash and name, `is_valid_image()`. The area's file is
  looked up by the CATEGORY ID, which is what makes the level check load-bearing (the twin
  category test of `branding_test` seeds a category under a course's own id with
  `insert_record_raw(..., true)` and a cache purge). A visitor is refused until stage 5 adds the
  public branch beside its own gate.
- **The cached payload is exactly `CACHED_KEYS`.** It is shared by every viewer, so it holds the
  row and the image's name and nothing a viewer's rights decide; `branding_cache_holds_no_count`
  adds a `total` and `branding_test` reads the keys back from the cache itself.
- **`file_save_draft_area_files()` is called only with a real draft id.** A zero draft area is
  "no files" to core, which then empties the target area; `save()` skips the call, and the
  form test submits `heroimage => 0` for that reason.
- **The cached pair is the RESOLVED one, so the cache is dropped whole** (round B, 2026-10-11). A
  category without `owncolours` draws the pair of its nearest ancestor that has one
  (`branding::colours()`: one statement for any number of categories, ancestors-or-self joined on
  the stored path, the deepest row with `owncolours = 1` winning, the pair taken whole from that
  row). An entry therefore depends on every ancestor's row, and `save()`, `delete_for_category()`
  and the `course_category_updated` observer (a move: `change_parent()`, and `delete_move()` per
  child) call `forget_all()`; there is no per-category forget any more. A grid asks
  `for_categories()` once, so a cold grid pays the colour lookup once (gate `inherit_batched`, held
  by a slope against a grid of cards with pairs of their own). **5.2's `sql_like()` refuses a `%` in
  its second argument** with a debugging notice: the `'/%'` suffix of the path match is a bound
  parameter. Unticked, the editor's two colour fields are `disabledIf` the box (not hidden: they show
  the inherited pair, from `form_data()`), are neither validated nor stored, and every Behat scenario
  that types a colour ticks "Own colours" first. The upgrade read a stored default pair (`#070093` on
  `#ffffff`) as "none of its own".
- **Screenshots of a hidden Browser pane lag.** While the pane is hidden the screenshot tool
  returns stale frames: the tab strip read as empty on three captures while the DOM, computed
  styles and `elementsFromPoint()` all placed the tabs. Scroll a notch and back before a capture
  that has to be believed, and prefer DOM checks for anything decisive.

## The category showcase: the anonymous shell (stage 5)

Stage 5 built `category.php`, `classes/route/controller/category.php`, `classes/local/category/{request,publicaccess}.php`,
the address helpers in `landing/links.php`, `redirector::category_page_target()`, the `after_config`
forward, `opengraph::for_category()`, the public branch and the og area of `branding`. What the code rests on:

- **One decision, `request::handle()`, in an order that is a security property**: a logged-in session
  (the guest included) is sent to the showcase; a visitor is refused before any lookup unless
  `publicaccess::is_public()` says yes; only then the lookup, the wiring, the population. The guest goes
  to shell A because core counts it as logged in and `population::scope()` refuses an anonymous
  scope inside a logged-in session. The script 303s to the route before any lookup while the router
  is configured, the controller is GET-only with a plain integer parameter and no `requirelogin`.
- **`publicaccess::is_public()` folds the switch in** (`categorypublicenabled`, only a stored `0` is
  off) and fails closed, so the page, the forward, the image route and the head tags all obey the
  one answer; `redirector::is_category_ghosted()` still fails open, on purpose.
- **Every address of the listing goes through `links::showcase()` / `showcase_form()`**: the chips,
  the pager, "clear filters", the toolbar's form and the hero's fold form all target the shell that
  drew them. A GET submission replaces the action's query string, so the id travels as a hidden field
  (`categoryid` on shell A, `id` on the script) or in the route's path (no hidden field at all).
- **Do not read a renderer class constant before a renderer exists.** `course_renderer::BODY_CLASS`
  autoloads the theme's course renderer, whose parent `core_course_renderer` lives in
  `course/renderer.php` and is loaded by the renderer factory, not the autoloader: the anonymous
  route died with "Class core_course_renderer not found" the first time it ran. The constant lives on
  `category_listing` now and the renderer aliases it.
- **The og derivative of a category names the CATEGORY's context even when the source is the
  site-wide fallback**, so a category URL never has to name the system context; the route resolves
  the source by category id and the hash is the cache-buster. The visitor's answer is cacheable in
  public (it depends on the stored state alone); a logged-in requester's stays private.
- **`format_text()` on the way to the head tags initialises the page's theme.** A test that builds
  the renderer after `opengraph::for_category()` (whose first-paragraph path formats the public
  description) fails on `force_theme()`: build the renderer first, as `core_renderer_test` already
  had to for the login panel.
- **The feature's kill switch is a hierarchy.** `publicaccess::enabled()` asks `population::enabled()`
  first, so `categorypageenabled = 0` closes the anonymous shell, the forward, the image route and
  the head tags with the logged-in shell (plan property 4); `categorypublicenabled` closes the
  anonymous half alone. The critic round found the first draft answering visitors with the full
  showcase while the logged-in page had reverted to Boost Union's - the one internet-facing surface
  MORE exposed with the feature "off".
- **A gate on an anonymity flag must mutate a reachable clause.** `$showstar = !$anonymous &&
  isloggedin() && ...` looks like three guards, but an anonymous page never exists in a logged-in
  session (`population::scope()` refuses it), so dropping `!$anonymous` changes nothing any test
  can reach and the gate reads green forever. `public_shell_no_star` forces the star on for
  everyone instead, which the visitor tests read. Apply every new gate to a copy and ask which
  test reddens BEFORE trusting the sweep's count.
- **A visitor's card has no link to a page that answers a visitor with the login form.** A public
  course without a landing page model has nowhere a visitor may go: the title is plain text
  (`hasurl`), the CTA absent; the course contacts are named to a logged-in reader only, as the
  landing page names nobody to the internet, while the summary excerpt is shown as the landing page shows it.
- **Every image a PUBLIC page emits goes through the theme's own file route, never core's.** Core's
  `course/overviewfiles` and `course/summary` run `require_login()` inside `file_pluginfile()` while
  the site forces login, so a visitor's browser fetches the LOGIN PAGE where a picture should be -
  303, `text/html`, and a card with no cover. The landing page knew (its `hero_image_url()` docblock and
  `landing_page_test` both say so); the category listing did not, because `cards::images()` was
  written for the logged-in page and reused unchanged, and it shipped through stage 6 with a green
  suite. **That is the shape to look for: a builder written for the logged-in shell, called by the
  public one.** Nothing catches it locally - every browser session is logged in and PHPUnit fetches
  no image - so the address has to be asserted, on both shells, in the same test. Fixed 2026-09-15
  with `publicfiles::AREA_COVER`; the reasoning is D32 in the original design record (not part of this repository).
- **`webserver` is the Behat site.** From inside the stack `http://webserver/` answers the
  acceptance site, not the dev site, and `host.docker.internal:8502` redirects to the `wwwroot`
  the container cannot reach: a real-Chrome screenshot of the dev site through the Selenium grid is
  not available; the Browser pane (after a scroll, see stage 4) and `curl` are the proof.
- **The ROOT of the course index is a category this shell serves too, since round E2**, and the
  three facts it rests on are core's, not this theme's. `$PAGE->set_category_by_id(0)` **throws**:
  `load_category()` does a bare `get_record()` on category 0 and raises `unknowncategory`, and the
  call would then ask for a `coursecat` context that does not exist - so the root branch of
  `request::handle()` sets the SYSTEM context instead, and a hand mutation putting the call back
  answers the route test with a 500. `core_course_category::get(0, MUST_EXIST, true)` needs no
  branch at all: it returns `top()`. And `top()->get_formatted_name()` is the literal word "Top",
  which is why `opengraph::for_category()` must never be handed the root - `for_root()` is a
  sibling reading the site, with no `og:image` at all (R2) - and why the hero titles the anonymous
  root with the site's full name.
- **The root's "public" is the INVERSE convention of every other switch in this class.**
  `publicaccess::enabled()` is on unless an explicit `'0'` closes it; `publicaccess::root_enabled()`
  is off unless an explicit `'1'` opens it (`categoryrootpublic`). The two readings sit four lines
  apart and are both deliberate: a site upgrading into round E2 never wrote the new setting and
  must keep D27's root - never public - until an administrator decides otherwise. Read a default-off
  checkbox for its stored `'1'` and never as `get_config(...) ?: 0`.
- **The population's admitted set is not the directory's card set, and only one of them is right
  for the internet.** `admit_for_visitor()` admits a LISTED top-level category as well as a PUBLIC
  one, correctly - the population's course rows are already restricted to public courses by the
  statement - but a listed category has no page a visitor may open, so `directory()` filters the
  anonymous root's children through `category_discoverability::are_public()` (one bulk call, as
  `subcategory_tree()` does for `linkable`). A test of this rule needs the listed sibling as its
  control or it passes against a directory that draws everything and against one that draws nothing.
- **A builder written for the logged-in shell and called by the public one is this feature's
  recurring defect shape.** `directory()` hardcoded `links::showcase($childid, [], false)`, which
  was invisible while the root was never public and would have pointed every card a visitor saw at
  `/course/index.php`; `cards::images()` had the same shape in stage 6 (D32, the visitor's card
  cover). When a surface becomes anonymous, grep the builders it reuses for a hardcoded shell.

## The category showcase: the visual refinement (between stages 5 and 6)

Presentation only - `scss/category.scss`, the four `category_*` templates and the badge class
strings - finishing the page to the mockup of the original design record (not part of this repository),
whose status block under **Visual refinement** holds every departure and every measured number.
What a maintainer must not lose:

- **The theme's compiled CSS is cached per theme revision, and editing a `.scss` file does not
  bump it.** `theme/styles.php` keeps answering with the compile of an earlier day, so an edit
  can be invisible in the browser and in a screenshot harness while it is plainly in the source.
  `mdl purge <stack>` (or `admin/cli/purge_caches.php`) is what invalidates it. Then two more
  facts: the FIRST request after a purge is answered with Boost's prebuilt fallback sheet
  (3.6MB, none of this theme's rules) while the real one compiles behind it, and **if the SCSS
  fails to compile, that fallback is what gets cached** - the site then renders with no theme CSS
  at all and nothing says why. Compile once on the CLI to see the error:
  `php -r 'define("CLI_SCRIPT",true); require("/var/www/html/config.php");
  echo strlen(theme_config::load("dimensions")->get_css_content());'`. A Sass error is
  never a message on the page; it is a page with no styles.
- **SCSS cannot add rem to px.** `$dim-bleed + 1rem` is a compile error, which by the rule above
  presents as a theme with no CSS. Keep the geometry arithmetic in one unit.
- **Never `clip-path` on the tab strip.** The design cuts the navy block's bottom-left corner by
  18px, and a clip on `.dim-hero__tabs` would clip core's "More" dropdown, which overflows it.
  The cut is a page-coloured triangle (`@mixin dim-chamfer`) on whichever element closes the
  block: the strip when the hero has tabs, the band when it has none and at phone width, where
  the rail leaves the block. Its `z-index` is the band's stack - over the photograph (0) and its
  overlay (1), under the hero's own content (2), far under the dropdown's 1000.
- **In the hero band, state the stack.** The `<img>` is a LATER positioned sibling than the
  overlay pseudo-element, so with everything at `z-index: auto` the photograph paints ON TOP of
  the overlay that darkens it: the overlay is dead paint and the eyebrow reads about 3:1 on a
  bare photo. Image 0, overlay 1, content 2.
- **`--bs-primary-text-emphasis` cannot be read inside the tab list.** Boost Union's 5.2
  `core/moremenu` override stamped `data-bs-theme="light"` on the more menu's own `<ul>` (5.3 core
  and Boost Union's `upgrade-503` do not, but any theme's override may), so every `--bs-*` token read
  below such an element is the LIGHT one - the active tab came out
  `#03003b` on `#1d2125`, 1.2:1. Resolve such a token on `.dim-hero` and let it inherit.
- **`.dim-hero__tabs .moremenu` must keep a FIXED height.** `height: auto` silently disables
  core's more-menu collapse everywhere on the page: `autoCollapse()` decides by comparing the
  list with `menu.parentNode.offsetHeight + 1` (`lib/amd/src/moremenu.js:58,64`), and a nav that
  grows with its list is never taller than itself. Measured at 390: six manager tabs on three
  wrapped lines. The link's own height is arithmetic beside the 40px (9 + 9 padding + a declared
  `.875rem × 1.45` line box = 38.3px); change one and check the other. For the same reason the
  list keeps Bootstrap's `flex-wrap: wrap` - wrapping is the signal the collapse reads.
- **A dark-mode rule still names the body class.** Moodle 5.2's one dark mechanism is
  `data-bs-theme` on the html element, so the selector is
  `:root[data-bs-theme="dark"] body.dim-category …` and nothing shorter:
  `category_scss_test::test_every_top_level_selector_is_scoped_to_the_showcase` accepts that
  prefix and no other, because the file is concatenated into the theme's main SCSS and reaches
  every page of the site. Keep the dark blocks at the FOOT of the file: the badge test splits the
  stylesheet at the first dark selector and reads everything before it as the light half.
- **The full bleed of the navy block rests on four measured Boost numbers**, named as SCSS
  variables at the top of `scss/category.scss` with their source lines: `#page.drawers`' 3rem of
  side padding, `.main-inner`'s 8px, the main region's 15px and the 1200px column. The technique
  is that the 1200px cap lives on `.main-inner`'s CHILDREN rather than on `.main-inner` itself -
  a capped, auto-centred `.main-inner` moves under the header by an amount that grows with the
  viewport, which no fixed negative margin can cancel. Nothing in the pipeline reads a computed
  box, so after a Boost Union update measure `band.left === 0`, `band.right === innerWidth` and
  `title.left === firstCard.left` at 390, 1280 and 1600 before believing the page.
- **Boost paints two hardcoded whites under every page** - `#page.drawers .main-inner`
  (`theme/boost/scss/moodle/layout.scss:44`) and `#region-main` - and neither has a dark variant.
  Both are cancelled on this page, or the dark showcase sits on a white sheet and the
  page-coloured chamfer reads as a black notch. Any new dark surface of this theme should check
  for a third.
- **The theme loads no webfont.** The mockup is set in Outfit with Noto Sans for long-form text, but
  the optional Google Fonts setting was removed (it broke the colour settings and the category page
  scripts), so a site renders in the system face unless the fonts are installed; a capture of it
  looks nothing like the record for reasons that have nothing to do with the CSS. The design
  record's capture harness loads the two faces from Google Fonts itself. Judge type against a capture that has them.
- **Core prints no breadcrumb once `/course/index.php` carries any parameter besides
  `categoryid`** (`view=`, `q=`, `status=`, `chip=`: one breadcrumb without, none with, measured on
  m502), and every control of the toolbar is a GET. So the hero's eyebrow is its own trail
  (`category_hero::trail()`, from the category's path), never core's navbar restyled: the first
  version of this stage did exactly that and lost the eyebrow on the first click. A visitor's trail
  names only public ancestors (D14); the gate `hero_trail_visitor_public_only` pins it.
- **At 390 the tab rail clipped its own "More" dropdown** (a 423px menu inside a 50px scroll box,
  because the rail was `overflow-x: auto` and Popper positions the menu inside it). Fixed with the
  filter line (stage T2): the rail is no scroll container at any width - core's more-menu already
  collapses what does not fit, so it has nothing to scroll - and it stacks at `z-index: 31`, above the
  toolbar's 30. See the filter line's section.
- **A control's boundary is not a card's line.** WCAG 1.4.11 asks 3:1 of the visual information
  that identifies a control, and the design's line token (`--bs-border-color`) reads 1.30:1 light
  and 1.98:1 dark. The search field and the quiet summary button use `--dim-control-line`
  (Boost's own `--bs-gray-500`, 3.02:1 and 5.37:1); `--dim-line` stays for card edges.

## The category showcase: the web services and the client (stage 6)

Stage 6 built `classes/external/{get_category_rows,get_card_details,toggle_wishlist}.php`,
`classes/local/category/{guard,rows,enrichment,wishlist}.php`, `classes/event/wishlist_updated.php`,
`amd/src/categoryvitrine.js` and the template hooks it addresses. What the code rests on, and what
the traps cost:

- **The guard validates a course's CATEGORY context, never the course context.** `validate_context()`
  ends in `require_login($course, false, $cm, false, true)`, which throws `require_login_exception`
  for anybody not enrolled - and the showcase exists to show courses the viewer is NOT enrolled in.
  Implemented the literal way first and measured on m502: the refusal matrix came back with
  `requireloginerror` instead of the one refusal, so the whole "every miss answers alike" property
  was gone. The category context is where the right is actually held (`moodle/category:viewcourselist`,
  which `can_view_course_info()` asks for two lines later). Gate `ws_context_derived`.
- **And it derives that context with a NON-THROWING lookup, which is the difference between one
  refusal and an enumeration oracle.** `\core\context\coursecat::instance($id)` and its course twin
  default `$strictness` to `MUST_EXIST` and forward it to `get_record()` when no `{context}` row
  exists - exactly the nonexistent-id case - so a bare call answers a made-up id with
  `dml_missing_record_exception` while a withheld one reaches the deliberate refusal below: two
  exception classes and two messages, which is a client able to tell "no such category" from
  "not for you" over every id on the site. `guard::category()`/`course()` pass `IGNORE_MISSING` and
  read the `false` return as the refusal. The whole point of the guard is that a nonexistent id, one
  core hides, one the plugin withholds and one in another tenant are the SAME answer with the SAME
  message, which is what the refusal matrix in `guard_test` and `toggle_wishlist_test` asserts by
  comparing every case against the first one's message. That refusal is `categoryunavailable` since
  stage 7 - the page's own key, not a second one carrying the same sentence.
- **The site course passes both gates unaided**: `can_view_course_info()` returns true outright for
  `SITEID` and its category is 0, which every category predicate answers for by default. It is
  refused explicitly as the first line of `guard::course()`, or the feature's one write can be aimed
  at the front page. Gate `ws_course_guard_site_course`.
- **Card text is `PARAM_RAW`, deliberately, and that is not weaker than page one.** `PARAM_TEXT` is
  validated on the way OUT: `clean_returnvalue()` throws `invalid_response_exception` for the whole
  response whenever `strip_tags()` would change the value, and the card's excerpt and programme
  lines are `html_entity_decode()`d out of the course summary, so one course whose summary holds
  `&lt;3` would kill the page for every reader. Strings the theme composes from a language string
  stay `PARAM_TEXT`. The client renders these through the same Mustache double stashes the server
  does, and a double stash escapes for itself.
- **`get_card_details` validates the SYSTEM context**: a batch spans up to 24 courses, so there is
  no single course context, and each course's own would hit the `require_login()` above. The
  per-course question is asked inside `enrichment::details()` - core's visibility pass, then the
  plugin's - and an id the viewer may not see is DROPPED, never named.
- **`perpage` is a request parameter of the rows function, not only of the address.** The showcase
  honours a `perpage` in its query string (`criteria::from_request()` reads it, `query_params()`
  re-emits it into every pager link), so a listing can be open at a size the site setting never
  mentions. Without it on the web service, page one of a `?perpage=6` listing comes back cut at the
  site default - and `population::clamp_page()` puts a page past the end back onto the LAST one, so
  the client asking for page 1 gets page 0 of a 48-row listing: the whole listing appended over the
  rows already on screen, duplicate `id` attributes and two stars per course. Nothing in the payload
  says it happened. Gates `ws_rows_page_size_reaches_criteria` and `js_rows_carry_page_size`.
- **`nexturl` has exactly one spelling**, `category_listing`'s own export. It was written twice for
  one commit - once in the listing and once in `rows::fetch()` - and only the listing's copy had a
  test that gave it a query and a page size to carry.
- **The client is armed for a real account only.** The guest is routed to this shell on purpose
  (`request.php`: core counts it as logged in), and all three services refuse it - so an armed
  module hides the no-JavaScript "Filter" submit and the numbered pager, which are the only controls
  a guest HAS, and answers every click with an exception dialogue. The fix is the `isloggedin() &&
  !isguestuser()` around the one `js_call_amd` in `course_category()`, never a loosened refusal.
  Gate `listing_client_armed_for_real_account`.
- **The module addresses `data-*` hooks and never a class name.** Four class selectors survived the
  first draft - the pill track, the details panel the peek clones, the title link focus lands on and
  the two view buttons - and three of the four had no test at all, so a design rename would have
  disarmed them in silence. `card_markup_test::test_the_client_modules_hooks_are_written_by_these_templates`
  renders all three templates and asserts every hook.
- **A disabled button cannot be focused, and disabling it already blurred it to `<body>`.** The
  load-more handler disabled the button, awaited the response, focused it, and only then re-enabled
  it in its `finally`: the focus call was a silent no-op and the reader who pressed it was left at
  the top of the document. `restoreMore()` runs before `focus()`. Behat's
  `the focused element is "Load more" "button"` is the observer - a mutation proved it.
- **An appended page is state of the document and has no address.** `state.page` follows the address
  and moves only when the grid was REPLACED; `lastpage` follows the grid and is what the next append
  asks after. Writing the appended page into the query string hands out a link to a document showing
  that page alone, with the numbered pager hidden and no route back to the pages above it.
- **The `view` parameter is written only when it was chosen.** Stamped into every link, a shared
  address overrides the recipient's own stored preference with the sharer's.
- **`settle()` removes an id from `pending` as well as adding it to `settled`.** The observer keeps
  firing while a batch is in flight, so a card that scrolls out and back is put BACK into pending
  after the batch took it out - and the next 100 ms drain asks for it again. And a replaced grid
  unobserves the cards it discards: `IntersectionObserver` holds its targets strongly, so a filter
  change leaks every enrolled card it discarded before that card was ever asked about.
- **A JavaScript mutation that is not rebuilt proves nothing**, and `mdl grunt` exits 0 when eslint
  fails. Every hand mutation of this module copies the build first, rebuilds, and asserts the build
  CHANGED before believing a red or a green. `amd_source_test::test_the_committed_build_was_made_from_the_committed_source`
  closes the standing half of it: it compares `amd/build/…​.map`'s `sourcesContent[0]` with `amd/src`
  byte for byte, so any unbuilt edit reddens. The three-string-literal check it replaced could only
  ever have caught a build older than this stage.
- **`local_unlistedcourses` answers TRUE WHOLESALE while nothing on the site is unlisted, and that
  decides which layer refuses the site course.** The theme's own guard refuses `SITEID` explicitly,
  because core (`can_view_course_info()` returns true outright for it), `filter_courses()` and the
  category term all admit the front page - its category is 0, which holds no row. But every test in
  the refusal matrix seeds an unlisted category first, which makes the plugin start evaluating, fail
  closed on the pseudo-category and refuse the site course one step EARLIER - so the explicit check
  is never reached there and its mutation gate reddened nothing. It is reached, and is the only
  refusal, on an ordinary site with nothing unlisted, which is what
  `guard_test::test_the_site_course_is_refused_where_the_plugin_admits_everything` sets up, with all
  three preconditions asserted. The general rule: a test that seeds an unlisted anything is testing
  a DIFFERENT plugin configuration from a site that has none, and a guard can be held by one and
  unreachable in the other.
- **A gate whose pattern matches nothing takes the WHOLE sweep down.** `card_state_badge_text_utility`
  had been dead since the visual refinement moved the badges from Bootstrap utilities to the theme's
  own tokens, and `mdl mutate` treats a no-op mutation as a hard exit - so a full-spec sweep exited
  at that line and never reached this stage's gates below it. Both implementers used `--only`, which
  is why neither saw it. After any change to a file a gate names, re-run the cheap check: apply every
  script in `gates.conf` with `perl -0777 -p` and assert each one changes its target. All 165 of
  them did on 2026-09-15.
- **A budget written as a ceiling has to be ratcheted to the measurement.** The details batch measured
  2.75 statements per course and the ceiling was four, so one extra read per course (66 -> 90) passed
  under 96 and only a SECOND would have failed - the docblock claimed a guard the number did not hold.
  It measures the SLOPE now (four ids against twenty-four), which removes the batch's fixed cost from
  the number entirely.
- **A Behat scenario over a two-course fixture at `perpage=1` cannot tell "the next page" from "the
  whole listing".** Both answers put both names in the grid. The scenario uses three courses and
  counts the cards (`I should see "2" node occurrences of type "article" in the ".dim-vitrine__grid"`),
  and was mutation-checked against the very defect it was rewritten for.
- **A `toggle` event does not bubble, so a delegated listener must be registered in the CAPTURE
  phase.** The grid's "one panel at a time" rule listens for `toggle` on the listing root, which
  is the only way a card appended by the load-more path behaves like a card the server drew - and
  with the third argument left off, that listener is never called at all. Nothing errors and the
  page looks entirely correct; every panel simply stays open, which is the defect the rule exists
  to fix. `amd_source_test` asserts the flag by shape for that reason.
- **The hover peek and the disclosure show the SAME panel, so "a peek closes the disclosures" eats
  the panel the reader just opened.** The pointer is still inside the card whose summary was
  pressed, so the next `mouseenter` promotes a peek and the peek closes the disclosure under it -
  on a card the reader never left, with no way to keep it open. `openPeek()` therefore refuses on
  a card whose own disclosure is open, which is also what makes the Behat scenario deterministic.
- **The offline `grunt amd --root=<plugin>` rebuilds EVERY module of the plugin, not the one being
  edited.** Two people working in one checkout each rebuild the other's module, so whoever edits
  an `amd/src` file last has to rebuild after that edit or `amd_source_test`'s build-currency
  assertion is red on somebody else's module.
- **Judge a JavaScript mutation by whether the BUILD changed, never by a test going red.** Removing
  the `details && details.open` term from `openPeek()` left `const details` unused, eslint aborted
  grunt, the build stayed as it was - and the run still came back red, at
  `test_the_committed_build_was_made_from_the_committed_source` and not at the assertion the
  mutation was aimed at. A mutation that cannot build proves nothing about the assertion it was
  written for; invert the condition instead of deleting the term, and compare the build against
  its saved copy before reading any verdict.
- **A legacy core class on a web-service path must be required by the delegate itself.**
  `completion_info` (`lib/completionlib.php`) is not autoloadable, `lib/ajax/service.php`
  bootstraps nothing that includes it, and every PHPUnit test of `get_card_details` was green
  because the test bootstrap had loaded the file. The first real request from the browser died
  with `Class "completion_info" not found`. `enrichment::details()` carries the `require_once`,
  the source test pins it and the gate `ws_details_completionlib_required` removes it. Before
  shipping any web service, load its page once in a browser - a test process is not a request.

## The category showcase: the owner's round after the browser session (stage 6b)

Four decisions the owner asked for after trying the page, recorded as D24-D27 in
the original design record (not part of this repository): the write's guard keeps the COURSE term alone, the write logs once per
real change, the chip strip becomes one subcategory selector over the whole admitted subtree, and
the ROOT of `/course/index.php` becomes a directory of category cards. The traps that cost time:

- **The root resolves the WHOLE SITE, so anything a category page does per request it would do per
  site.** `population::scope(0)` takes `top()->get_all_children_ids()`, which is every category
  there is. The first implementation then ran the entire listing pipeline over that scope and threw
  it away: `classifier::classify()` loading every enabled `{enrol}` row of every course on the site,
  `facets::values()` selecting every `{customfield_data}` row of every course on the site, then the
  sort, the page slice, the subcategory tree and the card names - while `category_listing` at the
  root reads `directory` and nothing else. **A statement count cannot see this**: every one of those
  is ONE statement whose row count is the site, so the budget test's slope stayed flat and green.
  `population::resolve_root()` is the short circuit; what pins it is a structural test (no rows, no
  facets, no tree, `counts` of one key) plus a read comparison against the category page, never a
  read count on its own.
- **A page with no toolbar must read no filter, and the two page-cutting modes make that a
  correctness bug rather than a tidiness one.** The root draws no search box, no chips and no clear
  link, but `criteria::from_request()` still read `q`, `chip` and `cf_<id>` there - and the two
  implementations of the directory tally disagree about them: the PHP cut tallies `$kept` BEFORE
  `facets::apply()` runs, while the SQL cut groups over the very predicate the field filters were
  folded into. So `/course/index.php?cf_5=9` printed one set of card numbers with
  `categorystatesort` on and a different set with it off, from the same data, with nothing on the
  page admitting it. `resolve_root()` resolves a plain `new criteria(0)` whoever asks; the renderer
  builds one too, which is what keeps the phantom filter out of the hero's fold form.
- **The decision layer owns every refusal, including the root's.** The root is never public, and the
  only thing keeping it off the internet was `course_renderer::showcase_category()` returning null -
  a renderer-level check, so any second caller resolving category 0 in a guest session would have
  got the whole tree. `population::scope()` now refuses it too (`!isloggedin() || isguestuser()`),
  and the renderer's check stays as the fallback to core's page, which is the right behaviour there.
- **The probe cap is per REQUEST, so at the root it spans the site.** `PROBE_CAP`/`RESCUE_CAP` = 50
  bound how many unrelated unlisted courses the plugin is asked about; on a category page that is
  one subtree, at the root it is everything. The directory's numbers inherit it and under-report,
  fail-closed and silently, past fifty. Kept deliberately (D27) and pinned by
  `population_test::test_the_root_directory_counts_are_capped_with_the_probe` in both modes - the
  two cap through different code, the PHP gate's probe and the SQL predicate's bounded rescue list.
- **`core/aria`'s `hide()` does three things, and clearing the attribute undoes one.** The
  subcategory module's fallback - the path taken when core's autocomplete resolves without drawing
  anything - was putting the select back visible, announced, and with `tabindex="-1"` still on it,
  because `Aria.hide()` stores the tabindex, sets it to -1 and leaves a MutationObserver watching
  it. A control reachable with a pointer and by nothing else, in the exact state the fallback exists
  for. `unhide()` is core's own reverse and undoes all three. Core also prepends a blank `<option>`
  of its own to every single select before rendering, so a failed enhancement leaves one behind; it
  carries no `value` attribute, which is what tells it apart from the template's placeholder.
- **A native `<select>` fires `change` on every arrow key while closed.** Core's autocomplete fires
  it on an explicit selection only, so submitting the form from the change handler is right for the
  enhanced control and wrong for the plain one: in the fallback a reader pressing Down would be
  navigated to the first subcategory without ever choosing it. The select carries
  `data-dimenhanced="1"` only on the branch where a control was actually found, and `onJump()`
  returns without it.
- **The em-space indentation is the option's text, and `format_string` never sees it.** Depth is
  drawn with U+2003 in front of the name (`category_listing::INDENT`), which HTML does not collapse
  and which core copies into its own suggestion markup. A Behat step asserting an indented name must
  therefore not assume a plain space, and the plain `<select>` and the enhanced list spell it alike.
- **The mutation spec is read as one file and a pattern that matches nothing is a hard exit**, which
  takes the rest of the sweep with it. Moving code between methods changes indentation and breaks
  anchored patterns silently: `root_directory_counts_gated` and `..._sql` both had to be rewritten
  when the root moved into `resolve_root()`, and `killswitch` had to be rewritten when D27 removed
  the `!$categoryid` term it matched. Dry-match every pattern against the tree before a sweep - a
  loop over `gates.conf` running `perl -0777 -pe <script>` and comparing with the original file
  costs seconds and names the dead ones.
- **A test whose fixture is "one category" is not a test of an empty directory.** A Moodle site
  always carries the default Miscellaneous category, which is a top-level card like any other, so
  emptying the root means withholding every row of `course_categories` with `parent = 0`.

## The category showcase: the owner's round B, the editor half (2026-09-17)

Six things the owner asked for after trying the page; this section is the editor's half (A1-A6),
recorded in the original design record (not part of this repository) under **Round B**. What a maintainer must not lose:

- **Adding 0 to a numeric vocabulary makes every cast of a non-value a valid choice.** `OVERLAYS`
  became `[0, 60, 78, 90]`, and from that moment `(int) ''`, `(int) 'transparent'` and
  `(int) '78.5'` were all the owner's "Transparent": a submission outside the vocabulary would
  have been STORED as "no colour over the photograph" rather than refused, which is the loudest
  wrong answer of the four and the only one that looks deliberate. It surfaced as the form test's
  out-of-vocabulary case going green, because a `<select>` hands an unknown choice back as `''`.
  `branding::overlay()` compares before it casts and is the one reader of that vocabulary;
  `hero_overlay_vocabulary` is the gate. **Any vocabulary that gains a falsy member needs the same
  pass**, and the tell is a refusal test that starts passing for a new reason.
- **A message shown only by this theme's stylesheet reads as VISIBLE under Behat.** The Behat site
  serves theme CSS built at `init.php` time and nothing in a scenario rebuilds it, so the
  `invalid-feedback` element of the colour fields is shown and hidden by toggling Bootstrap's own
  `d-block` (with the `hidden` attribute beside it for the accessibility tree), never by a rule of
  `scss/categoryhero.scss`. The first draft used a rule of our own and the "should not see" step
  failed against code that was already correct.
- **`og:description` has exactly one source, and the shape of its defect is a preview that
  WORKS.** `opengraph::for_category()` reads `publicsummary` and nothing else, as `for_course()`
  reads the landing page's reviewed field. Reading the tagline again - which is what the gate
  `og_category_from_summary` writes - puts a line under every shared link of every category that
  has a tagline, and nothing on any screen says it was not reviewed for that audience. With the
  fallback gone, `for_category()` no longer calls `format_text()` at all, so the stage-5 rule that
  a test must build the renderer before calling it no longer applies to the category path.
- **The category description on this form is CORE's field, and its editor options are core's.**
  `classes/form/categoryhero.php::description_editor_options()` is a copy of
  `core_course_editcategory_form::get_description_editor_options()`, and a test compares the two
  rather than restating them: both forms write into the same `coursecat`/`description` area, item
  0, so a narrower `maxfiles` or a cleaner `noclean` here would rewrite what a colleague saved on
  `/course/editcategory.php`. The save hands `core_course_category::update()` ONLY the description
  keys - it acts on `name`, `idnumber`, `theme`, `visible` and `parent` whenever those keys are
  SET - and the page's own source is what a test and the gate `categoryhero_description_saved`
  hold, because a page script cannot be executed from PHPUnit.
- **`tiny_media` is enabled by the editor element's DEFAULT, not by anything a form writes.**
  `MoodleQuickForm_editor::$_options['return_types']` is 15, which already carries `FILE_EXTERNAL`,
  and `tiny_media::is_enabled()` needs `maxfiles` OR an external return type - so the media
  controls are on for every editor on the site unless somebody sets `return_types` explicitly.
  Measured on m502, 2026-09-17.
- **And on 5.2 the multimedia dialogue cannot open on an editor that accepts no files.**
  `$fpoptions` is built by `lib/form/editor.php` only when `maxfiles != 0`, and `MediaEmbed`'s
  constructor dereferences it unguarded (`.../tiny/plugins/media/amd/src/embed/embed.js:41,49`)
  where `MediaImage` tests `typeof options !== 'undefined'` first (`image/image.js:56-62`);
  `MediaManager` has the same shape (`manager.js:40`). So the image button opens URL-only and the
  multimedia button and the media manager throw. It is a core defect, `return_types` cannot reach
  it, and the only lever is `maxfiles` - which at 1 with `return_types => FILE_EXTERNAL` gives a
  repository list of `areafiles, url, wikimedia` and no upload (measured), at the price of a draft
  area this text has no writer for.
- **The public page's own text already runs the filters**, so a pasted YouTube address plays
  without anything in this theme turning it into a player: `request::handle()` calls `format_text()`
  with its defaults, where `filter` is true and `noclean` is false. A route test cannot read it out
  of the response body - the hero is drawn inside `$OUTPUT->header()`, which renders nothing under
  PHPUnit - so it reads `category_hero::current()`, the registry the request arms.
- **A JavaScript mutation that leaves a function unused never builds**, which the fleet file
  records and this round paid again: deleting the two `addEventListener` lines made `mark` unused,
  eslint aborted grunt, the build did not change and the scenario would have read green against
  the old module. Break a condition instead - and check the build really changed before believing
  either colour.

## The category showcase: the crests and the card geometry (round E1, 2026-09-17)

Round E1 gave a category three badge areas of its own, put crests on the course card, the
directory card and the hero, and made the card grid three fixed tracks. The status block with
every departure and every measured number is in the original design record (not part of this repository) under **Round E1**;
these are the facts a maintainer must not lose.

- **A course card wears the COURSE's badges, a category surface the CATEGORY's, and neither ever
  borrows the other's.** They are different files, uploaded by different people, in different
  places: a course's are the landing page's four `customfield_picture` fields, a category's are the
  three file areas of `branding`. A course with no landing page model shows no crest and inherits
  nothing from its category; a child category inherits nothing from its parent. The rule reads
  like a nicety and is the one thing in this round that can go wrong while every page still looks
  deliberate, which is why it has three gates (`card_badges_are_the_courses`,
  `directory_badges_from_child`, `branding_cache_holds_badges`) and why the tests give the two
  sets DIFFERENT files: an assertion naming only its own passes against a card reading either.
- **Three AREAS, not one area with three item ids, because the item id is already spoken for.**
  `branding::image_url()` puts the CATEGORY ID in the pluginfile item id and `resolve()` compares
  it with the context's own instance; an item id carrying 1..3 would break that pairing and cost
  a fourth path segment. So the area name is the position, ordering and gap-closing are the order
  of `AREA_BADGES`, and **the editor's three pickers are named after the areas** - `form_data()`
  prepares and `save()` reads the draft under that same name, so an element renamed in the form
  stores a manager's crest nowhere at all, with the page still saving and the form reopening
  empty.
- **The badge route makes no image check, and the reason recorded for that decision is wrong on
  5.2.** B1 was taken on the analysis's claim that `stored_file::is_valid_image()` is
  getimagesize()-based and answers false for an SVG, so keeping it would 404 every crest.
  Measured here: core's `file_system::get_imageinfo_from_path()` PARSES an SVG for its `viewBox`
  instead, so a parseable SVG passes that check. The decision stands because it matches the
  landing page byte for byte; what it really costs is that a badge area serves whatever reached it,
  which `branding_test` asserts with a file core refuses as an image beside the hero area
  refusing the same bytes. **Whoever revisits this has the choice the analysis thought it did not
  have.** **REVERTED BY THE OWNER ON 2026-09-18 (round D2): the badge branch runs
  `is_valid_image()` exactly as the hero branch does.** The paragraph above stands as the record of
  why it was ever skipped; what changed is only the verdict on it - the exemption admitted nothing a
  browser can draw, and a file area serving a same-origin `.html` is the cost nobody was buying
  anything with. The landing page's own badge route runs the same check (and the same ghost rule as its page),
  pinned by the gates `files_badge_valid_image` and `files_badge_ghost`; `badge_route_valid_image`
  is the category route's.
- **Core forces `Content-Disposition: attachment` on every SVG it sends** (`send_file()`, unless
  the caller passes `dontforcesvgdownload`, which neither this route nor the landing page's does). Not
  measured in a browser this round; it is the same code path the landing page's badges have always
  taken.
- **The crests live in the card's BADGE ROW, not in the media band**, although the card view
  paints them over the image: one markup serves both views, and the list view moves them into the
  right-hand cluster at 24px beside the state badge, where the 56px thumbnail could not hold one.
  The stylesheet lifts them onto the band with an absolute position against `.dim-card`, exactly
  as it does the favourite star, and the offset reads `--dim-card-media` so the band's height and
  the crests' place cannot drift apart (`category_scss_test` pins both).
- **The grid is three named tracks and no longer a flex basis.** A flex item with any grow factor
  fills its line, which is what made a lone card stretch across the row; `1fr 1fr 1fr` with
  `min-width: 0` on the item says what the owner asked for directly. Two tracks below xl, one
  below sm - and the crests step down to 56px below lg, because three 80px crests plus a date
  tile need about 325px of card and a two-track grid on a 768px viewport gives 305px.
- **The counts moved into the left column of the hero** so the right one is the crests' alone.
  `.dim-hero__stats` is `display: inline-flex` for that: a block-level flex box would stretch the
  frosted card the width of the column, and `width: fit-content` is a value this fleet's
  stylelint has not been asked about.
- **The crests cost no statement per card, and the statement they DO cost is not this round's.**
  Measured on m502: exporting a page of cards with a crest each costs **0 reads** at four cards
  and at twenty-four, while the four bulk reads went from 15 to 35 over the same two sizes - one
  `{files}` statement per stored badge, inside `landingfields::values_many()`. Since R0
  (2026-10-27) every badge file of the call is one statement (`landingfields::crest_files()`), and
  `budget_test` holds the bulk reads flat with four crests per card. `budget_test` meters the export alone
  and primes the page's course contexts inside the meter, because `fixture::reads()` empties the
  context cache a real request still holds at that point and would otherwise charge each card a
  context statement and read it as a per-card cost of the crests.

## The category showcase: the toolbar, server side (round D1, 2026-09-18)

Round D1 rebuilt the toolbar in two rows, made "By category" the default organisation, added a
start-date filter and a navigation tree, and retired D26's subcategory select. The status block
with every departure and every measured number is in the original design record (not part of this repository) under **Round D1**;
these are the facts a maintainer must not lose.

- **A panel of the accordion is a REQUEST, not a section of this page, and that is what keeps the
  first paint flat.** The panels are the DIRECT children and nothing deeper, each one a listing of
  that child's whole subtree fetched through the existing `get_category_rows` with
  `categoryid = <child>` - so the child's own gate chain applies, there is no grouped SQL anywhere,
  and no second web service was needed. Two panels for categories on ONE PATH would list the same
  course twice, which is the whole reason the depth setting may not deepen the accordion however
  far it deepens the tree. Server-side a panel holds its hint and its link and **no rows**; the
  rows arrive in round D2, on open. A change that renders rows into the panels puts a page slice
  per child on the first paint, and `budget_test` is what says so (gate
  `accordion_headers_no_rows`).
- **The depth setting bounds the NAVIGATION and never the LISTING.** `categorytreedepth` stops
  `walk_subtree()`; the population is `scope->population_categories()` and is untouched by it. The
  two questions look alike and the second one is a discoverability rule, so they have a gate
  between them (`tree_depth_not_population`) and a test asserting both halves over one fixture: the
  tree stops at one level while the grandchild's course is still on the page and still inside its
  parent's number.
- **A date bound is an ISO string on the wire and a timestamp in the predicate, and the resolution
  happens once, in `criteria`.** Never `strtotime()` and never seconds added to a timestamp: a day
  is not always 86400 seconds long, and `criteria_test` pins two Brazilian daylight-saving
  transitions where it is not. The site timezone decides, not the viewer's, so the logged-in and
  the anonymous shells agree about what "today" is. `day_start($iso, 1)` is the EXCLUSIVE upper
  bound - midnight of the day after - so a course starting at any hour of the last day is inside
  the range.
- **The `c.startdate > 0` term of `date_sql()` is not redundant, and a lower bound does not imply
  it.** With only an upper bound set, "starting on or before the 19th" reads as true of every
  course that starts on no day at all, so the undated courses come back inside a dated range. Found
  by the test, not by review; the same rule is what the `recent` ordering states from the other
  side, through a portable `CASE` rather than `NULLS LAST`, which is PostgreSQL's alone.
- **The date predicate goes into BOTH page-cutting statements, and has a gate each.** `records()`
  for the PHP cut and `sql_predicate()` for the SQL cut, from one helper but appended at two call
  sites on purpose: two gates over one property (`date_predicate_php_mode`,
  `date_predicate_sql_mode`) are the only way to see a mode that stopped honouring it, and a range
  honoured by one mode and ignored by the other is one address answering two ways with only a
  setting between them.
- **The grouped tally is ONE statement and is shared.** In the SQL-cut mode the accordion's numbers
  and the anonymous tree's "holds a public course somewhere" rule are the same grouped count - the
  shape `resolve_root()` had before B3 retired it - taken once; in the PHP-cut mode the page's own
  population is tallied and rolled up the stored paths, and costs nothing. Both inherit the probe
  cap: past `PROBE_CAP` unrelated unlisted courses the population is short and every number taken
  from it is short with it.
- **A read-count comparison needs BOTH shapes warmed before either is metered.** The accordion's
  budget test first measured the flat listing at 31 reads and the grouped one at 8, because the
  flat one ran first in the process and paid every cold cache - so the accordion's cost came out at
  minus twenty-three and the assertion passed while proving nothing. `fixture::reads()` resets what
  a new REQUEST starts without, not what a new PROCESS does. Warm both, then meter.
- **The filter disclosure is closed on load, which changes what Selenium can reach.** Since the
  filter line (stage T2) it is closed on EVERY page, a narrowed one included - the row under the line
  says what narrows - where it used to open on the server for a criterion it held. What lives inside
  the native `<details>` is "More statuses", the custom fields and the period (the scope cells, the
  search and the star are on the line); a `@javascript` scenario must open the summary before it
  can click one of those pills or read its number, and a non-JavaScript scenario need not, because
  BrowserKit reads the DOM rather than what is painted.
- **The visible group label IS the legend.** The previous shape named every group twice - a
  `visually-hidden` `<legend>` for assistive technology and an `aria-hidden` span repeating it for
  the eye - and `category_listing_markup_test` now asserts that no such span exists, which is the
  only thing standing between that arrangement and its quiet return. The scope on the line is the
  one group whose legend is visually hidden: its cells are what the eye reads.
- **"Clear filters" keeps the organisation, and it is built in TWO places.** It drops everything
  that narrows and nothing that orders: `criteria::sort_params()` is what it carries, and
  `has_filters()` deliberately does not count the sort. A control that silently put the page back
  into the grouped default would send a reader looking for the chip they had just pressed.
  `category_toolbar` builds the one in the row under the filter line (labelled "Clear all" since
  stage T2, and drawn only from two criteria up) and `category_listing` builds a second, still
  "Clear filters", for the empty state, and the second one shipped this round carrying an empty array - correct before the
  sort existed, and invisible afterwards, because the only page that draws it is the page with no
  rows on it to show that the order had changed. Two builders of one rule need a gate on the
  quieter one: `listing_clear_keeps_sort`.
- **A category's default organisation is resolved in `criteria`'s constructor, from the one seam
  `criteria::default_sort()`** (round B, 2026-10-11): only the page's entries pass it (`from_request()`,
  and `rows::fetch()` for both web services); every request the code builds for itself - a panel, a
  slice, the root - names its organisation and gets none. `$sort` is the organisation IN FORCE and
  `sort_params()` leaves out the CATEGORY's default, never the theme's: on a category whose default is
  flat, "By area" travels as `sort=category` (gate `default_sort_names_grouped`), and the client's
  `buildUrl()` compares with the payload's `defaultsort` the same way. A per-person remembered order
  belongs in `default_sort()`, and makes the bare address mean something different per reader.
- **The first accordion group carries no number, and the reason is the pager.** The blueprint asked
  for the courses "directly in this category" in that group AND for "the page's own population,
  page-sliced as today"; the page's slice is cut over the whole recursive population, so the two
  cannot both hold. The group renders the page's slice, and any number on its header would either
  repeat the count line - children included, which the headers beside it count again - or
  contradict the rows under it. The consequence is stated in the template: a course in a child is
  reachable two ways, which is navigation rather than a second copy.
- **`amd/src/categoryvitrine.js` is untouched, and one thing about it is now stale.** Its
  `subcatselect` / `subcatform` selectors find nothing, because D26's select is gone; the module
  returns at the first line of `enhanceSubcategories()`, so nothing breaks, and `amd_source_test`
  still pins the behaviour of a control the page no longer draws. Clearing that belongs to round
  D2, with the client. **Done in round D2b on 2026-09-18**: the three functions, their two imports
  and the four tests are deleted, and one test asserts the absence in the source and in the build.

## The category showcase: the editor's two columns (round F, 2026-09-18)

Round F put the appearance editor's colours in a section of their own, beside the contrast panel,
and added a preview of the pair on the band. The status block with every departure and every
measured number is in the original design record (not part of this repository) under **Round F**; these are the facts a maintainer
must not lose - and the first is the one that generalises past this feature.

- **The way to lay a moodleform out in columns is a FIELDSET and a grid, never JavaScript.** The
  form declares the section (`addElement('header', …)` with `setDisableShortforms(false)`), core
  renders `fieldset#id_<name>` with a `div#id_<name>container.fcontainer` inside it, and the
  stylesheet makes THAT container a grid: the rows keep `grid-column: 1`, the panel the module
  appends takes column 2. Nothing is relocated, so no field can end up painted nowhere while still
  focusable - the defect `local_awareness` shipped and the reason the fleet rule exists. Which
  fieldset a row is declared in is therefore a LAYOUT decision now, and the only thing that records
  it is the order of the calls in `definition()`: the tagline had to move above the colours, because
  a field is in the section it was declared after. `categoryhero_test::test_the_colours_are_their_own_section_after_the_crests`
  is what holds that order.
- **Write the CSS against markup you have rendered, not against markup you remember.** The
  selectors were written after a PHPUnit render of the real form, and that render is kept as a test
  (`test_the_colours_are_a_fieldset_whose_container_the_grid_can_place`) because nothing else in the
  pipeline compares a selector with the page it is for. It also caught the trap in the same breath:
  **a `<fieldset>` is not a section** - core's filemanager, filepicker, group and date elements each
  wrap themselves in a borderless fieldset - so anything cutting the form into sections must cut at
  `<fieldset class="clearfix fitem`, and the module's own `closest('fieldset')` is only safe because
  a text input has no such wrapper.
- **A row hidden by `hideIf` is not a grid item, so never size a form grid by counting rows.**
  Core hides such a row by writing `display: none` on the `.fitem` (`lib/form/form.js`,
  `_hideElement`) and a `display: none` box generates no box at all: the colours container holds
  four rows, three while the overlay is hidden. The panel's `grid-row: 1 / span 6` is therefore
  headroom and not a count - the `1` is what starts it on the first line (auto-placed, an item with
  a column and no row lands level with the LAST row) and a span past the rows is what stops its
  height inflating row one alone, while implicit rows nothing lands in stay at zero height. The
  round shipped that comment claiming instead that the hidden row still takes a line; the number
  survived, the reasoning did not, and it is the reasoning a maintainer would reuse.
- **The grid is gated on a class the MODULE adds, once the panel is really mounted.** With
  JavaScript off there is no panel, and a grid switched on by the server would leave an empty 22rem
  track beside four squeezed rows. Two gates hold the two ends (`colours_split_class_added`,
  `colours_grid_gated`) because neither failure errors and both leave a page that looks deliberate.
- **`hideIf` crosses a fieldset.** The overlay hides itself from `layout`, which is now in the
  section above; a dependency is registered on the FORM and `M.form.initFormDependencies` runs over
  the whole of it. It reads like something that cannot work, so it is asserted - a rule that
  silently stopped applying would leave the field permanently visible and nothing else would notice.
- **The preview reads the stored row RAW, on purpose.** `branding::record()` sanitises on the way
  out, so a preview built from it would make its own `branding::hex()` call dead code and the gate
  over it would redden nothing. The style attribute is this feature's second CSS-context sink after
  the hero's inline style; the guard is the vocabulary, not the template, and the guard has to be
  provable. Same reasoning as the hero's, one layer down.
- **The live half writes from the valid-hex path alone**, inside the branch the swatch already
  takes, from one call site. One line further out and the band is repainted from every keystroke of
  a half-typed value, on the element whose whole job is to show what is about to be published.
- **Behat empties a field one character at a time.** Mink sends a backspace-and-delete pair per
  existing character before typing, so a module that reacts to valid values reacts to the valid
  PREFIXES of the old one on the way down: from `#12484f` the field passes through `#124`, a
  three-digit hex, which paints `#112244`. A scenario asserting "an invalid value leaves the last
  pair" has to start from a value whose erasure holds no colour at all - `#124` itself - or it
  fails against a module behaving exactly as designed.

## The category showcase: the toolbar's client half (round D2b, 2026-09-18)

Round D2's second half built the sort chips' behaviour, the accordion panels, the one calendar,
the toolbar's layout fix and the removal of D26's residue. The status block with every departure
and every measured number is in the original design record (not part of this repository) under **Round D2**, from D2-12; these
are the facts a maintainer must not lose.

- **Grouped and flat are two SHELLS, so ONE control of this toolbar is a navigation and every
  other is a fetch.** The grouped organisation draws the posters (`data-region="dim-areas"`, an
  accordion before round EL-1) beside the page's own grid and the flat ones draw that grid alone, so
  a fetch across that line paints a grouped answer into markup with nowhere to put the panel, or
  leaves the posters standing over a flat listing. Neither errors. `onSort()` compares
  `isGrouped()` before and after and calls `window.location.assign(buildUrl())` when they differ;
  A-Z to Recent, and every filter, period, search and page, stay `pushState` fetches. `GROUPED_SORTS` is the server's own
  `is_grouped()` in two values, and `buildUrl()` exists because two callers need that address for
  different things.
- **A panel is a REQUEST for its area's subtree, with `withcategories: true`, and no answer is kept
  for later.** It carries the page's criteria - never the tree's `cat`, and at the root the term
  alone (`categorypanel.js` `panelArgs()`) - and is rendered through `category_panel`, the template
  the server renders for `?open=`. Every answer to new criteria asks the OPEN panel again
  (`categoryareapanel`'s `criteria()`). A panel that kept its rows after a filter is the one part of
  the page still answering the previous question. Round D2's one-level `direct` option and its
  per-criteria panel cache are gone (the accordion with round EL-1, `direct` with the open date
  ranges); D2-4 and D2-14 in the original design record are history.
- **The calendar does NO timezone arithmetic, and that is its whole contract with the server.**
  Every boundary of this filter is resolved by PHP in the SITE timezone: the quick options carry
  theirs on the link, `data-today` carries the day the site calls today and `data-firstday` the day
  it begins weeks on. A browser's clock reads the READER's timezone and for part of every day the
  two name different days. `daterange_source_test` pins the absence of a bare `new Date()` and of
  every local-time accessor; what the module does compute is the calendar itself, on the
  `{y, m, d}` of an ISO string through `Date.UTC`, which has no offset and no daylight saving.
- **Repainting a grid of buttons takes the focus with it.** The calendar replaces every cell on
  each repaint, so the day the reader has just pressed leaves the document, the focus falls to
  `<body>` and the control's own focus-out rule closes the popover - which made the two-press rule
  impossible to use from the very first press. `focusDay()` restores it SYNCHRONOUSLY after every
  repaint, before the focus-out timeout can run. Any widget here that both repaints and closes on
  blur has this defect waiting in it.
- **Every row this module inserts carries `dim-vitrine__item--enter`, whose one rule is
  `opacity: 0`.** A path that inserts rows and does not take that class off two animation frames
  later has painted them invisible - in the document, in the accessibility tree and in
  find-in-page, and unreadable. `reveal()` is the one place it comes off and both paths call it.
  Behat is what found it ("found in the element but was not visible"); no PHPUnit assertion could.
- **`data-today` is the client's fourth hook and its absence has one honest answer: do not
  mount.** `DateRange.mount()` refuses without it rather than falling back to the browser's clock,
  which leaves the two native date fields exactly as the server drew them - the page a reader
  without any of this gets.
- **The two native date fields are hidden, never removed, and kept in step.** They are the form's
  own transport; `hidden` plus `tabindex="-1"` so that nothing which unhides a field by CSS alone
  can put it silently back into the tab order, and `paintDates()` writes both values on every
  change, because a control that stops being true the moment it stops being visible is one nobody
  can debug.
- **The toolbar's layout was wrong at 1200 as well as at 800, and none of it was the viewport.**
  The filter disclosure was a flex item sized by its own content, so the panel came out 420 px
  wide whatever the room; the row centred a 37 px search box against a 300 px neighbour; and the
  four date elements were loose in a flex row, so *To* ended one line and its own field began the
  next. Four rules fix it and not one is a media query: `flex: 1 1 100%` on the disclosure when it
  is `[open]`, `align-items: flex-start` on the row, one `.dim-toolbar__datefield` per label-and-
  field pair, and the auto margin moved from the kebab to the view toggle so the two right-hand
  controls are one cluster. Measured in the browser pane at both widths, before and after; the
  numbers are in the plan's D2-18. Of the four, the date field pairs stand; the filter line (stage
  T2) replaced the rest: the panel is a popover under the line from 576px and takes a line of its own
  (`flex: 1 1 100%` while open) only below it, the line centres its controls, and no control carries
  an auto margin.
- **`category_scss_test`'s scanner reads `amd/src/category*.js`, and the calendar is not called
  that.** `daterange.js` writes more class names than either module the glob reaches, so it is
  named explicitly in `markup_files()` and in the membership test beside it. A file the scanner
  cannot see is a file the Bootstrap rules do not apply to, and nothing anywhere says so.
- **The badge on the *Filter* summary is a LIVE number, and it is the only one a reader who shut
  the panel can still see.** Every other number the client repaints was in the returns from the
  start; this one was not, so a term or a period moved the rows, the count line, the pager and the
  clear link while the badge reported the filters of the address the page was loaded with.
  `filtercount` and `filtercountlabel` are in the payload now and `paintFilterBadge()` writes BOTH
  halves - the digit and the sentence a screen reader is read - because a repaint of the digit
  alone passes every assertion anybody thinks to write. Neither is composed in the browser: what
  counts as one filter is `criteria::filter_count()`'s rule and the sentence is a language string.
  Since stage T2 the badge counts what the PANEL holds - each custom field, the period as one and a
  state from "More statuses" - and no longer the term, a scope value, the star or the tree, which
  the line or the tree already shows; `narrowing_count()` counts everything, for "Clear all".
  Anything added to this toolbar that narrows the listing has to say where it states itself once
  the panel is shut: on the line, in that badge, or as a chip in the row under the line.

- **A PHP single-quoted string keeps the backslash in `{\$a}`.** Round D1 shipped
  `category_filtercount` that way in both packs, so `get_string()` had no placeholder to
  substitute and a screen reader was read the literal text while the badge beside it - the half
  everybody who can see reads - was correct. Nothing in the pipeline reads a language string's
  spelling. When a string's placeholder is inside single quotes, it is written `{$a}` and nothing
  else.
- **D26's residue is gone from the client** (`enhanceSubcategories`, `plainSelect`, `onJump`, the
  two selectors and the `core/form-autocomplete` and `core/aria` imports), with the four tests
  that pinned the behaviour of a control no page has drawn since round D1. One test asserts the
  absence, in the source AND in the build.

## The category showcase: the tree as a filter, and the listing's controls (round D3, 2026-09-18)

The owner reviewed round D2 in a browser and listed twelve interface adjustments. The status block
with every departure and every measured number is in the original design record (not part of this repository) under **Round D3**;
these are the facts a maintainer must not lose. The layers are the ones that file's own rule names:
the decisions first, then the listing, the web service, the client and the stylesheet.

- **The tree's criterion NARROWS the population and never replaces it.** `population::cat_sql()`
  appends `c.category IN (…)` to the statement that already restricts the population to the
  categories this viewer may draw from, in both page-cutting modes and BEFORE the facets are
  counted. The two id lists are ANDed, so a foreign category cannot leak a course onto the page -
  it empties the page instead, which is the loud failure rather than the quiet one. A later reader
  who "fixes" the gate that asserts this will be undoing the arrangement, not a bug.
- **A criterion the page never offered is dropped ON THE REQUEST OBJECT.** `population::resolve()`
  sets `$criteria->cat = 0` for a category of another subtree, one that does not exist, one this
  viewer may not browse and the page's own - and `criteria` itself drops it at construction under
  the grouped organisation. Dropping it only inside the statement would leave a page announcing a
  filter that narrows nothing and offering to clear something nobody set: the badge, the clear
  link, the pager and the tree's marking are all built from that one object afterwards.
- **`core_course_category::get_parents()` returns STRINGS.** It splits the stored path with
  `preg_split()`, so `in_array($id, $category->get_parents(), true)` is false for every entry when
  `$id` is an integer: the fleet's "ids come back as strings" rule met in a core helper rather than
  in `$DB`. The web service's old shape check (`population::is_descendant()`) hit it and is gone;
  `cat_ids()` reads the stored path through `path_ids()`. Cast whatever `get_parents()` hands you.
- **The tree is a control of the FLAT organisations alone.** `category_listing::tree()` returns
  nothing for a grouped request: the accordion already names every child and holds its courses, so
  a tree beside it is one question asked twice, with the second control's links carrying a
  criterion that organisation drops.
- **A `cat` the page does not offer is DROPPED, never refused, by both web services.** A foreign
  id, one that does not exist, one withheld from the viewer and the listed category itself each
  answer the bytes of a request with no `cat`: `population::resolve()` resets it on the request
  object, so the badge, the clear link and `nexturl` carry none. The logged-in function used to
  refuse a foreign one while dropping a withheld one, which let any account map the category ids of
  a subtree it may not browse (OD-13); the gate `ws_cat_dropped` puts a refusal back and
  `get_category_rows_test::test_a_tree_criterion_the_page_does_not_offer_is_dropped_alike` is the
  proof. A shape refusal is still right for what answers alike for every id (an unknown sort, any
  `cat` at the root).
- **A payload key that can only ever hold one value is not a key.** The category card's *Category*
  tag is a `{{#str}}` of `category_directory_card.mustache` and not a field of the builder, the web
  service's returns or the client - a category card can only be a category. It is D2-9's rule about
  `linkable` applied again, and it is what makes the tag right in all three surfaces at once.
- **A tree press is a fetch; the marking is a property of the STATE and not of the entry pressed.**
  `markTree()` loops over every link, because with nothing chosen the marked entry is the page's
  own - the first one, which is also the entry that clears. "Unmark the old, mark the new" leaves
  two marks on screen the first time a repaint and a press disagree about which the old one was.
  And `buildUrl()` writes `cat` only when the target organisation is flat: the server drops it
  under the grouped one, so an address carrying both would state a filter its own answer does not
  have.
- **A panel of the accordion carries every criterion EXCEPT the tree's.** It asks about a category
  below the page's, where the web service would drop the page's own as a criterion it does not offer
  (`population::cat_ids()`) - so sending it would be a filter that narrows nothing. The absence is
  asserted, not assumed.
- **The calendar has a DRAFT, and the ways out of it are not the same answer.** Two boxes, Start and
  End, open one popover, and the box that opened it is the MODE (`daterange.js` `press()`): a press
  in the start's mode sets the start, drops an end now before it and moves to the end's mode; a press
  in the end's mode sets the end, or restarts the period from a day before the start. A start alone
  or an end alone is a period like a range (the server's rule, `criteria`). Pressing a box while
  the popover is open switches the mode and never closes it (`onBox()`), so a reader saying "now the
  end" keeps the start just described. The presses apply nothing; *Done* applies the draft, and so
  does a press outside the control, whatever the draft holds (`commit()` from the `pointerdown`
  listener), while Escape - or the keyboard walking out - DISCARDS it and leaves the period that was
  in force. The discard lives in `close()` rather than at each caller, so there is one place a draft
  can outlive the popover. The boxes show the draft; the period's chip in the row under the line
  shows only what has been applied, because that chip is a statement about the listing.
- **Three controls carry `data-action="dim-daterange-clear"`**: each box's own (`data-bound="from"`
  or `"to"`, which drops that bound alone) and the footer's (no bound: the whole period). The count
  row's pill was a fourth until stage T2; the period is now a server chip (`dim-chip-remove`,
  `data-param="period"`, both bounds), written by the chip builder alone and no longer by the
  calendar. The handler matches by the ACTION and reads the bound off the control, and
  `paintBoxes()` hides each box's control by its `data-bound` - never by position or by the first
  match, because a `querySelector` on the action resolves to whichever the template writes first and
  nothing in PHPUnit says which. A clearing control is a one-press intent and applies at once.
- **The `<summary>` of the filter disclosure is an ordinary item of the filter line** (stage T2). It
  used to be positioned out of row 2 onto the search line, with the search box reserving its width
  through `padding-right` (8.5rem, and on a phone 151px, or 179px while the badge showed); the
  row, the reserves and the out-of-flow summary are gone. The line (`.dim-line`) is one
  wrapping flex row in DOM order, the search is the item that grows and shrinks, and the line is the
  positioned ancestor the panel opens under. None of it needs JavaScript.
- **A flex item's automatic minimum is its CONTENT, and that is how one pill track made the
  document wider than the phone.** The track laid its options out at 529px inside a 343px toolbar;
  the width travelled up through the disclosure, whose `min-width` was `auto`, and the document
  came out 581px wide against a 375px screen. `display: inline-flex` on the track and
  `min-width: 0` on the disclosure are one fix in two places: neither works without the other,
  because the track's `max-width: 100%` had nothing to resolve against until the box it was
  widening stopped being widened by it.
- **`position: sticky` dies silently under any ancestor whose overflow is not `visible`.** The
  sidebar is sticky, offset by Boost's own `$navbar-height`, `align-self: flex-start`, capped at
  the screen less that navbar, scrolling inside itself with `overscroll-behavior: contain` - and
  every one of those is useless without the others. The whole ancestor chain up to `body` was read
  on the running site before the rule was written (all `overflow: visible` on 5.2's Boost Union),
  and `category_scss_test` holds the half that is ours: neither box between the tree and
  `#region-main` may carry an overflow of any value. Anything added to this listing that scrolls or
  clips has to be checked against that.
- **`repeat(auto-fit, minmax(Nrem, 1fr))` cannot fall back to one column** inside a container
  narrower than its floor - it overflows - and the spelling that would, `minmax(min(Nrem, 100%),
  1fr)`, is one this fleet's stylelint rejects. So the filter panel's columns are gated on a media
  query and the phone keeps a single column. The floor is a MEASUREMENT: at 800px the panel is
  587px wide and two 18rem columns plus the gap ask for 588, so 18rem silently fell back to one
  column on exactly the width the columns were asked for. It is 17rem.
- **The treebox is two halves in two files and the number between them is asserted.** The
  stylesheet hides the tree's summary from `md` up (where the box's `open` attribute makes the list
  show) and the module closes the box below `md` at load - and listens for the media query to
  CHANGE, because a box closed on a phone that meets a width where the summary is hidden would
  leave no tree and no control to bring it back. `category_scss_test` reads both files and pins the
  one number.
- **A source-shape test over a JavaScript module needs no rebuild to see a mutation, and a
  behaviour test in a browser needs one.** These tests read `amd/src`; the browser is served
  `amd/build`. So a hand mutation of the source reddens them with the build untouched - and every
  such mutation also reddens the build-currency assertion, which is expected and is what
  `gates.conf` records for each `js_` line. What may never be skipped is the rebuild before a
  Behat run or a look in the browser.
- **A one-line scrolling track and a column layout are answers to different questions, and one
  round asking for both will hide options on the WIDE screen.** The track scrolls because a phone
  cannot hold 529px of options; the columns then made the container 321px at 1200px, and the same
  rule that saved the phone cut `Free access` in half and put `Favourites` off the track - two of
  four options gone from a desktop with room to spare. **`scrollbar-width: thin` does not make an
  overlay scrollbar visible**: macOS draws one only while it is already being scrolled, so the
  facet reads as having ended and no pointer goes looking. The track therefore scrolls BELOW the
  columns' breakpoint and wraps at and above it, and `category_scss_test` pins both halves,
  because either one alone reads as complete. Whenever a rule is chosen against a measured width,
  re-measure it after anything else changes that width.
- **Equal grid tracks answer "how many columns"; a row of filter groups asks "how wide is each".**
  Round D3's `repeat(auto-fit, minmax(17rem, 1fr))` was measured at 800px and never at a desktop:
  at 1440px it cut a 1124px panel into three 358px tracks, wrapped a 529px facet inside one and
  left a third empty (D3-28). The panel is now a wrapping flex row of `flex: 0 1 auto` groups -
  content-sized, stacking when they do not fit - and the track's wrap is only the last resort for
  a facet wider than the whole panel. **Measure a layout at every width it will meet, not only at
  the one that was complained about**, and prefer content-sized flex items over equal tracks
  whenever the items differ in width.
- **A toggle-all control must read the COLLECTION, not its own last press.** `onExpandAll()` took
  its direction from the `aria-expanded` it had itself written, so closing the panels by hand left
  it saying *Collapse all* over an accordion with nothing open, and the next press closed nothing
  and only renamed it - a lie to a sighted reader and to a screen reader alike, and two presses to
  do one thing. `panels.some((panel) => !panel.open)` is the whole fix. The same shape is waiting
  in any select-all, mute-all or collapse-all: the state belongs to the things, and the control
  reports it rather than remembering it. The accordion and this control went with round EL-1 (the
  tests now assert their absence); the rule stands for the next toggle-all.

## The category showcase: the card's cover and its hover film (round G, 2026-09-19)

Round G gave a category two files of its own for its CARD - a cover image and a short film the card
plays muted on hover - and gave the theme its second client module of this feature. The status block
with every departure and every measured number is in the original design record (not part of this repository) under **Round G**;
these are the facts a maintainer must not lose.

- **Core has no video validator, and a stored file's mimetype is its EXTENSION.** `mimeinfo()` calls
  `example.mp4` a `video/mp4` whatever is inside it, so the theme reads the first twelve bytes
  itself: `branding::video_type()` answers `video/mp4` for an `ftyp` box at offset 4, `video/webm`
  for the EBML header `1A 45 DF A3` at offset 0, and null otherwise. It reads through
  `get_content_file_handle()` and never `get_content()`, which would pull a 20 MB file into memory
  for a twelve-byte question. A file that fails is never named in the cached payload and never
  served - the route answers the same 404 as a stale hash - and the editor refuses it at validation,
  on its own field, reading the submitted DRAFT area because the element posts a draft id and never
  a file (a filepicker then, a filemanager since round H, identically). **The EBML magic is
  MATROSKA's**: a `.mkv` renamed `.webm` passes and would simply not
  play. Only the editor's two accepted extensions keep it off the form.
- **`send_stored_file()` cannot override a mimetype, so the video branch of `serve()` uses
  `send_file()`.** The former reads `get_mimetype()`, which is `mimeinfo()` over the extension - the
  very thing the signature check exists to distrust. `send_file()` with a stored file still goes
  through `readfile_accel()`, so X-Sendfile applies, and **production wants it**: a 20 MB film served
  by PHP holds one worker for the whole of a hover, one per card. Every stack of this fleet has it
  on, so nothing local would ever show that.
- **The band states its stack, and the band is a stacking context.** The template writes the `<img>`
  first and the `<video>` second, so at equal z-index the film paints ON TOP of the still it is meant
  to be revealed by - the stage-4 hero-band trap one layer down, and it looks deliberate, because a
  film over a picture is not obviously wrong. Film 0, still 1, the kind tag and the cluster and the
  bar 2. The half that is easy to lose is `z-index: 0` on `.dim-card__media` itself: that is what
  makes the band a stacking context, and without it a still lifted to 1 ranks against the crests of
  round E1 and the favourite star, which are positioned against the CARD and carry no z-index - so
  the crests vanish behind the picture. `card_markup_test` pins the DOM order, so that is not the
  thing to change when the paint order looks wrong.
- **Every listener of `covervideo.js` is on `document`, and `timeupdate` listens in the CAPTURE
  phase.** A panel of the accordion paints its cards long after `init()` ran, and so does a filter
  change and every appended page; a per-card binding serves the first screenful and silently misses
  the rest, which reads as a card whose preview "sometimes" does not work. Media events do not
  bubble, so the progress bar's listener is a capturing one - an ancestor is called on the way DOWN
  to the target - exactly as the vitrine's "one panel at a time" listens for `toggle`. Proven in a
  browser against twenty cards `categoryvitrine` painted into a panel, not merely asserted.
- **A touch pointer fires `pointerout` when the finger LIFTS, before the click** - it has no hover
  state, so the browser brackets every tap with a `pointerover` as the finger lands and a
  `pointerout` as it leaves. Round G guarded only the entering half (`hoverable()` refuses to
  START a film for a coarse pointer) and `onOut()` read the lift as a departure: it paused the
  film, rewound it and set `playing` to null, so the press arriving a moment later met a card at
  rest and both controls took their starting branch - the play control restarted the film instead
  of pausing it and the sound control restarted it instead of only unmuting it, which left tapping
  the picture as the only way to stop anything (measured on a phone, 2026-09-20, round G2). The
  rule generalises past this module: **any delegated hover logic must gate BOTH halves by pointer
  type, not only the entering one**, and the question belongs on the EVENT (`event.pointerType`,
  here `hovers()`) rather than on the device - `(hover: hover)` cannot see which pointer delivered
  an event, so on a hybrid laptop it answers "yes" to the finger as well. Gates
  `js_cover_touch_out_ignored` and `js_cover_sound_keeps_pause`; the behavioural half is the Behat
  step `I tap "X" "Y" as a touch pointer`, because Selenium drives a mouse and source shape cannot
  see an ordering.
- **`dim-cover-js` is NOT `dim-js`, and one module's mark may never stand for another's.** The
  cluster is hidden until `covervideo.js` marks the body. The obvious gate, the showcase's own
  `dim-js-only`, is un-hidden only when `dim-js` is present - and `dim-js` is written exclusively by
  `categoryvitrine`, which is armed only for a real logged-in account, never on the anonymous shell
  (it skipped the root as well until round EL-3). Writing `dim-js` from the cover module instead is
  worse: that class also HIDES the toolbar's no-JavaScript *Filter* submit, which on the anonymous
  shell without the visitor's live client (`categorypublicclient` off, the default) is the only
  filter control a visitor has. One mark per module; round EL-1's section lists them.
- **The cover module is armed on BOTH shells and for everybody**, which is the opposite of the rule
  beside it in `course_category()` and is not an inconsistency: it imports nothing, calls no web
  service and hides no control, so none of the three refusals that shut the guest out of the vitrine
  module reaches it. The arming sits outside that `if` (eight spaces of indentation, not twelve -
  which is what `covervideo_source_test` asserts) and is repeated in `request.php` for the anonymous
  page. Two gates, one per line: the failure is total and silent, and the public half is the one
  nobody would meet because every browser session on this fleet is logged in.
- **The cluster hides with `opacity`, never with `display` or `visibility`.** Those two take the
  buttons out of the focus order and `:focus-within` could then never bring them back. An invisible
  focusable control is the defect `local_awareness` shipped; what makes this the opposite is that
  focusing one is exactly what reveals it, so the focus-within half of the reveal is asserted
  separately from the hover half. Under `(hover: none)` the cluster is simply always on screen: a
  finger has no hover, and the play control is the only way in it has. **And each disc states the
  3px `--dim-ring`** — being revealed by focus is no use if, once revealed, three identical 28px
  discs look identical; the favourite star eight pixels away in the same band wears the same ring.
- **The full-screen control ADOPTS the film before it asks for the screen, and that is the one
  guard of this module whose loss cannot be undone from the page.** Two readers reach that control
  with the film stopped, and they are the two the design exists for: nothing auto-plays under
  `prefers-reduced-motion`, and nothing auto-plays on a touch device, where the cluster is on
  screen permanently and *Full screen* sits beside *Play*. Without the adoption they get full
  screen on a black rectangle — and then `onFullscreen()` hands that element the browser's own
  bar, so the film is played from there, and on the way out the bar is removed while the film keeps
  running with `playing` naming another band or nothing. The still is painted over a film nobody
  can see or stop, because `onOut()`, `onFocusOut()` and the IntersectionObserver all test
  `playing === band` and the observer was never given that band. The adoption is `toggle()` and not
  a bare `start()`, so a card paused in place is resumed and the reader's own sound survives. Gate
  `js_cover_fullscreen_adopts`, whose condition is a NAMED LOCAL for a gate reason: `if (playing
  !== band)` also opens a branch of `toggle()`, and a pattern matching both would move the next
  time the functions are reordered.
- **`stop()` puts BOTH toggles back, not only the play one.** The next start is muted whatever the
  reader asked for, so a stopped card whose sound control still reads "sound on" is a toggle
  reporting its own last press instead of what the card will do — round D3's collapse-all defect
  one feature along — and under `(hover: none)` that wrong glyph never goes away by itself. The
  film's own `muted` property is deliberately left alone there: `start()` is its only reader and
  always sets it, and writing `video.muted = true` in `stop()` would move the single assignment the
  gate `js_cover_muted_on_enter` is anchored on, because `stop()` is defined first in the file.
- **A hidden Browser pane never ticks a CSS transition.** Reading `getComputedStyle(...).opacity`
  while one is running returns the frozen start value, so the still read `1` while the film was
  playing and the cluster read `0` while a control was focused - two defects that were not there,
  and an hour spent on them. It is the same family as the stage-4 note about screenshots of a hidden
  pane lagging. Set `style.transition = 'none'` on the element, toggle the state, read, put the
  style back; all six readings were then exactly what the rules say. Anything animated that has to
  be believed is measured that way.
- **And a hidden pane PAUSES a muted film, which reads exactly like a preview that does not work.**
  The first press of the play control on a freshly loaded page leaves the card at rest; the promise
  says why - `AbortError: The play() request was interrupted because video-only background media
  was paused to save power` - and that is Chrome's background power saving for silent media, with
  `document.visibilityState === "hidden"`. The module's `play().catch(() => stop(band))` turns it
  into "the still stays", which is the designed answer, so nothing is wrong. The
  IntersectionObserver is not the cause: checked rather than assumed, a throwaway observer on the
  same band answered `isIntersecting: true, ratio: 1`. A WARM element plays there perfectly, so the
  way to measure playback in that pane is to start the film once, stop it, and then take the
  reading.
- **A file manager admits an ALIAS unless its options say otherwise.** `MoodleQuickForm_filemanager`
  defaults `return_types` to a copy, an alias and a controlled link; a `filepicker` to a copy
  alone - so swapping one for the other widens what the field accepts without a line saying so
  (round H did, and its fixer caught it). Every file field of this theme names
  `branding::copy_only()` (core's `FILE_INTERNAL`): the picker then offers no alias, and
  `file_save_draft_area_files()` skips an external file in the draft. The test makes a real alias
  from the private-files repository; `file_storage::pack_reference()` takes the ARRAY of the
  source's parameters, not the `stored_file`.
- **`settings.php` never reads a `$CFG` property bare, because a FRESH INSTALL builds the admin
  tree before core's defaults exist.** Round G's first matrix lost all four legs in ninety seconds
  to `Undefined property: stdClass::$maxbytes` from `get_max_upload_sizes($CFG->maxbytes, ...)`,
  with every local proof green: the developer stacks have had the value for months, the static leg
  installs nothing, and the PHPUnit site has its defaults applied. **`moodle-plugin-ci install` is
  the only fresh install this fleet performs, and it treats a PHP warning as fatal.** Read site
  configuration in a settings file through `??` with core's own default, and keep
  `test_the_settings_page_builds_on_a_site_with_no_maxbytes_yet` (it unsets the property, builds the
  whole tree, and turns a warning from this theme's files into a failure - a PHPUnit warning is not
  one, and a sweep reads failures). Gate `settings_maxbytes_guarded`.
- **`tests/fixtures/cover.webm` decodes and does not advance in that pane.** 204x61, an 11 s
  duration, `seekable` to 11, `readyState` 4, no error, `play()` resolves - and `currentTime` stays
  at 0, while the MP4 beside it plays. Nothing about the module; the Behat scenario therefore uses
  `cover.mp4`, which plays in the pane and in the Behat Chrome, and says so in the feature file.
- **In the dense LIST the band is 56px, so the cluster and the bar are not drawn there** - three
  28px buttons and a 3px bar do not belong in it, and the hover preview still plays without them.
  The suppressing selector has to carry the gate class, because the gate's own rule wins the tie
  otherwise.

## The category showcase: the editor's file fields and the public text (round H, 2026-09-19)

Round H made the editor's five single-file fields removable and made the public page text's
multimedia button work, link-only. The status block with every measurement is in
the original design record (not part of this repository) under **Round H**; these are the facts a maintainer must not lose, and
the first one generalises past this plugin.

- **A `filepicker` CANNOT REMOVE the file it holds - a single file somebody may take away is a
  `filemanager` with `maxfiles => 1`.** The picker shows a name, draws no thumbnail and offers only
  a replace; nothing else about the two elements differs, so a form built from pickers reads as
  deliberate and is simply missing an operation. Round E1 and round G both chose the picker and
  both recorded the reason as "one file per area", which is true and is what `maxfiles` says; the
  picker was never what said it. The owner met the result on 2026-09-19 with a hero image, a file
  manager since stage 4, removing its picture perfectly two rows above a cover that could not.
  `cover_fields_are_filemanagers` asserts the element TYPE, because that is the whole defect.
- **A ZERO draft id and an EMPTY draft area are opposite instructions.** Zero means no element was
  submitted and the area must be left alone (to core a zero draft area is "no files", so the call
  would empty the target); a real draft holding nothing is the removal. `branding::save()` skips
  the first and performs the second, and both halves are tested over all five areas with the other
  as the control. `cover_removed_clears_payload` skips an empty draft too, which is this feature's
  quietest failure: the page reports itself saved, the form reopens with the file back and the card
  still draws it.
- **Removal is three consequences, never one.** The stored file, the entry of the CACHED payload
  (or the card keeps drawing a picture that is gone) and the ROUTE, which must refuse the address
  the page minted a moment earlier. `branding::single_file_areas()` is the one list the form,
  `form_data()` and `save()` all read - an element offered with one option set, a draft prepared
  with a second and an area written back with a third is how a size bound stops applying at exactly
  the moment it is enforced.
- **`maxfiles` is the only lever on tiny_media's broken dialogue, and at 1 it works.**
  `lib/form/editor.php` builds `$fpoptions` only when `maxfiles != 0`, and `tiny_media`'s embed
  dialogue and media manager dereference them unguarded where the image dialogue tests for
  `undefined` first - so at 0 the multimedia button throws `Cannot read properties of undefined
  (reading 'repositories')` and opens nothing, while the image button beside it works, which is
  what makes a core defect read as a theme fault. `return_types => FILE_EXTERNAL` is what keeps it
  link-only: `initialise_filepicker()` drops every repository that cannot return an address,
  `upload` among them, and BOTH dialogues compute their drop zone as "some repository of type
  `upload`". Measured on m502b: the dialogue opens with a URL field and *Embedded files* and
  *Wikimedia*, no file input and no drop target, and a YouTube address and a direct `.mp4` are both
  inserted as `<a class="external-media-provider" href="...">`.
- **FILE_EXTERNAL is 1 and FILE_INTERNAL is 2** (`repository/lib.php:30-31`), the reverse of the
  order the names suggest. Read the constants; a `return_types=1` in a URL is the external one.
- **An upload path closed in every dialogue is still not a file area closed.** A paste or a
  drag-and-drop goes through `images_upload_handler` to `editor_tiny/uploader` whatever the options
  say. Here that POST answers `{"errorcode":"invalidrepositoryid"}` - there is no `upload`
  repository for it to name - and TinyMCE then leaves the picture INLINE: `blob:` in the live DOM,
  `data:image/png;base64,...` in the value the form submits. Neither survives core's HTMLPurifier
  scheme list, so the public page drops the element while the row keeps the base64; a
  `draftfile.php` address survives the purifier and breaks within the day instead. So
  `branding::holds_unservable_address()` refuses all three at the save, on the field, matching in
  an ATTRIBUTE position so the same words in prose are not a refusal. Refused and not stripped, on
  this plugin's own precedent for a colour that is not a colour.
- **An admin is exempt from `maxbytes` and from nothing else.** `get_user_max_upload_file_size()`
  answers `USER_CAN_IGNORE_FILE_SIZE_LIMITS` for anybody holding `ignorefilesizelimits`, which is
  why the media manager's own file manager reads "Maximum size for new files: Unlimited, overall
  limit: 1 bytes" - `areamaxbytes` is the half that still applies.
- **Clearing a flag out of a return-types value can hand core a 0, and 0 means "unset".**
  `manage.php` does `$returntypes & ~FILE_EXTERNAL` on the value the editor gave it; for a
  `FILE_EXTERNAL`-only editor that is 0, and `MoodleQuickForm_filemanager`'s constructor tests
  `empty($options['return_types'])` and restores `FILE_INTERNAL | FILE_REFERENCE |
  FILE_CONTROLLED_LINK`. So the media manager's file manager is the one surface of this editor
  that KEEPS an upload repository, and advertises a drop target - which the browser measurement
  saw and the docblock written beside it denied. `areamaxbytes` is what closes it, the draft is
  never written anywhere, and the save-time refusal catches the address. **Never read a bitmask
  narrowed to zero as "nothing allowed" without checking what the consumer does with zero.**
- **`filter_mediaplugin` REPLACES the anchor, so the editor's `external-media-provider` class is on
  no published page, and HTMLPurifier is not what removed it.** The route test carries a third
  anchor whose address is not media as the control: the filter leaves it alone and its `class`
  survives, which is what tells the two explanations apart. This round asserted the opposite before
  running the test.
- **The hiding fallback would have hit the wrong editor.** `body.dim-categoryhero` is on the PAGE,
  and the category description editor a few rows above is core's own field with
  `EDITOR_UNLIMITED_FILES`; a rule scoped to that class takes the working buttons off the working
  editor. A per-editor rule is not available either, because TinyMCE's menus are floating
  containers appended to `body`.

## The category showcase: the posters and the panel between rows (round EL-1, 2026-09-24)

Round EL-1 replaced the grouped organisation's accordion with a poster per area and ONE panel of the
open area's courses drawn between the rows, on category pages and at the root. The design, the picture it is
matched against and the status block with every measured number and every departure (under
**Round EL-1**) are in the original design record (not part of this repository). These are the facts a
maintainer must not lose.

- **The areas' grid must never carry `data-region="dim-vitrine-grid"`.** It carries the listing's
  class (`dim-vitrine__grid`, for the three tracks) but its hook is `dim-areas-grid`, and it comes
  FIRST in the document: `categoryvitrine` finds the courses' grid with `root.querySelector()`, so a
  shared hook would have it replace the posters with course cards on the first filter. The same
  goes for Behat: on a grouped page `".dim-vitrine__grid"` is the posters' grid, so every scenario
  scopes the courses to `[data-region='dim-vitrine-grid']`.
- **The panel is ONE list item of the grid and its row is five integers on its own style
  attribute.** `--dim-row-3/-2/-1` (the row after the open poster's at three, two and one column)
  and `--dim-col-3/-2` (where the arrow points), written by the server through `sprintf('%d')` and
  by the client through `style.setProperty()` (`categoryareapanel` on the visitor's shell, `areas/panel.ts`
  on the logged-in one since R3). The stylesheet reads the one for its
  breakpoint, and reads `--dim-row-1` in the list view at EVERY width, whose grid is one track -
  without that rule the panel falls rows away from its poster. An explicitly placed item is placed
  first and auto-placement skips its row, which is why a resize needs no script. The DOM order is
  the module's: after the LAST poster of the open poster's row, on open, on a breakpoint change and
  on a view change, never on `resize`. The breakpoints are the stylesheet's own 1199.98 and 575.98,
  and `categorypanel_source_test` reads both files to hold them together (`areas_component_source_test`
  holds the component's `model.ts` to the same two numbers).
- **A panel the server drew is OPEN by construction, with the module or without it.** The shut
  state is a class only the module writes (`dim-panel--shut`, for the moment it grows from or
  collapses into). The first version tied "open" to a class the module adds, which made the
  static renders of the design record's probe harness - which stamp the module's body mark and run
  no script -
  draw every server panel collapsed.
- **The poster's CARD is covervideo's band.** `data-region="dim-cover-media"` moved from the media
  div to `.dim-poster__card`, so the playing class covervideo writes (`dim-card__media--playing`)
  lands on the card, and the stylesheet pairs it with the poster's own still. The stack is stated:
  the card is a stacking context (0), the media sits under everything at -1 with the film at 0 and
  the still at 1 inside it, the top row at 1, the control's stretched `::after` at 2 and the film's
  controls at 3.
- **scssphp divides `a / b` in `aspect-ratio`** (`16 / 9` compiles to `1.7777777778`, still valid)
  **and keeps `grid-column: 1 / -1` verbatim.** Measured on m502. `@keyframes` nested inside
  `body.dim-category { }` are bubbled to the root unchanged, and nesting them is what keeps
  `category_scss_test`'s scope walker from meeting `from` and `to` as top-level selectors - every
  keyframes of this file is nested, and a test holds it.
- **`text-wrap` and `color-mix()` are rejected by this fleet's stylelint** (csstree: "Unknown
  property" and "Invalid value"). The design's `text-wrap: balance` is omitted, and the chevron's
  disc - the ink at 18% - is a `::before` with `background: currentcolor; opacity: .18`. A
  multi-line declaration needs a newline after its colon and one item per line, or stylelint fails
  on four rules at once.
- **The design's 15px is not the theme's 16px.** Boost Union's body is 1rem; the design's frame is
  15px in its reading face with the brand face only where it says so. `.dim-areas` states both
  (`font-size: .9375rem`, `font-family: var(--dim-ds-font-reading)`) and names the brand stack
  (`--bs-font-sans-serif`) on exactly the design's brand elements. Without it every inherited size
  and every line strut drifts: the call to action wrapped at 390 and the panel's heading sat 2px low.
- **Match what the design RENDERS, not what its sheet says.** Its `.panel__title { margin: 2px 0 0 }`
  is overruled by its own `.frame h3 { margin: 0 }`, so the rendered title has no margin and neither
  has ours. A DOM read of the design and of the page at one width is what found both of these,
  where a pixel diff only said "something moved".
- **The listing and the panel talk through events on the document and through the address.** The
  shared `paintView()` (`categorylisting`) sends `dim-vitrine:view` for both clients, and
  `categoryareapanel` re-places the panel on it. On the logged-in shell `categoryvitrine` sends
  `dim-vitrine:criteria` from `paintShells()`, on a REPLACED answer only, carrying the response's
  `accordion[]` with each area's `ctaurl` and `ctalabel` - the panel's shell, composed by the server,
  which the panel shows before its rows arrive - and since R3 the posters' component hears it
  (`areas/panel.ts` `criteria()`), writes the shells onto the posters, repaints them and re-asks the
  open panel; `categoryvitrine` writes nothing into the posters any more (R3's section).
  The visitor's `categorypublic` does the same in `paintAreas()`, with no event. Neither side
  composes a number or a sentence; the client changes only the `sort` of the shell's address for the
  A-Z order.
- **Each module writes its own mark, and there are five.** `dim-js` (the logged-in listing,
  `categoryvitrine`), `dim-public-js` (the visitor's, `categorypublic`), `dim-cover-js` (the film),
  `dim-panel-js` (the panel, written by the shared `categoryareapanel`'s `arm()` on the visitor's
  shell and by the component's `arm()` on the logged-in one, deferred there while a server-drawn panel
  is open: R3's section)
  and `dim-star-js` (the star, a module of its own because both logged-in modules arm it). A
  static render of the design record's probe harness stamps the marks of the modules a page ARMS, so
  it stamps `dim-panel-js` and not `dim-star-js`, which is imported: a static render of the root
  shows its minis without a star.
- **The panel's address is replaced, never pushed, and three other holders follow it.** Every
  write of `open`/`psort` goes through `replaceState` and then rewrites the toolbar form's and the
  hero fold form's hidden `open`/`psort` fields and the clear link's href (and, since stage T2, every
  chip's under the filter line: `categoryareapanel`'s `syncHolders()`, and `areas/panel.ts`'s on the
  logged-in shell since R3). And `categoryvitrine`
  rewrites ALL the fold form's criteria from the address after each of its own writes: the fold
  form is drawn with the server render's criteria, so a fold after a filter applied in place used
  to reload with the old filters (blueprint section 11, fixed in this round).
- **Escape closes the panel from inside it or from its poster, and from nowhere else.** Not from the
  search box, where it empties the field, and not from the film's controls, where covervideo stops
  the film on the same key - the panel must not close under either.
- **A mini is the course card itself.** `category_card.mustache` is unchanged; `.dim-panel__rail`
  restyles it. Its band is 16:9 (`aspect-ratio`), so the crests - positioned against the CARD, as
  on the full card - become an overlay of the band's own shape; its disclosure is `display: none`
  and `categoryvitrine` arms no peek on a card inside the panel (gate `js_mini_no_peek`); a
  visitor's mini keeps "Learn more" as text in the star's corner.
- **Behat of a panel: the poster's control is an `<a>` without JavaScript and a `<button>` with it.**
  The no-JavaScript feature clicks `//article[@data-name='...']//a[@data-region='dim-poster-control']`
  and the client feature the same path with `button`; a visitor's poster stays an `<a>` with the module
  too (`role="button"`), so the public client feature clicks the `a` path. Visibility of a toolbar
  control (the row under the filter line at rest, for one; the phone copy of the view toggle was
  another until stage T2 removed it) is a stylesheet fact, so the Behat site's CSS must be rebuilt
  (`mdl behat-init`) after any SCSS change before it is believed. At the root the poster's name is a link too, so
  `I follow "<area>"` from the course index opens that area's panel in place and does NOT reach the
  category page: follow the panel's title link (`[data-region='dim-panel-title']`) for that. Its call
  to action is the area's FLAT order (`sort=recent`), not the grouped page.
- **A `pause()` while a film is still loading rejects the pending `play()` with an AbortError, and
  that is not a refusal.** `covervideo` read every rejection as one and stopped the band, so a second
  tap on a film that had not started rewound and forgot it, and the sound control then started it.
  `refused()` now lets an AbortError of the adopted, paused band through (gate
  `js_cover_pause_not_refusal`). The Behat scenario "A finger pauses the film in place" met it only
  when the fixture loaded slowly - two red runs out of three on a loaded machine, three green once
  fixed.
- **A render is asynchronous, so "the latest request wins" is checked AFTER it resolves** (in
  `categoryareapanel` and, since R3, in `areas/panel.ts`, whose Jest suite orders the renders by hand).
  `renderWhole()` takes the opening's turn (`state.turn`, bumped by every open and close) and
  `transplant()` the request's ticket plus a number of its own (`state.paint`), and each compares
  between the render and the write. Checked only before the render, the skeleton's render and the
  answer's raced when the answer landed just after 150ms - the panel could stay a skeleton for good -
  and a superseded opening wrote its area's header, which transplants keep, over the next one's rows.
- **The panel's three transition classes are set only through `settle()`, which clears all three
  first.** `dim-panel--fading`, `--static` and `--shut` each last one transition and come off on
  their step's success path; a step superseded before it gets there (a second press inside the
  crossfade, a close while the first render is out) used to leave one on, drawing every later panel
  invisible or collapsed. `categorypanel_source_test` fails any `classList.add` of them elsewhere, and
  `areas_component_source_test` any `panel.classList.add` of the component's but `settle()`'s.
- **The visitor's root reads the open panel off the address and nothing else**, as the logged-in root
  does: `request.php` builds it with `criteria::panel_options_from_request()`, not `from_request()`.
  The root honours no filter and draws no control to clear one, so anything else it read rode the
  hero's fold form. Two route tests and the gates `visitor_root_asks_for_posters` /
  `visitor_root_reads_no_filter` hold it; the fold form is read from `category_hero::current()`
  because `$OUTPUT->header()` renders nothing under PHPUnit.

## The category showcase: search and filters on the posters, and the toolbar's order (round EL-2, 2026-09-25)

Round EL-2 made a search or a filter rewrite the posters (the spec's stage 3: sections 4.6 and 4.7,
V8, V9, V18) and put the toolbar in the design's order (the owner's answer 2). The status block with
every measured number and every departure is in the original design record (not part of this repository) under **Round EL-2**; these
are the facts a maintainer must not lose.

- **Result mode is ONE switch: a grouped page with areas and `criteria::has_filters()`.** It is
  computed in `category_listing::results()` - one builder the page (with its real open panel) and
  `get_category_rows` (with none, as `areas`) both read - written on the areas section as
  `data-filtered`, and rewritten by the client from every answer (the component's `criteria()` on the
  logged-in shell since R3, `categoryareapanel`'s on the visitor's). The root never is (it reads no
  filter). Nothing in it costs a statement: every number comes from the accordion already in hand.
- **Whether a poster opens has ONE spelling: `population::expandable($filtered, $count)`**, called
  by `posters()` and by `rows.php` for each `accordion[]` entry. Under a criterion every poster opens
  (a zeroed one opens its "no results" panel, which is how the open area stays); at rest an area with
  no course is the link to its page. Because the client applies filters in place, the poster's markup
  carries both states (the trigger's `data-empty`, the chevron, the visually hidden note) and
  `setExpandable()` (`categoryareapanel` on the visitor's shell, `areas/posters.ts` on the logged-in one)
  flips class, attribute, note, word and control - never on
  the open poster, which waits until it closes.
- **V18: a visitor's posters under a criterion are the areas the page shows at rest**, at the price
  of ONE grouped statement (`population::tree_at_rest()`, the at-rest anonymous predicate grouped by
  category), paid by a visitor's grouped page under a criterion, in both page-cutting modes. A visitor's
  FLAT page pays it too, for its filter tree, but only on the page's own request (`withposters`): the
  web service's answer carries no tree and is fetched on every keystroke (`budget_test` holds both).
  Without it `subcategory_tree()` names a child only when a row of the NARROWED population sits below
  it, so an area the search missed vanished instead of joining the summary. The counts still come
  from the post-filter tally; `budget_test` holds the +1 and the 0 everywhere else.
- **The count line is TWO keys, the number first** (`countnumber`, bold, and `countline`, the words
  after it), because the design sets the number apart. It holds for the two languages this theme
  ships; a third language putting the number elsewhere needs one string per sentence. At rest a
  grouped page counts the whole SUBTREE (the hero's number); under a criterion, what the page draws.
  The shared `paintCount()` (`categorylisting`) writes the number's and the sentence's regions and
  never the paragraph itself. Since stage T2 the paragraph is the first item of the row under the
  filter line, visually hidden at rest and never removed (it is the live region), and "See all as a
  list" and the automatic opening's note are its siblings in that row, outside the sentence.
- **The phone copy of the view toggle is gone** (stage T2): there is one view toggle, icons only and
  List first, on the line, `[data-region='dim-view-row']`; `dim-view-phone` no longer exists.
- **The "Organise:" label is a floated legend with `width: auto`.** A floated legend is not the
  fieldset's rendered legend, so it lays out as a flex item of the group beside its track; Bootstrap's
  reboot already floats every legend but gives it the whole width, which is what put the label on a
  line of its own - the width is the half that matters (gate `toolbar_legend_inline`), the float is
  written so the arrangement does not rest on a reboot rule. The colon is the stylesheet's `::after`.
- **A zero panel's suggestions are PAGE data, merged by the client like the cards.** The web
  service answers for one area and knows nothing of its siblings, so the server adds them in
  `category_panel::for_page()` from the page's posters and the client (`categoryareapanel`, or the
  component's `areas/model.ts` `suggestions()` since R3) reads them off the posters'
  `data-count`/`data-resultcount` into `Object.assign({}, response.panel, {cards, jsmode, suggestions,
  hassuggestions})`. A server-drawn suggestion is a link with the button's data attributes, which the
  adoption turns into a button.
- **`toButton()` copies the data attributes and nothing else**, so a poster's `aria-describedby` (its
  pill) is handed over by name in `convertPoster()`, and `toLink()` does the same the other way. A
  describedby lost in a swap is invisible to every test but a source-shape one.
- **Zeroed posters leave the grid through `hidden` on their item, never removal, except the open
  one**, which stays until the reader closes it (the open-stays rule); closing it sends it to the
  summary and the focus with it (`focusSummary()`), and moving from it to another area is always a
  move to another row, because the row it shared may be gone. The "nothing in N areas" summary carries
  both of its sentences (`data-label-all`, `data-label-less`), so the client picks one and composes
  none. The reveal past twenty-four follows the same DOM rule (`fitReveal()`): hidden under a
  criterion when no poster behind it is left in its grid.
- **The automatic opening is the client's decision on the server's data** (`areas.autoopen`): it
  opens with the focus left in the search box, never over a panel the READER opened
  (`state.openedby`), and nothing ever closes a panel on its own. It tells the listing's module
  (`dim-panel:autoopened`), which adds the answer's sentence to the row under the filter line (the
  count line until stage T2).
- **"Clear filters" is a fetch with JavaScript**, intercepted on `data-action="dim-clear"` (three
  controls carry it: the row's "Clear all" since stage T2, the empty state's and the areas' summary's
  "Clear filters"), and the focus goes to the search box because the pressed control hides itself.
  Each chip under the line is a fetch the same way, intercepted on `data-action="dim-chip-remove"`.
- **`<mark>` is painted by `categorymark`, on the TEXT of the card title's heading
  (`data-region="dim-card-title"`), never on the link's hook**: a visitor's card of a course with no
  page to open draws its title as a plain span, and the panel of a visitor's page is made of exactly
  those. The helper unmarks before it marks, so it is idempotent.
- **An empty range-pill region is `display: none`** in the row under the filter line (and so is an
  empty chip list): a flex item of no width still takes a gap on each side, which doubled the
  distance between the sentence and "Clear filters". Since stage T2 that region holds the period's
  server chip.
- **eslint's `complexity` (20) is a warning, and a plain `grunt amd` prints none of its warnings.**
  `openArea()` passed the build and failed `eslint --max-warnings 0` at 24; run eslint with
  `--max-warnings 0` on the modules after every edit, not only grunt.
- **A state chip nothing honours is dropped on the request** (`population::resolve()`, beside the
  tree's `cat`). A visitor's page and the SQL cut classify nothing, so a `chip=` on their address put
  the page in result mode over an unnarrowed listing, with no control to show or clear it; only the
  logged-in page with the state sort on honours it (gate `state_chip_dropped_unhonoured`).
- **The pending state has exactly two ways out: the answer's event and `dim-vitrine:failed`.**
  `requestRows()` sends the second from its catch, for the latest replacing request on a page with
  areas; the posters' component hears it (since R3; `categoryposters`' `failed()` on the visitor's
  shell, `areas/posters.ts`'s on the logged-in one), which drops `aria-busy` and
  repaints every pill through `paintPill()` from the poster's own `data-resultlabel`, which the server
  draws on the article and every answer rewrites. Without it a failed request left every pill - each
  control's description - at "…".
- **The rows answer checks its ticket after `renderCards()` as well**, as the panel's renders do, and
  writes the page's state (`lastpage`, `state.page`, `total`, `hasmore`) only after that check: an
  answer overtaken while it rendered used to repaint the posters and could open an area by itself.
- **Under a criterion the count line leaves out a match rescued inside a clamped child**, which no
  poster shows: its number can then be smaller than the list behind "See all as a list", and V9 can
  open the one area with a result while that match exists. Recorded as it is (`count_line()`'s
  docblock, `category_listing_test::test_under_a_criterion_the_count_line_leaves_out_a_course_no_poster_shows`);
  changing it needs a third part in the sentence, and so new strings.
- **The Filter badge is the call to action's pair (`--dim-cta` and white).** It painted
  `--dim-surface` as its ink on `--dim-primary`, and Bootstrap never redefines `--bs-primary` for
  the dark mode, so the digit read 1.1:1 there. A `--dim-primary` fill needs an ink that follows the
  mode; the surface never is one (gate `filtercount_dark_pair`).

## The category showcase: the toolbar's controls in the design's skin (round EL-S, 2026-09-26)

The owner's decision: the toolbar's controls follow the prototype, reversing the D1-D3 skins EL-2 kept
as accepted deltas; EL-2's layout is untouched. The status block, with the token mapping, the
departures and every measured number, is under **Round EL-S** in the original design record (not part of this repository).

- **The prototype's SHEET is not the prototype: take the values Chromium computes for it.** Its buttons
  inherit the 15px body (`button { font: inherit }`), so its "Filter" is 15px where a reading of
  `.tb__fbtn` finds no size at all; and `.viewseg > * + *` writes a divider that its own
  `.viewseg button { border: 0 }` outranks, so the design renders none and neither does the page. A
  rule read off the sheet would have shipped both wrong.
- **A scroll container clips an OUTSIDE focus ring.** The chip tracks still scroll on a phone (round D3)
  and the design's ring is 3px at 2px outside the chip, so the track carries 5px of padding and takes
  it back as a -5px margin: the ring is drawn whole and the chips do not move (gate
  `toolbar_track_ring_room`). An inset ring would have been the easy fix and is not the fleet's ring.
- **The Bootstrap 4 scanner reads `border-left` in the SCSS as a class name** and fails the build; a
  divider or an edge written in this file takes the logical property (`border-inline-start`), which is
  also the one that is right in RTL.
- **The toolbar is no card, so its rhythm with the page is ONE rule.** The last row carries
  `margin: 2px 0 4px` because the design's count row is 12px under the controls and 18px over the
  listing and the toolbar's 10px gap and `.dim-vitrine`'s 14px are already there. Since stage T2
  that row is the row under the filter line (`.dim-line__chips`), whose margin is `-3px -5px -1px`:
  the same 2px 0 4px less the 5px of focus-ring room it keeps on every side as a scroll container.
  Change either gap and the row moves; the skin test asserts the numbers together.
- **A chosen face is `--dim-cta` with white, never `--dim-primary` with the page's surface.** The Filter
  badge's dark-mode defect of EL-2 was still in the quick date option in force and the calendar's two
  ends; the chosen chip joined them (gates `toolbar_chip_checked_pair`, `toolbar_quick_on_pair`).
- **A Bootstrap button that stays one is re-pointed, not outranked.** The calendar's "Done" keeps
  `btn btn-primary`, so its colours are the `--bs-btn-*` variables of every state; the kebab
  (`.btn-icon`) takes a plain skin at a specificity above every `.btn` state. The toolbar's own no-JS
  submits and the clear link lost their Bootstrap classes instead (gate `toolbar_clear_is_link`).

## The category showcase: search at the root (round EL-3, 2026-09-27)

The spec's V12, with the owner's answers of 2026-09-27 (live search, no automatic opening and no count
line at the root, and the SQL cut's exception lists narrowed by the term). The blueprint and
the status block (under **Round EL-3**) are in the original design record (not part of this repository);
these are the facts a maintainer must not lose.

- **Every criterion the SQL cut folds into its predicate must narrow its two exception lists as well,
  or the two page-cutting modes disagree past the cap.** `admitted_unlisted()` and `rescued_hidden()`
  are capped at fifty in core order; the PHP cut's probe runs over what `records()` already narrowed.
  Built over the whole subtree, the SQL lists probed the first fifty courses of the SUBTREE and withheld
  a match past them that the PHP cut drew: `q=Zeta` answered 5 in one mode and 0 in the other, on a
  category page, before the root ever searched (measured 2026-09-26). The term narrows both lists now;
  the field filters, the period and the tree's category still do not (the root reads none of them), and
  the agreement test is `population_test::test_a_search_at_the_root_counts_the_same_in_both_modes`,
  over the fifty-five-course fixture that caught it. The agreement is about UNLISTED courses: the
  hidden list's cap of fifty is the SQL cut's alone (the PHP cut asks of every hidden match), so
  staff past fifty hidden matches still read fewer with the state sort off - older than this round,
  recorded in the status block, and the blueprint's first draft had called it symmetric.
- **The root's `total` is the hero's number, never "the courses sitting here".** `results()` reads a
  page's total as its direct courses; at the root nothing sits directly, so it reads 0 there - or
  "nothing anywhere" could never be said while a match sat in a category no poster shows, and an area
  would open by itself (the owner answered no to that at the root: gate `root_no_autoopen`).
- **The root at rest counts nothing, so its web-service answer names no poster**, and
  `categoryposters`' `paintPosters()` resets every poster an answer at rest does not name (pill
  hidden, item shown). Without that pass a search cleared in place leaves the last term's pills and
  hidden posters on the root; on a category page the answer names every poster and the pass changes
  nothing (gate `js_rest_resets_unnamed_posters`).
- **The root reads its term ALONE, in four places that must agree**: `criteria::root_from_request()`
  (both shells), `population::resolve_root()` (the term on `$plain`), the web service (it refuses
  anything else with category 0: its one client never sends it) and the client (`rowsArgs()` and
  `rootOnly()` in `categoryvitrine`, `panelArgs()` in `categorypanel`). A filter that reached any one of
  them would change a number with no control on the page to show or clear it.
- **`guard::category(0)` answers the system context and `top()`.** The course index is every logged-in
  account's page, so answering for it is no oracle; the guest is refused before the guard, and what the
  root may list is the population's own root gate. A NONEXISTENT id still takes the one refusal.
- **`categoryvitrine::applyResponse()` paints an answer with no course grid** - the root's - and
  `paintGrid()` is the grid's own writer. The source tests that read the grid's code read `paintGrid`;
  an early return on a missing grid would make the root's search line fetch and paint nothing.
- **"Search all areas" is offered only where the reader may open the root**: a real account; a visitor
  only where the site opened its root (round E2); never the guest, whose root is core's course index,
  which ignores the term (gate `searchall_root_reachable`).
- **Gate anchors moved with the code**: `listing_client_armed_for_real_account` now anchors on the one
  condition (the root arms the listing's module too), the two `visitor_root_*` gates on
  `root_from_request()`, and `js_rows_carry_page_size` on `rowsArgs()`'s indentation. Dry-match every
  line of `gates.conf` after a change that moves code between functions.

## The category showcase: the pages strip (round T2, 2026-09-27)

A category's local_page pages, named under the hero and above the toolbar. The plan (T2 of the theme's
local_page follow-ups) and the status block (under **Round T2**) are in the original design record (not part of this repository),
and the plugin half is `\local_page\local\catalogue::for_viewer()` (local_page `1.0.10+uai.13`).
These are the facts a maintainer must not lose.

- **local_page is optional, and that is the owner's rule, not a convenience.** It is not in
  `version.php`: a site without it installs this theme unchanged and draws no strip. The one coupling
  is `category_pages::CATALOGUE`, asked through `class_exists()` (a local_page older than
  `1.0.10+uai.13` has no such class) and `method_exists()` (a catalogue whose read method was renamed
  answers "no strip", not a fatal). Keep it that way;
  a dependency declared "because the fleet installs it anyway" makes the plugin mandatory for every
  site that runs the theme.
- **No try/catch around the catalogue call**, for `logo_url()`'s reason: a catch-all would make the
  `class_exists()` guard dead code (gate `pages_plugin_guard` would redden nothing), and a throw from
  the plugin is a real fault that must show as one.
- **The theme decides nothing about who may read a page.** Every such rule is the plugin's: the
  visitor refused before any lookup unless the category is public, the category's own pages only,
  live ones inside their window only (even for an editor: the owner's answer), and each page's
  access level and logged-in flag. The theme adds two rules of presentation and nothing else: no
  strip at the root (and the catalogue is not even asked there), and an address that is not
  `$CFG->wwwroot . '/'`-prefixed is dropped - the slash matters, as in `login::local_icon_url()`.
- **It is built by the page's two ENTRIES, never by the listing's export.** The course renderer's
  `course_category()` and `request::handle()` each call `category_pages::export()` and pass the
  answer into `category_listing`'s constructor. `get_category_rows` calls `export_for_template()` on
  every search and every panel, and the strip is outside everything a search rewrites, so building it
  in the export would pay the catalogue's statement on each keystroke for a region nobody repaints.
  Two entries means two wiring gates (`pages_renderer_wired`, `pages_visitor_wired`), because either
  one can stop building the strip while the other keeps it and the page still looks deliberate.
- **The plugin's answer is plain data and is escaped once, in the template.** Name and description
  arrive in the plain spelling; `category_pages.mustache` double-stashes both, and the description is
  the link's `title`, not text of its own (the owner chose compact links). `pages_name_escaped` is
  the gate; a triple stash there prints another plugin's stored text as markup.
- **Three of the nine gates are held only where local_page is installed.** The theme's own rules
  are tested with `tests/fixtures/catalogue_stub.php`, a stand-in that answers on every site; the
  wiring is tested by two integration tests against the real plugin (`course_renderer_test`, the
  route's `category_test`) that skip themselves without it. So `pages_listing_export`,
  `pages_renderer_wired` and `pages_visitor_wired` read GREEN on a site without the plugin, and that
  is the site, not the gate: the fleet's stacks mount local_page (`plugins.conf`, `auto`), `ci.yml`
  installs it, and `moodle-dev/bin/mdl-ci`'s `extra_deps_for()` installs it for `mdl ci` - without
  that entry the integration tests would skip locally while GitHub runs them.
- **`ci.yml` pins local_page's `main`, so the plugin's PR merges first.** Until `main` carries the
  catalogue, the integration tests skip rather than fail. To prove the wiring before that merge, a
  session points the `ci.yml` line at the plugin's branch for its own runs and back to `main` before
  the final gate; that is how this round was proved.
- **The strip costs one statement, flat, and the render budget counts it only where it is paid.**
  Measured 19 reads against 18 at N=2 and at N=200 with local_page installed; the category's context
  is already preloaded, so the statement is the context's pages alone. `course_renderer_test` adds
  `PAGES_READS` to `RENDER_CEILING` when the catalogue class exists, so the bound stays exact on a
  site with the plugin and on one without; raising the ceiling to 19 for everybody would have let a
  site without the plugin grow a read in silence.
- **The Behat feature needs the plugin** (`…_category_pages.feature`, tagged `@local_page`): it
  creates pages through the plugin's own generator. A site without local_page excludes it with
  `--tags '~@local_page'`; what the page looks like without the plugin is PHPUnit's
  (`category_pages_test`).

## The category showcase: the visitor's live client (stages S1 to S4, 2026-10-04 and 05)

With `categorypublicclient` ticked, the anonymous page searches, filters, orders, pages and opens its
panels in place, through a web service of its own. The decisions and the alternatives they rejected
are D33 to D39 of the original design record (not part of this repository), which also holds the stages'
status block, the design, the HTTP facts, the numbers and the cache verdicts. What the code rests on:

- **The server is one function, `get_public_category_rows`, `loginrequired => false`, never a flag
  on `get_category_rows`.** `loginrequired` belongs to the function, so a flag would put the
  logged-in listing on the internet. It is answered by `lib/ajax/service-nologin.php`, whose
  `NO_MOODLE_COOKIES` reads no session (measured: a session holding `pt_br` changes nothing), and
  nothing on that path reads forced login. It never calls `validate_context()`, which ends in
  `require_login()` and throws for every visitor, and never reads `$USER`: it sets the context once
  visibility is decided (`reset_theme_and_output()`, `set_context()`, as `tool_policy` does) and
  refuses `isloggedin()`, true for the guest too. It delegates to `rows::fetch()` with `anonymous` on,
  which builds what `request::handle()` builds for the page, and its returns are
  `get_category_rows::execute_returns()` key for key, so both clients paint one shape.
- **The client is `categorypublic.js` over the shared `categorylisting`, which imports nothing of a session**
  (the listing's painters, its address helpers and, since R4a, the mounts of the three React components: the
  calendar, the filter line, the posters with their panel; the R4a section). `categoryvitrine` and `categorypanel` keep
  what is an account's - the progress observer, the peek, the star, the view preference, the logged-in services - and
  bind the shared functions in one-line bindings at the top. Until R4a the visitor armed `categoryareapanel`,
  `categoryposters` and `categoryline`; they are imported by nothing now and leave with R5, their source tests and
  gates with them. `amd_source_test::body()` and `categorypanel_source_test::body()` look in the module and then in
  the shared file, and a one-line binding never matches, so a body found is the code that runs. A gate on moved code
  names the shared file in `mutations/gates.conf`.
- **The service's order is a security property and moves as one block**: it is the class docblock of
  `get_public_category_rows`, eleven steps - parameters, one call per request, no `cachekey`, no
  session, the cheap shape checks, the language, the service's switch then `publicaccess::is_public()`
  before any lookup, the context, the category, the filter fields, `rows::fetch()`. Several guards
  are backed by a later layer that answers the same way, so their tests observe the ORDER rather than
  the answer: a refusal on an id nothing may be served (a later layer would say
  `categoryunavailable` instead of `invalidparameter`), and a read count of zero metered on
  `execute()`, because `call_external_function()` reads the function's own record. The `pws_*` gates
  are written that way.
- **`rows::fetch()` with `anonymous` assumes its caller already refused a logged-in session.**
  `population::scope()` refuses an anonymous population built for a real account with a
  `coding_exception` - the anonymous predicate's category half reads the live session - which is a
  second error spelling; step 4 refuses first, with the one refusal. A new caller owes the same check.
  `scope()` also asks the whole public chain, `publicaccess::is_public()` (gate
  `scope_anonymous_publicaccess`): the plugin's predicate alone resolved a public category while the
  surface or the showcase was switched off.
- **The switch, `categorypublicclient`, is off unless a stored `'1'` opens it, and
  `publicaccess::client_enabled()` sits BESIDE `is_public()`, never inside it.** `is_public()` is the
  gate of every public page, the forward, the image route and the head tags, so folding the switch in
  would take them all off the internet on upgrade (gate `client_switch_beside_publicaccess`). It is
  read in two places only: step 7 of the service and the arming in `request::handle()`. **The arming
  condition is the switch alone**: step 1 of `handle()` sends every session, the guest's included, to
  the logged-in shell before the page is wired, so an `isloggedin()` beside it would be unreachable and
  its gate would redden nothing; `course_renderer_test` holds the other half, nothing on the logged-in
  shell arms the module (gate `public_client_never_logged_in`).
- **`dim-public-js` is the visitor's mark, never `dim-js`.** `dim-js` reveals `dim-js-only`, which is
  the star's. The stylesheet's twin rule hides the no-JavaScript submits under `dim-public-js` too, and
  load more and the continue link carry `dim-live-only`, which either mark reveals (gate
  `js_public_mark_never_dim_js`).
- **`Ajax.call([...], true, false)` and nothing after it.** core/ajax sends a GET only when a cachekey
  is the sixth argument, and `service.php` answers such a GET with ninety days of `public, immutable`
  for any past integer; three arguments is a POST. The service refuses a `cachekey` too (step 3), so a
  `$_GET['cachekey']` left set in a test refuses every later call in the process: the test's
  `tearDown()` unsets it.
- **The language is a parameter, and a refusal ignores it.** The request has no session to keep the
  reader's choice, so the client sends `Config.language` (`M.cfg.language`) and the service forces a
  pack that `translation_exists($lang, false)` and gives it back in a `finally` (PHPUnit and a batch
  share the process). But `get_exception_info()` re-reads a refusal's message after `execute()`
  returned, so a refusal speaks the request's own language whatever `lang` says. The client therefore
  never shows a service message: it shows the page's `category_publicfallback`, preloaded with
  `strings_for_js()` so a failed network costs it nothing, and loads the address asked for
  (`location.assign`, not `form.submit()`: the toolbar carries no `cat` field). Only the latest request
  falls back, and `init` never fetches, so a failure ends in a page load and never loops. PHPUnit
  installs English alone, so the language test writes a two-string pack to `$CFG->dataroot/lang/xx`
  and resets the string caches (`translation_exists()` reads the language-menu cache).
- **The one refusal is one only below DEVELOPER debugging.** At DEVELOPER the framework adds a
  backtrace naming the line that refused, so a test of the matrix sets `$CFG->debug = DEBUG_NONE` and
  asserts that precondition.
- **A public area is asked by its own id (`withcategories`), a merely listed one as a `slice`.** The
  poster says which: the server leaves its `data-titleurl` empty exactly when the area has no page a
  visitor may open. A slice is flat (`recent` or `name`, never `''` or `category`), with no `cat`,
  `page`, `perpage` or `withcategories`, which the service refuses beside it; its shape is checked
  BEFORE the gates, so a grouped slice on a withheld id is `invalidparameter`, not the category
  refusal. A slice naming no merely listed poster of the page is dropped, never refused, and so is a
  `cat` the page does not offer: the answer is the unfiltered listing, byte for byte the same for a
  missing id and a withheld one (gate `pws_cat_dropped` puts back the refusal the logged-in function had before OD-13). Sending every
  poster as a slice, as a first reading of the service suggests, drops every public area's slice and
  turns each press into a page load; `rows_anonymous_test` holds both shapes against the page's own
  panel. Every poster of a visitor's page opens its panel, at rest and under a criterion
  (`population::expandable()`).
- **One call per HTTP request, and the counter is static.** `service.php` runs every call of a batch
  with no cap and does not stop on an error without a session, so the second call of this function in
  one request is refused. `fixture::forget()` resets the counter (`get_public_category_rows::reset()`);
  a test that calls twice without it gets the second call refused. The client asks a panel's question
  once per opening (`client.question`; `adopt()` counts the panel the server drew as asked), after
  `PANEL_QUIET` (120 ms); core/ajax has no abort, so a superseded answer is dropped by its ticket.
- **The visitor's import graph is the security property**, walked transitively, builds included
  (`categorypublic_source_test`): a session module pulled in through a shared module ships to the internet as surely
  as one in the entry file (`js_public_graph_no_star` adds the star to the shared listing, `js_public_graph_panel_no_star`
  asks the bridge for it from the posters' component, to prove the walk sees both). So no module of the graph may be
  named `categorypanel*`, `categorystar*` or `categoryvitrine*` - the test forbids `theme_dimensions/categorypanel` in
  any build of the graph - and, since R4a, no module of it names those three or a logged-in service ANYWHERE, comments
  included, because the forbidden strings are read from the raw source and both builds. `daterange` is shared for its
  `period()`: it imports only core modules, whose template and string services are `loginrequired => false`.
- **The visitor's poster stays an `<a>` announced as a button** (`role`, `aria-expanded`,
  `aria-controls`): the posters' component's `links` option since R4a. A plain click, Enter and Space (`onSpace()`)
  open it in place; a click with a modifier or already prevented follows the link (`elsewhere()`), and middle and
  secondary presses never reach a click listener. `linkPosters()` points every poster at its panel under the address
  on screen after every change - the one write on a poster that is not the component's. Any failure, and an answer
  with no panel of the area asked about, loads the poster's link with the panel's order and its fragment
  (`panelFailed()`, the visitor's answer to `dim-areas:unanswered`); the logged-in client keeps its inline error and
  retry. The panel's criteria are read off the address through `Listing.readUrl()` on a throwaway object, not off the
  listing's state: the visitor's popstate listener runs before the component's (registered later, at mount), and the
  address is already the new one for both.
- **`dim-panel-js` is the posters' component's mark**, written by its `arm()` on both shells (at mount where no panel is
  open, otherwise on the first change: R3). No hover peek for
  visitors: the minis hide their disclosure on both shells, and a peek on the visitor's grid is a
  change of its own.
- **The budget is a fixture's.** `get_public_category_rows_test` records 17 reads (PHP cut) or 19
  (SQL cut), flat in N and in P, and 1 for a refusal. Its fixture stores no crest: on m502's data flat
  12 read 27 and flat 48 read 51, one `{files}` statement per stored crest inside
  `landingfields::values_many()` (round E1), until R0 (2026-10-27) read every badge file in one statement;
  a catalogue with crests pays that one statement more, flat in P.
- **Proving a JavaScript mutation builds**: Moodle's eslint override for AMD applies to
  `**/amd/src/*.js` only, so a mutated copy is linted under such a path, in a scratch copy mounted at
  the plugin's mount point. Never mount a scratch directory BELOW `~/dev/moodle-502/public/...`:
  docker creates the missing mount points on the host, inside the checkout.
- **No cache of the public catalogue, on purpose (D39).** No layer of the design's §3 meets the
  owner's rule (OD-12) at m502's 49 public courses: the card export is the largest piece, and the
  population work a cache would replace is about 3 ms. Revisit when the busiest production subtree
  reaches about 930 public courses at 24-card pages (440 at 48, 1,080 at 12) or a concurrency run
  shows the database as the larger CPU consumer; D39 has both readings of that trigger and the open
  decisions (OD-4, OD-6: a rate limit on `/lib/ajax/service-nologin.php`). **Measure with Xdebug off
  and debugging none**: the stack's defaults double every number. Count errors by body, not status:
  at 100 concurrent requests m502 answered 1 to 2 % with `dbconnectionfailed` inside an HTTP 200.
- **Pending the owner's sign-off (OD-5), not agreed:** `local_unlistedcourses`' `CLAUDE.md` says never
  to cache its answers across requests, because cohort membership changes without events. The visitor
  path reads no cohort and no role (`category_access` skips both for a visitor), which is the only
  thing that would exempt a theme snapshot of the public catalogue. If a cache is ever built, that
  sentence needs the owner's sign-off in BOTH repositories' `CLAUDE.md` first, with the test of the
  design's §3.6 (cohorts and roles changed without events, the anonymous answer unchanged).

## The category showcase: the state left per category, the open area's colour, the folded crests (round I, lot C1, 2026-10-11)

The owner's three requests of the round I mockups (screen 1 variant B, screen 2 variant A, and the
remembered state); section 5.2 of the original design record (not part of this repository) carries
the design and what is not verified. These are the facts a maintainer must not lose.

- **A remembered state is ONE WHOLE and fills in a BARE address only** (`criteria::with_remembered()`):
  an address that names an organisation, a panel, a term, a chip, the favourites (`fav`, since stage
  T1), a field, a bound or the tree is read as written, with the category's default for what it
  leaves out. That, and `criteria::$remembered` making `sort_params()` name the organisation in every address minted for a reader with a state on
  the category (the client's `buildUrl()` likewise, and the landing address rewritten in full), is
  what keeps a shared link meaning the same to whoever opens it. `default_sort()` stays per category
  and never per person: whatever it answers is what `sort_params()` leaves out.
- **The write is a web service, not a declared preference**, because 5.2's preference route refuses
  any value its clean callback changes (`$cleanvalue !== $value`), so a declared JSON map would have to
  arrive merged, bounded and validated byte for byte from the client. The preference is deliberately
  NOT declared in `lib.php`, which is what makes core's own route refuse it. No event: the service
  writes the reader's own display preference on every toolbar press; the fleet rule of an event per
  write was weighed and not applied (recorded in the class docblock for the owner).
- **`remembered::record()` refuses a value outside its vocabulary and keeps what the entry held**, and
  `decode()` validates the stored value again on every read (a field taken off the filter list, an
  option removed, a hand-edited row). The bound is twenty categories, then whatever it takes to fit
  1,333 characters, the least recently WRITTEN first; reading refreshes nothing.
- **The view is the one part of the state an explicit address still leaves to the reader**, read
  address > this category's entry > the global `categoryview` preference > cards; the global one is
  still written on every change. `course_category()` calls `view()` BEFORE `from_request()`, because a
  `view=` in the address creates the entry the request then has to know about.
- **The panel's colour is the open poster's `--dim-card-band`**, written on the panel's style by the
  server (`panel_style()`, through `branding::hex()`: the sink is five integers and one colour) and
  copied by the client's `place()` on every placement (`categoryareapanel`, or `areas/panel.ts` on the
  logged-in shell since R3, both writing it only when it changes). The rail, the head's tint, the glow,
  the arrow's tint and the dark page's lift are each the band itself as a layer at an opacity, because
  stylelint refuses `color-mix()`; `.dim-panel__inner` reaches 16px below the panel and gives it back
  as padding so the glow is inside the clip of the grow.
- **The spotlight dims the other posters' WHOLE card, text included** (the owner's round I decision,
  superseding "images only, never the text"): `filter` on `.dim-poster__card` and the vignette as the
  card's `::after` at z-index 2, transparent to the pointer. A filter on any CHILD of the card would
  make it the containing block of the stretched control's `::after`, shrinking the click area to it;
  the focus lifts it all (`filter: none`), or the 3px ring would be greyed with the card.
- **A compact or folded hero keeps its crests** (36x40, 26x29 on a phone): the context draws the same
  crests in both bands and the size is the stylesheet's alone.

### Round I, lot C2: the course dialogue (2026-10-11)

"More information" on a card opens a dialogue instead of the in-place disclosure (screen 3, variant B of
the round I mockups). Files: `amd/src/categorycoursemodal.js`,
`templates/category_card_modal.mustache`, `templates/category_course_modal.mustache`,
`category_card::modal()` and `modal_primary()`, `.dim-cmodal` in `scss/category.scss`.

- **The dialogue is `core/modal` with the theme's own frame template, and the course title lives on
  the cover, not in the header.** The header keeps core's close button and a visually hidden
  placeholder `h2`, because the mustache lint rejects a header with no element it can label. The module
  then hands the placeholder's id (the one core's root is labelled by) to the cover's title and removes
  the placeholder, so the dialogue has ONE heading, the visible one, which scrolls away with its picture.
  The cover's title carries core's `data-region="title"` hook and no id of its own because a page holds
  many dialogues' templates and only one dialogue.
- **The data is the card's own context, in an inert `<template data-region="dim-card-modal">` inside
  each card, so there is no new web service and the visitor shell needs none.** The server draws it and
  every client module that renders cards draws it from the same context, so a card appended by "Load
  more" or a filter opens the same dialogue. The module's listener is on the `document` and calls
  nothing; it clones the template and moves the sheet into the modal's body and the actions into its
  footer.
- **The buttons decide nothing of their own.** Secondary = `category_card::url()` (with
  `may_enter()`), labelled course page or event page. Primary = the state's word over that same rule
  (`MODAL_PRIMARY`: enrolled, free, open; every other state keeps the card's `cta()`, so the dialogue
  never offers less than the card). "Enrol" targets `url(false)`, the enrolment surface a reader who may
  NOT walk in would get, so staff are offered both. When primary and secondary have the same URL only
  the primary is drawn. A held event and the visitor shell get no primary.
- **A course whose enrolment opens later gets an `aria-disabled` button, never `disabled`**, reading
  "Enrolment from dd/mm/yyyy" (`category_modal_opens`; `opens_label()` decides, exactly as the card does). A disabled
  button leaves the focus order and a screen reader could not reach the one place the date is printed;
  it is therefore printed ONCE, there, and the secondary button stays beside it because a disabled
  primary goes nowhere.
- **No JavaScript, a failed module or a card with no template: the native `<details>` disclosure opens
  the panel in place.** That is the whole fallback. With the module armed the click's default is
  prevented (a summary toggles from Enter and Space too), so `categoryvitrine`'s `closeOthers()` and
  its `toggle` listener now run only on that path.
- **The backdrop is core's single shared element**, so it is tinted only while this dialogue is open
  (`:has(.dim-cmodal.show) .modal-backdrop`) and goes back to core's the moment it closes; the footer
  gets a shadow only while the sheet has more to scroll.

## The category showcase: the filter line (T1 to T4, 2026-10-16 and 17)

The toolbar's three rows became ONE line in the fleet contract's slot order (scope, search, star,
Filters, Organise, view, the managers' menu) and a row under it that shows only while something
narrows; the favourites left the state chip and became a toggle of their own. The contract and the
plan, with the decisions taken while briefing, are `moodle-dev/docs/filter-line/CONTRACT.md` and
`THEME-PLAN.md`; `CHANGELOG.md` has the stage entries. These are the facts a maintainer must not lose.

- **`fav` is read only for a real logged-in account, never a visitor nor the guest**
  (`criteria::can_favourite()`): favourites are rows of the reader's own, and core keeps none for the
  guest. The toolbar draws the star on the same answer, and the visitor's service declares no `fav`.
- **Older spellings of the favourites are read as the toggle with no state**: `chip=favourites` on an
  address (`criteria::from_request()`) and a remembered entry holding the favourites as its state
  (`remembered::entry()`). Bookmarks and shared links from before T1 must keep opening on the
  reader's favourites. The anonymous shell reads no remembered entry at all, so it never restores one.
- **`fav` is dropped on the request wherever the state chip is** (`population::resolve()`: a
  visitor's page and the SQL cut, which classify nothing). Kept, it would put the page in result
  mode over a listing nothing narrowed, with no control to show or clear it.
- **The state and `fav` are each counted with the other applied**, as the facets are: a scope cell's
  number is what pressing it would leave under the favourites in force, the star's what pressing it
  would leave under the state in force. Both are taken before either narrows the ids
  (`population`'s PHP cut).
- **The star posts without JavaScript through a submit button carrying the NEXT value** (`value` 1
  while released, 0 while pressed), plus a hidden `fav=1` field EARLIER in the form, enabled only
  while pressed, so every other submission (Enter in the search, the view toggle, "Filter") keeps the
  favourites. Turning it off posts both and PHP's last value wins, which is the button's; the client
  enables and disables the field as it toggles. The name-less default submit stays the form's first
  submit, before the star and the view buttons (`toolbar_default_submit`; stage 3's rule).
  `card_markup_test::test_the_favourites_toggle_posts_without_javascript` reads the form as a browser
  builds the query and as PHP reads it.
- **A layout state the server knows is a class, not a `:has()`.** The `:has()` written for T1's star
  made scssphp warn ("Array to string conversion"), so T1 put `dim-toolbar--hasstar` on the form
  instead; the one state left on the line is `dim-line__chips--rest`, written by the server on the
  row and toggled by `paintChips()` from the answer's `hasfilters`. Other `:has()` rules of this file
  compile, so a new one is tried against a real compile (the `get_css_content()` one-liner of the
  visual refinement's section) before it is believed.
- **The scope (All, My courses, Open, Scheduled) and "More statuses" (Under review, Free access) are
  ONE radio name, `chip`, across the line and the panel**, so one choice holds across both. The split
  is `criteria::SCOPE`, mirrored as `SCOPE` in `categorylisting.js`, and
  `category_listing_markup_test::test_the_scope_holds_the_scope_values_and_more_statuses_the_rest`
  pins the two lists equal: change both or neither. The client inserts a returning cell in `SCOPE`'s
  order (`paintGroup()` moves only what is out of place, so the focused radio is never detached); a
  cell appended at the end, "All, Open, My courses", was a real defect of this stage.
- **The chips under the line are built by ONE server builder, `category_toolbar::chips()`, for the
  page and both web services** (`chips` and `clearall` in the rows answer, through `rows.php`); the
  browser composes none, as no other sentence or number of the showcase is composed there. Each chip
  is a link dropping exactly its criterion and keeping everything else - the organisation, the page
  size and the open panel included - and the period's chip drops both bounds. "Clear all" is drawn
  from two criteria up (`criteria::narrowing_count()`). The search, the scope and the star are on the
  line and get no chip.
- **The Filters panel's popover is NOT a scroll container**: no `max-height`, no `overflow`. With them
  it clipped the date calendar, which opens inside the panel. `category_scss_test` asserts the
  absence on the base rule and the 576px popover rule alike.
- **The hero's tab rail is no scroll container on a phone, and stacks at `z-index: 31`.** Its
  `overflow-x: auto` turned it into a 50px scroll box that clipped core's More menu, and the toolbar's
  stacking context (`.dim-toolbar`, `z-index: 30`) painted over what was left. Core's more-menu
  already collapses the tabs that do not fit, so the rail has nothing to scroll. The 31 must stay
  above the toolbar's number; `category_scss_test` pins it.
- **Both shells' line is the React component since R4a** (R2's section below, which every rule of this one binds as
  well): `categorypublic` hands its line over with the shared `mountLine()` and calls `tellAnswer()` after every answer,
  as `categoryvitrine` does. `categoryline` and `categorypreview` are armed by no shell and leave with R5.
- **The step is measured on the line's own width with every scope cell drawn**: the server writes a cell with nothing
  to offer as `absent` (hidden), the measure unhides them under the `measuring` class, with no transition. Digit widths
  still move the step at a boundary (measured: 1024 flips s3 to s1 when counts drop to one digit).
- **Suggestion counts come from the last answer given while the field held no term**, or a typed word would zero
  every option. The search opens only on the reader's own focus (a press, Tab, typing), never on a focus a client or
  WebDriver moves there, which keeps the older Behat scenarios unobstructed.
- **A term in force while the field is folded is a server chip** (`param=q`, dropping `q` alone), shown by the
  stylesheet only then; at steps 1 and 2 the field itself says it.
- **The preview (T4) decides from facts the card states and never asks the server** (`filterline/preview.ts`, imported
  by the line's component on first use, so it is in the visitor's graph and imports nothing). The facts are `data-chip`, `data-fields`
  (`fieldid:value` pairs, `:0` for none) and `data-start` (the site timezone's day, `criteria::iso()`) on the
  article, decided by `category_card` and declared in `get_category_rows::card_structure()` - a fact left out of the
  returns reaches the page's cards and never a client-rendered one. The favourite is the card star's
  `aria-pressed`, not a data attribute: `categorystar` keeps that one live and a `data-fav` would go stale on the
  first press.
- **A card states what its reader may already see on it, and a fact a card does not state stands the preview
  down.** A visitor's card has no chip and no star, a field only where `course_handler::can_view()` says yes or the
  card prints it (`category_card::PRINTED_FIELDS`: the model is the kind tag, the modality the meta line) and a start
  day only on an event. A missing fact is "not known", never "goes": the module lights nothing rather than dim a card
  it cannot judge. The field values come from the population's map, free in the PHP cut; the SQL cut reads no map,
  so its cards state no field and a field option previews nothing there. Adding a statement for it would move
  `budget_test`'s SQL-cut numbers.
- **A preview previews a control that would NARROW**: a pill or the star already in force, an "All" or a field's
  "Any" previews nothing, or a filter applied by keyboard would leave every card lit on the answer. Pointer previews
  are a mouse's or a pen's (`pointerType`); a finger brackets every tap with an over and an out. Keyboard previews
  need `:focus-visible`, because a click on a pill's label focuses its radio. The listeners are delegated
  `pointerover`/`pointerout` on the form (enter does not bubble, and the line's pills are recreated by every
  repaint); `categoryline.repaint()` calls `Preview.refresh()`, since the answer replaced the cards.
- **A receding card remaps tokens, never opacity**: its surface, inks, line, every coloured pill (fill with ink, to
  the none pair), the star's gold, the call to action through `--bs-btn-*`, and `--dim-card-film`, a layer over the
  band drawn only under `dim-vitrine--previewing` at z-index 3 inside the band's stacking context. The lit ring is
  2px INSIDE the card (`outline-offset: -2px`): the panel's rail is a scroll container and would clip an outside one.
  The receding values and their dark half are tokens on the roots (`--dim-preview-ring`, `--dim-recede-*`).
- **Write the `version.php` bump at commit time, and ask before upgrading the stacks.** This checkout
  is mounted live on m503 and m503b (`plugins.conf`), shared with other sessions; a bump on disk makes
  both wait for an upgrade - cron suspended, administrators redirected to the upgrade page - under
  everyone using them. The stage's number goes in `CHANGELOG.md` while it is worked on, and the stacks
  are upgraded after asking the other sessions.

## The category showcase: first paint and first use (P0, 2026-10-26)

P0 of `docs/vitrine-react/PLAN.md` (the performance plan; section 5 has the measurements). What a maintainer
must not lose:

- **`eager` is decided once, on the page's own first paint.**
  - `category_listing`'s constructor takes `$firstpaint`, and only the two page entries pass it:
    `course_renderer::course_category()` and `request::handle()`.
  - With it, the first `FIRST_ROW` (3) covers of the first page load at once when no posters sit above them;
    otherwise the posters' first visible row does.
  - A web service answer (rows, panels, slices, later pages) never sets it. That is what keeps the
    page-versus-answer parity tests exact: they compare every key but `eager`, and assert that an answer never
    marks it.
  - Gates: `first_row_*`, `posters_first_row_eager`, `first_paint_entries`, `first_paint_visitor`.
- **The cover film has two modules.**
  - `covervideo` is the entry the page arms. It marks the body (`dim-cover-js`) and imports
    `local/coverfilm` on the first pointer, focus or finger on a cover.
  - A press on a cluster control that arrives before the film is held (`stopImmediatePropagation`) and given
    back to the same control once the film listens.
  - A hover is replayed only while the band is still `:hover`, or the film would start under a pointer that
    has already left.
- **The course dialogue imports `core/modal` on the first press, held as pending.** Core already loads the
  modal stack on these pages, so the saving shows only where nothing else asks for it.
- **The filter line imports `categorypreview` on the first pointer or focus on the toolbar**, or on the first
  suggestion walked to. `Preview` in `categoryline` is a stand-in until then: clear and refresh do nothing, and
  show imports the preview. The component, on both shells since R4a, does the same with `filterline/preview.ts`,
  through `filterline/lazy.ts` (R2).
- **A dynamic `import()` is part of the visitor's import graph.** `categorypublic_source_test::imports()` reads
  both import lines and `import('...')`. A walk that read only import lines would let a module loaded on first
  use leave the walk in silence.

## The performance probe (R0)

Section 5.4 of `docs/vitrine-react/PLAN.md`. Three instruments; only the first is a gate.

- **The gate: the deterministic counts, in PHPUnit.** `tests/output/core/first_paint_budget_test.php` holds the
  bytes one card costs and the rest of a listing costs (a slope, four cards against twenty-four), the hero's bytes,
  and EXACTLY the theme's AMD modules and strings_for_js a page asks for at load, on the logged-in shell and on the
  visitor's; `budget_test` and `course_renderer_test` hold the statements. A byte ceiling is the measurement plus
  3 % (two runs differed by 1.3 %); a module list is exact, so a module added to or moved off the first paint
  changes the list in the same change. Filters' modules (MathJax) are the site's and are not compared.
- **The meter: `docs/vitrine-react/probe/loadprobe.php`** (export-ignored, phpcs-ignored). `--seed` builds the
  5,000-course subtree idempotently (fields, covers on a third, a quarter public, unlisted and hidden ones, enrol
  shapes, a student and a manager with random passwords nobody types, a branded root); `--run` meters the REAL
  entries (`course_category()` + `full_header()` as student/manager/admin, `request::handle()` + render as the
  visitor), warm or `--cold`, as a table or `--json`. The stack mounts the live checkout, so run a copy:
  `docker cp docs/vitrine-react/probe/loadprobe.php m503b-webserver-1:/tmp/loadprobe.php`, then
  `docker exec -u www-data m503b-webserver-1 php /tmp/loadprobe.php --seed` (or `--run --viewer=visitor --cold`).
  **The 5,000-course set lives on m503b and the owner keeps it for tests: never delete it.** Its numbers carry
  Xdebug unless the stack's Xdebug is off (`mdl xdebug`); compare runs of one configuration only.
- **The trend: the browser probe**, `tests/behat/theme_dimensions_perf.feature` (`@theme_dimensions_perf`, one
  tag per scenario) over `behat_theme_dimensions_perf` and the engine `tests/behat/theme_dimensions_perf_probe.php`.
  It SKIPS unless the site opts in: `$CFG->behat_theme_dimensions_perfprobe = true;` (or an array overriding the
  engine's `DEFAULTS`: runs, cpu, latency, download, upload, quiet, timeout) in the stack's `config.php`, or
  `THEME_DIMENSIONS_PERFPROBE=1` in the runner's environment (`mdl behat` does not pass one through). Then
  `mdl behat m503b @theme_dimensions_perf` on a stack that mounts the checkout under test; every probe step loads its
  page five times under 4x CPU and a 150 ms / 1.6 Mbps network and writes every run and the medians to
  `~/dev/moodle-dev/data/<stack>/faildumps/theme_dimensions_perf/`. FCP, LCP, TBT, CLS and INP are a trend and an
  A/B measure until the hosts' noise is known; the browser's counts (scripts and stylesheets before load, their
  bytes, DOM nodes, HTML bytes) are read from the COLD series, because a warm load answers most requests from
  memory and reports fewer of them.
- **How the engine measures, and why each piece is there.** CDP goes over chromedriver's vendor endpoint
  (`POST /session/{id}/goog/cdp/execute`, php-webdriver's `ChromeDevToolsDriver`), which the Selenium grid forwards
  (measured on m503b's 4.46 grid). The collector is registered with `Page.addScriptToEvaluateOnNewDocument`, before
  any page script, so no entry type depends on the browser buffering it for a late observer. A page is settled
  when it is loaded, `M.util.pending_js` is empty and no entry arrived for two seconds. The press for INP is a real
  WebDriver click, so the browser counts it as an interaction.
- **The dev site from the grid**: the Selenium container reaches the dev site, not only the Behat site at
  `webserver`, through Chrome's `--host-resolver-rules=MAP localhost:9503 webserver:80` (the Host header stays the
  dev site's). That is how the engine was proved against m503b's seeded visitor page outside Behat.
- **The stacks run `cachejs` off**, so a page asks for its AMD modules one by one (95 scripts before load on the
  visitor's page, cold). For production-like numbers measure under `mdl fast <stack> on`, and say which.
- **Seed every discoverability state explicitly.** local_unlistedcourses' creation default can be unlisted (it
  was on m503b): a seed that sets only the public courses measures a catalogue of unlisted ones, whose cost is the
  plugin's probe cap (a few statements per unrelated unlisted course, up to fifty), not the showcase.

## The category showcase: the server render of the first paint (R0, 2026-10-27)

What the page costs per card after the bulk reads, from a measured review (m503, Xdebug off: 37 cards in 32-35 ms).
What a maintainer must not lose:

- **A card reads ten landing page fields, and only those are read.** `cards::values()` hands
  `landingfields::values_many()` `cards::CARD_SHORTNAMES`; the landing page and every other caller read all
  of them. A card that starts reading another value names its field there: `cards_test` reads the keys
  `category_card` reads off its source and compares them with a full read and with a read of the course alone.
- **Only a DEFAULT controller is shared between courses**, and that is safe only because a default reads the
  field and never its course (a textarea formats its default in the field's configuration context, a picture
  has no data id). A controller over a stored row is never shared (gate `values_default_shared_only`).
- **A field's name is formatted once per call only while `filterall` is off**; with it on, format_string()
  runs each course context's filters and the name may read differently per course (`values_names_filterall`).
- **The badge files are one statement** (`landingfields::crest_files()`), which must answer what
  customfield_picture's `get_file()` answers row by row: the row's own context, no directory, the first by
  item id, path and name. Re-read `get_file()` on every customfield_picture update; three gates hold the rules.
- **Three per-request memos are reset by `landingfields::reset_field_cache()`**: the field cache, the option
  lines of the selects and `facets::fields()`. The last is keyed on the raw setting and on the very list of
  controllers the course handler holds (identity), which core rebuilds when a field changes through it and per
  PHPUnit test. `category_card`'s labels are memoised per language; the fixture's `forget()` drops them.
- **Core's `course_image` cache is compared before it is written**, without its data source: `has()` asks the
  store, `get_many()` gets only held keys (a missing key in `get_many()` loads one course at a time).
- **`priority` (fetchpriority="high") is the first eager card with a cover**, on the page's own first paint,
  and only where the hero armed for the same category paints no picture (`category_hero::draws_image()`, the
  same answer as its `hasimage`). It is a card key the rows service declares and always answers false; the
  page-versus-answer parity tests normalise it with `eager`.
- **The unlisted courses handed to the plugin arrive with their contexts preloaded** (F1), in both page-cutting
  modes (`gate()` and `admitted_unlisted()`): local_unlistedcourses' relationship check asks core for each probed
  course's context, which on a cold cache is one `{context}` statement per course - measured on m503b's 5,000-course
  seed as the root of the subtree not being statement-flat for a logged-in reader. From a clone, by the stage 2
  rule. `budget_test` counts the single-row context reads at one and at thirteen unlisted courses.
- **The modal partial stays a partial.** Inlining it into `category_card.mustache` would save
  `Engine::loadPartial()`'s re-hash, but `category_card_modal_test` and `card_markup_test` render it alone and
  two gates target its file: a copy kept for them would be a template nothing ships.

## The category showcase: the calendar as a React component (R1, 2026-10-27)

Stage R1 of `docs/vitrine-react/PLAN.md`. The logged-in shell drew `js/esm/src/daterange/DateRange.tsx` from R1 and the
visitor's from R4a: one calendar. `amd/src/daterange.js` mounts on no shell and stays for `period()`, the reading of an
address both clients share; `model.ts` keeps the AMD functions' names.

- **The contract is three events on the toolbar's `dim-daterange` region**, spelled in `categorylisting.js`
  (`CALENDAR`) and `DateRange.tsx` (`EVENTS`), held equal by `daterange_component_source_test`: the component
  sends `dim-daterange:mounted` once drawn and `:pick` (detail `{from, to}`, bubbling) where the AMD module called
  `onpick`; the listing sends `:set` from `paintDates()` on both shells. The
  listeners go on before the node; the fields hide in the mounted listener alone, and the listing tells the
  period again there, for a quick option or Back pressed while the module was still loading.
- **The specifier lives in `categorylisting`'s `COMPONENTS` since R4a**, with the line's and the posters'; no client
  names a component, and the visitor's walk reaches each through the shared listing (`categorypublic_source_test`).
- **The mount is an APPENDED child** with `data-react-props` the props object itself (`{id, firstday, today, from,
  to}`): `react_autoinit` observes `childList` only. So the markup is region > mount node > `.dim-daterange`, one
  block wrapper deeper than the AMD calendar; the popover still positions against the region.
- **The component renders nothing until its eleven strings arrive** (`@moodle/lms/core/stringUtils`), holding
  `theme_dimensions/daterange:mount` on core's pending counter until it says it is drawn; the mounted event goes
  out from a layout effect, so the boxes appear and the fields hide in one frame. Without a valid `today` it draws
  nothing and the fields stay. A string failure shows core's exception through the bridge.
- **Focus across repaints**: cells are keyed by date, so a press within the months on screen keeps the focused
  button; a change of months asks a layout effect to focus the day stood on, which runs before the focus-out
  rule's timeout, as `focusDay()` does in the AMD module.
- **Jest cannot move the process's timezone**: it hands a test a copy of `process.env`, so `TZ` set there never
  reaches Node. `js/esm/tests/daterange/zone.ts` replaces Date's local-time methods with New York's 2026 rules for
  a suite instead, with a control test; a local-time constructor is invisible to it, which is what the PHP source
  test's `new Date(Date.UTC(` count is for.
- **`grunt eslint:react` runs with `--fix`, and the fix WRITES empty docblocks** into the source for an arrow
  function in an object literal (the block_compass trap of the `.eslintrc`). Lint a change with
  `npx --no-install eslint --no-fix --max-warnings 0 public/theme/dimensions/js/esm/src` first, and write such a
  table as a function.

## The category showcase: the filter line as a React component (R2, 2026-10-27)

Stage R2 of `docs/vitrine-react/PLAN.md`. The logged-in shell drew `js/esm/src/filterline/FilterLine.tsx` from R2 and the
visitor's from R4a (whose line has no star, and a scope only where the server draws one: the component draws what it
reads). A rule of the line changes in the module of the component that carries it (`steps.ts`, `measure.ts`,
`suggest.ts`, `mark.ts`, `preview.ts`, named after the AMD functions of `categoryline`, which no shell arms since R4a).

- **The mount is the server's line itself.** `categorylisting`'s `mountLine()` writes `data-react-component` and
  `data-react-props` (`{form}`, the props object itself) on `[data-region="dim-line"]` and puts the line back where it
  stands, which `react_autoinit` reads as an added node; it listens for `dim-line:dropterm` and holds a pending until
  `dim-line:mounted`, both before it names the line. The line keeps its place in the form, its class and the sibling
  rule `.dim-line--folded ~ .dim-line__chips` the stylesheet writes against it; a wrapper would break that rule and add
  a box to the toolbar's flex column. A line without its field or its listbox is never handed over: the component's
  first commit empties its node (`textContent = ''`, React 19) whatever it renders.
- **The first render reads the markup it replaces** (`model.ts` `read()`, inside a `useState` initialiser, before the
  first commit empties the node) and draws it back, the template's markup hook for hook, the same names in the same
  GET form, and any term typed or radio pressed before the mount. Jest compares the two trees
  (`FilterLine.test.tsx`, over `js/esm/tests/filterline/toolbar.ts`, the template rendered from its example context),
  and `filterline_component_source_test` holds that copy equal to Moodle's render: **change the toolbar's line in the
  template and render the copy again** (core's `lib/amd/src/mustache.js` in node renders it). The component adds only
  the combobox's and the listbox's ARIA, which `categoryline` used to write at arming.
- **The ownership boundary: React draws the line's own controls; three frames hold server markup it adopts and never
  diffs.** Drawn: the scope, the search with its eraser and icon, the star, the Filters and Organise summaries (the
  badge included), the view toggle, the close button, the listbox. Adopted on the first commit (their child nodes
  moved into empty frames React drew): the Filters panel (`dim-filters-panel`: More statuses, the custom fields, the
  period with R1's calendar, the quick dates, the no-JS submit), the Organise menu (`dim-organise-menu`) and the
  managers' menu (`dim-line-manage`, a hook added in R2). The panel stays server markup because R1's calendar, the
  native date fields and the panel's pills are painted by `categorylisting` on both shells; drawing them here would
  have meant a second spelling of every one of those painters.
- **No AMD module writes into what React draws.** Every painter of `categorylisting` that touched the line asks
  `drawnByLine(element)` - inside a line the component marked `data-line-drawn` and outside the three adopted frames -
  and tells the component instead: `dim-line:state` (`{query, chip, fav, view}` or part of it, from `paintControls`,
  `paintFavToggle`, `paintView`) and `dim-line:answer` (`{scope, favourites, filtercount, filtercountlabel}`, from
  `tellAnswer()`, called by `categoryvitrine` after the painters, the scope in `SCOPE`'s order). The four names are
  `LINE` in `categorylisting` and `EVENTS` in `FilterLine.tsx`, the mark `LINE_DRAWN`/`DRAWN`; the source test holds
  them equal. The component marks the line in its first commit's layout effect, so until then the painters paint the
  server's line as before and the component reads what they painted; a line never handed over (no field, no listbox)
  is never marked, and every painter paints it as it always did. Both clients read the field delegated on the form (the
  component's field replaces the server's) and never write it (`onErase` and `focusTarget()`, read off the page).
- **The calendar's node goes in after the line is handed over** (`init`: `mountLine()` before `mountCalendar()`). The
  line put back where it stands is seen by core's observer as leaving and coming back, which would unmount a calendar
  already inside it. The adoption itself is safe: it happens inside the first commit, before the observer's
  microtask, and by then the removed server node no longer holds the calendar.
- **The step is measured once per content, up to the first try that fits, and taken from a width alone.**
  `measure.ts` `stepWidths()` lays the line out under `dim-line--measuring` with every scope cell drawn, once per try,
  and records the width each try needs (the controls' extent with the search at its step's floor), stopping at the
  first that fits the line's width, as `categoryline`'s `fit()` does: each try is a forced layout of the page, and
  measuring all five cost 407 ms of one frame under 4x CPU (the R2 follow-up). A ResizeObserver entry then picks the
  step with `steps.ts` `choose()`, under `flushSync`, before the frame is painted, and reads no layout - unless the
  line narrowed past every try measured (`settled()`), which measures the rest. The widths are measured
  again only when the words, the counts or the slots change (the component's `signature`) and when the fonts arrive.
  The line is folded before its first paint (next bullet).
- **The first measure is the `ResizeObserver`'s first entry's, never the commit's** (PLAN 5.2.6). A layout read inside
  React's commit forces the page's FIRST full layout there (130-150 ms of one frame at 4x CPU on the visitor's page,
  for a line that fitted at the first try); the observer's first entry comes after the browser's own layout and before
  the paint, so `measureFirst()` folds the line in time and takes the width from the entry's content box. Until it
  runs (`measuredOnce`), nothing on the line reads layout: the layers' `place()` and the rows' `fades()`
  (`offsetParent`, `scrollWidth`), a content change and the fonts' remeasure all wait for it. `data-line-drawn` and
  `dim-line:mounted` stay in the commit. Without a `ResizeObserver` the commit measures as before. Gates
  `js_line_first_measure_deferred`, `js_line_first_entry_measures`, `js_line_layers_wait_measure`; any new layout
  read on the mount path belongs after the first measure too.
- **The line is hidden from the commit to that first fold** (`visibility: hidden`, the inline value kept in `veiled`
  and given back by `measureFirst()`, failed or not, and by the effect's cleanup). The browser lays the unfolded line
  out once before the observer's callback, so a fold there moves nodes already laid out, which counts as a layout
  shift (0.027 on every load of the logged-in flat page at 900 px, where the fold goes to s4); hidden nodes count for
  none, and visibility keeps the line's box, so nothing below it moves. A line holding the focus at the commit is
  never hidden, because a focused element inside a hidden ancestor loses the focus: the rare shift is accepted there.
  Gate `js_line_hidden_until_folded`.
- **The first paint's shift (0.031-0.032, PLAN 5.2.1) is removed only where the client keeps the first step.** The
  search's flex basis is the first step's floor, `13rem` (`.dim-toolbar__search`), so a line no client has measured
  wraps exactly where the first step stops fitting. A width at which the client takes step 2, 3 or 4 still moves once
  when the component mounts: the server cannot know the step, and drawing one before the client measures would cost
  the page without JavaScript (a line that never wraps). Measure it with the probe before calling it gone.
- **The component's state is a ref read synchronously, as `categoryline`'s `state` is**, and React draws from it
  (`live`, plus a reducer that redraws); the native listeners call the latest render's functions through `api`. The
  line's container classes (the step, the fold, `searching`, `measuring`) are written on the mount node imperatively,
  because React draws its children and not the node. The search's class goes on at once in `openSearch()`: a field
  folded into its icon takes the focus only once the class has shown it.
- **Focus across draws**: everything is keyed, so a press keeps its control; the scope moved to its own line at the
  fourth step gets its focused cell's focus back after the move; a focus the reader had on the server's line before the
  mount is given to its counterpart, quietly.
- **Jest cannot run a native `import()`**: core's ts-jest compiles the tree as CommonJS under node16, which keeps
  `import()`, and Jest refuses it without `--experimental-vm-modules`. The preview's first-use import is therefore the
  one function of `filterline/lazy.ts`, which the component suite stands in for; the browser runs the real one.
- **Gates**: `js_line_drawn_guard`, `js_line_adopted_exempt`, the seven `js_line_guard_*` (one per painter),
  `js_line_tells_state`, `js_line_answer_told`, `js_line_answer_scope_order`, `js_line_mount_guard`,
  `js_line_reinserted`, `js_line_listens_first`, `js_line_before_calendar`, `js_line_visitor_no_component`,
  `js_line_input_delegated`, `js_line_erase_guard`, `js_line_focus_icon`; `js_vitrine_line_armed`,
  `js_vitrine_drop_term` and `js_clear_focus_target` were retargeted to the logged-in shell's new spelling. The
  component's own rules are Jest's and are mutation-checked by hand, never by the sweep.

## The category showcase: the panel and the posters as React components (R3, 2026-10-28)

Stage R3 of `docs/vitrine-react/PLAN.md`. The logged-in shell drew `js/esm/src/areas/Areas.tsx` from R3 and the
visitor's from R4a, with the visitor's differences as props (the R4a section). A rule of the panel or the posters
changes in the module of the tree that carries it (`panel.ts` for the panel's behaviour, `posters.ts` for the posters'
result layer, `rail.ts`, `model.ts`, `mark.ts`), each named after the AMD function of `categoryareapanel` or
`categoryposters` it stood for. Every rule of the EL-1, EL-2, EL-3 and round I sections binds it.

- **The mount is an empty, hidden child of the areas section**, appended by `categorylisting`'s `mountAreas()` (since
  R4a; `categorypanel` held it in R3), with `data-react-props` the props object itself (`{}` on the logged-in shell). It draws no box, so the first paint is the server's to the pixel: the component never re-renders a
  poster or a panel the server drew, it takes them over. Mounting on the panel's list item would not do: core's
  `react_autoinit` UNMOUNTS a removed node and mounts an added one, so the panel's moves between rows (`moveAfter()`)
  would remount it and lose its state; the areas section never moves. `mountAreas()` runs only where the areas carry a
  panel, puts its listeners on before it appends the node, and holds `theme_dimensions/listing:areas` on
  core's pending counter until `dim-areas:mounted`, so Behat never presses a poster that is not yet taken over.
- **The ownership boundary.** The component owns, and is the only writer of, on the logged-in shell: the panel's
  FRAME (the one list item: hidden state, the three transition classes through `settle()` alone, the five integers and
  the colour on its style, its DOM order; `panel.ts`); the posters' RESULT LAYER (pills, `data-count`/`resultcount`/
  `resultlabel`/`ctaurl`/`ctalabel`/`expandable` on the articles, the control as a button that opens or the link to its
  page with `aria-describedby` handed over, the open state and trigger word, the items' `hidden`, the reveal past the
  slice, "nothing anywhere" and "search all", the hint, `data-filtered`/`data-elsewhere`, the live status; `posters.ts`);
  and the "nothing in N areas" SUMMARY, which React draws through portals into the server's `<summary>` and list, read
  off them in the first render (`readSummary()`), emptied in the first commit's layout effect and drawn back before
  paint. Server markup it never diffs: the panel's CONTENT, `category_panel` rendered by `core/templates` through the
  bridge and inserted whole (`replaceNodeContents`) or region by region (`appendNodeContents` into a holder, then the
  subtitle, the strip, the body and the foot swapped), and each card's own internals, which keep their own modules
  (the star, the film, the dialogue, the progress). The areas' head (`dim-direct-skip`, `paintDirect()`) and the
  holders outside the areas (the toolbar and fold forms' hidden fields, the clear link, the chips) are not the
  component's; it writes the holders as `syncHolders()` always did.
- **The event contract.** On the document, from `categoryvitrine`: `dim-vitrine:pending` (`{filtered}`), `:criteria`
  (`{accordion, areas, filtered, total}`) and `:failed`; from `categorylisting`'s `paintView()`: `:view`; the component
  sends `dim-panel:autoopened` (`{id}`). On the areas section, with `categorypanel`: the component dispatches
  `dim-areas:ask` with `{poster, psort, answer: null}` and `categorypanel` fills `answer` with the logged-in service's
  promise while the event is dispatched (no answer is the panel's inline error); `dim-areas:painted` (`{panel}`) after the
  rows are drawn (`categorypanel` paints the progress); `dim-areas:mounted`. The names are `EVENTS` in `areas/panel.ts`,
  `AREAS` in `categorypanel` and `EVENTS` in `categoryvitrine`, held equal by `areas_component_source_test`. **The
  component calls no service and reads no session** (the test reads its sources for it): what an account adds stays in
  `categorypanel` (`panelArgs()`, `ask()`, `paintProgress()`, the star), so R4 can hand the same component the
  visitor's client. `paintShells()` no longer writes the shells onto the posters: they travel in the accordion.
- **Layout is read in three places, never at mount.** The row is the five integers and the DOM order the last
  poster of the open one's row, both counted from the posters SHOWN in its grid (`shownItems()`, a DOM read) and the
  grid's tracks (`model.ts` `columns()`: `matchMedia` of `WIDE`/`PHONE` and the listing's `dim-vitrine--list` class),
  where `categoryareapanel`'s `place()` read every item's `offsetTop`. The reads left: a move from one open area to
  another reads the pressed poster's top before the move and once after it (one forced layout per such press, to keep
  the poster under the pointer); `ensureVisible()` reads the panel's box 340 ms after the opening, on a laid-out page;
  the grow reads one computed style (`opacity`, a style flush and no layout) so the transition starts from the shut
  state; the navbar's height is a computed custom property. The rail reads its scroll (`railState()`) only from a
  ResizeObserver entry - after layout, before paint - and from its own scroll events. `adopt()` reads nothing: the
  server already placed the panel, and `place()` writes an integer or the colour only where it changes.
  `areas_component_source_test::test_layout_is_read_only_where_the_component_says` counts the reads per function.
- **A server-drawn open panel keeps its no-JavaScript presentation until the component first changes it.** The body's
  `dim-panel-js` turns the rail from a grid of four (1200px up) into a scroll and shows the arrows and the phone
  counter, which redraws an open panel's height: the mark goes on at mount only where no panel is open, otherwise in
  the component's `arm()` on its first opening, close, order or answer. Every write of the posters' layer and the
  rail goes through `setHidden`/`setClass`/`setAttr`, which write only a change, so a pass that agrees with the page
  invalidates no style.
- **The logged-in shell passes no options**: posters are buttons, a question is asked every time, a failure is the
  inline error with its retry, and the one matching area opens by itself. The visitor's options arrived in R4a.
- **Jest is the behaviour gate** (`js/esm/tests/areas`: `model`, `posters`, `Areas`, 32 tests over `page.ts`, a page
  with the templates' hooks and a stand-in of `category_panel`): the latest request writing after each async render
  (the opening's turn, the request's ticket, the transplant's paint), the skeleton never outliving the answer, every
  step starting from `settle()`, Escape only from the panel or its poster, the zeroed open poster staying until closed
  and its close focusing the summary, the automatic opening, the address replaced and never pushed with the holders in
  step, the order's radio group, Back, a mount that reads no layout, and the placement by `matchMedia`. Each of those
  guards was mutation-checked by hand (the suite reddens with it removed); the sweep runs PHPUnit alone.
- **Gates**: `js_areas_mount_guard`, `js_areas_listens_first`, `js_areas_node_hidden`, `js_areas_props_object`,
  `js_areas_ask_answered`, `js_areas_progress_painted`, `js_areas_shells_untouched`, `js_areas_visitor_no_component`,
  `js_areas_no_amd_panel`, `js_areas_events_spelled`. The panel's older gates on `categoryareapanel`/`categoryposters`
  hold the visitor's shell; `js_panel_sends_no_direct`, `js_root_panel_carries_query` and `js_panel_period_read` still
  hold `categorypanel`'s `panelArgs()`, which the logged-in panel asks through.

## The category showcase: the visitor's shell on the React components (R4a, 2026-10-28)

Stage R4a of `docs/vitrine-react/PLAN.md`, the first half of R4 (R4 is split in two to keep each pull request under MDL
Shield's 3,000 effective lines; R4b builds the one listing store). The visitor's live client (`categorypublic`, armed
by `categorypublicclient`) draws the calendar, the filter line and the posters with their panel with the logged-in
shell's components. Every rule of the S1-S4, R1, R2 and R3 sections binds it. What a maintainer must not lose:

- **The components are named once, in `categorylisting`'s `COMPONENTS`, and mounted by its three mounts**:
  `mountCalendar(root, state, onpick)`, `mountLine(root, dropTerm)` and `mountAreas(areas, props, client)`. No client
  names a specifier; `categorypublic_source_test::test_the_visitors_graph_reaches_each_component_named_once` counts them
  per file. The visitor hands its line over before the calendar goes in (R2's reason) and its posters before the root
  without a search line returns (the root's directory has posters).
- **The visitor's graph reaches the components by design, and names nothing of the logged-in shell, comments
  included.** `test_nothing_the_walk_reaches_names_the_logged_in_shell` reads every module of the walk from the
  visitor's module and from the bridge - `categorylisting`, `categorymark`, `daterange` and all of `js/esm/src` the
  components import - raw and in both builds, for `categoryvitrine`, `categorypanel` and `categorystar`; the logged-in
  services are the older test's. A docblock that explains the component by naming the logged-in module answering it
  fails the test: say "the logged-in shell" (`categorylisting` and the `areas` docblocks were rewritten so).
- **The visitor's differences are the posters' component's options, from the mount's props** (`panel.ts`
  `optionsOf()`, anything but the exact value reads as the logged-in shell's): `{links: true, quiet: 120, autoopen:
  false}` (`categorypublic`'s `AREAS_PROPS`) against `{}`. `links` keeps the server's `<a>` and announces it as a
  button (`posters.ts` `driven()`, `convertPoster()`, `setExpandable()` take it off a link that opens nothing),
  answers Space (`onSpace()`) and leaves a modified press to the link (`elsewhere()`); `quiet` delays every question
  and the skeleton by its milliseconds and drops a question the reader pressed past; `autoopen: false` is the
  visitor's shell, which never opened an area by itself.
- **Two answers are events, because they are the client's to compute**: `dim-areas:question` (detail `{poster, psort,
  question}`: the visitor's `panelQuestion()`, its request's JSON) makes a question asked once per opening - a drawn
  panel counts its address's question as asked (`adopt()`), and every opening or closing takes a ticket, so the same
  question about a new opening is asked again; `dim-areas:unanswered` (detail `{poster, psort, taken}`) offers a
  failure before the inline error, and the visitor's `panelFailed()` takes it by loading the poster's link. A client
  hearing neither (the logged-in one) gets R3's behaviour. `mountAreas()` hears them only where the client passes the
  function; the names are `AREAS` in `categorylisting` and `EVENTS` in `panel.ts`.
- **The visitor answers the components as the logged-in client does, with its own service**: `dim-areas:ask` with the
  public call's promise (`askPanel()`: a public area by its id, a merely listed one as a slice), `dim-vitrine:pending`
  and `:criteria` on the document (its `EVENTS`; the component writes the posters' shells from the accordion, and
  nothing of the visitor's writes a poster but its link), `tellAnswer()` after the painters, the search read delegated
  on the form, the eraser and `focusTarget()` never writing the component's field. A failed rows request still loads
  the address (S1-S4); it sends no `:failed`, because the page that loads next is the answer.
- **A line moved after the hand-over is mounted again, and must be drawn again.** Core's `react_autoinit` unmounts a
  mounted node that leaves the document (a move of the line or of any ancestor) and mounts it when it comes back; the
  filter line's container is the server's line itself, so React's unmount empties it and the next mount read nothing:
  no field, no `dim-line:mounted`, `theme_dimensions/listing:line` held for ever (reproduced in a browser by
  moving the line after its mount; the CI timeout of the same day was the next bullet's). `FilterLine`'s unmount gives
  the adopted frames' nodes back to the server's frames and keeps them with the last state in `KEPT`, by node, and a
  mount finds them there before it reads markup. A line it cannot draw sends `dim-line:failed`, which `mountLine()`
  hears like `mounted` (gate `js_line_failed_released`); the calendar's mounted listener is not `once`, so a calendar
  mounted again with the line is told the period again (gate `js_calendar_mounted_every_time`). Jest holds it on the
  visitor's own markup (`js/esm/tests/visitor`): recapture `page.ts` from an anonymous page when the toolbar or the
  posters' templates change.
- **The toolbar form's id is read as an attribute, never as `form.id`.** The visitor's script entry (`category.php`,
  the page a site without the router serves, `mdl ci` and GitHub's CI included) carries the category in a hidden field
  named `id`, and a form's named-control lookup makes `form.id` that field: `mountLine()` handed the component
  `{"form":{}}`, it found no line, and with no line it had nowhere to send `dim-line:failed`, so the pending mount held
  every step of the five visitor client scenarios (CI, 2026-10-28; found by a throwaway build that wrote the line's
  state into a pending key, which Behat's timeout message prints). The routed page has no such field, which is why the
  stacks never showed it. Gate `js_line_form_id_attribute`; use `getAttribute()` for any attribute of a form.
- **The replaced AMD modules stay, unimported, until R5**: `categoryline`, `categorypreview`, `categoryareapanel`,
  `categoryposters` (3,918 lines with their builds and source tests) would have put this pull request over MDL Shield's
  limit. Their source tests and their gates still hold their files; `daterange` keeps `period()` for both clients and
  no shell mounts its calendar (`paintDates()` no longer calls its `set()`).
- **The first paint's module list is unchanged** (`first_paint_budget_test`: `covervideo`, `categorycoursemodal`,
  `categorypublic` and one string). What moves is the graph behind `categorypublic`: three AMD modules
  (`categoryline`, `categoryareapanel`, `categoryposters`) leave it and the components' ES modules join it, as they
  did on the logged-in pages in R1-R3 (four for the calendar, eight for the line, six for the posters).
- **Gates**: the R4a block at the end of `mutations/gates.conf` (25, `js_public_*`, `js_areas_*`, `js_panel_*`, three `js_line_*`/`js_calendar_*`); seven
  S1-S4 panel gates and the R3 mount's gates were retargeted to the component's files and to `categorylisting`, and
  `js_calendar_option_guard`, `js_calendar_visitor_no_component`, `js_line_visitor_no_component` and
  `js_areas_visitor_no_component` were retired. The component's behaviour in both modes is Jest's
  (`js/esm/tests/areas/Areas.test.tsx`, 13 visitor tests, each guard mutation-checked by hand); only Behat
  (`theme_dimensions_category_public_client.feature`) runs the wiring in a browser.

## The category showcase: the shared listing in TypeScript (R4b1, 2026-10-29)

Stage R4b1 of `docs/vitrine-react/R4b.md`, the first of the listing store's three slices (R4b2 the store and the visitor's
shell, R4b3 the account's). `amd/src/categorylisting.js` became `js/esm/src/listing/`, function for function and with no
behaviour change. Every rule of the S1-S4, R1, R2, R3 and R4a sections binds it. What a maintainer must not lose:

- **The AMD clients load it through `core/esm!@moodle/lms/theme_dimensions/listing/listing`**, one import line each
  (`categoryvitrine`, `categorypublic`, `categorypanel`). Core's own `core/str` and `core/ajax` reach their ES modules
  this way; checked on m503b before the port: RequireJS resolves a theme ES module so, it is the instance a native
  `import()` gets, and babel's wildcard interop copies the namespace's exports. `js_call_amd` cannot name an ES module
  (it cleans the name to `PARAM_ALPHANUMEXT`), so a page still arms an AMD module, and `first_paint_budget_test`'s list
  is unchanged.
- **The barrel `listing.ts` exports exactly `categorylisting`'s names**, which is what `Listing.<name>` in the clients
  reads. A name it leaves out is undefined in the browser and nowhere else: `listing_source_test` reads every
  `Listing.<name>` of the clients against it (gate `js_listing_barrel_complete`). The barrel goes with the last AMD
  client (R4b3).
- **One module of `js/esm/src` writes the history, `listing/history.ts`**: `writeUrl()` pushes a change of criteria and
  replaces a correction; `replaceKeeping()` is the posters' component's write of `open`/`psort`, which keeps the entry's
  state and never pushes. `listing_source_test` fails a `pushState`/`replaceState` anywhere else in the tree (gates
  `js_history_*`). `categorypublic`'s `load()` still writes its own until R4b2.
- **Every event between the listing and the components is spelled once, in `listing/events.ts`** (`LISTING`, `LINE`,
  `LINE_DRAWN`, `CALENDAR`, `AREAS`, and the hooks as `SELECTORS`); the components' `EVENTS` are those objects. No other
  module of `js/esm/src` may write an event literal (`areas_component_source_test`, gate `js_events_one_spelling`). The
  AMD clients' own `EVENTS` stay, held equal to `LISTING`, until they move to the store.
- **The visitor's walk crosses the `core/esm!` edge**, and reads an ES module's source AND its builds: a mutation of a
  source alone leaves the build's edges in the graph. A gate that removes an edge from a `js/esm/src` file therefore
  reddens the tests that read the source's spelling, never the walk's reach (`js_public_graph_reaches_areas` names
  no walk test since R4b1).
- **The source tests read the TypeScript**: `amd_source_test`, `categorypublic_source_test`, `categorypanel_source_test`
  and the three component source tests look a shared function up in `js/esm/src/listing/*.ts`, with an extractor that
  accepts a signature written one parameter a line (keep any other signature on one line, as the extractor of the other
  tests expects). The 52 gates that named `categorylisting.js` name the TypeScript file holding their line.
- **The pending keys are `theme_dimensions/listing:line`, `:areas` and `:reveal`.** A key that named an AMD module
  (`theme_dimensions/categorylisting:line`) was an edge of the visitor's walk to that module, since the walk reads a
  quoted `theme_dimensions/<name>` with an `amd/src/<name>.js` as one.
- **`categorylisting.js` stays, unimported, until the removal pull request** deletes it with its build; nothing reads it
  but `category_scss_test`'s scan for Bootstrap 4 names, which reads `js/esm/src/listing` as well.

## The category showcase: the store and the visitor's shell (R4b2, 2026-10-29)

Stage R4b2 of `docs/vitrine-react/R4b.md`. The visitor's live listing is `js/esm/src/listing/store.ts` driven by its shell,
`visitor.ts`, armed by `amd/src/listingvisitor.js`; the logged-in shell keeps `categoryvitrine` until R4b3. Every rule of
the S1-S4 and R4a sections binds it. What a maintainer must not lose:

- **One listing runs on a page, so the store's state is module scope**, and its functions keep `categorypublic`'s names
  (`requestRows`, `applyResponse`, `apply`, `onTree`, ...) so the source tests and the gates followed the code. Its entry
  is `init(shell, categoryid)`; the visitor's `start(categoryid)` hands it `SHELL`. It names the listing through the
  barrel (`import * as Listing from './listing'`), which therefore stays after R4b3.
- **The shell is injected and the store reaches none**: no `./visitor` or `./account` import, no service, method, mark or
  `fetchOne` (`listing_source_test::test_the_store_reaches_no_shell`, gate `js_store_names_no_shell`). What differs
  between shells is the `Shell` object: `rows`, `failed`, `written` (after every address the store writes: the
  visitor's posters' links), `leave`, `normalise`, `answered`, `areas` and `mark`.
- **Back is heard before the posters' component is mounted** (`init` registers `popstate`, removing it first so a second
  `init` never doubles it, then mounts), and only where the page has a toolbar: a root without a search line arms its
  posters alone and hears no Back, as `categorypublic` did (gates `js_popstate_before_mounts`, `js_store_root_live_only`).
- **The visitor's service goes through core's ES module `@moodle/lms/core/ajax`**: `fetchOne(..., {loginrequired: false})`
  and nothing else, which is what AMD `Ajax.call([...], true, false)` reached (core/ajax is a shim over it). Strings
  come from `@moodle/lms/core/stringUtils`, which reads the preloaded `M.str` first, so the fallback sentence still
  costs no request.
- **The hooks the store reads are `events.ts`'s `SELECTORS`** (the root, the areas, the notice, the clear and chip
  actions, the drawn line, the quick dates were added there).
- **The visitor's walk starts at `listingvisitor`** and crosses `core/esm!` into the shell and the store; it reaches no
  AMD listing module any more (`categorypublic`, `categorylisting`, `categorymark`, `daterange` and R4a's leftovers are
  asserted absent). The visitor's shell is the graph's one caller of a service.
- **A repaint must hold a pending across the template load**: `requestRows` takes one token per request
  (`theme_dimensions/listing:rows`, `theme_dimensions/categoryvitrine:rows`) and releases it in its `finally`, painted,
  failed or overtaken. core/ajax's own token ends with the answer, and `renderCards` then loads `core/templates` through
  the module loader, which answers after a timer even for a module already loaded (RequireJS's `nextTick`); Behat read
  that gap as a settled page and counted rows being replaced under it (the visitor's tree scenario, three matrices).
  Gates `js_store_rows_pending`, `js_vitrine_rows_pending`.

## The category showcase: the account's shell (R4b3, 2026-10-29)

Stage R4b3 of `docs/vitrine-react/R4b.md`. An account's live listing is the store driven by `js/esm/src/listing/account.ts`,
armed by `amd/src/listingaccount.js` (one `js_call_amd` in `course_renderer.php`, where `categoryvitrine` and
`categorypanel` had two); both AMD modules are imported by nothing until the removal pull request (R4c). What a
maintainer must not lose:

- **The entry arms the star, then starts the shell.** `categorystar` stays AMD until R5, outside the ES modules' tree and
  its bridge, so `listingaccount.js` imports it and nothing else beside its shell (gates `js_account_arms_star`,
  `js_areas_no_amd_panel`). The star's one delegated listener covers the panel's cards.
- **The Shell gained four hooks, no-ops on the visitor's**: `changed(kind)`, told BEFORE the store writes the address
  (`term`, `tree`, `period`, `filter`, `view`, `history`), so the account marks the state remembered and the address
  names its organisation from the first change (gate `js_changed_before_write`); `discard`, every card a replaced grid
  drops, which the observer must let go; `painted`, the cards an answer drew, appended or not; `unmounted(error)`, a
  calendar that could not mount over the native fields (the account's is core's dialogue, as `categoryvitrine` had it,
  gate `js_calendar_failure_told`; the visitor's says nothing). `init` returns whether the
  listing is live (false for a root with nothing to search: posters only), and the account arms its observer, its cards
  and its listeners only then. The store also handles the favourites toggle and reads the payload's chip and fav, which
  the visitor's `normalise` drops.
- **The search, the tree and the period are not remembered** (`UNREMEMBERED`), as `categoryvitrine` had it; every other
  change is, after the quiet, and an unchanged state is not sent again. The view preference is written for a real
  account only (`canpersist`, gate `js_view_preference_real_account`), through `@moodle/lms/core/fetch`'s
  `performPost`, which is what `core_user/repository`'s `setUserPreference` does. A page drawn from the remembered state
  writes its address out in full with `replaceState` (gate `js_history_start_replaces`; Behat: Back leaves for the page
  before).
- **The shell is handed an answer before the posters' component hears it** (`paintAreas`: `answered`, then
  `criteria`). The component handles `criteria` synchronously and may open the one area holding every result inside
  it, and the account's note of that opening is the answer's sentence: told after, the note never showed (the Behat
  scenario of a search matching one area). Gate `js_answered_before_criteria`.
- **`leave` navigates through `@moodle/lms/core/location`'s `redirect`**, `window.location.assign` behind a seam Jest can
  watch (core's `expectRedirect`), and builds the address after the state is remembered.
- **Every failed page is core's exception dialogue**, an overtaken one's too (`rows` catches each request), and the
  posters are told only for the latest replacing one (`failed`). The panel's progress fails in silence.
- **The account's code moved its tests with it**: `listing_account_source_test` holds what `amd_source_test` and
  `categorypanel_source_test` pinned of the two modules; what is left there reads the shared painters and the dead
  modules until R4c. The visitor's walk names `listingaccount` and `listing/account` among the logged-in names (gate
  `js_public_graph_names_account`).
- **Gates**: 24 retargeted to the shell, the store or the entry; 10 retired (the six line twins, `js_root_live_search`,
  `js_areas_mount_guard`, `js_vitrine_rows_pending`, `js_panel_listing_through_esm`); 7 new. `js_settle_leaves_pending`
  is the source shape's alone: `drainPending` filters settled ids, so the mutant sends no second request and Jest
  cannot see it.
- **Jest's account tests share the module's state**, a settled course included: give each test course ids no other
  test settled.

## The category showcase: the old listing removed (R4c, 2026-10-30)

A removal-only change: `amd/src/{categorylisting,categorypublic,categoryvitrine,categorypanel,categoryline,categorypreview,
categoryareapanel,categoryposters,daterange,categorymark}.js` and their builds, imported by nothing since R4b3, are gone.
No kept module, template, string or rule changed what it does.

- **The source tests read the TypeScript now.** `amd_source_test` keeps its name and reads `js/esm/src/listing/store.ts`
  over the shared listing; `categorypanel_source_test` became `categorystar_source_test`, the star's alone (the panel's
  contracts are the component's: `areas_component_source_test` and `js/esm/tests/areas`); `categoryline_source_test`,
  `categorypreview_source_test` and `daterange_source_test` read nothing that is left and were deleted. Where an
  assertion pinned a contract the ES code carries, it was pointed at that file (the calendar's two-month breakpoint at
  `daterange/DateRange.tsx`, the listing's event names at `listing/events.ts`).
- **The visitor's walk names what still exists**: `categorystar`, `listingaccount` and `listing/account`. The gates
  that inject a logged-in name into the visitor's graph inject those.
- **Gates**: the 46 on the ten files retired with their scripts, and `js_areas_no_amd_panel` (no AMD panel is left
  to import); the gates whose mutant named a removed module now name one that exists.
- Every class and lang string the ten modules wrote is still read by the ES port, so no rule or string went.

