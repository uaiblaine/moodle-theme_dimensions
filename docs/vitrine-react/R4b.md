# R4b: one listing store for both shells

Status: **design note, 2026-10-29, nothing implemented.** Stage R4b of `PLAN.md` (section 6), phase 1 of 2. It waits on
the owner's answers in section 6. Line counts are `wc -l` on main `32598cd` (version 2026100536).

## 1. Inventory: what the store replaces

| Module | Lines | Role |
|---|---|---|
| `categorylisting` | 1625 | Shared by both clients: the address helpers, every painter, the three mounts. Holds no state but two document counters (`appended`, `announcements`). |
| `categoryvitrine` | 1334 | The account's controller: state, actions, requests, history, remembered state, view preference, progress, peek. |
| `categorypublic` | 974 | The visitor's controller: the same actions over the public service, the fallback, the panel's question, the posters' links. |
| `categorypanel` | 207 | The account's answers to the posters' component: the panel's request, its progress, the star's arming. |

Entry points: `course_renderer.php:187-189` arms `categoryvitrine` and `categorypanel` (`isloggedin() && !isguestuser()`);
`request.php:213` arms `categorypublic` with the category id (`publicaccess::client_enabled()`). Nothing else imports the
four. `daterange.js` (`period()`) and `categorymark` are imported only through them and the dead R4a leftovers.

### 1.1 State

One plain object per client, built in `init` from `root.dataset.vitrine` and the address:

- **In the address:** `query`, `chip`, `fav`, `filters` (`cf_<id>`), `sort`, `datefrom`/`dateto`, `cat`, `page`,
  `view` (only when `viewchosen`), `pagesize` (carried, never owned). The panel's `open`/`psort` ride along untouched
  and are dropped under a flat sort.
- **From the payload, not the address:** `categoryid`, `defaultsort`, `total`, `hasmore`; the account adds `remembered`
  (then `sort` is the payload's) and `canpersist`.
- **Document state with no address:** `lastpage` (the grid's; `page` is the address's), `seq` (the ticket), the search
  timer and its `Pending`; the account adds the remember/view timers, `rememberedsent`, the observer with
  `pending`/`settled`/`answers`, `inflight`, the peek timer and `autoopenlabel`.
- **Normalisers:** `rootOnly()` (the root reads the term alone) on both; `publicOnly()` (no chip, no favourites) on the
  visitor's.

### 1.2 The address: who writes it, and how

| Writer | Call | When |
|---|---|---|
| `categorylisting.writeUrl(state, true)` | `pushState({}, ...)` + `syncFold()` | every criterion change on both shells: term (after the 300 ms debounce), chip, field, favourites, sort inside a shell, tree, period, view, clear, chip removed |
| `categorylisting.writeUrl(state, false)` | `replaceState({}, ...)` | account `init` only, when the page was drawn from a remembered state (the address written out in full) |
| `categorypublic.load()` | `replaceState(history.state, target)` + `reload()` | the fallback to an address that differs from the current one by its fragment alone |
| `areas/panel.ts writeAddress()` | `replaceState(history.state, ...)` | the panel's `open`/`psort`, never pushed |
| account `leave()` / visitor `navigate()` | `location.assign` | sort across shells (grouped and flat); the account first remembers, racing 1,000 ms |

`popstate` has two listeners, in registration order: the client's (`readUrl`, normalise, `paintControls`, `syncFold`, the
account's `remember(false)`, the visitor's `linkPosters()`, then `requestRows(0)`), registered in `init` before the
mounts, and `Areas.tsx`'s `onPopState` (the panel alone), registered at mount. The order matters: the client's state is
the new one before the component reads the panel.

### 1.3 Requests

