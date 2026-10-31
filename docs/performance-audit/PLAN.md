# theme_dimensions: client-side performance audit

Template: `moodle-dev/docs/client-performance-audit/README.md` (the method, the four axes, the instruments and the
phases; this file only instantiates it). Owner's decision of 2026-10-28: the audit runs once the category
showcase's React port has closed (`docs/vitrine-react/PLAN.md`, stages P0-R5), and what it teaches goes back into
the template before block_compass's audit.

**Started 2026-10-31** on `b919c5e` (version 2026100549), R5 merged as #73. Phase 0 below re-lists the scope and
recounts the signals on that commit.

## Scope

In scope, everything of the theme's JavaScript, 14,632 lines on `b919c5e`:

- **The ES tree**, `js/esm/src`, 28 files, 10,871 lines: `listing/` (store 669, account 771, paint 748, visitor 392,
  address 403, mounts 310, events 140, star 128, history 56, the barrel 37), `areas/` (panel 1,404, posters 563,
  model 515, Areas 213, rail 153, mark 109), `filterline/` (FilterLine 1,312, preview 441, model 378, measure 284,
  suggest 280, steps 132, mark 108, lazy 38), `daterange/` (DateRange 656, calendar 274, model 269) and the `amd.ts`
  bridge (88).
- **The AMD modules**, `amd/src`, 15 files, 3,761 lines: `categoryhero` (766, the category appearance editor),
  `local/coverfilm` (607) with `covervideo` (157), `focusmode` (383), `readingbar` (368), `drawer` (365), `compactnav`
  (289), `categorycoursemodal` (215), `phonemenu` (175), `coursenotice` (124), `linearfooter` (91), `flavourchoice`
  (85), `landingguest` (64), and the two shells' entries `listingaccount` (35) and `listingvisitor` (37).

Out of scope:

- nothing of the showcase's old AMD listing is left (R4c, #71, deleted it; R5, #73, the star);
- core's code and `first.js` (core's combo of every non-lazy AMD module, 10.8 MB raw on m503b): its weight is
  core's and the vendored libraries' (the `-lazy` work), not the theme's;
- the server side: the statement and byte budgets already gate it (`first_paint_budget_test`, the budget tests).

## Scenarios

All on m503b, over the 5,000-course data set kept there (never delete it), at 4× CPU, warm cache, 3-5 runs, at
1358 px and at a true 390 px (emulated: section 2.1 of the template). The probe's four scenarios
(`tests/behat/theme_dimensions_perf.feature`, `@theme_dimensions_perf`) are the A/B gate; the meters give the
attribution.

| Id | Page | Viewer | Interaction | Axes |
|---|---|---|---|---|
| S1 | A flat category page with covers (`/course/index.php?categoryid=15&sort=name`) | student, admin | load; a filter press on the line; type a search term; load more ×10 | A, B, C, D |
| S2 | The course index, the directory of areas | student | load; open and close an area ×10 | A, C, D |
| S3 | A grouped category page, its areas as posters | student | load; open a poster's panel; Escape; ×10 | A, C, D |
| S4 | The visitor's public category page (`/theme_dimensions/category/8?sort=name`) | visitor, forcelogin on | load; a filter press; load more ×10 | A, B, C, D |
| S5 | A course page with the reading bar, the course notice and the linear footer | student | load; scroll the page top to bottom and back; open the course index drawer | B, C |
| S6 | An activity in focus mode | student | switch focus mode on and off ×10 | C, D |
| S7 | The category appearance editor (`categoryhero.php`) | manager | type a colour; pick the quick colours ×10 | B, C |
| S8 | A public landing page with a cover film | visitor | load; play and stop the film | A, C |
| S9 | The login page | visitor | load (control: the theme draws little there) | control |

### Data set, viewers and instruments (fixed in phase 0)

- **m503b**, the 5,003-course set under "Showcase load probe" (category 5: areas 6-24, each with leaves of 25-35
  courses; area 8 holds 20 leaves and 490 courses), `forcelogin` on, `categorypublicclient` on, `cachejs` on, theme
  designer mode off, focus mode's master switch OFF (the site's state; S6 needs it on, see below).
