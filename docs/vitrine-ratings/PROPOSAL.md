# Course ratings on the showcase, and a lighter card: proposal

Status: **Accepted** (2026-11-01): the owner took every recommendation of section 9. Nothing implemented; L0 is next. Written from a read of tool_courserating
(`~/dev/moodle-tool_courserating`, upstream 4.5.2, mounted on m503/m503b at `admin/tool/courserating`), of this
theme's showcase, and from two throwaway meters run on m503b's 5,000-course probe set (section 6).

## 1. What the owner asked for

1. Show tool_courserating's ratings on the showcase, for courses where rating is enabled **and** that already
   hold at least one rating; decide the card, the "More information" dialogue and whether a filter is worth it.
   **Decided (2026-11-01): card variant A and a rating block in the dialogue; no filter.**
2. Measure the cost before building it (section 6.1).
3. Then two adjustments to the card itself: **remove the hover peek**, and **load the dialogue's content
   asynchronously** instead of drawing it into every card. Does that remove the dialogue's share of the page
   (recorded earlier as 39 % of the HTML)? Section 6.2 answers with numbers; sections 3 and 4 are the design.

## 2. What tool_courserating gives us

- **One summary row per course**, `{tool_courserating_summary}` (`db/install.xml`), unique index on `courseid`:
  `avgrating` (2 decimals), `cntall` (ratings), `cntreviews` (ratings with a review), `cnt01`..`cnt05` (the
  distribution), and `ratingmode`, the course's **effective** mode, recorded (the global setting or the per-course
  override; `api::reindex()` and `observer::course_updated()` keep it current). The plugin creates the row for
  every course (`observer::course_created()`), so on m503b all 5,526 courses have one.
- **"Enabled and rated" is one predicate on that row**: `ratingmode <> 1 AND cntall > 0`
  (`constants::RATEBY_NOONE = 1`). No custom field read per course, and the recorded fact is the one the promise
  is about (the mode the plugin itself applies), not a re-derivation from settings.
- **No bulk API.** `summary::get_for_course()`, `permission::can_view_ratings()` (a `get_course()` plus
  `can_view_course_info()` or `is_enrolled()`), `helper::get_course_rating_mode()` are all per course. The theme
  reads the table itself over the page slice, with the plugin optional (asked by class name, as `local_page` is),
  and says which plugin code it mirrors.
- **Who may see a rating**: the plugin shows it to whoever can see the course in a listing
  (`can_view_ratings()`; its custom field is `VISIBLETOALL`). The showcase only lists courses the viewer may
  discover, so the aggregate follows the card. **Reviews are not shown on the showcase**: the dialogue links to
  the plugin's own popup, which applies `can_view_reviews()` (the `allowreviews` setting, hidden reviews to
  `tool/courserating:reports` only).
- **The popup needs a session.** The plugin opens it from `core_get_fragment` (`amd/src/rating.js`, a document
  click listener on `.tool_courserating-cfield .tool_courserating-ratings-courseid-N`), and that service has no
  `loginrequired => false` (`lib/db/services.php`). So the visitor sees the aggregate and never the link.