| Shell | Service | Called by | Rules |
|---|---|---|---|
| account | `theme_dimensions_get_category_rows` | `categoryvitrine.requestRows`, `categorypanel.ask` (`withcategories`, 8 rows, criteria read off the address, root: the term alone, legacy `chip=favourites`) | `Ajax.call` login required; failure: `Notification.exception`, `dim-vitrine:failed` |
| account | `theme_dimensions_get_card_details` | the grid's observer (batches of 24 every 100 ms, `settle()`), `categorypanel.paintProgress` (one batch per painted panel) | asked once per id, failure settles |
| account | `theme_dimensions_remember_category_state` | `remember()` (500 ms quiet, `now` on a navigation, deduplicated by JSON) | `canpersist` and not the root |
| account | `core_user_set_user_preferences` (`setUserPreference`, `theme_dimensions_categoryview`) | `setView` (500 ms quiet) | `canpersist` |
| account | `theme_dimensions_toggle_wishlist` | `categorystar` (armed by both account modules) | |
| visitor | `theme_dimensions_get_public_category_rows` | `requestRows`, `askPanel` (a public area by id, a listed one as a `slice`) | `Ajax.call([...], true, false)`, no cachekey, `lang`, one call per request; failure: the page's sentence, then the address loads |

`init` fetches nothing on either shell.

### 1.4 Painting, paging, view

- **Painters (all in `categorylisting`, both shells):** grid (`renderCards` through `core/templates`, `paintGrid` with the
  `discard` hook, `reveal` held pending), count (four regions, the zero-width announcer), chips row, groups (the scope
  split by `SCOPE`, in order, moving only what is out of place), Filters badge, favourites, eraser, dates (fields plus
  `dim-daterange:set`), tree mark, direct section, pager, view. Every painter of the line asks `drawnByLine()` and tells
  the component instead (`tellLine`).
- **Load more:** `requestRows(lastpage + 1, true)`; no address; `paintPager` closes after `PAGE_WINDOW` (10) appends and
  hands over to the continue link; `restoreMore()` before `focus()`; focus falls to the link, then the first new title.