- **The viewer `dim_student`** (password `moodle`), seeded by `seed_viewers.php`: a student in the same 42 courses as
  the probe's `probe_student`, whose password is random, plus `DIMPERF1` (id 5047), a topics course of twelve
  sections, each with a label and a page, long enough to scroll. The admin account measures the editor and the
  admin's listing. Nothing of the owner's fixture or of block_compass's viewers is touched.
- **Instruments**: `moodle-dev/docs/client-performance-audit/theme_dimensions-2026-10-31/` (`seed_viewers.php`,
  `baseline.sh`), over the template's `profile.php`, which this audit extended with `--scroll` (a trusted scroll
  gesture to the foot and back), `--cycle`/`--repeat` (a lifecycle interaction repeated with the DOM nodes, the
  listeners and the heap read after a forced garbage collection), the "after load" sums (the frames, blocking and
  forced layout of what follows the load alone) and instant scrolling before every trusted click (a page with
  `scroll-behavior: smooth` was still moving when the click landed).
- **The URLs**: S1 `/course/index.php?categoryid=15&sort=name` (a leaf, 35 courses); S2 `/course/index.php`; S3
  `/course/index.php?categoryid=8` (area 1, 20 leaves as posters); S4 `/theme_dimensions/category/8?sort=name`; S5
  `/course/view.php?id=5047`; S7 `/theme/dimensions/categoryhero.php?id=5`; S9 `/login/index.php`.
- **Not measured in phase 0**: S6 needs focus mode's master switch on, a site setting of the shared m503b that
  recompiles the theme's CSS (the owner's call); S8 needs a course with a cover film, which m503b does not have (no
  course carries `dimensions_videourl`).

## Targets

The port's own (`docs/vitrine-react/PLAN.md` section 5.1): FCP ≤ 1.8 s, LCP ≤ 2.5 s, TBT ≤ 200 ms, CLS ≤ 0.1 and 0
at a React mount, INP of a filter press ≤ 200 ms. Added here: no lifecycle interaction (S1-S4, S6, ×10) leaves
detached DOM or a heap that grows past the warm-up after a forced garbage collection.

## Known items carried in

- **The filter line's first commit forces the page's first layout** (PLAN 5.2.6): on the visitor's page after R4a, one
  long frame of about 130-150 ms at 4x CPU, 120-135 ms of it forced layout, in `filterline/measure.ts` called from
  `FilterLine`'s first layout effect, with the line already fitting at `s1`. Axis C; the candidate fixes (the first
  measure in the `ResizeObserver`'s first callback, `contain` on the line) are listed there, to measure on both shells.
- **R2's leftover blocking time**: the flat warm page is about 30 ms of TBT above R1, and the scope press's INP is
  96 ms against 48-64 before (PLAN 5.2.4). Axis A or C; the profile of the press is the first measurement. R3's
  profile already put about 60 ms of the warm grouped page in the line's one measured step: likely the item above.
- **The phone layout shift**: 0.038-0.041 when the filter line folds to two lines, measured at Chrome's 500 px
  window floor, not at 390 (PLAN 5.2.4). Re-measure at a true 390; if it stands, axis C.
- **Lighthouse's "render-blocking requests"** on S4, reported by the owner on 2026-10-28: the stylesheet and the
  synchronous scripts in the head. The owner chose then not to change the CSS during the port; phase 0 measures
  it again as loading context, and the owner decides whether it enters the audit.

## Starting signals (not findings)

Counted with `rg` over `amd/src` and `js/esm/src`; the first count was taken on 23be220, before R4a, over 23 + 18
files (12,912 + 6,905 lines) much of which R4c deleted.

| Signal | 23be220 | b919c5e | Axis |
|---|---|---|---|
| `scroll`/`resize`/`mousemove`/`pointermove`/`wheel`/`input`/`touchmove` listeners | 17 | 11 | B |
| `debounce`/`throttle` | 3 | 1 | B |
| `requestAnimationFrame` | 10 | 6 | B, C |
| `ResizeObserver` / `IntersectionObserver` / `MutationObserver` | 11 / 7 / 3 | 3 / 2 / 2 | B, D |
| Layout reads (`getBoundingClientRect`, `offset*`, `client*`, `scroll{Height,Width}`, `getComputedStyle`) | 50 | 36 | C |
| `addEventListener` / `removeEventListener` / `disconnect()`, `unobserve(` | - / 20 / - | 77 / 12 / 8 | D |
| `memo` / `useMemo` / `useCallback`; `useDeferredValue` / `startTransition` | 0; - | 0; 0 | C (measure before adding any) |
| `setTimeout` / `setInterval` | 24 | 16 | A, D |
| `passive`; `AbortController` | -; - | 6; 0 | B; D |

