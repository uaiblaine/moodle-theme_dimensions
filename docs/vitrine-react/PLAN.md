# The category showcase in React: study and staged plan

Status: **proposal, 2026-10-26, nothing implemented.** It waits on the owner's decisions in section 6.
It was written after the Boost Union `upgrade-503` re-sync of 2026-10-26, where Boost Union's own
React navigation (`js/esm/src/nav/Nav.tsx`) was read as a model.

## 1. Goal

Move the showcase's client (the logged-in and the visitor listing, the posters and their panel, the
filter line, the calendar) from AMD modules to React components in `js/esm/src/`. Keep every
property the showcase has today:

- the server's first paint,
- the page without JavaScript (GET forms, native `<details>`, the numbered pager),
- the visitor's surface and its security rules,
- one refusal for every miss,
- the statement budgets,
- the Behat scenarios.

**Not in scope:**

- The decision layer (`classes/local/category/`), the web services and their returns. The client
  reads the same payloads.
- `categoryhero` (the appearance editor).
- `covervideo` (the cover film; it is media code with no state to share).
- `categorycoursemodal`, which stays a thin `core/modal` wrapper.

## 2. What exists (inventory of 2026-10-26)

Line counts are from `wc -l`.

| Module | Lines | Role | Web services |
|---|---|---|---|
| `categoryvitrine` | 1325 | Logged-in listing controller: filters, paging, view, remembered state, progress batches | `get_category_rows`, `get_card_details`, `remember_category_state` |
| `categorypublic` | 948 | Visitor listing controller | `get_public_category_rows` (`loginrequired => false`) |
| `categorylisting` | 1311 | Shared painters and address helpers (about 35 exports) | none |
| `categoryareapanel` | 1843 | The panel between the poster rows: open/close, rail, keyboard, sort, address | none |
| `categorypanel` | 232 | Logged-in wiring of the panel | rows, details |
| `categoryposters` | 369 | Poster result painters | none |
| `categoryline` | 1206 | The filter line: search, suggestions, chips, steps | none |
| `categorypreview` | 399 | The scope-cell preview on the cards | none |
| `categorymark` | 150 | `<mark>` on card titles | none |
| `categorystar` | 127 | The favourite star | `toggle_wishlist` |
| `daterange` | 1029 | The calendar popover | none |

That is about 8,900 lines in scope. The modules communicate in four ways:

- Five DOM events on `document` (`dim-vitrine:view`, `:criteria`, `:pending`, `:failed`,
  `dim-panel:autoopened`).
- The address (`pushState` for the listing, `replaceState` for the panel).
- Two user preferences.
- About 60 `data-region` / `data-action` hooks.

**One markup today:**

- `category_card`, `category_panel` and `category_daterange` are Mustache templates. PHP renders
  them for the first paint, and `core/templates` renders the same file in the browser.
- No card is described twice.

**The tests that pin the JavaScript:**

- About 200 PHPUnit methods in 13 files read `amd/src` text, by function body (`tests/local/category/*_source_test.php`,
  `category_scss_test`, `category_listing_markup_test`, `rows_anonymous_test`, `toggle_wishlist_test`).