- **View:** `paintView` (classes, `aria-pressed` or the line's state, `dim-vitrine:view`); the account adds `remember`
  and the preference. On Back to an address naming no view the view in force stays: on the account's shell that is what a
  reload of the address would draw, because the toggle wrote both the preference and the category's entry.

### 1.5 Events with the components

| Where | Names | Sent by → heard by |
|---|---|---|
| `document` | `dim-vitrine:pending` `{filtered}`, `:criteria` `{accordion, areas, filtered, total}` | both clients → `Areas` |
| `document` | `dim-vitrine:failed` | account → `Areas` (the visitor loads a page instead) |
| `document` | `dim-vitrine:view` `{view}` | `paintView` → `Areas` |
| `document` | `dim-panel:autoopened` `{id}` | `Areas` → account (the count line's note) |
| the line | `dim-line:mounted`, `:failed`, `:dropterm` / `:state`, `:answer` | `FilterLine` → listing / listing → `FilterLine` |
| the calendar region | `dim-daterange:mounted`, `:pick` / `:set` | `DateRange` → listing / listing → `DateRange` |
| the areas section | `dim-areas:mounted`, `:ask`, `:question`, `:unanswered`, `:painted` | `Areas` → the client through `mountAreas()` (question and unanswered: visitor; painted: account) |

Each name is spelled twice today (`LINE`, `CALENDAR`, `AREAS`, `EVENTS` in the AMD files and `EVENTS` in the components),
held equal by three component source tests.

### 1.6 What only the account's shell has

The progress observer and `get_card_details`, the hover peek with `closeOthers()` and the capture-phase `toggle`
listener, Escape closing the peeks, `remember()`/`leave()` and the remembered sort, the view preference,
`Notification.exception`, the `dim-js` mark, the automatic opening's note, `categorypanel`'s request and progress, and the
star. None of it may enter the visitor's graph: the three module names and the seven strings of
`categorypublic_source_test::FORBIDDEN_STRINGS`, comments included.

## 2. The store

### 2.1 Where it lives

`js/esm/src/listing/`, plain TypeScript: the store draws nothing, so it is no React component and has no mount box.

| File | Content | Visitor's graph |
|---|---|---|
| `events.ts` | every event name of 1.5, the one spelling | yes |
| `address.ts` | `PARAM`, `SCOPE`, `SORTS`, the `State` type, `buildUrl`, `readUrl`, `sortOf`, `catOf`, `period` (port of `daterange.period`), `chipOf`, `favOf`, `pageSizeOf`, `rootOnly`, `narrows`, `filterArgs`, `withPanel`, `dropCriterion`, `syncFold` | yes |
| `history.ts` | the one writer of `window.history` (section 2.3) | yes |
| `paint.ts` | every painter of 1.4, ported function for function, the two counters at module scope | yes |
| `mounts.ts` | `COMPONENTS`, `mountLine`, `mountCalendar`, `mountAreas`, `drawnByLine`, `tellLine`, `tellAnswer`, `focusTarget` | yes |
| `store.ts` | `createStore(root, shell)`: state, actions, tickets, requests, listeners, `popstate` | yes |
| `visitor.ts` | the visitor's `Shell` and `start(categoryid)` | yes (its entry) |
| `account.ts` | the account's `Shell` and `start()` | **never** |

The title marks reuse `areas/mark.ts` (`markTitles`, the port of `categorymark`). Requests use core 5.3's ES module
`@moodle/lms/core/ajax` (`fetchOne`); core's AMD `core/ajax` is already a shim over it, so the transport is the same.
Strings come from `@moodle/lms/core/stringUtils`, the language from `@moodle/lms/core/config`. The bridge (`amd.ts`)
keeps `core/templates` and `core/notification` and gains nothing: a session module named in its `Modules` type would put
that name in the visitor's graph.

### 2.2 State and actions

```ts
type Criteria = {query: string, chip: string, fav: boolean, filters: Record<string, number>, sort: string,
    datefrom: string, dateto: string, cat: number, view: 'cards' | 'list', viewchosen: boolean, pagesize: number};
type State = Criteria & {categoryid: number, defaultsort: string, remembered: boolean,
    page: number, total: number, hasmore: boolean};
```

The document state (`lastpage`, `seq`, the search timer and its `Pending`) is the store's closure, never in `State`.
Every action runs the same pipeline, in this order, which is today's: change the state, paint the controls the action
paints, `shell.changed(kind)`, write the address, request.

| Action | Address | Request | `kind` |
|---|---|---|---|
| `search(term)` after 300 ms, floor of 2 | push | page 0 | `term` |
| `choose(chip or field)`, `fav()`, `clear()`, `dropChip(param)` | push | page 0 | `filter` |
| `sort(s)` inside one shell | push | page 0 | `filter` |
| `sort(s)` across shells | `shell.leave(url)` (a load) | none | |
| `tree(cat)` | push | page 0 | `tree` |
| `period(from, to)`, a quick option | push | page 0 | `period` |
| `view(v)` | push | none | `view` |
| `more()` | none | `lastpage + 1`, appended | |
| `dropTerm()`, `erase()` | none / via `search('')` | | |
| `popstate` | none (read) | page 0 | `history` |
| `start` | `shell.start(state)` may replace | none | |

An answer is painted only while its ticket is the latest, checked again after the cards render (today's
`applyResponse`). A replaced grid of more than 48 rows is inserted in chunks of 24, each in its own frame, under one
`Pending` (`perpage` reaches 96, PLAN 5.3); fewer go in at once as today.

### 2.3 History: one writer

`history.ts` is the only module of `js/esm/src` that names `pushState` or `replaceState`, and a source test scans the
tree for it.

- `push(state)` and `replace(state)` write `buildUrl(state)` with `{}` and run `syncFold()`: the criteria.
- `replaceParams(open, psort)` is `areas/panel.ts`'s `writeAddress()`, moved here unchanged (`history.state` kept, never
  pushed). The component keeps deciding when; it stops spelling the call.
- `replaceUrl(url)` is the visitor's `load()` before its reload.
- The store's `popstate` listener goes on in `start`, before the mounts, so it still runs before `Areas.onPopState`.
- Push or replace is the action table's column, never a caller's flag: an action cannot pick the other one.

The two parameter sets stay disjoint: the store writes `open`/`psort` only by dropping them under a flat sort (as
`buildUrl` does), the component writes nothing else.

### 2.4 The shells are injected

```ts
type Shell = {
    mark: 'dim-js' | 'dim-public-js',
    normalise(state: State): void,                  // rootOnly, and publicOnly on the visitor's
    rows(args: RowsArgs): Promise<Rows>,            // the shell's own service, the visitor's with lang and no cachekey
    rowsArgs(state: State, page: number): RowsArgs,
    failed(page: number, append: boolean): void,    // account: exception and dim-vitrine:failed; visitor: fall back
    changed(kind: Kind): void,                      // account: remember(false) unless term, tree or period
    view(view: string): void,                       // account: the preference; visitor: nothing
    leave(url: URL): void,                          // account: remember(true) raced, then assign; visitor: load
    start(state: State): void,                      // account: the remembered address replaced, the observer
    answered(response: Rows, append: boolean): void,// account: progress, peek, the opening's note; visitor: linkPosters
    areas: {props: object, client: AreasClient},    // mountAreas()'s props and answers, as today
    cards?: {attach(cards: Element[]): void, discard(card: Element): void},
};
```

`visitor.ts` and `account.ts` each import `store.ts` and hand it their `Shell`; `store.ts` and everything below it import
no shell. So the visitor's walk starts at its entry, reaches `visitor.ts`, the store, the listing files, the components
and the bridge, and nothing that names `account`. The store's docblocks say "the logged-in shell", never the file.

### 2.5 Cards and panel rows

Unchanged by the store: `paint.ts`'s `renderCards` renders `theme_dimensions/category_card` through the bridge and
appends the HTML into list items the store owns and never diffs inside; panel rows stay the posters' component's
(`category_panel`, R3). The star, the film and the dialogue keep listening on `document`, so injected cards work.

### 2.6 The components keep their DOM-event contracts

The line, the calendar and the posters are separate `react_autoinit` roots that mount asynchronously; events dispatched
on their own nodes, with the listener on before the node, are what make that order safe today. A direct subscription to
the store would rewrite three tested contracts and gain nothing the events lack. What changes: every name comes from
`listing/events.ts`, which the components and the store import, so the three "held equal" assertions become one rule
(no event literal outside `events.ts`).

### 2.7 How the page mounts it

Two thin AMD entries, one function each, called by the same `js_call_amd` lines with today's arguments:

- `amd/src/listingvisitor.js`: `import {start} from 'core/esm!@moodle/lms/theme_dimensions/listing/visitor'`, `init(categoryid)`.
- `amd/src/listingaccount.js`: arms `categorystar` (AMD until R5, kept out of the ESM tree and the bridge) and calls
  `account.ts`'s `start()`. It replaces both `categoryvitrine` and `categorypanel`, so the logged-in shell loses one
  `js_call_amd`.

`core/esm!` is how core's own `core/str` and `core/ajax` reach their ES modules. Mounting through `react_autoinit`
instead would need a mount node in the templates and the arming conditions moved into them; the first paint, the
`js_call_amd` gates and `first_paint_budget_test`'s module list would all move for no gain. The template markup and the
page without JavaScript do not change at all.

### 2.8 Performance

- Nothing at load fetches; the store draws no box, so its mount cannot shift anything (CLS 0 holds by construction).
- What runs at load is the store, the line and, on the account's shell, the observer and the star, as today.
- `startTransition` is for React renders; the store renders none. The line's redraw on `dim-line:answer` is the
  component's to defer.
- Each slice reports the probe (PLAN 5.4) against R4a: the visitor's page from R4b2 on, the logged-in pages from R4b3.
  Expected: AMD modules leave the cold "scripts before load" and ES modules join; nothing else.

## 3. Size and split

### 3.1 How the count works

`mdl mdlshield size moodle-theme_dimensions-r4b` on the empty branch: **0 effective changed lines, verdict "nothing to
review"**. The estimate is additions plus deletions per file (`git diff --numstat`), minus `.mdlshield/config.yml`'s
`exclude.paths`: `docs/**`, `amd/build/**`, `**/*.min.js`, `**/*.map`, `mutations/**`, Behat features, the prose files.
So deletions of `amd/src` count in full, and **`js/esm/build/X.js` and `X.dev.js` count**: they match none of the paths.
Measured on the two last stages:

| Merge | Effective | `esm` src | `esm/build` `.dev.js` | `esm/build` `.js` | Jest | PHPUnit |
|---|---|---|---|---|---|---|
| R3 #42 | **6,198** (over) | +2,755 −1 | +1,700 | +110 −1 | +977 | +461 −36 |
| R4a #46 | **2,948** | +372 −100 | +272 −136 | +75 −55 | +738 −7 | +477 −298 |

A TypeScript line therefore costs about 1.7 effective lines with its build.

### 3.2 Estimate

The four modules hold 4,140 lines (PLAN's 3,580 was 2026-10-26's count of three of them). Ports R1-R3 grew 1.3-1.6x,
but those draw React; the store is imperative, and the two controllers' twin code (search, clear, chips, erase, tree,
period, requests, listeners: about 600 lines) becomes one. Estimate: **about 3,600 TS lines** (3,200-4,000), plus two AMD
entries of about 70; about 2,000 lines of Jest and 1,800 of PHPUnit added or rewritten; about 4,140 lines of `amd/src`
removed, and about 1,500 of source tests with them.