The 11 listeners of axis B on b919c5e: `readingbar` scroll and resize (passive), `linearfooter` scroll (passive),
`categorycoursemodal` the dialogue body's scroll (passive), `areas/rail.ts` a window `pointermove`, `areas/panel.ts`
a window `scrollend`, `listing/store.ts` the toolbar form's `input`, and four `input` listeners of `categoryhero`.

## Baseline

Phase 0, 2026-10-31 04:03-04:18 UTC, m503b at 2026100549 (`b919c5e`), `bash baseline.sh`, log
`logs/2026-10-31-baseline.log`. 4x CPU, warm cache, medians of 5 loads (lines with an interaction or a cycle: 1, 3
or 5 as noted); host load 4.3-8.7 (shared host, rule 3 of the template: S9 is the control, 0 ms of blocking at
both widths). "After" is what follows the load (the press, the typing, the scroll) alone; forced is forced style
and layout summed over the long animation frames.

| Line | Viewer | Width | TBT | Long tasks | Forced | INP | CLS | FCP / LCP | After: blocking / forced |
|---|---|---|---|---|---|---|---|---|---|
| S9 login (control) | visitor | 1358 / 390 | 0 / 0 | 0 / 0 | 0 / 0 | - | 0 / 0 | 232 / 164 | - |
| S1 leaf, load | student | 1358 | 9 | 1 | 137 | - | 0 | 384 | - |
| S1 leaf, load | admin | 1358 | 23 | 2 | 188 | - | 0 | 292 | - |
| S1 leaf, load | student | 390 | 79 | 2 | 244 | - | 0 | 260 | - |
| S1 a filter press | student | 1358 | 60 | 4 | 233 | 96 | 0 | 328 | 35 / 22 |
| S1 type a term | student | 1358 | 61 | 2 | 205 | 152 | 0 | 388 | 49 / 24 |
| S1 load more (1 run) | student | 1358 | 73 | 3 | 372 | 64 | 0 | 360 | 85 / 165 |
| S2 directory, load | student | 1358 / 390 | 0 / 0 | 0 / 0 | 17 / 21 | - | 0 / 0 | 292 / 256 | - |
| S2 open an area (1 run) | student | 1358 | 0 | 0 | 76 | 48 | 0 | 336 | 0 / 53 |
| S3 posters, load | student | 1358 / 390 | 0 / 31 | 0 / 1 | 63 / 145 | - | 0 / 0 | 264 / 244 | - |
| S3 open a panel (1 run) | student | 1358 | 5 | 1 | 137 | 56 | 0 | 316 | 19 / 71 |
| S4 visitor, load | visitor | 1358 / 390 | 0 / 18 | 0 / 1 | 86 / 110 | - | 0 / 0 | 248 / 228 | - |
| S4 load more (1 run) | visitor | 1358 | 44 | 3 | 168 | 56 | 0 | 248 | 35 / 79 |
| S5 course page, scroll | student | 1358 / 390 | 49 / 78 | 1 / 2 | 123 / 195 | - | 0.026 / 0 | 348 / 320 | 0 / 0 |
| S7 appearance editor, load | admin | 1358 | 803 | 4 | 926 | - | 0 | 348 | - |

Lifecycle, `--cycle` x10 after a warm-up cycle, read after a forced garbage collection:

| Cycle | DOM nodes | Listeners | Heap |
|---|---|---|---|
| S1 two filter presses (a pill, then the first) | 10,789 -> 10,789 | 1,392 -> 1,662 (+27 a cycle) | 24.9 -> 25.4 MB |
| S2 open an area, Escape | 10,189 -> 13,209 (+302 a cycle) | 2,130 -> 2,150 | 27.1 -> 27.5 MB |
| S3 open a poster's panel, Escape | 14,639 -> 26,289 (+1,165 a cycle) | 2,800 -> 2,820 | 27.5 -> 27.9 MB |
| S5 close and reopen the course index | 10,824 -> 10,824 | 1,566 -> 1,566 | 24.3 -> 24.7 MB |