- **Already on every page**: the plugin's `before_http_headers` hook calls `js_call_amd('tool_courserating/rating',
  'init')` (10 KB minified) and its head hook prints a `<style>` with the star colours, on the showcase too. Our
  link reuses that listener; we add no JavaScript for it.
- **Its custom field `tool_courserating`** holds the rendered stars as HTML (five pix icons). The showcase never
  prints it (cards read `cards::CARD_SHORTNAMES` only) and should not start: one glyph and a number are enough.

## 3. The design

### 3.1 The card (variant A)

`★ 4,7 (23)` opens the card's meta line (`36 h · Presencial · ...`), drawn only when the course passes the
predicate above. The star is an amber glyph, `aria-hidden`; the group carries an accessible sentence
("Rating 4.7 out of 5, 23 ratings"). It must not read as the favourite: that star is a button in the actions
cluster, outlined until pressed. Number formatting is `format_float($avg, 1, true)` (the decimal comma in pt_br).
Variants B (a pill in the badge row) and C (a badge over the cover) were rejected: B fights the state pill and
the crests in the list view, C disappears on a card without a cover and fights the crests the card view lifts
onto the image.

### 3.2 The dialogue's rating block

Below the summary: the average large, five stars (full, half, empty), "23 ratings", the distribution as five
rows (stars, a bar, the count) and, for an account only and only when `cntreviews > 0`, "Read 9 reviews",
which is the plugin's own trigger (the class contract above) and opens its popup. The bars are `<progress>`
elements (or width classes in 5 % steps): never a `style="width: N%"`, the showcase's CSS-context rule.
The plugin's popup is a second `core/modal`; ours closes first so two dialogues never stack.

### 3.3 The dialogue, fetched when it opens

Today every card carries its whole dialogue in an inert `<template data-region="dim-card-modal">`
(`category_card.mustache:229-231`, the partial `category_card_modal.mustache`), and every client render of a card
draws it again from the rows answer (`paint.ts:591-599` renders `category_card` with the partial nested). Round I C2
chose that on purpose: no new service, and none for the visitor (history, "Round I, lot C2"). The measurements of
section 6.2 are the price of that choice, and this section changes it.

The dialogue has two kinds of content, and they are split along that line:

- **What the card already decided stays on the card.** The cover, the state pill, the kicker, the title and the two
  buttons come from the classifier's run over the page (`modal()` :458, `modal_primary()` :497-533: the state word,
  `url()` against `url(false)`, the `aria-disabled` opening date). Re-deciding them for one course in a service would
  mean running the classifier for that course alone, a second implementation of the page's decision. So the card
  keeps them, small: the buttons and the kicker (the course's own category name, the constructor's `$kicker`
  :273, which the visible card does not print) as one `data-dialogue` attribute (label, url, disabled, note per
  button; about 200 B), and the cover, pill and title read from the card's own markup, which already holds them.
- **What only the dialogue shows is fetched**: the summary excerpt, the facts (workload, modality, place, contacts,
  the event's dateline), "what you will learn" and, once section 3.2 lands, the rating block. None of it changes the
  page's decision about the course; all of it is what the 33-37 % is made of.

The press: `categorycoursemodal` opens `core/modal` **at once** with the parts the card holds (cover, pill, title,
buttons) and a skeleton where the content goes, then fills it from the service. The existing Pending token
(`theme_dimensions/categorycoursemodal:open`) spans the request and the render. A failure leaves the header and the
buttons, says the details could not be loaded, and keeps the card's link to the course page. The request starts
earlier than the press where the reader shows intent: pointer entering or focus reaching the "More information"
control (that control only, not the card), after the cover film's `INTENT_DELAY` (180 ms, `amd/src/local/coverfilm.js:69`). Answers are kept per course id for
the page's life, so a second opening costs nothing.

### 3.4 No peek, no panel

- **The hover peek goes** (`account.ts`: `peekable()` :447-453, `closePeeks()` :455-463, `openPeek()` :481-513, the
  `mouseenter`/`mouseleave` listeners in `attachCards` :524-534, the constants :84-91; the SCSS block
  `category.scss:2689-2726` and its reduced-motion entry :6338). On its own it saves no bytes: it clones the details
  panel's children, and the panel is also the no-JavaScript dialogue.
- **The panel goes with the template, because "More information" becomes a link.** The `<details>`/`<summary>`
  (`category_card.mustache:200-223`) becomes `<a class="btn btn-sm dim-card__more" href="{{url}}"
  data-action="dim-card-more">`: without JavaScript it leads to the course page (`url()`: the course for whoever may
  enter, else the landing page when the course has a model, else the enrolment page), where all of that information
  already is; with JavaScript the module prevents it and opens the dialogue. **A card with no address draws no
  control** (`hasurl` false: a visitor's public course with no landing page model, `url()` :648-662).
- What leaves with them: the one-panel-at-a-time rule (`closeOthers()` :465-479, the capture `toggle` listener
  :669-680, Escape :681-685, gate `js_one_panel_at_a_time`), `HOOKS.details`/`HOOKS.panel`, the panel's rules
  (`category.scss:2561-2660` and the dark groups) and its place in the token roots `category_scss_test` pins.
- **Server work that leaves the page**: `cards::contacts()` (cards.php:279-310) is read only by the facts, so the
  page loses that layer (core's bulk contacts preload); `SHORTNAME_VENUE` and `SHORTNAME_PROGRAMME` leave
  `CARD_SHORTNAMES`; `summary, summaryformat` leave `cards::COURSE_FIELDS` (the summary is the one text column of the
  course rows the page reads, and only `excerpt()` :909 used it). The rows answers lose `detailsid`, `detailslabel`, `excerpt`, `facts`, `learn`, `learnlabel` and `modal`
  (`get_category_rows.php` `card_structure()`), and the public rows with them, since they share the structure.

## 4. Services and shells

### 4.1 The account: `theme_dimensions_get_course_dialogue`

One course per call, `type => read`, `ajax => true`, `readonlysession => true`, `loginrequired => true`, no
capability, as `get_card_details` (`db/services.php:50-58`). Its order, in `get_card_details`' shape (:76-93):
`validate_parameters()`, `require_login()`, the guest refused with `guard::ERROR_COURSE`,
`validate_context(system)`, then **discoverability exactly as `enrichment::details()` decides it**: core's
`can_view_course_info()` first, then `local_unlistedcourses\access::filter_courses()`. Every miss (nonexistent,
core-hidden, withheld, the site course) answers the same exception with the same message, the showcase's one
refusal. It returns the excerpt, the facts, the programme lines and, with tool_courserating present and the course
enabled and rated, the rating (average, count, distribution, review count and whether the reviews link applies).
Its reads: the course row, `landingfields::values_many()` for one course, core's contacts for one course, the
rating row. No cross-request cache: the answer is per viewer, and the public catalogue has none (D39).

### 4.2 The visitor: V1 (decision 1)

- **V1, chosen: the visitor's "More information" is the link, and nothing more.** It leads to the public
  landing page, which is the visitor's full description of the course and already public (`publicfiles`,
  `request::handle()`). No new anonymous surface, `categorycoursemodal` leaves the visitor's first paint (the
  `MODULES` list of `first_paint_budget_test` and the gate `cmodal_armed_visitor` change), and the visitor's import
  graph shrinks. The cost: a public course with no landing page model shows no "More information" at all, where
  today it shows the panel or the dialogue.
- **V2: a public twin, `theme_dimensions_get_public_course_dialogue`**, `loginrequired => false`, in
  `get_public_category_rows`' eleven-step order: one call per request, refuse a `cachekey`, refuse any session (the
  guest included), the cheap shape checks, the named language restored in a `finally`,
  `publicaccess::client_enabled()` and the COURSE's `is_public()` before any lookup, then the data with `anonymous`
  on (no contacts). It is new anonymous surface: `security-diff-read` and an MDL Shield review before the merge,
  and its own `pws_*`-style gates on the order.

### 4.3 The ratings on the card

The card's line needs the rating on the page and in every rows answer (both shells: the aggregate is public with
the card, section 2): one layer in `cards` over the page slice, `cards::ratings()`, one statement on
`{tool_courserating_summary}` driven by the page's ids (never the population's), skipped entirely when the plugin is
absent (class name) or rated nowhere (`helper::course_ratings_enabled_anywhere()` reads config only). A new card key
`rating {avg, count, label}` joins `card_structure()`. The dialogue's block comes from the service (4.1).

## 5. What changes in tests and gates

From the survey of the checkout (main 5474224); every line is a place a change must touch, not a result.

- **Bytes and modules**: `first_paint_budget_test` pins `CARD_BYTES = 4834`, `PUBLIC_CARD_BYTES = 4572` and
  `LISTING_BYTES = 17825` (:76-82), which include the template and the panel today: re-measured and lowered in the
  same change, so they hold the gain. Its `MODULES` lists (:88-98) change only under V1.
- **Statements**: `budget_test` pins reads, not markup; the contacts layer leaving the page should show as a read
  less per page, to be measured, and the ratings layer as one more, plugin present and rated.
- **Markup**: `card_markup_test` (:426-427 the details and panel hooks, :436-440 the dialogue hooks, :921-952 the
  partial), `category_card_modal_test` (renders the partial, :350), `cards_test` (compares the keys a card reads
  with the fields read: `venue` and `programme` leave).
- **Client source**: `categorycoursemodal_source_test` (:119 the summary selector, :222 the build naming
  `dim-card-modal`, :237 the Pending token), `listing_account_source_test` (:223 `closePeeks()`, :233-239 the peek
  test), `covervideo_source_test` (:175-179 pins `INTENT_DELAY = 180` "in step with" `PEEK_DELAY`: the cover keeps
  its own delay, the cross-pin goes), Jest `js/esm/tests/listing/account.test.ts` (:68-69 fixture, :293-311 the peek).
- **Stylesheet**: `category_scss_test` (:206-207, and the `dim-card__panel` patterns :1419, :1579, :1683, :1907,
  :1955, :1975).
- **Behat**: `theme_dimensions_category_client.feature:255-274` (the dialogue: "Enrol" and "Course page", Escape
  gives the focus back) keeps its words and gains a wait on the request through the existing token; a new scenario
  for the no-JavaScript link; the visitor's feature changes with decision 1.
- **Gates** (`mutations/gates.conf`): retired with the code they guard: `js_one_panel_at_a_time` :363,
  `js_mini_no_peek` :684, `card_carries_modal` :1722; retargeted: `modal_closed_aria_disabled` :1736 (the button
  now comes from `data-dialogue`), `js_cmodal_prevent_disclosure` :1744 (prevents the link), the `js_cmodal_*` gates
  :1746-1750 and :2394, `cmodal_armed_visitor` :1754 (gone under V1); new: the service's order (guest refused before
  any lookup, discoverability before the data, one refusal), the ratings layer driven by the page slice, the
  plugin-absent path reading nothing, and, under V2, the public twin's order.

## 6. Measurements (m503b, 2026-11-01)

Both meters ran as the probe student against the real `course_renderer::course_category()` over m503b's
5,000-course probe set (`docs/vitrine-react/probe/loadprobe.php`'s fixture, kept), copied into the webserver
container and deleted after. The ratings meter seeded ratings inside one delegated transaction it rolled back
(left on the stack afterwards: 0 rated summaries, 0 ratings).

### 6.1 Ratings, variant A plus the dialogue block (drawn in every card, as today's dialogue is)

| page | rated cards | statement | PG execution | PHP call (median / p95) | listing HTML | gzip |
|---|---|---|---|---|---|---|
| area, 24 per page, 60 % rated | 16 | 1, index scan on the unique `courseid` | 0.019 ms | 0.12 / 0.15 ms | 153 → 185 KB (+21 %) | +1.8 KB |
| area, 48 per page, 60 % rated | 32 | 1 | 0.027 ms | 0.16 / 0.19 ms | 277 → 342 KB (+24 %) | +3.2 KB |
| area, 48 per page, all rated | 48 | 1 | 0.032 ms | 0.17 / 0.19 ms | 277 → 374 KB (+35 %) | +4.0 KB |
| leaf, 24 per page, 60 % rated | 12 | 1 | 0.023 ms | 0.12 / 0.14 ms | 140 → 164 KB (+18 %) | +1.5 KB |

The card's line is about 125 B and 4 live elements per rated card; **88 % of the growth is the dialogue block**
(about 900 B and 31 inert elements per rated card, in every card's `<template>`). The server side is flat: one
statement per page, whatever the page size.

### 6.2 What the dialogue and the panel weigh today (no ratings)

| page | listing | dialogue `<template>` | details panel | without both |
|---|---|---|---|---|
| account, area, 24 cards | 153 KB, 1,467 elements | 33 % (509 inert elements) | 11 % (168 live, hidden) | −43 % raw, −1.8 KB gzip |
| account, area, 48 cards | 277 KB, 2,674 elements | 36 % (1,007) | 12 % (336) | −47 % raw, −3.3 KB gzip |
| visitor, area, 24 cards | 132 KB | 38 % | 12 % | −49 % raw, −1.8 KB gzip |
| account, leaf, 24 cards | 140 KB | 36 % | 12 % | −47 % raw, −1.7 KB gzip |

The cards repeat each other, so compression already absorbs almost all of it on the wire: the gain is in what
the browser parses and keeps (up to 46 % of the listing's elements), not in bytes transferred. **Browser TBT,
LCP and parse time were not measured**: that needs a prototype on a stack (section 7).

## 7. Stages

The lighter card goes first: once the dialogue is fetched, the rating block costs the page nothing, and the
ratings stage only adds the card's line and a field to the service.

| stage | what | tier and gate (fleet section 4) |
|---|---|---|
| L0 | Baseline in a browser on m503b before any change: the R0 probe (`theme_dimensions_perf.feature`, cold series, and `loadprobe.php --run` for reads and bytes), account and visitor, 24 and 48 cards. TBT, LCP, parse time and DOM size, which section 6 could not see. | none (measurement) |
| L1 | Remove the hover peek (section 3.4, first bullet). Small, alone, easy to revert. | 2: one full leg `--behat` |
| L2 | The fetched dialogue, the link in place of the disclosure, the panel and template gone, the data layers out of the page, `theme_dimensions_get_course_dialogue`, the visitor per decision 1 (V1 here; V2 its own stage after). | 3: `mdl ci --matrix --behat`; `security-diff-read` before the push |
| L3 | Measure L0 again on the new head; record both in this file. | none |
| R1 | Ratings: `cards::ratings()`, the card's line on both shells, the `rating` key in the rows structure, the block in the dialogue through the service, the reviews link for an account. | 3 (a new optional dependency read in SQL) |
| R2 | Measure R1 against L3 with ratings seeded in a rolled-back transaction (the throwaway meter of section 6.1 does it). | none |

## 8. Risks and how they are held

- **The first opening waits for a request.** Held by opening at once with what the card holds (section 3.3), the
  fetch on intent, and the per-course memo; measured in L3 as the time from the press to the filled dialogue.
- **A service that reveals a course the page would not.** Held by deciding on the same facts the page decides on
  (`can_view_course_info()` then `access::filter_courses()`, as `enrichment::details()`), one refusal for every
  miss, and gates on the order. Under V2, the public twin's order as in `get_public_category_rows`.
- **Two answers about the same course.** The buttons and the state are the page's; the service never returns
  either, so the dialogue cannot contradict its card.
- **The visitor loses the dialogue under V1** on public courses with no landing page model. That is decision 1.
- **tool_courserating is upstream code we do not own.** The theme reads one table and one constant, guarded by the
  class name; `tests` skip without the plugin, or the plugin joins `ci.yml`'s `plugin-dependencies` (it is public:
  `marinaglancy/moodle-tool_courserating`), which is decision 4.
- **The cover film's intent delay was pinned to the peek's** (`coverfilm.js:66-69` says so, `covervideo_source_test`
  :175-179 holds it). The film keeps its own 180 ms; the comment and the pin are rewritten to stand on their own,
  and the dialogue's fetch on intent reads the same delay.

## 9. Decisions (owner, 2026-11-01: every recommendation taken)

1. **The visitor: V1.** "More information" is a link to the public landing page; no anonymous dialogue service. A
   public course with no landing page model shows no "More information" control. V2 stays possible as a stage of
   its own later.
2. **The opening: at once.** The dialogue opens with the card's cover, pill, title and buttons and a skeleton for
   the fetched content.
3. **The buttons: on the card.** The page's decision travels in one `data-dialogue` attribute (buttons and kicker);
   the service never returns a state or a button.
4. **tool_courserating in CI: yes.** It joins `plugin-dependencies` in `ci.yml` (public,
   `marinaglancy/moodle-tool_courserating`), in stage R1, so the rating tests run in CI as well as on the stacks.
5. **The order: the lighter card first** (L0-L3), then the ratings (R1-R2).

## 10. L0 results (2026-11-02)

The baseline before any change, on m503b. The stack served the live checkout: origin/main 5474224 plus the
calendar's uncommitted C3b edits (calendar blocks, a core_calendar renderer), which reach no showcase code. Xdebug
loaded, `cachejs` off, MUC on the stack's Redis (moved the day before, so cold numbers differ from older series).

### 10.1 The server (`loadprobe.php --run`, three warm runs, two cold)

| viewer | page | reads warm | ms warm | reads cold | ms cold | listing bytes | hero bytes |
|---|---|---|---|---|---|---|---|
| student | area, 24 cards | 35 | 35-44 | 199 | 280-502 | 152,708 | 4,207 |
| student | area, 48 cards | 35 | 44-49 | 199 | 266-271 | 276,516 | 4,271 |
| student | leaf, 24 cards | 39 | 26-30 | | | 139,769 | 4,345 |
| student | root, 10 posters | 35 | 81-83 | | | 38,118 | 4,723 |
| manager | area, 24 cards | 38 | 36-47 | | | 158,870 | 13,566 |
| visitor | area, 24 cards | 25 | 22-27 | 70 | 148 | 132,209 | 1,712 |

### 10.2 The browser (`theme_dimensions_perf.feature`, medians of five runs, 4x CPU, 150 ms / 1.6 Mbps)

| page | mode | FCP | LCP | TBT | CLS | INP | long tasks | DOM nodes | HTML decoded / transferred | scripts before load |
|---|---|---|---|---|---|---|---|---|---|---|
| student, flat | cold | 5,328 | 5,328 | 667 | 0 | 96 | 7 | 1,684 | 269,545 / 26,210 | 103 |
| student, flat | warm | 536 | 536 | 9 | 0 | 72 | 3 | 1,683 | 269,545 / 26,212 | 19 |
| student, root | cold | 5,136 | 5,136 | 605 | 0 | 52 | 6 | 957 | 149,001 / 19,575 | 100 |
| student, root | warm | 504 | 504 | 2 | 0 | 40 | 4 | 956 | 149,001 / 19,573 | 47 |
| student, grouped | cold | 5,052 | 5,052 | 585 | 0 | 48 | 5 | 1,202 | 174,374 / 23,429 | 108 |
| student, grouped | warm | 528 | 528 | 17 | 0 | 48 | 4 | 1,201 | 174,374 / 23,430 | 47 |
| visitor, flat | cold | 5,296 | 5,296 | 299 | 0 | 56 | 5 | 859 | 147,360 / 14,446 | 14 |
| visitor, flat | warm | 504 | 504 | 15 | 0 | 48 | 4 | 859 | 147,360 / 14,447 | 17 |

Times in ms. The student's flat page is the one L2 changes most: 1,684 DOM nodes and 270 KB of HTML for 26 KB on the
wire.

- **Every theme_dimensions Behat scenario fails on m503 and m503b since 2026-10-30**, before its first step:
  `tool_mulib`'s 5.3 branch (mounted that day) declares `behat_tool_mulib_files extends behat_repository_upload`,
  which re-exposes core's upload steps in the `dimensions` suite, where `behat_theme_dimensions_behat_repository_upload`
  already overrides them. The probe ran after deleting that context from the suite in the generated `behat.yml`
  (restored afterwards). `mdl ci --behat` does not install tool_mulib and is unaffected. The fix belongs to the owner.
- **L3 cannot use m503b for the after**: every 5.3 stack mounts the live checkout, so the before/after of L2 is
  measured in two `mdl ci` installs on one host (the base and the branch), with the probe opted in, rather than on
  the shared stack.

## 11. L3 results (2026-11-02): L2 against L1

Measured in two `mdl ci` installs on the M4 mini (one host for both sides), not on a shared stack (section 10):
throwaway detached worktrees of L1 (1c24a26, the base) and L2 (55944db), each reduced to
`theme_dimensions_perf.feature`, with the probe opted in and its medians printed to the Behat log. Run in the
order base, L2, base, L2; each cell is the median of five loads, and the two runs of each side are given. The
installs serve HTML without compression (`php -S`), so bytes are decoded bytes; `cachejs` is on there, unlike on
the stacks, so the script counts differ from section 10.2. The student's root and grouped pages draw no cards:
they are the controls, and moved only by the host's noise.

| page | mode | metric | L1 (base) | L2 | change |
|---|---|---|---|---|---|
| student, flat | cold | FCP | 5,708 / 5,764 | 5,404 / 5,400 | about −330 ms (−6 %) |
| student, flat | cold | TBT | 334 / 421 | 279 / 230 | about −120 ms (−32 %) |
| student, flat | cold | INP (scope chip press) | 120 / 112 | 64 / 56 | about −55 ms (−48 %) |
| student, flat | warm | FCP / TBT | 492, 632 / 33, 114 | 484, 464 / 42, 37 | within noise |
| student, flat | both | DOM nodes | 1,674 | 1,534 | −140 (−8 %) |
| student, flat | both | HTML bytes | 271,595 | 220,241 | −51 KB (−19 %) |
| visitor, flat | cold | FCP | 5,036 / 5,256 | 4,804 / 4,804 | about −340 ms (−7 %) |
| visitor, flat | cold | TBT | 127 / 156 | 138 / 141 | within noise |
| visitor, flat | both | DOM nodes | 860 | 720 | −140 (−16 %) |
| visitor, flat | both | HTML bytes | 150,942 | 98,497 | −52 KB (−35 %) |
| student, root (control) | cold | FCP / TBT | 4,852, 5,060 / 225, 445 | 4,868, 4,916 / 245, 222 | none expected |
| student, grouped (control) | cold | FCP / TBT | 4,912, 5,100 / 237, 403 | 5,044, 4,904 / 255, 219 | none expected |

- **The DOM count understates the template.** A `<template>`'s content is a document fragment, outside the page's
  element count, so the −140 nodes are the details panels; the templates' 500-1,000 inert elements were parse and
  memory cost that no DOM count shows, and they are what the HTML bytes column measures.
- **The press got cheaper**: the scope chip's INP halved on the student's page, because the client renders every card
  of a rows answer through `category_card`, which no longer nests the dialogue.
- **The second base run was the noisiest** (its controls moved too: root cold TBT 445), so the TBT change is the
  least certain number here; FCP, INP, bytes and nodes moved the same way in both pairs.
- The first opening of a dialogue now waits for one request; the probe does not press "More information", so that
  latency is not in this table (the Behat dialogue scenario of the matrix waits on it and passed on all four legs).

## 12. R2 results (2026-11-02): R1 against L2, both with tool_courserating and ratings

Same harness and host as section 11 (the M4 mini, base and after alternated twice). Both sides install
tool_courserating (an uncommitted local extra of the throwaway clones) and the probe fixture seeds ratings on two
thirds of the flat page's courses through the plugin's API (five raters, one review each), so the only difference is
R1's code: L2 at 55944db draws no rating, R1 at 2bbfb90 draws the card's line (the dialogue's block is fetched on
opening and is not on the page).

| page | mode | metric | L2 + plugin | R1 | change |
|---|---|---|---|---|---|
| student, flat | both | HTML bytes | 220,464 / 220,646 | 224,791 / 224,850 | +4.3 KB (+2 %) |
| student, flat | both | DOM nodes | 1,535 | 1,611 | +76 (4 per rated card) |
| student, flat | cold | FCP | 5,480 / 5,372 | 5,400 / 5,404 | within noise |
| student, flat | cold | TBT | 266 / 233 | 246 / 259 | within noise |
| student, flat | cold | INP | 80 / 56 | 56 / 56 | within noise |
| visitor, flat | both | HTML bytes / DOM nodes | 98,716 / 721 | 103,165 / 799 | +4.4 KB, +78 |
| visitor, flat | cold | FCP / TBT | 4,828, 4,796 / 159, 133 | 4,784, 4,892 / 128, 161 | within noise |
| student, root (control) | cold | FCP / TBT | 4,928, 4,864 / 271, 231 | 4,840, 4,860 / 224, 222 | none expected |
| student, grouped (control) | cold | FCP / TBT | 5,020, 4,988 / 265, 304 | 4,948, 5,012 / 235, 309 | none expected |

- **The server**: one statement per page on the plugin's summary table, flat in the page size (`cards_test`); on the
  5,000-course set it costs 0.02-0.03 ms in PostgreSQL and 0.12-0.17 ms through the DML layer (section 6.1). With the
  plugin absent or rating nothing, no statement (`cards_test`).
- **Section 6.1's worry is gone**: drawn into every card, the dialogue's rating block would have added 17-35 % to the
  listing; with the dialogue fetched (L2) the page pays the card's line alone, about 2 %.
- **Net of the series** (section 11 and this one): from L1 to R1, the student's flat page goes from 271.6 KB to
  224.8 KB of HTML (−17 %) and 1,674 to 1,611 DOM nodes while gaining the ratings; the visitor's from 150.9 KB to
  103.2 KB (−32 %).