As one pull request that is about 15,000 effective lines with the builds, over the 12,000 hard limit. It has to be split.

### 3.3 The split

Each slice switches one consumer and leaves the code it replaces unimported, as R4a did, so every intermediate main runs
all scenarios green and every slice is mostly additions.

| Slice | Contains | Switches | Effective, builds counted / excluded |
|---|---|---|---|
| **R4b1** the listing in TS | `events.ts`, `address.ts`, `history.ts`, `paint.ts`, `mounts.ts`, a barrel `listing.ts` with `categorylisting`'s export names; the components import `events.ts`, `panel.ts` writes through `history.ts`; Jest of the pure functions and painters; source tests and 52 gates retargeted | the three AMD clients' one import line, to `core/esm!@moodle/lms/theme_dimensions/listing/listing`; `categorylisting.js` unimported | ~3,950 / ~2,700 |
| **R4b2** the store and the visitor's shell | `store.ts`, `visitor.ts`, `amd/src/listingvisitor.js`; Jest of the store over a stand-in shell and of the visitor's shell; `categorypublic_source_test` walks from the new entry; 28 + ~14 gates retargeted | `request.php`'s `js_call_amd`; `categorypublic.js` unimported | ~3,250 / ~2,450 |
| **R4b3** the account's shell | `account.ts`, `amd/src/listingaccount.js`; Jest of remember, the preference, the observer, the peek; ~11 + 6 gates retargeted | `course_renderer.php`'s two lines become one; `categoryvitrine.js`, `categorypanel.js` unimported | ~2,500 / ~2,000 |
| **R4b4** removal only | `categorylisting`, `categorypublic`, `categoryvitrine`, `categorypanel` with their builds, the barrel, every assertion still reading them, the gates left without code | | ~5,600, unreviewed (fleet rule: a removal-only slice) |