- They also read the build (`amd/build` maps' `sourcesContent`).
- `categorypublic_source_test` walks the visitor's import graph transitively. That walk is a
  security property: no session module may ship to the internet.
- The `js_*` lines of `mutations/gates.conf` target these modules.
- The behavioural contract is Behat: 82 scenarios across `theme_dimensions_category*.feature`, 38 of
  them `@javascript`.

## 3. Moodle 5.3 facts that shape the plan

Each was read in core or measured on this fleet (block_compass ADR-006, `moodle-block_compass`).

1. **A React mount REPLACES the server's HTML; it never hydrates.**
   - Core's `mount.ts` calls `createRoot(container)`.
   - `react_autoinit` mounts every `[data-react-component]` on the page and watches the document
     with a MutationObserver.
   - Whatever the server drew inside the mount point is a fallback, thrown away on mount. The risks
     on mount are a layout shift and a lost focus if the reader already interacted.
2. **`data-react-props` is the props object itself.** If the props are nested under a key of their
   own, the component renders nothing, and only Behat sees it (block_compass R2).
3. **A component cannot import AMD.**
   - It must go through `requireAsync()` / `requireManyAsync()` of `@moodle/lms/core/amd`. Boost
     Union's `Nav.tsx` does exactly that.
   - So `core/templates`, `core/ajax`, `core/str`, `core/pending` and `core/modal` are reached
     asynchronously, through one bridge module.
4. **No React lint in core.**
   - There is no `react-hooks` plugin, so `rules-of-hooks` and `exhaustive-deps` do not exist, and a
     disable comment naming them is an error.
   - There is no jsdoc rule on `.tsx` either. block_compass restores core's AMD jsdoc set with a local
     `.eslintrc` that cascades.
   - `no-unused-vars` needs `args: "none"` for function types.
5. **Type checking is ours.** Core runs no `tsc`; the fleet's `mdl ci` gate runs `tsc --noEmit`, after
   `grunt jsconfig`.
6. **Builds and caches.**
   - `mdl grunt` builds `js/esm/build`.
   - A rebuilt ESM module is served `immutable` for a year until the JS revision moves, so every
     rebuild is followed by `mdl purge`.
   - Behind the corporate proxy, `npm install` deletes core's React bundles. Use the offline recipe in
     the CLAUDE.md "stage 4" section.
7. **React is probably already on these pages.** Core's primary navigation is a React mount
   (`core/nav/PrimaryNav`, or Boost Union's `theme_boost_union/nav/Nav` with smart menus), so the
   runtime (about 213 KB measured on 5.2) is already paid on every page that has a navbar. The
   showcase's own bundle would be the only new weight. Not yet checked on the visitor shell.
8. **Core 5.3 ships Jest** (`jest.config.js`, `**/esm/tests/**/*.test.{ts,tsx}`, jsdom, Testing
   Library). Whether `moodle-plugin-ci` and `mdl ci` run a plugin's tests is unverified. If they
   do, it is the behaviour gate the source-shape tests stand in for today.
9. **A filter line already exists in React on this fleet**: block_compass's `FilterLine.tsx` /
   `FilterPanel.tsx`, built to the same fleet contract (`moodle-dev/docs/filter-line/CONTRACT.md`).
   The theme's `categoryline` is the AMD implementation of that contract.

## 4. The central decision: who draws the content

**A. React draws everything; the server keeps the first paint in Mustache.**
- Every card, poster and panel would be described twice, once in Mustache (first paint and no
  JavaScript) and once in TSX.
- A parity gate between the two renders would be needed. None exists in the pipeline: block_compass
  avoided it by sending an empty shell, which this page cannot do.
- The first interaction would re-render the server's cards, a visible repaint of the whole grid.

**B. React draws the state and the controls; Mustache stays the one description of the content
(recommended).**
- React owns the toolbar's line, the filter panel, the calendar, the panel's frame and rail, the
  posters' result layer, the pager, and one store of the criteria in step with the address.
- Cards and panel rows stay `category_card` / `category_panel`, rendered by `core/templates`
  through the bridge and inserted as HTML into containers React owns but never diffs inside.
- The star, the course dialogue and the cover film already listen on `document`, so injected cards
  keep working. The no-JavaScript page is unchanged.

**C. A client-only shell** (an empty mount point plus a config, as block_compass does).
- It loses the first paint, the page without JavaScript and the visitor's crawlable listing.
- Rejected: it breaks the theme's progressive-enhancement rules.

## 5. Performance: first paint, blocking time, skeleton first

The owner asked on 2026-10-26 that the port optimise First Contentful Paint and Total Blocking Time,
and show a skeleton first wherever the full content is still on its way. Performance is therefore a
gate of every stage, not a side effect of the rewrite.

### 5.1 Targets

These are the "good" thresholds of Core Web Vitals, measured in the lab by the probe of section 5.4:

| Metric | Target |
|---|---|
| FCP | ≤ 1.8 s |
| LCP | ≤ 2.5 s |
| TBT | ≤ 200 ms |
| CLS | ≤ 0.1, and 0 at the React mount |
| INP of a filter press | ≤ 200 ms |

On top of those, no stage may grow two numbers over the stage before it:
- the showcase's initial JavaScript,
- the TBT.

### 5.2 Baseline, measured on m503 on 2026-10-26

All numbers come from an in-process compile, the AMD builds, or a CLI render as admin.

| What | Measured | Reading |
|---|---|---|
| Theme stylesheet (render-blocking, every page) | 2,748 KB raw, **418 KB gzip** | Boost alone is 370 KB gzip and Boost Union 387, so the theme adds 31 KB, of which the showcase's 796 `dim-category` rules are 114 KB raw / **17 KB gzip**, shipped to every page of the site |
| Showcase JavaScript (13 AMD modules) | 160 KB raw, **54 KB gzip** | each module is a request of its own through RequireJS; the visitor's set is smaller (it has no `categoryvitrine` / `categorypanel`) |
| Server render of a category listing (`course_category()`, CLI, admin, Xdebug loaded) | **18-20 ms warm**, 411 ms cold; 17 reads; 45 KB of HTML | m503's catalogue is tiny (6 courses in the largest category); the statement count is the budget test's, flat in N and P |
| Hero image | no `width`/`height`, no `fetchpriority` | the likely LCP element of a branded category |
| Card covers | every one `loading="lazy"`, first row included | lazy loading the first row delays the LCP of a category without a hero image |
| Crests | `loading="lazy"`, no intrinsic size | a shift unless the stylesheet sizes the box first |

Not measured yet:
- **FCP, LCP, TBT and CLS in a browser.** The Browser pane has no session on m503, and no password is
  typed there (the owner's rule), so these need the probe of 5.4.
- **The time to first byte on a large subtree.** It needs m502's 5,000-course probe data or its
  equivalent on m503.

### 5.2.1 Measured in the browser (m503, logged in as admin, 2026-10-26)

The Browser pane was visible for these runs (a hidden pane records no paint).

| Load | TTFB | FCP | LCP | TBT | CLS |
|---|---|---|---|---|---|
| First visit after a cache purge | 302 ms | **23.5 s** | | | |
| Warm (three runs) | 163-263 ms | 252-368 ms | = FCP, a card cover | 0 | 0.032 |

- **The 23 s is Boost Union's flavour stylesheet compiled on the first request**
  (`theme/boost_union/flavours/styles.php/dimensions/.../1/all`, 23,068 ms), not the theme's own:
  - `admin/cli/build_theme_css.php --themes=dimensions` (57.7 s with Xdebug loaded) pre-builds the theme's sheet and
    not a flavour's;
  - so after every purge the first reader of each flavour waits for a whole compile.
  - Pre-building the flavours after an upgrade or a purge is an operational step to decide (a CLI over
    `theme_boost_union` flavours, or a task); it is worth more to FCP than anything below.
- **The one layout shift (0.032) is the filter line** settling on its step: at the first paint "Organise" wraps
  onto a second row (y 315), and at about 600 ms `categoryline` folds the line onto one (y 261).
  - A rule that keeps the line on one row until the client measures it would only reverse the shift where the
    client chooses the two-line step (phones).
  - So the fix needs the step measured per breakpoint first. It is left to R2, where the line is rewritten.
- **TBT is 0 on this machine** (an Apple laptop, no CPU throttling). The page makes 190 requests: 83 AMD modules
  one by one (the stack serves `requirejs.php/-1/`), 77 ESM modules (React and the navigation), and 3.2 MB of
  decoded script, almost all of it core's. On a mid-range phone that is where blocking time will come from; the
  probe of 5.4 has to throttle the CPU to see it.
- `core/modal` is already loaded by core on these pages, so deferring it in the course dialogue saves nothing
  here. It does on a page where nothing else asks for it.

**P0 implemented on 2026-10-26 (branch `perf-p0`, not committed):**
- The first row's covers and the posters' first row are `eager`.
- The hero's picture has `fetchpriority="high"`.
- The crests are sized, and the hero's crests are not lazy.
- The film, the dialogue's `core/modal` and the preview load on first use.

Verified in the pane:
- three eager covers, then lazy ones;
- the film and the preview not requested with the page;
- the preview requested on the first pointer on the line;
- the dialogue opening on the first press.

**After P0 (m503, same machine, warm caches, 2026-10-26 evening):**
- A flat page (`sort=name`, three runs): TTFB 144-174 ms, FCP = LCP 232-256 ms (a card cover), TBT 0, CLS 0.031.
- The page of category 2 by area (two runs): FCP = LCP 292-336 ms, CLS 0.031.
  - The category has no posters, so its first three covers are the eager ones, as designed.
- The film and the preview are not requested with the page.

These numbers are within the noise of the baseline. On a warm image cache, on this machine, nothing waits for an
image: what P0 changes (a first row that is not held back by lazy loading, the hero's picture asked for first, less
script at load) shows on a cold cache, a slow network and a slow CPU, which is what the probe of 5.4 has to throttle.

### 5.2.2 The R0 baseline at 5,000 courses (m503b, 2026-10-27)

The baseline every React stage is compared with. It was measured on m503b with theme main `076e160`
(P0, R0 and the probe fix #35). The stack also mounted local_unlistedcourses with its batched
discoverability probe (F2/F3, branch `perf/batch-discoverability-probe`, not merged yet), so the
logged-in reads below already include that plugin change.

**The server meter** (`docs/vitrine-react/probe/loadprobe.php --run`, warm, the seeded 5,000-course subtree,
Xdebug loaded in trigger mode, so the milliseconds are an upper bound and only the statements gate):

| Viewer | Subtree (courses) | Statements (listing + hero) | ms | Peak | Listing HTML | Hero HTML |
|---|---|---|---|---|---|---|
| Student | root (5,000) | 35 (21 + 14) | 78 | 19.2 MB | 37 KB | 4 KB |
| Student | mid (510) | 33 (19 + 14) | 35 | 2.4 MB | 56 KB | 4 KB |
| Student | leaf (35) | 39 (19 + 20) | 25 | 1.2 MB | 136 KB | 4 KB |
| Manager | root (5,000) | 37 (20 + 17) | 166 | 19.2 MB | 40 KB | 13 KB |
| Manager | mid (510) | 36 (19 + 17) | 46 | 2.4 MB | 61 KB | 13 KB |
| Manager | leaf (35) | 44 (21 + 23) | 27 | 1.2 MB | 141 KB | 13 KB |
| Visitor | root (5,000) | 18 (18 + 0) | 29 | 10.1 MB | 33 KB | 2 KB |
| Visitor | mid (510) | 19 (17 + 2) | 11 | 1.1 MB | 23 KB | 2 KB |

- Before R0, on the same data, the student's root cost 127 listing statements and the manager's 78. The theme's
  context preload (R0, #34) and the plugin's batching (F2/F3) brought them to 21 and 20.
- The visitor's leaf is not measured: the leaf the meter picks is not public, and the meter refuses a redirect.
- The root's 19 MB peak is the O(N) statements over the whole subtree (D22): flat in statements, linear in rows.

**The browser probe** (`@theme_dimensions_perf`, five runs each, median kept):
- Throttling: 4x CPU, 150 ms latency, 1.6 Mbps down.
- The Behat site (`cachejs` on).
- Small fixtures built by the probe itself: 24 flat courses, the course index, an areas category.

| Page | Cache | TTFB | FCP = LCP | TBT | CLS | INP of the press | Scripts before load | DOM nodes |
|---|---|---|---|---|---|---|---|---|
| Student, flat category | cold | 156 ms | 5,052 ms | 1,017 ms | 0 | 64 ms | 81 | 1,678 |
| Student, flat category | warm | 143 ms | 556 ms | 99 ms | 0 | 64 ms | 47 | 1,677 |
| Student, course index | cold | 143 ms | 4,832 ms | 833 ms | 0 | 56 ms | 81 | 951 |
| Student, course index | warm | 110 ms | 496 ms | 1 ms | 0 | 48 ms | 19 | 950 |
| Student, grouped category | cold | 157 ms | 4,836 ms | 931 ms | 0 | 48 ms | 81 | 1,195 |
| Student, grouped category | warm | 116 ms | 540 ms | 61 ms | 0 | 40 ms | 47 | 1,194 |
| Visitor, flat public page | cold | 113 ms | 5,032 ms | 457 ms | 0.0001 | 48 ms | 14 | 857 |
| Visitor, flat public page | warm | 89 ms | 504 ms | 11 ms | 0.0001 | 40 ms | 17 | 857 |

What the probe says:
- **The cold first paint is the cost of core's JavaScript and stylesheets on a slow line, not the showcase's
  HTML.**
  - FCP is about 5 s on every cold page, the visitor's included.
  - The visitor's page asks for 14 scripts before load and the student's for 81 (the logged-in page's AMD
    modules, one request each), yet both paint at about 5 s.
  - The difference shows in TBT instead: 457 ms against 833-1,017 ms.
- **The cold TBT is the logged-in page's main target for the React stages.** Warm, it is at or under the 200 ms
  target everywhere; cold, it is four to five times over.
- **The noise is small on one host.** Across five runs:
  - FCP spreads by 1-3 % cold and about 20 % warm;
  - cold TBT spreads by about 15 %, the visitor's by up to 50 % (one run of 689 ms among four of about 400-490);
  - an A/B on the same host needs a difference larger than those spreads.
- **CLS is 0 on the logged-in pages and 0.0001 on the visitor's**, under the 0.1 target. The filter line's shift of
  5.2.1 does not appear at the probe's viewport.
- **The INP of a press is 40-80 ms** everywhere: the controls already answer within the target.
- The flat page's cold `load` event is about 31 s for the student and 20 s for the visitor. That is the whole
  page, every module and image, at 1.6 Mbps; it is reported but is not one of the targets.

Running it again (section 5.4):
1. Add `$CFG->behat_theme_dimensions_perfprobe = true;` to the stack's `config.php`. The `behat_` prefix is what
   survives core's `behat_clean_init_config()`.
2. Run `mdl behat m503b @theme_dimensions_perf`.
3. The reports land under `~/dev/moodle-dev/data/m503b/faildumps/theme_dimensions_perf/`.
4. Remove the line afterwards.

### 5.2.3 R1 against the baseline (m503b, 2026-10-28)

Theme main `77717c2` (R1, #37), same stack, data, fixtures and throttling as 5.2.2; medians of five runs. R1 draws
the React calendar on the logged-in shell only, so the visitor's page is a control, and so is the student's course
index, which draws no calendar.

| Page | Cache | FCP R0 → R1 | TBT R0 → R1 | INP R0 → R1 | Scripts before load R0 → R1 | DOM nodes R0 → R1 |
|---|---|---|---|---|---|---|
| Student, flat category | cold | 5,052 → 5,012 ms | 1,017 → 985 ms | 64 → 64 ms | 81 → 85 | 1,678 → 1,679 |
| Student, flat category | warm | 556 → 536 ms | 99 → 98 ms | 64 → 48 ms | 47 → 47 | 1,677 → 1,678 |
| Student, course index | cold | 4,832 → 4,844 ms | 833 → 773 ms | 56 → 48 ms | 81 → 81 | 951 → 951 |
| Student, course index | warm | 496 → 480 ms | 1 → 4 ms | 48 → 40 ms | 19 → 48 | 950 → 950 |
| Student, grouped category | cold | 4,836 → 4,764 ms | 931 → 819 ms | 48 → 56 ms | 81 → 85 | 1,195 → 1,196 |
| Student, grouped category | warm | 540 → 524 ms | 61 → 39 ms | 40 → 48 ms | 47 → 47 | 1,194 → 1,195 |
| Visitor, flat public page | cold | 5,032 → 5,020 ms | 457 → 388 ms | 48 → 48 ms | 14 → 14 | 857 → 857 |
| Visitor, flat public page | warm | 504 → 524 ms | 11 → 22 ms | 40 → 48 ms | 17 → 17 | 857 → 857 |

What it says:
- **R1 costs nothing measurable and was not meant to save anything.** It is a port: the calendar's work moved from
  an AMD module to a component. The logged-in pages with a calendar ask for four more scripts before load (the
  component's ES modules) and hold one more DOM node (the mount). Their FCP and TBT moved by less than the
  controls did: the course index's cold TBT fell 7 % and the visitor's 15 % with no code of theirs changed. HTML is
  4 bytes shorter. CLS stayed 0 and 0.0001.
- **The warm script count is bimodal and is not a measure.** On the course index the five warm runs read about 18 or
  about 47 scripts, in both series (R0: 47, 18, 19, 18, 21; R1: 16, 45, 48, 50, 47), so the median flips with the
  browser's cache rather than with the code. Read script counts from the cold series only, as 5.4 says.
- **A first run was discarded.** It overlapped another session's Behat run on m503 (same host) and read every
  logged-in page's cold TBT 30 to 40 % higher, the course index included, while the visitor's pages, measured last
  after that run had ended, read as here. The controls are what told it apart. An A/B on this host needs nothing
  else running in the Selenium or PHP containers; check before trusting a run.

### 5.3 What option B does for each metric

**FCP and LCP.**
- The first paint stays the server's HTML, which is the content itself.
- A skeleton in place of server-rendered cards would make FCP earlier and LCP later, and would cost
  the page without JavaScript. That is no gain while the server answers in tens of milliseconds.
  Revisit this only if the probe shows a time to first byte over about 600 ms on a large subtree.
  The options then are an early flush of the header (unverified on Moodle's output path) or a shell
  whose first page arrives through the web service with a no-script link to the plain page, and
  either one is an owner decision.
- Independent of React:
  - `fetchpriority="high"` and an intrinsic size on the hero image;
  - an optional `<link rel="preload" as="image">` for it from `before_standard_head_html_generation`;
  - eager loading for the first row of covers (three cards at xl), lazy loading for the rest;
  - a sized box for every crest.
- The showcase's 17 KB of CSS could leave the site-wide bundle for a stylesheet requested on the
  showcase pages only. That helps every other page, not the showcase, and it means compiling per
  flavour, which is a full compile on each flavour's first request today. It is low priority.

**Skeleton first, wherever content is not in the HTML.**
- Every client fetch shows its skeleton within the same frame as the press: a filter, a search, the
  panel, "Load more". The panel already does this after 150 ms. Under React the skeleton is the
  pending state of the store, drawn at once, while the answer replaces it.
- Every skeleton keeps the height of what it stands for, so the swap moves nothing (CLS 0).
- A cover is a box of its final aspect ratio painted in the card's `--dim-card-band` until the image
  decodes, so an image filling it moves nothing.
- **The mount itself is a moment without content.** `createRoot` throws away the server's markup, so
  the component's first render must draw the same boxes the server drew: a React island owns only
  controls whose markup the server also drew (the line, the calendar's trigger, the panel's frame).
  The probe compares the islands' boxes before and after mount, and any shift fails the stage.

**TBT and INP.**
- Only what the first screen needs runs at load: the listing store and the filter line.
- The film (`covervideo`), the course dialogue, the scope preview and the calendar's popover load on
  first use or when the browser is idle (`requestIdleCallback`, or a dynamic `import()`). R0 must
  verify that the import map resolves a dynamic import of a theme module.
- Layout reads are batched before writes. Today `categoryline` measures its steps with every scope
  cell unhidden, which forces a synchronous layout. Under React the measure runs once in a
  `useLayoutEffect`, from a `ResizeObserver` entry instead of `getBoundingClientRect`.
- An answer is rendered in a `startTransition`, so a press is never blocked by a repaint of 24
  cards. More than about 50 inserted cards are inserted in chunks that yield to the browser.
- React's runtime is already on the page through core's navigation (section 3.7), so what the
  showcase adds is its own components, held to the 54 KB gzip of today or less.

### 5.4 The probe (built in R0)

- A Behat scenario tagged `@theme_dimensions_perf`, not part of the ordinary suite. It runs logged in
  (`I log in as`, so no password is typed anywhere) and as a visitor, on a category page with
  covers, the root and a grouped page.
- It reads the browser's own entries with buffered observers:
  - `paint` for FCP,
  - `largest-contentful-paint`,
  - `longtask`, summed over 50 ms between FCP and a quiet window, for TBT,
  - `layout-shift`, including one taken across the mount,
  - an `event` entry for a filter press, for INP.
- It writes them as JSON under the stack's faildumps, five runs, the median kept.
- It runs on m503b and on the CI host, never on the MacBook. Its numbers are a trend first; they
  become a gate once two stages have shown how noisy the hosts are.
- A data set of realistic size is part of the probe: the 5,000-course load probe of the design record,
  re-created on m503b, because a lab number on six courses measures the framework, not the
  showcase.

## 6. Stages (under option B)

The stages run in order, one pull request each, every one under MDL Shield's 3,000 effective-line
limit. Every stage keeps all 82 showcase scenarios green, reports the probe's numbers before and after (section 5), and replaces the source-shape tests of the
code it removes with tests of the new code, either TSX source tests or Jest if section 3.8 holds.

| Stage | Content | Removes | Main risk |
|---|---|---|---|
| P0 | Quick wins without React (5.3): hero `fetchpriority` and intrinsic size, first row of covers eager, sized crests, the film / dialogue / preview modules loaded on first use | nothing | a first row that differs per breakpoint (three, two, one cards) |
| R0 | Tooling: `js/esm/src` in the theme, `.eslintrc` (block_compass's), `tsconfig`, the `tsc` and build-currency gates for `js/esm`, the AMD bridge module, the visitor import-graph walk extended to `js/esm/build`; the performance probe (5.4) and its baseline before any port | nothing | a visitor build that silently pulls a session module; a noisy probe |
| R1 | `daterange` as a React component on the logged-in shell; its native date fields stay the transport (done, #37; numbers in 5.2.3) | nothing yet: the visitor keeps `daterange.js` (1029) until R4 (owner, 2026-10-27) | focus kept across repaints, the draft/commit rules, no timezone arithmetic (CLAUDE.md, round D2b/D3) |
| R2 | The filter line (`categoryline`, `categorypreview`, `categorymark`) as one component over the server's GET form, aligned with block_compass's `FilterLine` where the contract allows | about 1,750 | the steps measured on the line's own width; suggestions from the last answer without a term |
| R3 | The panel and the posters (`categoryareapanel`, `categoryposters`, `categorypanel`) | about 2,440 | the ticket/turn races, `settle()`, the five integers of the row placement, Escape scope |
| R4 | One listing store for both shells (`categoryvitrine`, `categorypublic`, `categorylisting`), visitor features behind props, the visitor entry importing nothing of a session | about 3,580 | the visitor graph; `pushState` vs `replaceState` ownership; the remembered state and the view preference |
| R5 | The star joins the store; clean-up of the dead hooks, `gates.conf` retargeted, CLAUDE.md sections rewritten | `categorystar` (127) | mutation specs left matching nothing (a hard exit for `mdl mutate`) |

Rough size: R1 to R4 are each a long implementer task (several hours of agent time) plus the matrix.
The whole series is weeks of rounds, at the cadence of the showcase's earlier rounds.

## 7. Decisions for the owner

1. **Option B** (React for state and controls, Mustache for the content), or A with a parity gate
   to build?
2. **Order**: start with R1 (the calendar, self-contained, proves the toolchain on a small surface),
   or go straight to R4 (the store, the largest gain and the largest risk)?
3. **Test strategy**: port the source-shape gates to TSX source, or adopt Jest if plugin CI can run
   it (to be measured in R0)?
4. **The visitor client** (`categorypublic`, off by default with `categorypublicclient`): in the series
   (R4), or kept as AMD until the logged-in side has shipped?
5. **Sharing with block_compass**: a common filter-line component would need a package both plugins
   import, and Moodle has no mechanism for one plugin's ESM to be a dependency of another's beyond
   the import map (`@moodle/lms/<component>/...` resolves by component directory). Copy the
   component with an `.upstream`-style snapshot, or keep two implementations of the contract?
6. **P0 first**: ship the quick wins of 5.3 (hero priority, the eager first row, sized crests,
   loading the film, the dialogue and the preview on first use) as their own pull request before
   any React stage? They need no decision above.
7. **The probe's data set**: re-create the 5,000-course load probe on m503b (a shared stack: the
   other sessions are asked first), or a smaller fixed fixture built by the probe itself?
8. **When the probe gates**: trend only for the first two stages, then the targets of 5.1 as a hard
   gate, or a gate from R0 on?
9. **Skeleton for the first paint**: keep the server's content as the first paint (recommended while
   the time to first byte stays low), or a shell with a skeleton and the first page fetched by the
   client, accepting a later LCP and a page without JavaScript that has to be kept by other means?

### 7.1 The owner's answers (2026-10-27)

1. **Option B.**
2. **R1 first**, the calendar.
3. **Jest, if it works.** It does: core 5.3's own `jest.config.js` runs a plugin's `js/esm/tests`.
   - `mdl ci` has a `jest` step (uaiblaine/moodle-dev#124), and the reusable workflow has one too
     (uaiblaine/moodle-workflows#7).
   - The theme's first three tests (`js/esm/tests/amd.test.ts`) run in the matrix.
4. **The visitor client stays AMD** until the logged-in side has shipped.
5. **Two implementations of the same contract**, the theme's and block_compass's, which is already React.
6. **P0 first**: done, #31.
7. **The 5,000-course load probe re-created on m503b**, and its data kept there for tests: never delete it.
8. **What makes the probe assertive.**
   - Asked as a question; the answer adopted for R0 is in three parts.
   - The deterministic numbers gate from R0 on:
     - statements;
     - the HTML bytes of a card, of a listing and of the hero;
     - the exact module and string lists (`first_paint_budget_test`, the budget tests).
   - The browser timings are an A/B measure, with medians of five throttled runs on one host.
   - The targets of 5.1 become a hard gate once two stages have shown the hosts' spread (5.2.2 is the first
     measure of it).
9. **The server's content stays the first paint**, and the code that renders it was reviewed for cost (R0, #34).