Attribution (the long frames' scripts and the CPU profile's self time per source):

- **S7's 803 ms of TBT is core's**: Moodle's YUI rollup (`yui_combo.php`, 778 ms of self time; `_getWinSize`,
  `Y_DOM`, `isCSSLoaded`; one 580 ms frame, 470 ms of it forced layout, in its `onLoad`) and TinyMCE (166 ms), the
  form's file managers and editors; `categoryhero.js` does not appear among the top sources. Whether the theme's
  form shape (how many file managers, how many editors) is the lever is a phase 2 question.
- **On every listing load the theme's own frame is the filter line's first fold**: `filterline/measure.ts` from
  the `ResizeObserver`'s first callback, 25-43 ms at 1358 px and 70-96 ms at 390 px, all of it forced layout
  (`getBoundingClientRect`, 112 ms of self time on the phone). The other frames are React's commits (core's
  `react-dom/client`, 22-139 ms, mostly forced layout) and the ES module graph's resolution (`import.then`).
- **Not the theme's**: Boost Union's React navigation (`theme_boost_union/nav/Nav`, 56-67 ms on every page,
  S9 excepted), `core/first.js`, jQuery and RequireJS.
- **The course page (S5) scrolls for free**: no long frame after the load at either width, the reading bar's and
  the linear footer's passive listeners included; its 49-78 ms of TBT is the load (the ES module graph and the
  navigation's React commit). Its CLS of 0.026 at 1358 px is below the target.
- **Not measured yet**: S4's filter press and S7's typing (both scripted controls refused the trusted click:
  "element not interactable"); S6 and S8 (section "Data set, viewers and instruments").

## Findings

### Phase 1: candidates from reading (2026-10-31), each to be reproduced as a number in phase 2

Three readers (`fleet-reader`: axis D; axis C; axes A and B) over `b919c5e` with the baseline in hand. A candidate
names its file:line and the measured number it is expected to explain; none is a finding until phase 2 measures it.