- **With `js/esm/build/**` excluded (question 1), R4b1-R4b3 fit**, R4b1 with the least margin: write its comments to
  the fleet rule, and run `mdl mdlshield size` before opening each slice.
- **Without it, R4b1 and R4b2 are over** and split once more along a seam that changes nothing at run time: the new
  modules land first with their tests and no consumer (R4b1a: `events`, `address`, `history`, `mounts`; R4b2a: `store`),
  and the switch follows with the rest (R4b1b: `paint` and the import line; R4b2b: `visitor` and the arming). Five
  reviewed slices instead of three.
- R4b1 must verify first, in a browser, that a theme AMD module reaches a theme ES module through `core/esm!` (core does
  it for its own modules; no theme module does yet). The namespace goes through babel's wildcard interop; the counters
  stay inside the ES module, one instance per page.

## 4. Tests and gates

### 4.1 Source tests

| Today | After R4b |
|---|---|
| `amd_source_test` (42 methods, `body()` falling back to `categorylisting`) | its listing and account methods move with their code: `listing_source_test` (address, history, paint, mounts, store) in R4b1-R4b2, `listing_account_source_test` in R4b3; it keeps its generic build-currency test |
| `categorypublic_source_test` (19) | stays the visitor's walk: `MODULE` becomes `listingvisitor`, `LOGGED_IN_NAMES` gains `listingaccount` and `listing/account`; its shell assertions read `visitor.ts`; the cachekey rule becomes `fetchOne(..., {loginrequired: false})` with no `cachekey` |
| `categorypanel_source_test` (26, mostly `categoryareapanel`'s) | the methods about `categorypanel` (the panel's arguments, the root's term, the star armed once, the marks) go to the account's test in R4b3; the rest stays with `categoryareapanel` until its removal |
| `filterline_`, `daterange_`, `areas_component_source_test` | the "names held equal" assertions become "no event literal outside `listing/events.ts`" |
| `category_scss_test` (Bootstrap 4 names scanned in `amd/src/category*.js`; the `dim-public-js` twin) | the scan covers `js/esm/src/listing` |
| `category_listing_markup_test` (`SCOPE` mirrored) | reads `address.ts` |
| `course_renderer_test`, `route/controller/category_test`, `first_paint_budget_test` | the armed module names: `listingvisitor` (R4b2); `listingaccount` instead of `categoryvitrine` + `categorypanel` (R4b3) |

New source-shape tests, each the PHPUnit side of a gate below: one history writer; the action table's push/replace column;
`start` registering `popstate` before the mounts; `start` fetching nothing (both shells); the pipeline order (`changed`
before the address write); the store importing no shell; the bridge naming no session module; the chunked insertion
holding one `Pending`.

### 4.2 Jest (`js/esm/tests/listing`)

`address` (round trips of `buildUrl`/`readUrl`, the remembered sort, the view only when chosen, `open`/`psort` under a
flat sort, `period`), `paint` (group order and focus, chips, the announcer, the page window, focus after load more),
`store` over a stand-in shell (push per action, replace at start, popstate then page 0, the ticket after render, the
appended page off the address, the debounce and its `Pending`, sort across shells, the chunks), `visitor` (arguments,
`lang`, the fallback load, slices, posters' links), `account` (remember's quiet, `now` and race, the flip before the
write, the preference only with `canpersist`, `settle`/`discard`, the batch cap and interval, the peek refused on an open
disclosure, the capture `toggle`). `js/esm/tests/visitor` keeps running on the visitor's markup.

### 4.3 Behat

Covered today (`theme_dimensions_category_client.feature`, 30; `..._public_client.feature`, 7): the address after a
search, a sort, a period and a typed start; Back on the account's shell; the view remembered; the organisation remembered
per category; load more with its focus and the tenth page; the visitor's in-place search, calendar, panels and tree.
Four scenarios added for the store's risks (86 showcase scenarios after R4b):

1. **Visitor, Back**: two in-place changes, Back twice; the listing, the line and the address follow (popstate on the
   visitor's shell has none today). R4b2.
2. **Visitor, load more**: the next page appended, the focus kept, the address unchanged. R4b2.
3. **Arriving on a remembered bare address writes it in full without a history entry**: Back leaves for the page before.
   R4b3.
4. **Back with a panel open**: a filter pushed while a panel is open, then Back; criteria and panel both restored (the two
   writers and the listener order). R4b3.

### 4.4 Mutation gates

111 gates name the four files: 52 `categorylisting`, 28 `categorypublic`, 25 `categoryvitrine`, 6 `categorypanel`.

- **Retargeted with their code**, in the slice that moves it: the 52 in R4b1; the 28 visitor gates and about 14 account
  gates on shared behaviour (tickets, the appended page, the page size, chips, clear, the line and calendar wiring, the
  tree, the root) in R4b2; the rest (remember, the observer, the peek, one panel, favourites in the request) and the 6
  panel gates in R4b3. Each `.pl` is rewritten for the TS spelling and each `#   ->` line still names the source-shape
  test, never `esm_source_test`'s build currency, which every TS mutation also reddens.
- **Retired as duplicates**, the twin gates on twin code: `js_line_input_delegated`/`js_public_line_input_delegated`,
  `js_line_erase_guard`/`js_public_erase_guard`, `js_line_answer_told`/`js_public_line_answer_told`,
  `js_line_before_calendar`/`js_public_line_before_calendar`, `js_clear_focus_target`/`js_public_focus_target`,
  `js_vitrine_line_armed`/`js_public_line_armed`: one gate each on `store.ts`.
- **New**: `js_history_one_writer`, `js_history_push_criteria`, `js_history_start_replaces`,
  `js_popstate_before_mounts`, `js_start_never_fetches`, `js_changed_before_write`, `js_store_names_no_shell`,
  `js_bridge_visitor_safe`, `js_visitor_nologin`, `js_view_preference_real_account`, `js_grid_chunks_pending`,
  `js_events_one_spelling`.
- Sweep each slice with `--fast --changed <its base>`, both DB families, before the commit that adds its gates; the
  dry-match of every pattern runs after each retarget, since a dead pattern stops the sweep.

## 5. Risks

| Risk | Decision |
|---|---|
| Two writers of the address | One module writes (`history.ts`), the parameter sets stay disjoint, push or replace is the action's, a test scans the tree |
| `popstate` order between the store and the posters | The store listens in `start`, before the mounts; Behat scenario 4 |
| The remembered sort | `changed()` before the write, as `remember()` sets `remembered` first today; Jest and gate `js_changed_before_write` |
| The view on Back | Parity: an address naming no view keeps the view in force (1.4) |
| A session module in the visitor's graph | Injected shells, `account.ts` reached only from its entry, the bridge unchanged, the walk extended |
| `core/esm!` from a theme AMD module | Verified in a browser in R4b1's first commit, before anything depends on it |

## 6. The owner's answers (2026-10-29)

1. **`js/esm/build/**` is excluded from MDL Shield's count**, as `amd/build/**` is (in `.mdlshield/config.yml`, on main
   before R4b1). R4b goes in three reviewed slices: R4b1, R4b2, R4b3.
2. **One removal-only pull request at the end** takes everything left unimported: R4b's four modules, R4a's four
   (`categoryline`, `categorypreview`, `categoryareapanel`, `categoryposters`), and `daterange.js` and `categorymark` once
   nothing imports them, with their builds, source tests and gates. R5 keeps the star and the CLAUDE.md rewrite.
3. **No skeleton in R4b**: the port keeps parity, with `aria-busy` on both shells. The card skeleton on a replaced grid
   comes afterwards as its own change, measured by the probe (template markup, CLS, a Behat-safe reveal).