| Id | Axis | Where | Mechanism | Explains |
|---|---|---|---|---|
| C1 | C | `filterline/measure.ts:107-128` `stepWidths` (from `FilterLine.tsx` the observer's first entry, in `flushSync`) | one forced layout per try (`paint()` then `requiredWidth()`: a rect per child and `getComputedStyle`); a phone tries all five steps, then `void line.offsetWidth`; nothing in `scss/` uses `contain` | every listing load: 25-43 ms at 1358, 70-96 ms at 390 (the known item) |
| C2 | C | `FilterLine.tsx:945-960` the `signature` effect | the signature holds every count, so an answer that changes a count remeasures (1-5 layouts), against measure.ts's own docblock ("never the numbers of the moment") | S1 press: forced 22 ms, part of INP 96 |
| C3 | C, A | `FilterLine.tsx:973-998` a `useLayoutEffect` with no deps; `onFieldInput` (:603-612) → `relistNow` → `offers()` (suggest.ts: a DOM scan of every radio and label) | per key: the whole line re-renders, the suggestions are rebuilt from the DOM, and `placeList()` reads rects after the commit's writes (and writes `style.top` unchanged) | S1 typing: INP 152, the `DIV.oninput` frame of 27 ms (23 forced) |
| C4 | C | `listing/paint.ts:621-638` `focusAfterAppend` | `focus()` without `preventScroll` right after `grid.append(...items)`: scrolls, so lays out the appended batch | S1 load more: after-load forced 165 ms |
| A1 | A | `listing/store.ts:109-148` `applyResponse` | one task renders the cards (`renderForPromise` per card), paints the grid, re-renders the line (`onAnswer`, two state sets, then `remember`/`relistNow`/`preview.refresh`) and the areas | the after-load blocking of the press, the typing (49 ms) and load more (85 ms) |
| C5 | C | `areas/panel.ts:837-851` `moveTo`, `:893-898` `openArea` | rect reads after a template render and a DOM move | S3 open: forced 71 ms; S2: 53 ms |
| D1 | D | not found in the theme's code | the panel's DOM survives a forced GC: `closeArea` empties it (`panel.ts:974`), the module state holds ids only. Suspects outside the theme: a handler of core's `filterContentUpdated` (fired by `replaceNodeContents`), or the probe | S3 +1,165 nodes a cycle, S2 +302 |
| D2 | D | `listing/account.ts:521-535` `attachCards` | a `mouseenter` and a `mouseleave` closure per new card on every grid repaint; a delegated pair on the root would replace them | S1 +27 listeners a cycle (nodes stable: unexplained by this alone) |
| B1 | B | `account.ts:757` | a 100 ms `setInterval` (`drainPending`) for the page's lifetime, a no-op while nothing is pending | nothing measured |
| B2 | B | `Areas.tsx:157` → `panel.ts:372-376`; `FilterLine.tsx:871`; `areas/rail.ts:151` | scroll (the panel frame's capture listener is not passive) and pointermove handlers that read layout per event, not coalesced to a frame; rail's pair has no `pointercancel` path | nothing measured (scrolling a course page cost 0) |
| B3 | B | `store.ts` `requestRows` | no `AbortController`: a superseded request still runs on the server (the `seq` ticket drops its answer) | server work, not the main thread |

### Phase 2: verified (2026-10-31)

Instruments: `detached.php` (every live `Node` not connected to the document, after a forced GC, grouped by the root
of its detached tree, found through `Runtime.queryObjects`; `--holders` lists the arrays, maps, sets and objects
that hold one; `--eval` after the cycles), `listeners.js` (an `--init` ledger of the net listeners added per target
and type, with the call stacks of one of them), `phase2.sh`.

- **D1 dropped: the panel leaked into the probe, not into the page.** The detached trees were the panel's old
  `section[data-region=dim-panel-section]`, +38 wrapped nodes a cycle, and their holders were pairs
  `[h3.dim-panel__title, {"status":0,"value":{"element-6066-...":0}}]`: ChromeDriver's element cache. WebDriver sends
  a key to the active element, which it resolves and keeps; the open panel had moved the focus to its title. With
  the key sent through CDP (`Input.dispatchKeyEvent`, now what `profile.php` and `detached.php` do), three S3
  cycles leave 4 detached nodes before and 4 after. The baseline's +1,165 and +302 nodes a cycle are the
  instrument's; the template takes the lesson.
- **D2 re-attributed: the listeners that grow are core's, one per card the theme announces.** The ledger over five
  S1 cycles: `article[dim-card]` mouseenter and mouseleave 186 each (`attachCards`, collected with their cards: the
  detached count stays 5 -> 5) and `document keydown` +174, never removed. Every one of those comes from
  `enableTooltips()` in core's `theme/boost/amd/src/loader.js:350-361`: on each `filterContentUpdated` it adds a
  `document` keydown listener per node announced, whether the node holds a tooltip or not, and keeps its tooltip
  list in the closure. The theme announces each card on its own (`listing/paint.ts:591`,
  `Templates.appendNodeContents(item, ...)` per card; the panel's `replaceNodeContents`, `panel.ts:502` and `:527`),
  so a press adds about 14. Small per press (an Escape runs every closure, each a no-op on an empty list), unbounded
  over a session; core's defect, the theme's lever is to announce one container per repaint.

`phase2.sh`, 2026-10-31 04:42-04:48 UTC, host load 1.9-2.9 (quieter than the baseline), log
`logs/2026-10-31-phase2.log`. Layout reads counted by an `--init` wrapper of `getBoundingClientRect` and
`getComputedStyle`, by calling module.

- **C1 confirmed, and `contain` is not the remedy.** A load's layout reads: `filterline/measure` 29 at 1358 px, 45
  at 390 px (every other caller: 4 and 7). At 390 px the line's first fold is the theme's largest frame on a load:
  `FilterLine`'s `ResizeObserverCallback`, 78 and 80 ms, 76 and 79 of them forced (TBT 48 ms). `contain: layout
  style` on the line, interleaved A/B at 390 px, 5 loads each: forced 133 / 145 ms without, 159 / 143 with, and one
  contained series shifted the page (CLS 0.237). What costs is the number of write-then-read rounds of `stepWidths`,
  so the remedy is fewer rounds (one measure, the narrower steps derived), not a cheaper round.
- **C3 confirmed: typing is the theme's slowest interaction.** Six typed characters: INP 168 ms (152 in the
  baseline), one long frame of 109 ms of blocking after the load, of which only 32 ms is script (`react-dom`'s
  `DIV.oninput`, 25 ms forced): the rest is the style and layout of what the key re-rendered. `filterline/measure`
  read layout 50 times over the six keys (about 8 a key: `placeList` after every commit). Under the 200 ms target at
  4x CPU, but the only interaction near it.
- **C2 confirmed, small.** One press: `filterline/measure` 29 reads (a full remeasure, as C1's load), after-load
  blocking 15 ms, forced 19 ms, INP 72 ms; the press's largest frame is core's click handler in `first.js` (18 ms).
- **C4 dropped.** Load more with every `focus()` made `preventScroll`, interleaved A/B, 3 loads each: after-load
  forced 87 / 87 ms without, 111 / 85 with. The forced layout of load more is core's and jQuery's scroll listeners
  (`DOMWindow.onscroll` 45 ms, `#document.onscroll` 19 ms) answering a scroll, which the probe's own
  `scrollIntoView` before the trusted click also causes: not the theme's focus.
- **C5 dropped.** Opening a poster's panel: after-load blocking 8 ms, forced 22 ms; `areas/panel` reads layout 4
  times. Opening an area of the directory: 0 ms blocking, 30 ms forced.
- **A1 dropped for now.** The answer's one task stays under 50 ms of blocking at this load (press 15 ms, load more
  28-31 ms); its share of the typing frame is inside C3.
- **The lifecycle is clean with keys sent through CDP**: S3 and S2 open/Escape x10 leave the DOM node count equal
  (23,310 and 15,206), listeners +2 a cycle (D2's core listener, one per panel announced).
- **B1-B3 dropped by rule 1**: nothing measured costs (scrolling a course page has no long frame; the interval is a
  no-op while nothing is pending). The non-passive `scroll` listener of the panel's frame is worth the template's
  passive-listener source test as a gate, not a fix.
- **Not measured**: S4's press (the visitor's pill refuses WebDriver's click as "not interactable" at click time,
  though it reads visible in the same page settled; the line is the same component as S1's, measured there), S7's
  typing (its colour fields are disabled until a custom palette is chosen; `categoryhero.js` is innocent by reading
  and S7's cost is core's).

### C3 re-measured at a typist's pace (2026-10-31, before its fix)

The phase 2 typing numbers were the probe's. `profile.php --type` sent the text with WebDriver's `sendKeys`, which
queues every key at once: the six keydowns started within 0.6 ms of each other and all six presented in the frame
after the burst, so the INP measured (152-168 ms) was six keys' processing in a row (52 + 20 + 18 + 18 + 19 + 19 ms),
which no reader produces. `--cadence=<ms>` now types one character at a time through CDP, `<ms>` apart. At 150 ms
(`logs/2026-10-31-phase3-c3.log`, 3 loads a line):

- 1358 px: INP 72 ms, after-load blocking 11 ms. The first key, which opens the list, is 64-72 ms (one 29-32 ms
  `DIV.oninput`, 25 of it forced); every later key 32-56 ms. The burst on the same pages: INP 152, blocking 85.
- 390 px: INP 64 ms, keys 32-72 ms; a key reads layout 8 times (53 reads with one key against the load's 45).
- The larger frame of typing on a phone is the ANSWER, about 1.4 s after the first key: 78-92 ms, 65-78 ms of React's
  scheduler, 60-73 of it forced, blocking 17-29. The line's reads after the new grid is painted pull that grid's
  layout into the script; how much of it is extra (a second layout round) rather than early is C1 + C2's A/B to show.

So C3 is small, the size of C2's press, and gets no pull request of its own; the trap went into the template's
README with D1's.

### H1: the stylesheet's `:has()` tax, found under C1 (2026-10-31)

C1's cost is not the theme's. Split into style and layout (`stylesplit.js`), each try of the fold is 15.7-17.5 ms of
style and 4.1-4.8 of layout at 390 px. And the style is not the step classes': ANY class change on the line costs
16.3-20.5 ms of style, a class no rule names included, while an inherited custom property set on the same line costs
0 (`stylecalib.js`). Taking selectors out of the page's 22,848 rules (`stylebisect.js`): every `:has()` rule out, 0;
only the five whose `:has()` argument reads the class attribute out, 0. They are local_dimensions' (`styles.css`
6151-6189, the hub modals' close chip, on every branch):

```css
.modal:not(.local-dimensions-related-modal):has(.modal-body [class*='local-dimensions-']) .modal-header .btn-close
```

A `[class*=...]` inside `:has()` makes a change of the `class` attribute of ANY element a `:has()` invalidation, and
plugin stylesheets are in the theme's bundle: every page of a site carrying local_dimensions pays it on every class
change, whoever's script makes it (the drawers, the tooltips, React). Interleaved A/B with the five selectors dropped
at DOMContentLoaded (`phase3-has.sh`, `logs/2026-10-31-phase3-has.log`, five loads a line):

- 390 px, the line's first fold (the `ResizeObserverCallback` frame): forced 118 / 101 ms with them, 50 / 52 without.
- 1358 px: 40 / 43 both ways (one try; that frame's forced layout is the page's first, moved and not added).
- Typing at 390 px: after-load blocking 19 / 20 ms with them, 10 / 6 without. A filter press: 18 ms forced both ways.
- TBT of the drop lines is the instrument's (walking the rules is one long task; S9: 0 to 132 ms): read the frames.

What the fold costs the theme after H1 is about 8-10 ms on a phone (50 against desktop's 40-43, four tries against
one), so C1 + C2 is small until re-measured with H1 fixed. The fix is local_dimensions': the `:has()` argument keyed
on a class the plugin's modals carry, never on the class attribute, and a scanning test that fails a `:has()` whose
argument reads an attribute of every element (`[class...]`, `[style...]`).

### C1 + C2 re-measured with H1 fixed (2026-10-31)

local_dimensions #117-#120 merged and live (m503b at 2026100517). `phase3-after-h1.sh` runs phase3-has.sh's lines
again, the drop kept as a control that must now change nothing, plus phase2.sh's read counts
(`logs/2026-10-31-phase3-after-h1.log`, 16:43-16:49 UTC, host load 3.0-4.2, higher than the H1 series):

- The line's first fold at 390 px, forced: medians 41 / 40 ms (H1 series: 118 / 101 with the selectors, 50 / 52
  dropped). The control's drop changes nothing (45 / 36), so H1 is gone; the 9 rules it still matches are core's
  `.mds-checkbox[class] ...:has(:disabled)` family, whose `[class]` is outside the `:has()`.
- At 1358 px: 37 / 33 ms (40 / 43 before), the control 34 / 33.
- So what the theme's extra fold tries cost a phone is the gap between the two widths, about 4-7 ms on the load.
- The layout reads are unchanged, as they must be: `filterline/measure` 29 at 1358 px, 45 at 390 px, 29 on a press.
- A press at 1358 px: forced 18-20 ms after the load, blocking 22-26, INP 80-88 (phase 2: 19, 15, 72). The press
  never paid H1 (its 18 ms was the same both ways): it is the remeasure itself plus core's click handler.
- Typing at 390 px, 150 ms a key: INP 56 ms, after-load blocking 0-2 ms (H1 series: 19 / 20 with, 10 / 6 without).
- Not measured: the press at 390 px (WebDriver refuses the second visible pill as not interactable at that width).

C1 + C2 is now a few milliseconds a fold, under every target with room: the one-measure rewrite would buy about
5 ms on a phone load and part of a press's 18-20 ms. Not worth a pull request on its own; kept as a note for the
next change that touches `filterline/measure.ts`.

### Confirmed, ranked by cost

| Rank | Id | Cost (4x CPU) | Proposed fix | Gate |
|---|---|---|---|---|
| 1 | H1 (local_dimensions) | 16-20 ms of style for every class change of any element on this page; the phone's first fold 101-118 ms forced against 50-52 without it | local_dimensions: the five selectors keyed on a marker class of the plugin's modals | its own scanning test over `styles.css`; `stylecalib.js` on m503b: a class change on the line back to about 0 |
| - | C1 + C2 | measured after H1: the phone's first fold 40-41 ms forced against desktop's 33-37 (about 4-7 ms of extra tries); a press 18-20 ms forced, a full remeasure on every answer that moves a count | one measure of the line in its widest step, the narrower steps derived from it; counts out of the signature | the `steps.ts` `settled()` Jest pattern: one layout round per fold |
| - | C3 | typing at 150 ms a key: INP 64-72 ms, keys 32-72 ms, 8 layout reads a key (the 152-168 ms of phase 2 was the probe's burst) | none on its own; `placeList` only when the list opens or the fold changes, if C1 + C2's change passes through it | - |
| 2 | D2 | about 14 permanent `document` keydown listeners a press (core's `enableTooltips()`), each Escape runs them all | announce one container per repaint, not one per card: insert the rendered cards and notify `filterContentUpdated` once for the grid (and once for the panel); report core's listener to the tracker | the listener ledger's count over five presses |

Read and found innocent: `categoryhero.js` for S7's 803 ms (no layout reads, no YUI calls: core's YUI and TinyMCE
own it), `readingbar.js` (a clean frame), the observers' hygiene (`discard` unobserves, `railobserver` disconnects,
`FilterLine`'s and `Areas`' listeners are removed on cleanup). Read and not measured, small: `daterange/calendar.ts`
builds an `Intl.DateTimeFormat` per day per render of an open calendar (about 84), `linearfooter.js` reads layout on
every scroll at phone width.

## Fixes

To be filled in phase 3: one row per pull request, with its before/after numbers and the gate it left.

| Id | Pull request | Change | Before | After | Gate |
|---|---|---|---|---|---|
| H1 | local_dimensions #117-#120 (2026100517 / 2026042013 / 2025100613 / 2024100713) | the close chip keyed on page classes and the marker `local-dimensions-modal`, no `:has()` reading `[class...]` | a class change on the line 16-20 ms of style; the phone's first fold 101-118 ms forced | 0 ms of style (`stylecalib.js`); the fold 40-41 ms | `tests/local/selector_cost_test.php` (9 mutations red) |
| D2 | theme_dimensions #79 (merge 1ff26a4, 2026100550) | `renderCards()` inserts a page of cards in one `appendNodeContents()` into a holder, so core's filter event (and Boost's `enableTooltips()` listener) fires once per repaint, not once per card | `document` keydown +174 over five S1 cycles (phase 2 ledger) | `document` keydown 24 net over the whole page (load, the warm-up cycle and five cycles: 12 presses), 12 of them `enableTooltips()`: one a press (`logs/2026-10-31-phase3-d2.log`) | two Jest tests in `listing/paint.test.ts` (reverting to one insertion per card reddens one) |

The panel (`areas/panel.ts`) is left as it is: its transplant already announces one holder, and its whole render
announces the template's top-level nodes, the +2 a cycle of the lifecycle series.

## After

Every target holds on the scenarios measured, at 4x CPU, against the Targets above:

- The phone's first fold of the filter line: 40-41 ms forced (101-118 before H1); a filter press INP 80-88 ms; typing
  at 150 ms a key INP 56 ms; no load past 50 ms of blocking after the load.
- Lifecycle: S1-S3 open/close and filter cycles leave the detached DOM flat (8 -> 8 over five filter cycles); core's
  `document` keydown listener grows one a press (about 17 before D2), still unbounded and core's to fix.
- What is left is noted, not fixed: C1 + C2's one-measure rewrite (about 5 ms a phone load), C3's `placeList` per
  key, `daterange/calendar.ts`'s formatter per day, `linearfooter.js`'s scroll reads.

What went back into the template (`moodle-dev/docs/client-performance-audit/README.md`) and the fleet standards:

- Section 2.3: keys sent through WebDriver leak into the probe (send them through CDP); typed text is a burst unless
  paced (`--cadence`); forced layout is often style, and often another plugin's selector (split it, calibrate with an
  unnamed class, bisect the rules); a listener count names no target until a ledger does.
- Section 3 D: every `core/templates` insertion is a permanent core listener per announced node: one insertion per
  repaint.
- Section 4: an A/B by `--init` when the mechanism can be switched off in the page, kept as a control in the
  after-series; counts, not timings, across an upgrade.
- Fleet standards, CSS: no `:has()` whose argument reads `[class...]` or `[style...]`, pinned by a scanning test.
- Instruments: `theme_dimensions-2026-10-31/` (the series scripts, `detached.php`, `listeners.js`, the three style
  probes) and `profile.php`'s `--cadence`, `--scroll` and `--cycle` (keys sent through CDP).
