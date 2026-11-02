# The calendar, redesigned: proposal

Status: **Accepted** (2026-10-31): the owner took every recommendation of section 8; C0 under way. Nothing implemented. Written from three read-only surveys of
Moodle 5.3 core (`~/dev/moodle-503/public`), React calendar libraries, and this theme plus the fleet.
Every core claim carries the file it was read in; the surveys ran no code.

## 1. What the owner asked for

From the request and the reference screenshot (month grid, day panel on the right, event popover):

1. Multi-day events drawn **once**, as a bar that runs across the days (and wraps into the next week),
   instead of a copy in every day cell.
2. On desktop, clicking a busy day opens a **day drawer** beside the month; the grid stays.
3. A better control for the **event keys** (site, category, course, group, user, other).
4. Filtering by **course category** (core only filters by course), and more filters.
5. New web services if needed, measured before optimised.
6. **Adaptive density**: a day with one event shows more of it; a crowded day shows a compact summary.
7. Better modals and popovers.
8. Use the theme's course models (course, event, and future ones such as trails).

## 2. What core 5.3 gives us

### 2.1 The page

- `calendar/view.php` builds `calendar_information::create()` and calls `calendar_get_view()`
  (`calendar/lib.php:2832-3085`). That function reads events through `\core_calendar\local\api::get_events()`,
  with no limit for the month and day views. A closure then drops module events whose `cm_info->uservisible` is false
  and category events that are not `is_uservisible()`.
- The markup comes from exporters plus templates (`month_exporter`, `week_exporter`, `day_exporter`,
  `calendar_event_exporter`; `templates/month_detailed.mustache`). It does not come from the renderer:
  `core_calendar_renderer` only draws the filter selector, the event-key filter and the add button. A theme can
  override the templates, but `calendar_get_view()` is a plain function, so there is nothing to override.
- **No router route and no hook** cover calendar output or event listing.
- The JS is AMD (`calendar/amd/src/view_manager.js`, 632 lines). It refreshes by calling the monthly-view WS and
  re-rendering the template named in `data-template`.

### 2.2 Why multi-day events repeat

`week_exporter` walks every day of the month over the whole event list and keeps an event on each day it touches.
`day_exporter` then runs a full `calendar_event_exporter` for it again in that day. A 10-day event is therefore
exported ten times: icon, course summary (image and progress included), formatted description, urls.
`month_detailed.mustache` reads `underway` and `durationevents`, but **no PHP under `calendar/` sets either**
(grep checked). So the spanning layout core seems to have planned was never wired. Days of the neighbouring months
are empty padding.

### 2.3 Event keys today

- Type is implied by the columns (`raw_event_retrieval_strategy.php`):
  - user: `userid` only;
  - group: `groupid`;
  - course: `courseid` (`SITEID` is "site");
  - category: `categoryid` with `courseid = 0`;
  - module and "other" events: course events carrying `modulename` / `component`.
- The key toggle (`calendar_filter.js`) is **client-side only**: it hides items by `data-eventtype-*`.
- The persisted bitmask (`calendar_savedflt`, read by `calendar_show_event_type()`, `lib.php:1996`) is written by
  `calendar_set_event_type_display()`, **which nothing calls** (grep checked). So the keys forget themselves, and a
  hidden key still costs its events on the wire.

### 2.4 Web services (`lib/db/services.php`)

| Function | Fit for the redesign |
|---|---|
| `get_calendar_monthly_view` | Whole month as `weeks[].days[].events[]`, full event payload repeated per spanned day. It also embeds the course selector as an HTML string. No range, category subtree, group or type parameter. |
| `get_calendar_day_view` | One day, same heavy payload. |
| `get_calendar_events` | Each event once, with any time range. **Not `ajax`** (REST tokens only). Category ids are exact and match category events only. No location, course data, action or type. No `uservisible` check for system-level managers. |
| `get_calendar_event_by_id` | Good for the detail popover (ajax). |
| `get_action_events_by_timesort` / `_by_courses` | Action events only (the timeline's source). |
| `submit_create_update_form`, `update_event_start_day`, `delete_calendar_events` | Reusable as they are for create, edit, move and delete. |

### 2.5 Category filtering

- `calendar_information::create()` with a category keeps the user's courses in that category **and its direct
  children only** (`lib.php:1146`, `get_children()`). The category events use the whole subtree
  (`get_all_children_ids()`), so the two halves disagree on deeper levels.
- `{event}` has no category column for course events. Filtering by a category tree means resolving the tree to
  course ids first, then `courseid IN (...)`.

### 2.6 What no core service gives (the gaps)

- Each event once, with its span.
- Neighbouring-month days filled in.
- A category subtree.
- A server-side key filter.
- The course facts this theme cares about: model, modality, city, venue.
- A participant count.
- Overlaps.
- Telling a plain event from an action event whose action is null (the entity carries no `type`; block_compass
  re-reads the row for it).

## 3. What the theme and the fleet already have

- **Course models** are the custom field `dimensions_model` (`landingfields.php:63`; `MODEL_COURSE = 1`,
  `MODEL_EVENT = 2`). An event course has no date field of its own: its dates are the course `startdate` and
  `enddate`. It already has these, all ready for a rich event chip:
  - modality (`dimensions_modality`), `dimensions_city`, `dimensions_venue` and `dimensions_address`;
  - a date tile, a "held" badge, and the landing page with its enrol actions.
- **Trails** exist today only as competency lists inside learning plans (`local_dimensions/classes/plan_trail_cache.php`).
  Their only date is the plan's due date (core `{competency_plan}.duedate`).
- **Category colours** are in `theme_dimensions_cat`: `bgcolour` and `inkcolour`, a contrast pair that always
  travels together, inherited through `branding::colours()`. They can colour events by category without a new
  setting.
- **The React tree** (`js/esm/src`, React 19.2 from core's import map) gives a pattern for each piece:
  - the AMD bridge (`amd.ts`) and mounts (`listing/mounts.ts`);
  - an accessible grid (`daterange/DateRange.tsx`: `role=grid`, `aria-current`, live month caption, Escape and
    focus return);
  - a timezone-free date core (`daterange/calendar.ts`: `{y, m, d}` on `Date.UTC`, `weeks(month, firstday)`,
    `Intl` names). The server supplies "today" and the first weekday.
- **Taking over a page** follows the category showcase:
  - a 5.x route controller (`classes/route/controller/category.php`, `/r.php/theme_dimensions/...`);
  - a renderable and a template with a mount node;
  - `classes/external/` services.
- **block_compass** (5.3 checkout) already reads calendar events the way core's views do, and measured what it
  costs:
  - `classes/local/calendar/day.php::events()` calls `api::get_events()` over a time range, with the day view's
    uservisible filter and `SITEID` merged. This is the server core to reuse.
  - The `{event}` read costs 26 / 157 / 2,500 ms on PostgreSQL at 48 / 286 / 2,851 courses
    (`docs/adr/014-calendar-strip.md`). A forced range scan is 4.5-5.5 s on MariaDB. An override check without a
    guard walked 2.5-106 s. The course set must stay bounded, and the override check runs for module events only.
- **Other event sources in the fleet:**
  - mod_googlemeet (`googlemeet_event`), mod_attendance (`attendance`) and mod_scheduler create calendar events;
    they are the "other" key.
  - block_feedback_tracker keeps the platform's days off (`block_feedback_tracker_cday`), which block_compass
    already reads to shade non-working days.

## 4. Library: build on our own date core

Full survey in the session report; the short version:

| Option | Verdict |
|---|---|
| FullCalendar v7 (`@fullcalendar/react` 7.1.3, MIT core) | **Fallback.** JSX content hooks, no bundled CSS, Bootstrap 5 theme, spanning bars and "+N more" proven. Costs: the `temporal-polyfill` peer, a two-week-old major, a large API whose day cell we would replace anyway for adaptive density. |
| Schedule-X 4.x | MIT, but premium features. It needs Preact plus signals beside React, and month spanning / "+more" were unconfirmed. |
| react-big-calendar 1.20 | Heavy: moment, lodash, luxon and globalize among 16 dependencies. Weak a11y. |
| Toast UI | Repository archived. |
| EventCalendar (vkurko) | Svelte-compiled, so event bodies would not be React. |
| Ilamy | Requires Tailwind 4 plus Radix. |
| `@full-ui/headless-calendar` 7.1.3 | Checked its type declarations: date math and formatting only, **no layout**. It saves nothing over `calendar.ts`. |

**Recommendation: build our own** on `daterange/calendar.ts`, moved to a shared `js/esm/src/calendar/core/`.
Everything the owner listed is rendering: density, dots against detail, popovers, tokens, pt_BR, and
accessibility. That rendering is exactly what a library makes us fight. The two layout algorithms are small and
well known:

- **Month bars** (about 200 lines plus tests):
  1. Clip each event to its week row.
  2. Sort by start, then longer first, then title.
  3. Greedily give each segment the lowest slot that is free on all its columns.
  4. Draw it as a CSS-grid item (`grid-column: start / span n; grid-row: slot`) with open ends where clipped.
  5. Slots past the row's capacity become "+N" per day.
- **Time grid** (about 150 lines plus tests):
  1. Group overlapping timed events into clusters.
  2. Give each event the first free column inside its cluster; its width is 1 / columns.
  3. Optionally widen an event into free columns to its right.
  4. Its overlap count feeds the "2 overlaps" badges of the reference.

The larger cost is the keyboard and screen-reader contract: a roving tabindex over the grid, arrows,
PageUp/PageDown, a cell label carrying the count, and focus return from the drawer and the popover. It gets a
written spec before any code.

## 5. The design

### 5.1 One page, three regions

```
┌──────────────────────────── toolbar ─────────────────────────────┐
│ Today  ‹ October 2026 ›          [Keys ▾] [Category ▾] [More ▾]  Month | Week │
├──────────────────────── month grid ─────────────────┬── day drawer ──┤
│ bars span days; cells adapt to their load           │ all-day strip   │
│                                                     │ hour column     │
│                                                     │ overlaps marked │
└─────────────────────────────────────────────────────┴─────────────────┘
```

- **Desktop:**
  - Clicking a day opens the drawer on the right and narrows the grid.
  - Clicking an event opens a popover anchored to it: name, kind chip, time, place, overlap warning, course,
    actions.
  - "Open" goes to the event or activity; the primary action is the event's own (submit, attempt, join, enrol).
- **Phone:**
  - The month collapses to dots per day, with an agenda list for the selected day underneath (a sheet, not a
    drawer).
  - The popover becomes a bottom sheet.
- **Week view:** a time grid with the same overlap layout.
- **Day view:** the drawer's content, full width. On a phone the agenda covers it.

### 5.2 Adaptive density (per week row, measured once)

The grid measures each row's height with one `ResizeObserver` per grid (never per cell; filter-line lesson,
`docs/performance-audit`). From that height and the row's slot count it picks one level per cell:

| Load in the cell | Level |
|---|---|
| 1 event | **Card**: time range, name, place or course, the course image as a thumbnail when the course has one, the action button. |
| 2-3 | **Lines**: kind dot, time, name. |
| More than fit | **Summary**: the lines that fit, then "+N more", plus a dot row by key. The cell's `aria-label` says the count. |

Multi-day bars always take the top slots, in the same slot across the whole row, so a bar never zigzags.

### 5.3 Event keys and filters

- **Keys** become a row of toggle chips, each in its key colour with a count for the visible range: Site,
  Category, Course, Group, Mine (user), Activities, Other (component events: enrol, competency, googlemeet,
  attendance and the like).
- A chip off means the server is not asked for that key (unlike core).
- The state is a **user preference** of the theme, so it survives, unlike core's session-only filter. The
  preference needs the privacy provider's `export_user_preferences`.
- **Category:** a tree picker of the categories the viewer can see, "include subcategories" on by default.
  It resolves to course ids on the server.
- **More:** course model (course or event), modality, city, and "only things to do" (action events).
- **Colour by:** key (default) or category (the branding pair).

### 5.4 Course models as calendar citizens

- **Event courses become events.** A course with `dimensions_model = 2` is drawn as one bar from `startdate` to
  `enddate`, with the event chip, city or venue, modality, and the "held" state. These are virtual events, not
  `{event}` rows, so nothing is duplicated. For courses the viewer is enrolled in they always show. For courses
  the viewer could join, an **"Open events" layer** shows them too:
  - only what the showcase would list to this viewer (the same population and unlisted rules, never a convenient
    shortcut);
  - the popover's primary action is the landing page's enrol, which is the reference's "RSVP".
- **Regular courses** contribute their activities' events (core) and nothing else by default.
- **Trails (future):** a "source" seam (below) lets a trail's due dates and milestones arrive later without
  touching the grid.

### 5.5 Server: one range service plus sources

`theme_dimensions_get_calendar_range` (ajax, logged-in only):

- **Parameters:** `from`, `to` (ISO days), `keys[]`, `categoryid` plus `recursive`, `courseid`, and filters
  (`model`, `modality`, `city`, `actiononly`, `layers[]`).
- **Bounds:** the range is capped at 6 weeks plus padding, so a month grid with its neighbours' days is one call.
- **Returns, normalised:**
  - `events[]` once each: id, source, key, name, start, duration, allday, courseid, categoryid, groupid,
    module/component, location, url, action `{name, url, count, actionable}`, overdue;
  - `courses{}` once each: name, image, categoryid, model, modality, city, venue, held, landing url;
  - `categories{}` once each: name, colour pair;
  - per-day counts by key, for the chips and the phone dots.
- **No HTML and no formatted description.** The popover fetches the description lazily via core's
  `get_calendar_event_by_id`.
- **Sources** behind an interface:
  - `core_events`: `api::get_events()` with core's visibility closure, block_compass's `day.php` shape;
  - `event_courses`;
  - later `trails`.
  - Each source gets the resolved course and category sets and the keys; a source whose key is off is not asked.
- **Participant counts** ("+8 going") only for event courses, and only as a number, unless the viewer may see
  participants in that course (then avatars).

Create, edit, move and delete stay core's: `core_calendar/modal_event_form` through the AMD bridge, and
`update_event_start_day` for drag to move. Capabilities and events stay core's own.

### 5.6 Where the page lives

A route `/r.php/theme_dimensions/calendar` (the showcase's shape) linked from the theme's navigation
(`drawernav.php` already maps `calendar`). `calendar/view.php` stays as it is: the Moodle App, notification links
and the calendar blocks keep using core. Whether to redirect core's month and day views to the new page is an
owner decision (D1).

### 5.7 Core's calendar blocks (owner, 2026-11-01)

Two core blocks draw a calendar of their own on every page that carries them: `block_calendar_month` (the mini
month) and `block_calendar_upcoming` (the next events). Left alone they would look like core's calendar and show
what core shows, which is not what the theme's page shows: no event courses, no "open events" layer, the unlisted
category events the range drops, none of the viewer's keys or filters. The owner asked to redo them visually and to
apply the page's rules where a renderer can, and otherwise to explain the gap in the theme's documentation and
recommend switching the blocks off.

What the code allows (read in core 5.3):

- **Both blocks render through the `core_calendar` renderer.** Each calls `calendar_get_view()` and then
  `$this->page->get_renderer('core_calendar')->render_from_template($template, $data)`:
  - the month block uses `core_calendar/calendar_mini` and sets `$data->iscalendarblock`;
  - the upcoming block uses `core_calendar/calendar_upcoming_mini`.

  The renderer factory derives `theme_dimensions\output\core_calendar_renderer`. Neither Boost nor Boost Union has
  one, so the theme's would extend `\core_calendar_renderer` and override `render_from_template()`, guarded by the
  template name and by `iscalendarblock`. Core's own calendar page uses the same renderer, and those guards leave
  it alone.
- **A renderer that only filters `$data` holds for the first paint alone.** The month block's previous and next
  buttons run in the browser:
  1. `core_calendar/view_manager` asks `core_calendar_get_calendar_monthly_view`;
  2. it renders the template named in `data-template` with that answer.

  So the second month is core's again, whatever the first one hid.
- **So the theme replaces the blocks' content, not their data.** For those two templates the override returns the
  theme's own mount, wired with the range service (`theme_dimensions_get_calendar_range`):
  - a mini month with dots per key, today and the selected day, navigating through the range service;
  - a short agenda of the next events, with the event courses and the open layer.

  Every month the block shows then follows the page's rules and the viewer's saved keys and filters.

  Its first paint must stay visible without JavaScript. Two options; the measurement decides:
  - the server draws the next events from the same range classes;
  - the core markup stays under the mount until it mounts.

  The block's header, its region and its settings stay core's: only the content changes.
- **What a renderer cannot reach, and the documentation says so:**
  - the Moodle App, which calls core's web services directly;
  - core's own `calendar/view.php` until the D1 redirect (after C3);
  - the iCal export (`calendar/export_execute.php`);
  - any other client of core's calendar web services.

  They keep core's rules, so a category event of an unlisted category stays visible there. The theme's README and
  docs say this in a short section with the recommendation: where the difference matters, switch off the export
  and point the app's users to the web calendar.
  The section also lists `block_timeline`. It is already React, reads only action events, and stays core's.
- **A setting keeps the way back.** "Draw the calendar blocks with the theme's calendar" is on by default. Off, the
  override passes the call through untouched, and the documentation recommends removing the two blocks from the
  default block regions if a site keeps them core's but wants one set of rules.

Tests:
- the renderer factory returns the theme's class (as `myprofile_renderer_test` does);
- the two templates are replaced, core's page is not, and the setting off restores core's output;
- the mount's answer never names what the range would drop.

### 5.8 A public calendar of public categories (owner, 2026-11-01: feasibility)

The owner asked whether a visitor who is not logged in can open a calendar that shows only the events of public
course categories, either as the main page in a visitor mode or as a page of its own.

**Feasible, and best as the same page in a visitor mode**, the showcase's two-shell pattern (`visitor.ts` /
`account.ts` over one store). The facts:

- **Who is public is already decided, and fail-closed.** local_unlistedcourses'
  `category_discoverability::public_ids()` gives the categories whose own row says public, and `are_public($ids)`
  gives the effective answer: every ancestor visible, none unlisted. That takes four statements whatever the
  number of categories. The theme's anonymous category route already rests on it through
  `publicaccess::is_public()`.
- **C1's sources cannot serve a visitor as they are,** and must not be bent to:
  - `scope` is built from the viewer's courses and groups;
  - `core_events` keeps core's closure, whose `is_uservisible()` ends in `has_capability()`, which refuses user id
    0 under `forcelogin` (`accesslib.php`, as the landing page section of the theme's CLAUDE.md records).

  A visitor source of its own is short and has no viewer in it.
- **The visitor source reads one statement.** `{event}` rows with `eventtype = 'category'`, `courseid = 0`,
  `groupid = 0`, `visible = 1`, the category among the effective public ids, and the start inside the window or
  the span overlapping it. It rides core's `groupid-courseid-categoryid-visible-userid` index. Names and locations
  are formatted in the category's context with the filters off. The creator (`userid`) is never answered.
- **Optional second layer: public event courses.** Event courses (`dimensions_model = 2`) that
  `discoverability::are_public()` serves anonymously, with the public landing page as their action ("Inscrever-se"
  leads there; the landing page decides the rest). This is the visitor's version of the "open events" layer, and
  an owner decision (V1).
- **The service is the showcase visitor's twin.** `theme_dimensions_get_public_calendar_range` keeps the
  `loginrequired => false` path through `service-nologin.php` and the order of `get_public_category_rows`' eleven
  steps:
  - it refuses a logged-in session (the guest included), which gets the normal page;
  - it refuses a `cachekey`;
  - it refuses a second call in one request;
  - it forces the language it is sent and gives it back;
  - it never calls `validate_context()` and never reads `$USER`.
- **The switch is opt-in and sits beside the gate.** A setting "Public calendar" is off unless a stored `'1'`, read
  beside `publicaccess`, never inside it, as `categorypublicclient` is. The page is `noindex` unless the site is
  open to search engines.
- **The page:**
  - The same route answers a visitor when the switch is on, never through `require_login`; the landing route's
    shape decides that the visitor is refused before any lookup when the switch is off.
  - The grid, chips and density are the same components, with the key set reduced to "Categoria" and, if V1, the
    open events.
  - No saved state, no "Novo evento", no drawer action that needs a session.
  - The visitor's import walk (`categorypublic_source_test`) extends to the calendar's entry, so no session module
    is reachable.
- **Limits a renderer or a page cannot lift:**
  - **Description images.** An event description with an image is served by core's `calendar` file area, which
    calls `require_login()` under `forcelogin` (`filelib.php`, `event_description`). The visitor's popover shows
    the description as text only, or its images go through the theme's own public file route, as the landing page
    does. That is V2.
  - **No caching across requests** (the theme's D39 / OD-5): no cache of the public calendar without the owner's
    sign-off in both repositories.
  - **Other events stay hidden.** Site events, course events, group and user events are never shown to a visitor:
    core has no notion of a public course event, and the owner asked for categories.
- **A page of its own** would duplicate the grid, the keyboard contract and the density rules for a smaller key set.
  It only pays off if the public calendar should look different (an embeddable agenda for an institutional site,
  for instance). In that case it is a thin template over the same visitor service, not a second calendar.

Decisions (owner, 2026-11-01: every recommendation taken):
- **V1:** should public event courses show beside the category events? **Decided:** yes, with "Inscrever-se"
  leading to the public landing page.
- **V2:** should description images show (through the theme's file route) or should the text show alone?
  **Decided:** the text alone first.
- **V3:** should the setting default to off? **Decided:** off.

## 6. Risks and how they are held

- **Visibility parity.** The new page must never show an event core would hide, nor hide one core would show.
  A PHPUnit parity test compares, per role and month, the ids from `calendar_get_view()` with those of
  `core_events`. One deliberate difference: a category event whose category local_unlistedcourses withholds from
  the viewer is dropped (one batched `category_access` call per answer), and a narrowing to such a category is
  refused like a nonexistent id; core's calendar shows both, and the range must not name what the showcase hides.
- **Cost on large sites.** Bound the course set:
  - a category filter resolves to the viewer's enrolled courses in the tree, never to every course in it;
  - the override check is guarded to module events;
  - measure reads and payload against core's monthly WS on the compass bench data before shipping.
- **Timezones.** The server sends day boundaries in the viewer's timezone. The client stays timezone-free, as
  `calendar.ts` already is.
- **Bootstrap / 4.5:** not a concern; the theme is 5.3 only.

## 7. Stages

| Stage | Content | Exit |
|---|---|---|
| C0 | Decisions D1-D7 and a design canvas of month, drawer, popover and phone. Baseline of core's month WS: payload, reads, ms. **Done 2026-10-31, section 9.** | Owner signs the mockup. |
| C1 | Range service plus `core_events` and `event_courses` sources, parity and visibility tests, measurement. **Built 2026-11-01, uncommitted, section 10.** | Parity test green on both DBs; numbers recorded. |
| C2 | Page route, toolbar, month grid with spanning bars and adaptive density, key chips, category tree, preference persistence. | Behat smoke; a11y spec met. |
| C3 | Day drawer (desktop) and agenda sheet (phone), event popover, overlaps. | Behat smoke on desktop and phone widths. |
| C3b | Core's calendar blocks (section 5.7): `theme_dimensions\output\core_calendar_renderer` replaces the content of `block_calendar_month` and `block_calendar_upcoming` with the theme's mini month and short agenda on the range service, behind a default-on setting; a README section on what a renderer cannot reach (app, export, core's page before D1) with the recommendation to switch those off where the rules matter. | Factory and replacement tests, setting off = core's output byte for byte; Behat smoke of both blocks. |
| CV | Public calendar (section 5.8): the visitor source over public categories (plus public event courses, V1), `theme_dimensions_get_public_calendar_range` on the nologin path with the showcase visitor's step order, the opt-in setting, the same page in a visitor mode with the reduced key set, the visitor import walk extended. After C3, which brings the popover. | The anonymous refusal order pinned like `pws_*`; a non-public, unlisted or hidden category's event never answered, each with its control; the visitor walk green; Behat as a visitor. |
| C4 | Week time grid. | |
| C5 | Create and edit through core's modal, drag to move, iCal link. | |
| C6 | Trails source, when that format exists. | |

## 8. Decisions (owner, 2026-10-31: every recommendation taken)

- **D1.** Should core's `calendar/view.php` (month and day) redirect to the new page, or should both live side by
  side with the theme linking to the new one? **Decided:** side by side first, redirect after C3.
- **D2.** Should the "Open events" layer (event courses the viewer could join, with enrol as the primary action)
  be on by default? **Decided:** on, as its own chip.
- **D3.** Should participants show as a count only, or as avatars when the viewer may see participants?
  **Decided:** a count, avatars only with the capability.
- **D4.** Is the week view in the first release? **Decided:** month plus drawer first (C2-C3), week in C4.
- **D5.** Which filters go under "More"? **Decided:** model, modality, city and "only things to do".
- **D6.** What colours an event? **Decided:** its key by default, its category's branding pair on demand.
- **D7.** Build or adopt? **Decided:** build our own on `calendar.ts`; FullCalendar v7 stays the fallback.

## 9. C0 results (2026-10-31)

### 9.1 The mockup

A design canvas, private to the owner: https://claude.ai/artifact/UpKxpNmuzaMFXDiHtxExEa

| Artboard | Shows |
|---|---|
| Main (interactive) | The month with spanning bars that wrap into the next week, the neighbouring days filled in, the key chips (toggle them), the day drawer with its all-day strip, deadlines and the hour column with overlaps boxed, and the popover. |
| Density | The three levels of a day cell: card, lines, summary with "+N mais". |
| Popovers | An activity deadline, an open event course (enrol as the primary action, a count of who is enrolled), a group meeting with its overlap warning. |
| Filters | The key chips, the category tree with branding colours and "include subcategories", and "Mais filtros" (model, modality, city, only to do, colour by). |
| Phone | The month as dots and the selected day's agenda as a sheet. |

The mockup is in Brazilian Portuguese (the site's language). It invents its key colours from the theme's palette only;
C2 decides the final key tokens with the contrast tests the theme already runs for its other pairs.

### 9.2 The baseline

`probe/calbaseline_test.php`, a throwaway PHPUnit file run inside m503b's webserver container (recipe in its
docblock). It runs on PHP 8.4.24 with PostgreSQL 18.6, on the PHPUnit site's timezone (Australia/Perth), as a student.

**Data.** The student is enrolled in N courses spread over a parent category and two subcategories. Each course has,
in November 2026:
- six one-hour events;
- a 3-day and a 10-day event;
- a group event;
- two assignments with due dates.

The site adds two site events, three user events and one category event.
Timings are the median of five warm calls; "reads" is `perf_get_reads()`, which counts statements (a recordset is 3).

| | N | reads | ms | raw bytes | gzip | entries / distinct events |
|---|---|---|---|---|---|---|
| a. `get_calendar_monthly_view`, site | 5 | 123 | 254 | 2,444,424 | 103,980 | 128 / 61 |
| a. `get_calendar_monthly_view`, site | 25 | 675 | 1,197 | 11,526,295 | 477,339 | 608 / 281 |
| b. same, `categoryid` = the parent | 25 | 677 | 1,232 | 11,526,828 | 477,561 | 608 / 281 |
| c. `get_calendar_day_view`, busiest day | 5 | 12 | 30 | 213,037 | 9,972 | 11 |
| c. `get_calendar_day_view`, busiest day | 25 | 12 | 102 | 830,060 | 32,711 | 43 |
| d. `api::get_events` over 42 days + slim allowlist + course dictionary | 5 | 86 | 16 | 11,889 | 1,092 | 61 |
| d. `api::get_events` over 42 days + slim allowlist + course dictionary | 25 | 481 | 118 | 55,189 | 3,764 | 281 |

What it says:

- **One exported event weighs 25,445 bytes, and 19,385 of them are the course image.** The test courses have none,
  so `course_summary_exporter` sends `get_generated_image_for_id()`, the pattern as a data URI. The course summary
  adds 3,585 bytes. Both are repeated for every event of the course on every day it touches.
- **The month view is 11.5 MB raw (477 KB over the wire) for a student with 25 courses**, against 55 KB (3.8 KB
  gzip) for the same events, once each, with a course dictionary. The month view also takes 10x the time (1.2 s
  against 118 ms).
- **Multi-day events cost 2.2 entries per event on average** (608 for 281). The repetition grows with the span: a
  10-day event is exported ten times.
- **The category filter changes nothing on a two-level tree.** The subcategories here are direct children of the
  parent, which `calendar_information::create()` keeps. The deeper-level gap (section 2.5) stays a code read; the
  C1 parity test must cover a third level.
- **The floor (d) is not yet flat:** 86 to 481 reads for 61 to 281 events, about 1.7 statements per event. They are
  the event factory's per-event lookups (course module, course) plus the slim mapping's lazy course reads. C1 has to
  batch them (the course dictionary from one statement, the module info from `get_fast_modinfo()` per course) and
  pin the count with a budget test, as `first_paint_budget_test` does for the showcase.
- **The two paths agree on ids:** no event came back in one and not the other, so a visibility parity test between
  the range service and `calendar_get_view()` is realistic.
- The month view's `filter_selector` HTML is small (470-1,287 bytes); it matters only because it is HTML in a data
  service.

C1's targets, from these numbers:
- one call per visible month grid;
- payload within twice the floor (d);
- statements flat in the number of events and linear at most in the number of courses;
- no image or formatted text in the range answer. The course image comes once per course, as an address, never
  a data URI.

## 10. C1 results (2026-11-01)

### 10.1 What exists

Branch `calendar-c1`, uncommitted:
- the service `theme_dimensions_get_calendar_range` (`classes/external/get_calendar_range.php`): read, ajax,
  read-only session, logged-in;
- the decision layer `classes/local/calendar/`: `window`, `scope`, `keys`, `filters`, `refusal`, `range`, and the
  two sources `source/core_events` and `source/event_courses`;
- one new public method, `population::admitted()`;
- one lang string.

The layer's facts:

- **Window:** two ISO days in the viewer's timezone, at most 42. A non-Gregorian calendar type is refused, so the
  C2 page checks the type before mounting.
- **Scope:** core's own calendar sets (`calendar_get_default_courses()`, `calendar_set_filters()`), narrowed by a
  category (whole subtree by default, direct children with `recursive = false`, as core) or by one of the viewer's
  courses. A course narrowing wins over a category, as in core. Every miss is the same refusal: a nonexistent,
  hidden or unlisted category, a course that is not the viewer's, a malformed or oversized window, a guest.
  - Core's `calendar_set_filters()` writes a session key even when told to ignore filters; `scope` restores it.
  - Core's session-mode `calendar_categories` cache is read, never written. The service's session stays read-only.
- **Keys:** a key that is off spares the vault an argument only when every key that argument feeds is off. An
  empty users argument also loses group events and user overrides, and an empty groups argument loses
  group-overridden due dates. The rest is dropped after the read.
- **Event courses:**
  - the enrolled ones come from the viewer's calendar courses, which core already filtered for visibility;
  - the open ones go through `population::admitted()` over the root scope, then `access::get_next_actions()` in
    pages of 50. At most 200 candidates are read and at most 50 joinable ones are answered; `truncated` says when
    more were left.
- **Answer:**
  - events once each;
  - the course page once, in `courses[].url`;
  - the course image as an address, never a data URI;
  - participants counted over active enrolments of active, undeleted accounts;
  - categories named only where core and local_unlistedcourses let the viewer see them;
  - day counts per key.

### 10.2 Tests and gates

**PHPUnit** (`tests/local/calendar/`, `tests/local/category/admitted_test.php`,
`tests/external/get_calendar_range_test.php`, the read-only session test). The parity cases:
- **Viewers:** a student, an editing teacher and a manager. For each, the range's event ids equal
  `calendar_get_view(..., 'month')`'s, case by case.
- **Cases:**
  - a site event and a 10-day event crossing weeks;
  - a hidden course, a hidden category's event, a hidden activity, an activity restricted by availability;
  - own and other groups (separate groups), own and other users' events;
  - assignments submitted and not, completion-expected done and not.
- **Category tree:** three levels, with `recursive` both ways; `false` equals core's category month view.
- **Deliberate difference:** core keeps a category event of an unlisted category; the range drops it.

**Security read.** It found eight points, all fixed with a test and a control:
- an enrolled hidden course;
- unlisted category items and an unlisted-category oracle;
- a session-cache write under the read-only session;
- `admitted()` failing open outside its scope;
- the open cap applied before the gate;
- a narrowing error that was not the one refusal;
- suspended or deleted accounts in the participant count;
- the city comparison.

**Mutation sweep** (`mdl mutate --fast --branch MOODLE_503_STABLE`, both database families). 35 of 37 gates went
red on pgsql and on mariadb. The two that reddened nothing were second checks of facts already decided upstream,
and both are removed, each with its reason in the code:
- the showcase gate on enrolled event courses: core's enrolled-course list already drops a hidden course, and an
  active enrolment lifts every clamp;
- a second `clean_param()` on the city: both sides already arrive in the plain spelling.

**Local matrix** (`mdl ci moodle-theme_dimensions --matrix --behat --keep-logs`, on the Mac mini, working tree of
2026-11-01 without the version bump): 4/4 legs pass. Every leg has PHPUnit 1,769 tests (16 skipped) and Behat 173
scenarios (169 passed, 4 skipped = the opt-in perf probe). The static gates of the 8.4/pgsql leg all pass.

### 10.3 Numbers

Measured with the C0 probe's data shape (`range_budget_test`), against section 9.2:

| 25 courses | Core's month WS | Range service |
|---|---|---|
| Statements | 675 | 531 |
| ms | 1,197 | 125-164 |
| Raw bytes | 11,526,295 | 108,957 |
| Gzip bytes | 477,339 | 4,930 |

- At 5 courses the range costs 112 statements and about 24 ms.
- Plain events cost a flat 15-21 statements whatever their number.
- The growth is core's: mod_assign's action callbacks read about 10 statements per due date, and modinfo priming
  (one statement, at 10 courses or fewer, the size of modinfo's static cache) cannot reach them.
- `range_budget_test` holds 125 statements at 5 courses, 585 at 25, and 110,378 bytes (twice the C0 floor) at 25.

### 10.4 What the commit needs

- A `version.php` bump, which registers the service: the external tests need it on a stack, while a fresh install
  (CI) registers it anyway.
- A CHANGELOG line.
- The local matrix with Behat on the head.

MDL Shield: the owner decided (2026-11-01) on one full review at the end of the series, once the theme's calendar
is mature, instead of one per stage. Each stage keeps its own security read (the `security-diff-read` questions).

## 11. C2 plan (2026-11-01)

C2 puts the page in front of the C1 service: the route, the server-drawn shell, the month grid with spanning bars and
adaptive density, the key chips, the category tree, the "Mais filtros" panel and the saved state. The day drawer and
the popover are C3; until then a day opens core's day view and an event opens its own page.

### 11.1 Server

- **Route and script.** `classes/route/controller/calendar.php` serves `/r.php/theme_dimensions/calendar`, the
  category showcase's shape (`#[route]`, `require_login`, the guest sent to core's calendar). The script entry is
  `calendar.php`: it answers 303 to the route while the router is configured and serves the page itself otherwise,
  as `landing.php` does.
  - Query: `month=YYYY-MM` (default this month, viewer's timezone), plus the narrowing and the filters as the
    service spells them. A missing or out-of-vocabulary value is dropped, never refused, as the showcase does.
  - A viewer whose calendar type is not Gregorian is redirected to core's `calendar/view.php` (C1 refuses it).
- **The page draws its first answer.** `output\calendar_page` (a renderable) and `calendar_page.mustache` draw
  four parts:
  - the toolbar: the title, "Hoje", previous and next as real links, the month caption, the view switch with Month
    alone active;
  - the chips as a GET form;
  - the category picker and the "Mais filtros" panel;
  - the mount node.

  The page embeds the range answer for the six-week grid as the component's props, so the first paint costs no
  request. The props also carry the category tree the picker offers: the viewer's calendar categories and their
  ancestors, names and branding pairs, only what `category_access` and core let them see.
- **No JavaScript.** The template draws the month's items as a plain agenda grouped by day, under a notice linking
  core's calendar. The client replaces it on mount.
- **The saved state** is one user preference, `theme_dimensions_calendar`: the keys that are off, the category, the
  subtree flag, model, modality, city and "only things to do".
  - It is written by a new service, `theme_dimensions_remember_calendar_state` (write, ajax), which re-validates
    the vocabulary as `remembered::record()` does for the showcase.
  - It is read on every page load; an address that names anything wins over it, as on the showcase.
  - It is declared, exported and deleted in the privacy provider, the seventh preference.
- **Navigation (D1).** The theme's drawer entry for the calendar (`classes/local/drawernav.php`) points to the new
  page; core's page stays reachable from it ("Calendário clássico") until C3's redirect decision.

### 11.2 Client (`js/esm/src/calendar/`)

- `core/` reuses `daterange/calendar.ts`'s timezone-free date maths (`weeks()`, `weekdays()`, `say()`). It is
  imported, never copied; whether the file moves is the implementer's call, as long as `daterange` and its source
  tests stay green.
- `layout.ts`, pure and Jest-tested, holds three things:
  - **The bar slots** (section 4): clip per week row; sort by start, then longer first, then title; take the
    lowest free slot; the hidden slots become "+N" per day.
  - **The density level per cell**: card for one item, lines, summary. It comes from the row height, measured by
    one `ResizeObserver` per grid, never per cell, with no layout read on the mount path (the filter-line lesson).
  - **The day counts** for the aria labels.
- **Components:**
  - `Calendar.tsx` (state, fetch, history);
  - `MonthGrid.tsx`, `WeekRow.tsx`, `DayCell.tsx`, `Bar.tsx`;
  - `KeyChips.tsx`, `CategoryPicker.tsx` (a tree with search and "include subcategories"), `MoreFilters.tsx`.

  Mounted the showcase's way (`mounts.ts` pattern, core's `react_autoinit`); AMD only through `amd.ts`. The service
  is called with `core/ajax`.
- **Behaviour:**
  - The latest request wins: a ticket checked after every await, and a Pending token from the request to the
    painted grid.
  - Month navigation writes the address (`history.pushState`, one writer).
  - A change of keys or filters is remembered after a quiet, never on every press.
- **The look:**
  - The mockup's (C0 canvas), on the theme's tokens.
  - The key colours are tokens `--dim-cal-<key>-{tint,edge,ink}` declared on `body.dim-calendar` with dark
    counterparts. A contrast test computes every pair: ink on tint at least 4.5:1, edge on the card surface at
    least 3:1.
  - "Colour by category" uses the branding pair as it travels, ink with its ground.
  - Nothing in the sheet is `:has()` keyed on a shared attribute. No BS5-only class needs a polyfill (5.3 only).

### 11.3 Keyboard and screen reader (the spec, before code)

- The grid is `role="grid"` with a `columnheader` row. Each day is a `gridcell` holding one day button, with a
  roving tabindex: one stop in the Tab order, on the selected day, today by default.
- **Keys:**
  - arrows move by day or by week;
  - Home and End go to the week's first and last day;
  - PageUp and PageDown go to the previous and next month (fetched; the focus lands on the same day number or the
    month's last);
  - Enter opens the day: core's day view in C2, the drawer in C3;
  - F2 or Enter on a day that has items moves into them; Tab cycles them; Escape goes back to the day.
- **Labels:**
  - The day button's label is the long date, the item count, and "hoje" or "feriado" when they apply.
  - A bar is a button whose label carries its title and its whole span ("10 a 12 de novembro").
  - "+N mais" names the day it opens.
- **Announcements:** the month caption is `aria-live="polite"`; a chip is a toggle button with `aria-pressed`; the
  category tree is `role="tree"` with `aria-expanded` and `aria-selected`.
- **Motion:** none beyond the theme's motion tokens, and none under `prefers-reduced-motion`.

### 11.4 Tests and gates

- **PHPUnit:**
  - the route and the script, including the guest, a non-Gregorian viewer and the dropped vocabulary;
  - the renderable's context, with the embedded answer equal to `range::answer()`;
  - the preference service: the vocabulary, a stale category, and that it writes nothing else;
  - the privacy export and deletion of the new preference;
  - the drawer link;
  - the contrast pairs;
  - an import-graph test that the component reaches AMD only through the bridge.
- **Jest:** `layout.ts` (slots, wrap across weeks, clipping marks, "+N", density per row height) and the
  components' keyboard contract.
- **Behat** (`@theme_dimensions_calendar`), on a student with a 10-day event and a key of each kind:
  - the event appears once as a bar;
  - a chip hides its key;
  - the category narrows;
  - the state survives a reload;
  - the month moves with the keyboard;
  - the no-JavaScript agenda lists the month.
- **Gates** for every guard, swept on both database families before the commit.

## 12. C2 results (2026-11-01)

Branch `calendar-c2`, built from section 11.

**Server:**
- `calendar.php` and `classes/route/controller/calendar.php`;
- `classes/local/calendar/{page,state,tree}.php` (the one decision, the saved state's vocabulary, the category
  tree);
- `classes/output/calendar_page.php` with `templates/calendar_page.mustache`;
- `classes/external/remember_calendar_state.php`;
- `scss/calendar.scss`;
- the seventh preference in the privacy provider and the uninstall;
- the drawer's calendar row in `classes/local/drawernav.php`.

**Client:** `js/esm/src/calendar/` (18 sources).

**Choices the plan left open:**
- **The view switch** draws Month only, as a current marker: a disabled Week or Day cannot take the focus, so its
  "coming soon" would be unreachable.
- **Keys.** Enter opens the day; F2 moves into its items.
- **Day links** use core's `cal_d`/`cal_m`/`cal_y`, so neither side computes a timestamp.
- **Timezone.** Items are placed in the viewer's timezone through `Intl`, never the browser's.
- **Bars.** An item is a bar when it spans more than one day or is an event course.
- **The saved state** also holds the colouring choice; a stale category is stored as none.
- **"Mais filtros"** applies each change at once and remembers after an 800 ms quiet.
- **The calendar has its own single history writer**, `calendar/history.ts`.
- **The colour tokens** are `--dim-cal-*`, the theme's component prefix. A first version used `--dimensions-*`,
  which only `post.scss` may declare, and `family_tokens_test` caught it.

**Security read:**
- no blocking finding;
- three test gaps closed: the uninstall of the preference, the script entry's forward without a lookup, and the
  service's login and context gates.

**Gates:**
- **Mutation sweep:** the C2 block's 27 gates went red on pgsql and mariadb
  (`mdl mutate --fast --branch MOODLE_503_STABLE --only <C2 gates>`, exit 0 on both).
- **Local matrix:** `mdl ci moodle-theme_dimensions --matrix --behat --keep-logs` passed 4/4 legs. Every leg ran
  PHPUnit 1,817 tests (16 skipped) and Behat 179 scenarios (175 passed, 4 skipped: the perf probe). The six new
  calendar scenarios are among the passed.

**Not verified:** the page by eye. Claude does not type a password into a login page, so the look rests on Behat
and on the owner's review. Until a stack runs the bumped version, the live page's remember call has no registered
service.

**Deferred to C3:** the drawer, the popover, the phone's dots and agenda sheet, the overlaps, and "feriado" in the
day label (no holiday source yet).

## 13. C3 plan (2026-11-01)

C3 replaces C2's two seams: a day opens the **day drawer** (desktop) or the **agenda sheet** (phone) instead of
core's day view, and an item opens the **event popover** (a bottom sheet on a phone) instead of its own page. Both
seams keep their `href`, so a middle click, a copied link and the no-JavaScript agenda still lead where C2 led.
Holidays stay deferred: no holiday source exists, and "feriado" is not drawn until one does. Core's redirect (D1)
comes as its own small change after C3 merges, so this stage leaves `calendar/view.php` alone.

### 13.1 Server

The answer already carries the whole grid, so the drawer and the sheet need no new service. Two small additions:

- **The open day travels in the address.** `day=YYYY-MM-DD`, read by `page` and kept only when it falls inside the
  month's grid window (`page::grid()`); anything else is dropped, never refused, like every other value. The script
  entry's forward carries it. It is not part of the saved state: a reload reopens the drawer, a new visit does not.
- **The popover's description** comes lazily from core's `core_calendar_get_calendar_event_by_id` (ajax, core's own
  visibility check through the event vault and `calendar_view_event_allowed()`), only for `source = core` items,
  only when the popover opens, one request per event per page life (a cache in the client). Event courses have no
  `{event}` row: their popover draws the answer's course facts and no description.

### 13.2 Client

- **`overlap.ts`** (pure, Jest-tested): for a day, the timed items (not all-day, duration above zero) laid out in
  columns: sort by start, then longer first; an item takes the first column whose last item ended at or before its
  start; a cluster of mutually overlapping items shares one column count. It also answers, per item, the other
  items whose interval intersects its own. A point item (a deadline, duration 0) never overlaps anything. All-day
  and multi-day items go to the all-day strip and never count as overlaps.
- **`DayDrawer.tsx`** (desktop, container width at or above the grid's phone breakpoint):
  - a `<section role="region">` beside the grid, labelled by its heading (the long date), which narrows the grid;
    not modal: the grid stays usable, and moving the selected day with the keyboard while the drawer is open
    changes the drawer's day (its heading is `aria-live="polite"`);
  - an all-day strip (all-day items, multi-day bars that cover the day, event courses), then an hour column with
    the overlap layout, the earliest item scrolled into view, then "no items" when the day is empty;
  - a close button; Escape closes it and gives the focus back to the day; the address gains and loses `day=`
    through the calendar's one history writer (`history.ts`, `replaceState` for a day change, so Back leaves the
    month rather than walking the days).
- **Phone** (container width below the breakpoint, measured by the grid's existing `ResizeObserver`, never the
  viewport): the month draws one dot row per day (one dot per key with items, from `days[].counts`, at most four,
  then "+"), and the selected day's agenda is a sheet under the grid with the same content as the drawer, as a list
  (no hour column). The day button's label still carries the count.
- **`EventPopover.tsx`**, one at a time, anchored to the bar or line that opened it (flipped to stay inside the
  viewport; a bottom sheet on a phone):
  - `role="dialog"`, labelled by the item's name, non-modal: the focus moves to its heading on open; Escape, the
    close button or a press outside close it and give the focus back to the item; Tab past its last control closes
    it and moves on;
  - content: the name; the kind chip (the key's colour and name); the time range in the viewer's timezone (`Intl`,
    as C2 places items), "all day" or the span for multi-day items; the place (`location`, or for an event course
    its venue and city); the modality; an **overlap warning** naming the other timed items it intersects; the course
    (a link to its page); the description (a placeholder while it loads, nothing if core refuses or it is empty);
    for an event course, the participant count (D3: a number; avatars wait for a capability-checked source);
  - actions: the primary is the item's own (core's `action` when `actionable`, with its name; for an event course
    the viewer may join, `join`; else "Open", the item's `itemHref()`); "Open" stays as a secondary link when the
    primary is something else; the course's page is the third.
  - The description is core's formatted HTML (`format_text` output from core's exporter), drawn as trusted server
    HTML in one place, with a comment saying where it comes from.
- **Behaviour**: the description request takes a Pending token and a ticket (the latest open popover wins; closing
  before the answer drops it); the drawer and the popover close on a month change; nothing measures layout on the
  mount path.
- **Look**: the drawer, the sheet, the popover and the dots on the `--dim-cal-*` tokens, light and dark; the
  popover's surface reads the theme's elevation tokens through the fallback chain; no `:has()` on a shared attribute.

### 13.3 Keyboard and screen reader (changes to 11.3)

- Enter on a day opens the drawer (desktop) or the sheet (phone) and moves the focus to its heading; Escape returns
  it to the day.
- Enter or Space on a bar or a line opens the popover; Escape returns to the opener.
- The overlap warning is text in the popover, not colour alone; the drawer marks an overlapping item with a visible
  label as well as its column.

### 13.4 Tests and gates

- **PHPUnit:** `day=` kept inside the window and dropped outside it, malformed, or on another month; the forward
  carries it; the template draws the drawer's server shell closed and the agenda unchanged.
- **Jest:** `overlap.ts` (columns, clusters, touching ends, point items, all-day exclusion); the drawer's and the
  popover's keyboard contract; the popover's primary action per item shape (action event, event course to join,
  plain event); the description request (cache, latest wins, refusal draws nothing); the phone dots from counts.
- **Behat** (`@theme_dimensions_calendar`):
  - desktop: a day opens the drawer listing its items and the address keeps `day=` through a reload; an event
    opens the popover with its name and its description, Escape closes it; two overlapping events show the warning;
  - phone width: the month shows dots, a day opens the agenda sheet, an event opens the popover as a sheet.
- **Gates** for every guard (the `day=` window, the description's latest-wins and cache, the overlap rule's
  boundaries, the primary-action choice), swept on both database families before the commit.

## 14. C3 results (2026-11-01)

Branch `calendar-c3`, built from section 13.

**Server:** `page::PARAM_DAY` and `page::day_from_request()` (the `day=` value, kept inside `page::grid()`'s window,
dropped otherwise, carried by the script entry's forward); `calendar_page` passes the day and seventeen strings.

**Client:** `overlap.ts`, `DayDrawer.tsx`, `EventPopover.tsx` and `description.ts` are new; `Calendar.tsx`,
`MonthGrid.tsx`, `WeekRow.tsx`, `DayCell.tsx`, `Bar.tsx`, `model.ts`, `layout.ts`, `days.ts` and `strings.ts` changed.

**Where the build left the plan:**
- **The phone breakpoint** is read from the frame the grid shares with the drawer, not from the grid itself: the
  grid's own width would cross the breakpoint as soon as the drawer narrowed it.
- **The hour column has no inner scroll.** Its rows run from the first item's hour to the last one's end
  (`overlap.hourWindow()`, at least four hours, inside the day), so every item is visible without scrolling, and
  "the earliest item scrolled into view" is no longer needed. A timed item is at least one row tall: its mark and
  name on the first line, its time on the second; the place is left to the popover.
- **The popover takes the focus once it is placed** (anchored, or drawn as a sheet), never while it is still
  hidden: a browser refuses `focus()` on a hidden element, which jsdom does not model, so Jest passed and Behat
  failed until this changed. The `cal_js_pop_focus_shown` gate holds it.
- **Answer addresses go through `model.safeHref()`** (http(s) or relative, else a fallback) before any `href`:
  the item's own URL, core's action URL and the course URL. The security read asked for it.
- **The description** is the calendar's one trusted HTML sink, in `EventPopover.tsx`, fed only by
  `description.ts`.

**Security read:** two findings, both fixed before the gates (M1: an answer URL reached an `href` unchecked; M2: no
test pinned the trusted HTML sink as the only one, fed only by core's description); no blocking finding left.

**Gates:**
- **Mutation sweep:** the C3 block's 18 gates and C2's re-pointed `cal_js_history_push`, 19 in all, went red on
  pgsql and mariadb (`mdl mutate --fast --branch MOODLE_503_STABLE --only <19 gates>`, exit 0 on both).
- **Local matrix:** `mdl ci moodle-theme_dimensions --matrix --behat --keep-logs` passed 4/4 legs. Every leg ran
  PHPUnit 1,848 tests (16 skipped) and Behat 183 scenarios (179 passed, 4 skipped: the perf probe); Jest 305. The
  four new calendar scenarios (drawer and reload, popover and Escape, overlap, phone) are among the passed.

**Not verified:** the page by eye on a stack (no password typed into a login page); the layout was measured in a
static page built from the compiled theme CSS, and the rest rests on Behat and on the owner's review.

**Left for later:** holidays ("feriado", no source yet); core's redirect (D1) as its own small change; C3b, CV,
C4-C6.

## 15. C3r plan: core's month and day views lead to the page (decision D1, 2026-11-01)

D1 put the page beside core's calendar until C3 brought the drawer and the popover; C3 is merged, so core's
`calendar/view.php` now sends the reader of its month and day views to the page. Core's other views, the course
calendar and core's event editing stay where they are, and the page's "Classic calendar" link still reaches core's
page, since creating and editing events stays core's until C5.

### 15.1 What is sent, and what is not

The decision is one pure class, `classes/local/calendar/coreview.php`, called from the theme's existing
`after_config` callback (`classes/local/hook/after_config.php`) on `$SCRIPT === '/calendar/view.php'`. after_config is
the only moment before core's view does its work (calendar_get_view(), ~1.2 s on the C0 baseline). The callback
decides inside a try and redirects outside it, as its other branches do. A request is sent to the page only when
all of these hold, in this order (each one a reason core's page must keep answering):

1. **The setting is on, and the site runs this theme.** `theme_dimensions/calendarcoreview`, an
   `admin_setting_configcheckbox` that ships on, read off only as an explicit `'0'` (fleet rule), is the site's way
   back. `$CFG->theme` must be `dimensions` (as `flavourcss\planner` requires): the page is drawn by this theme's CSS
   and client, so a site that has the theme installed but runs another keeps core's calendar.
2. **`view` is `month` or `day`.** Core's default view is `upcoming` (no `view` parameter), which stays core's, as do
   `upcoming_mini` and any other value.
3. **The course is the site.** `course` read with core's default, `SITEID`; a course calendar stays core's (the page
   has no course scope), and so does an explicit `course=0`, which core refuses.
4. **A real account.** Logged in and not the guest: the page sends a visitor to the login page and the guest to core's
   calendar ({@see page::handle()} steps 1-2), so sending either there would loop. A visitor stays on core's page,
   whose own `require_login()` (with guest auto-login) decides.
5. **The Gregorian calendar.** The page sends any other calendar type back to core's (`window::require_gregorian()`),
   so the same check stands here, for the same reason.
6. **Not "classic for this session".** Opening core's calendar from the page's "Classic calendar" link carries
   `classic=1`; that request is never sent, and for a real account it sets `$SESSION->theme_dimensions_calendar_classic`
   to the account's id. While the flag holds the requesting account's id, core's calendar answers (the session
   survives a login, so a flag another account or a visitor left counts for no one): its own month arrows (AJAX, which push `view.php` addresses without the marker), its
   view switch and a reload all stay there. Opening the theme's page clears the flag (`page::handle()`, after
   `require_login()`). The flag is navigation, like `day=`: never saved, gone with the session.

The target carries what core's address meant, in the page's vocabulary:
- **the month and the day.** `cal_d`, `cal_m` and `cal_y`, when all three form a valid date (core's own rule,
  `checkdate()`), give them as written (core reads them as Gregorian); otherwise `time` (a timestamp, the current time
  when absent) is read in the viewer's timezone (`core_date::get_user_timezone_object()`). The month view sends
  `month=YYYY-MM`; the day view sends `month` and `day=YYYY-MM-DD`, so the drawer opens on that day.
- **the category.** Core reads `category` (and writes `categoryid` into its own page address, which it then ignores);
  either is forwarded as the page's `categoryid`, and the page drops one the range refuses, as it does every value.
- nothing else: `lookahead`, `course=SITEID` and unknown parameters are dropped.

The redirect is a 303 through `redirect()`, never a 301, for the reason `calendar.php` gives.

### 15.2 The page's side

- `page::classic()` and the client's `monthHref()` add `classic=1`.
- `page::handle()` clears the session flag once the reader is a real account on the page.
- The setting and its strings (`calendarcoreview`, `calendarcoreview_desc`, en and pt_br) go on the calendar's
  settings tab; there is none yet, so it goes beside the drawer's calendar row setting if one exists, else on the
  tab that holds the drawer's settings.

### 15.3 Tests and gates

- **PHPUnit** (`tests/local/calendar/coreview_test.php`), each refusal with its control: month and day sent with the
  month, the day and the category; `upcoming`, an absent `view`, a course calendar, a visitor, the guest, a
  non-Gregorian type (the `test_calendar_type` core ships for tests, if available), the session flag and the setting
  off each kept on core's page, beside the same request sent once the one reason is gone; `classic=1` sets the flag
  and is not sent; the page clears the flag; `time` read in the viewer's timezone across midnight (a viewer in a
  timezone where the UTC date differs); an invalid `cal_*` date falls back to `time`; `page::classic()` carries the
  marker, and a request built from it is never sent (the loop guard).
- **Jest:** `monthHref()` carries `classic=1`.
- **Behat:** core's month view lands on the page; the day view opens the drawer on that day; the page's "Classic
  calendar" reaches core's calendar and a reload stays there; the setting off leaves core's page.
- **Gates** for every guard of 15.1, swept on both database families before the commit.
- **Tier 2** (PHP and JS, no SQL, nothing PHP-version-specific): one full leg with Behat
  (`mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE --behat`), plus `security-diff-read`, since this is a
  redirect decided from session state.

## 16. C3r results (2026-11-01)

Branch `calendar-c3r`, built from section 15 (whose text already carries the security read's changes).

**What exists:** `classes/local/calendar/coreview.php` (the decision, `account()`, `gregorian()`, `enabled()`); a
`/calendar/view.php` branch in `classes/local/hook/after_config.php`; the `classic=1` marker on `page::classic()` and
on the client's `monthHref()` and `dayHref()`; the flag cleared in `page::handle()`; the setting `calendarcoreview`
beside the drawer's settings, with its strings.

**Where the build left the plan:**
- **`dayHref()` carries the marker too.** The drawer's "this day in the classic calendar" link opens core's day
  view, which would otherwise come straight back to the page.
- **Gates are `cal_redirect_*`** (`cal_core_*` was taken by the event source's), 21 of them.

**Security read:** no blocking finding. Fixed:
- the flag is the account's id, written only for a real account and counted only for that account (the session
  survives a login, so a visitor's or another account's flag decided for the next reader);
- the account and the calendar type moved out of the callback into `coreview::account()` and `coreview::gregorian()`,
  so a guest counted as real (a redirect loop) reddens a test;
- the redirect needs the site's theme to be this one;
- `course` is read with core's default (`SITEID`), so an explicit `course=0` stays core's;
- a five-digit `cal_y` falls back to `time`.

Kept as a note: `classic=1` changes the session on a GET without `sesskey`; a third party's link can at most switch
the redirect off for that reader's session.

**The sweep found two gaps the tests had:**
- `setUser()` starts an empty session, so the test of the page clearing the flag passed with the clear deleted; it
  now sets the flag after `setUser()`.
- An absent flag read as `0` matched the visitor's account `0`, so the real-account guard could be deleted unseen; an
  absent flag now matches no account.

**Gates:**
- **Mutation sweep:** the 21 `cal_redirect_*` gates went red on pgsql and mariadb
  (`mdl mutate --fast --branch MOODLE_503_STABLE --only <21 gates>`, exit 0 on both).
- **Tier 2:** `mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE --behat` passed every step. Counts are in
  the pull request.

**Not verified:** the redirect by eye on a stack; the Moodle App, which calls core's web services and never opens
`view.php`.

## 17. C3b plan (2026-11-01)

C3b draws core's two calendar blocks with the theme's calendar (section 5.7): `block_calendar_month` becomes a mini
month on the range, `block_calendar_upcoming` a short agenda on the range. The block's header, region, settings and
footer stay core's; only the content changes. Decisions settled by the owner (2026-11-01), written here so they are
not reopened: a renderer, four conditions, a server-drawn first paint, the page's saved state, core's upcoming
preferences, and the range's answer as the only source.

### 17.1 Server

- **What core really passes (read in core 5.3).** Both blocks call `calendar_get_view()` and then
  `$this->page->get_renderer('core_calendar')->render_from_template($template, $data)`:
  - the month block asks for the view `monthblock`, and `calendar_get_view()` names the template
    `core_calendar/calendar_month` for every month view but `mini` and `minithree` (the only two that get
    `core_calendar/calendar_mini`); the block then sets `$data->iscalendarblock = true`. Core's own month page renders
    the same template without the flag (`core_calendar/view_manager` adds it only in the browser, for the block's own
    arrows), so **the guard is `core_calendar/calendar_month` with `iscalendarblock` set**, not `calendar_mini`, which
    no block renders. Section 5.7 named the wrong template; the flag is what keeps core's page out.
  - the upcoming block asks for `upcoming_mini`, rendered as `core_calendar/calendar_upcoming_mini` (which includes
    the partial `core_calendar/upcoming_mini`; only the outer name reaches the renderer).
  - The month block's `start_layout()`/`complete_layout()` wrapper (`div.maincalendar[data-region=calendar]`) and its
    footer (`core_calendar/footer_options`: "Full calendar", and the export and subscription links) are other calls
    and stay core's; so does the upcoming block's "Go to calendar..." footer, written with `html_writer` in the block.
- **The renderer.** `classes/output/core_calendar_renderer.php`, `theme_dimensions\output\core_calendar_renderer
  extends \core_calendar_renderer`, derived by the renderer factory (neither Boost nor Boost Union has one). It
  overrides `render_from_template()` alone: it asks `local\calendar\blocks::kind()` which block the call is (month,
  upcoming or none) and `blocks::applies()` whether the theme answers; any miss is `parent::render_from_template()`
  with the same arguments, untouched. The decision is pure, like `coreview::target()`: every input is an argument.
- **When the theme's content replaces core's** (each refusal is a reason core's output stays):
  1. the call is one of the two blocks (the template name, and for the month the flag);
  2. the setting `theme_dimensions/calendarblocks` is on: an `admin_setting_configcheckbox` that ships on and is read
     as off only for an explicit `'0'`, beside `calendarcoreview`, with en and pt_br strings;
  3. the page's course is the site (`$this->page->course->id`, which the block reads too): a course's block shows that
     course's events, and the range has no course scope, the same reason C3r leaves course calendars to core;
  4. the viewer is a real account, `coreview::account()` (a visitor and the guest keep core's);
  5. the calendar type is the Gregorian, `coreview::gregorian()` (the range refuses any other).
- **The data.** The saved state as the page reads it for a bare address (`state::saved()`), its category through
  `page::scope_for()` (a category the range refuses is dropped, never named), then `range::answer()`, the page's own
  orchestration. Nothing new reaches the database: no SQL is added. Core's `calendar_get_view()` has already run when
  the renderer is called (the block calls it first) and its data is discarded; the renderer cannot avoid that cost,
  and does not try. A category page's block is narrowed by core to that category; the theme's reads the saved state,
  as the page does for a bare address: the page's rules are the viewer's, not the page's.
- **The mini month** (`output\calendar_block_month`, `templates/calendar_block_month.mustache` around the partial
  `calendar_block_month_body.mustache`):
  - the month of now in the viewer's timezone, laid out as `page::grid()` lays it (whole weeks on the site's first
    weekday), the neighbours' days marked as outside the month;
  - each day a link to the page with `month=` (the day's own month) and `day=`, which opens the drawer; today marked
    (`aria-current="date"`); dots per key from the answer's `days[].counts` in the keys' order, at most four, then
    "+" (`blocks::dots()`, the PHP twin of `layout.ts`'s `dayDots()`);
  - the caption a link to the page's month; previous and next links to the page's adjacent months;
  - the links are bare (`month`, `day`): the page reads the saved state for a bare address, which is the state the
    block drew.
- **The short agenda** (`output\calendar_block_upcoming`, `templates/calendar_block_upcoming.mustache`):
  - core's upcoming window, read as core reads it (`calendar_get_view()`, view `upcoming_mini`): from the start of the
    current hour in the viewer's timezone to `calendar_lookahead` days later, less a second; the lookahead is the
    preference `calendar_lookahead` over the site's `$CFG->calendar_lookahead` over `CALENDAR_DEFAULT_UPCOMING_LOOKAHEAD`
    (21), the count the preference `calendar_maxevents` over `$CFG->calendar_maxevents` over
    `CALENDAR_DEFAULT_UPCOMING_MAXEVENTS` (10) (`blocks::lookahead()`, `blocks::maxevents()`);
  - an item is kept as core's event vault keeps one with its duration (`timestart >= from` or `timestart + duration >
    from`, and `timestart <= to`), so an ongoing event stays; sorted by start, then source and id; the first
    `maxevents`;
  - the range answers at most 42 days per window, and a reader's lookahead may be a year: the window is walked in
    42-day pieces, an item seen twice kept once, and the walk stops as soon as a piece leaves `maxevents` items (every
    later piece only adds items that start later);
  - each item links where the page's items link: its own address, else its course's page, else the site's home
    (`calendar_page::item_url()`'s rule, `model.ts`'s `itemHref()`), every address checked to be http(s) first
    (`blocks::safe_url()`, the twin of `safeHref()`);
  - it ends with "Full calendar", a link to the page.
- **Never answer what the range drops.** Both blocks are built from `range::answer()` alone; nothing of core's `$data`
  reaches the theme's markup.

### 17.2 Client

- **The mini month's arrows** go through the range service in a small client, `js/esm/src/calendar/block.ts`, armed by
  `amd/src/calendarblock.js` (the `js_call_amd` bridge, as `listingaccount.js` is) on a page whose month block the
  theme drew:
  - one delegated click listener per block root; a modified click and a middle click follow the link;
  - the month after or before, its grid (`days.ts`'s `gridWeeks()`), `fetchOne()` of
    `theme_dimensions_get_calendar_range` with the block's state (`model.ts`'s `rangeArgs()`, and `emptyAnswer()` when
    every key is off), the context built in `block.ts` (dots by `layout.ts`'s `dayDots()`, labels from `strings.ts`'s
    `fill()`), rendered with `core/templates` (through `amd.ts`) from the same partial the server drew;
  - the latest press wins (a ticket checked after each await) and a Pending token spans the request to the painted
    month; a refusal or a failure follows the link the arrow already is.
- **Without JavaScript** every link works: the arrows open the page's adjacent month, a day opens the page's drawer.
- **The short agenda has no client**: nothing in it moves.
- The look: `scss/calendar.scss`, a `.dim-calblock` scope beside `body.dim-calendar`; the key tokens declared on both
  roots (light and dark), the dots on the key classes; no `:has()`, no `!important`, no literal colour.

### 17.3 Keyboard and screen reader

- The mini month is a table: a caption-less `thead` of weekday abbreviations (`abbr` with the full name), one link
  per day whose accessible name is the long date, the item count and "today" (the page's `calendar_daylabel`); the
  dots are `aria-hidden`.
- With JavaScript the grid has one Tab stop (today when it is shown, else the 1st), arrows move by day and week,
  Home and End to the week's ends, PageUp and PageDown to the previous and next month (fetched, the focus on the same
  day number or the month's last); Enter follows the link. Without it every day is a link in the Tab order.
- The month caption is announced through a polite live region outside the painted body after a move.
- The agenda is a list: each item a link with its name, the time as text beside it, the key's dot `aria-hidden` and
  the key's name for screen readers.

### 17.4 Tests and gates

- **PHPUnit** (`tests/output/core_calendar_renderer_test.php`, `tests/local/calendar/blocks_test.php`):
  - the factory returns the theme's renderer (as `myprofile_renderer_test` does);
  - `kind()`: the month template with and without the flag, the upcoming template, any other name;
  - `applies()`: each condition of 17.1 refused beside its control (the same call, accepted once the one reason is
    gone);
  - through the renderer: both blocks replaced; core's month page (the template without the flag) and every refusal
    left as core's output byte for byte (the uniqid core draws per render aside);
  - the dropped item: a category event of an unlisted category is in neither block, beside a listed category's
    event in both (the saved state narrowed to their parent, as the range's own test narrows);
  - the saved state: a key off drops its items from both blocks, beside the default;
  - lookahead and maxevents: core's defaults, the site's settings and the reader's preferences, each pinned by an
    item just inside and one just outside, and the agenda's count capped; a lookahead past 42 days is walked;
  - the template draws the month's links (`day=`, the adjacent months) and the agenda's "Full calendar".
- **Jest** (`js/esm/tests/calendar/block.test.ts`): the context built from an answer (dots, outside days, today,
  labels, links); the arrow's fetch, the latest press winning, the refusal following the link; the keyboard.
- **Behat** (`theme_dimensions_calendar.feature`): one scenario per block on the dashboard, each showing an event of
  the month with a link to the page.
- **Gates**: a "C3b calendar blocks" block in `mutations/gates.conf`, one per guard (each condition, the template name
  and the flag, the dropped item, the lookahead and the count), each with the test it must redden; every `.pl`
  dry-applied against its target.
- **Tier 2** (PHP and JS, no SQL added: the blocks call the range classes as the page does): one full leg with
  Behat (`mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE --behat`), plus `security-diff-read`, since the
  blocks show events under the range's visibility rules.

## 18. C3b results (2026-11-01)

Branch `calendar-c3b`, built from section 17, uncommitted, no `version.php` bump.

**What exists:**
- `classes/local/calendar/blocks.php`: the decision (`kind()`, `applies()`, `enabled()`), core's upcoming parity
  (`lookahead()`, `maxevents()`, `upcoming_window()`, `upcoming_keeps()`), the data (`month()`, `upcoming()`, both
  given "now"), `dots()`, `safe_url()` and `item_url()`;
- `classes/output/core_calendar_renderer.php` (the override of `render_from_template()` alone),
  `classes/output/calendar_block_month.php` and `classes/output/calendar_block_upcoming.php`;
- `templates/calendar_block_month.mustache` around `templates/calendar_block_month_body.mustache`, and
  `templates/calendar_block_upcoming.mustache`;
- the client `js/esm/src/calendar/block.ts` (built to `js/esm/build/calendar/block*`) and its `js_call_amd` bridge
  `amd/src/calendarblock.js` (built to `amd/build`);
- the setting `theme_dimensions/calendarblocks` beside `calendarcoreview`, five strings in en and pt_br;
- the `.dim-calblock` rules in `scss/calendar.scss`, the key tokens declared on that root as well as on the page's;
- the README section "The calendar blocks";
- tests: `tests/local/calendar/blocks_test.php` (18), `tests/local/calendar/blocks_budget_test.php` (1),
  `tests/output/core_calendar_renderer_test.php` (11), a new case in `calendar_component_source_test`, the second root
  in `calendar_scss_test`, Jest `js/esm/tests/calendar/block.test.ts` (10), and two scenarios at the end of
  `theme_dimensions_calendar.feature`;
- 28 gates in the "C3b calendar blocks" block of `mutations/gates.conf`.

**Where the build left the plan:**
- **The month block's template is `core_calendar/calendar_month`, not `calendar_mini`** (17.1): the decision named the
  mini template, core passes the month one with the flag. `blocks_test::test_the_template_names_are_the_ones_core_passes`
  reads both names from `calendar_get_view()` and the flag from the block's source, so a core change reddens it.
- **The agenda walks the window in growing pieces**: 7 days, then 14, then 28 and 42 (the range's cap), stopping once a
  piece fills the count. One piece for the whole lookahead cost 532 statements at 25 courses; the walk costs 226,
  because a range answer pays core's module callbacks for every item of its window and a busy week fills the count. A
  sparse lookahead pays one more range call per piece. Gate `cal_blocks_walk_stop` (the budget test) holds the stop.
- **Item links are kept only when absolute http(s)** (`blocks::safe_url()`), stricter than `safeHref()`, which also takes
  a relative address: the server builds every address absolute, and a relative one is never answered.
- **The client reads the weekday names back from the server's paint** (the `abbr` of each head cell), so a moved month
  keeps core's spelling of them; the caption and the day labels are the client's (`Intl`), as the page's are.
- **The selected day of 5.7** is not drawn: a block has no selection; today is marked (`aria-current="date"`).
- **Two existing tests follow the new client**: `calendar_component_source_test` counts two bridge imports (the page's
  notification, the block's templates) and lets `block.ts` redirect, where its failure follows the arrow's own link;
  `calendar_scss_test` accepts the blocks' root beside the body class and compiles the token blocks under both.
- **Core's footers stay core's**, as 17.1 says: the month block's "Full calendar" leads to `calendar/view.php`, which
  C3r sends to the page; the upcoming block's "Go to calendar..." leads to core's upcoming view, which stays core's. So
  the agenda shows the theme's "Full calendar" and core's footer link below it.

**Measurement** (`blocks_budget_test`, m503b, PHP 8.4, PostgreSQL 18, the C1 baseline's shape at 25 courses, "now"
on the 10th of the seeded month at 09:00, each render after one warm call with every per-request memo reset):

| One render | Statements | ms | Bytes |
|---|---|---|---|
| Core's month block (`calendar_get_view('monthblock')` and its template) | 733 | 865-906 | 948,484 |
| The theme's month (`blocks::month()` and its template) | 533 | 105-113 | 24,738 |
| Core's upcoming block (`calendar_get_view('upcoming_mini')` and its template) | 13 | 23-25 | 14,860 |
| The theme's agenda, one 22-day piece (first build) | 532 | 97-101 | 6,012 |
| The theme's agenda, walked (7-day first piece) | 226 | 45 | 6,012 |

Core's half runs whatever the theme does: the block calls `calendar_get_view()` before the renderer, and the theme
discards that data. So a page with the month block pays core's 733 statements and the theme's 533 on top; with the
upcoming block, core's 13 and the theme's 226. The budget test holds the theme's halves at 586 and 249 statements (a
tenth over) and the theme's month below core's.

**Security read:** no blocking finding. One should-fix was refuted against core: the read took a category page's
block to narrow to that category, as `block_calendar_month::get_content()` means to, but that code reads
`!empty($this->page->category)` and `moodle_page` has no `__isset()`, so the test is always false and core's block
never narrows; the upcoming block has no narrowing at all. The theme keeps core's effective behaviour (the viewer's
saved state on every page) and says why in `blocks::scope()`. Notes kept: the month client reads the state drawn at
page time (a change in another tab shows on reload); `data-page` is server-written and not scheme-checked again in
the client; a very long lookahead with no event cap walks the whole window, at the reader's own preference.

**Gates:**
- **Mutation sweep:** the 28 C3b gates went red on pgsql and mariadb
  (`mdl mutate --fast --branch MOODLE_503_STABLE --only <28 gates>`, exit 0 on both).
- **Tier 2:** `mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE --behat` passed every step but Behat, where
  one scenario of 190 failed: the month block's "today" click hit the default Dashboard's own calendar block, which
  sits in the closed block drawer and is not interactable. The scenario now reads the block in `#block-region-content`;
  the whole calendar feature then passed on m503b (16 of 16 scenarios, `tool_mulib`'s duplicate step left out of the
  generated `behat.yml` for that run and put back after). PHPUnit 1,908 (16 skipped), Jest 316.

**Not verified:**
- Behat on the last day of a month near midnight (the blocks read today).
- The blocks by eye on a stack (no password typed into a login page), light and dark.
- A screen reader over the mini month; the keyboard contract is Jest's.
- MariaDB and PHP 8.3 for the PHPUnit cases (run on m503b only, PHP 8.4 and PostgreSQL).
- The Moodle App, the iCal export and core's other calendar clients, which the README says a renderer cannot reach.

## 19. CV plan (2026-11-02)

CV opens the calendar page to a visitor who is not logged in, for the events of public categories and, by V1, the
public event courses (section 5.8). Decisions settled by the owner (2026-11-01), written here so they are not
reopened: the same page in a visitor mode, a visitor source with no viewer in it, a nologin service that is the
showcase visitor's twin, a switch that ships off and is read beside `publicaccess`, text-only descriptions (V2), no
saved state and no cache across requests (D39/OD-5).

### 19.1 Server

- **The switch.** `theme_dimensions/calendarpublic`, an `admin_setting_configcheckbox` that ships off and reads on only
  for a stored `'1'` (`local\calendar\visitor::enabled()`), beside `calendarcoreview` and `calendarblocks`, with en and
  pt_br strings. It is read beside `publicaccess`, never inside it: `category\publicaccess::is_public()` keeps gating
  every public category page, image route and head tag whatever the calendar's switch says.
- **Who is public, batched.** Two adapters, each failing closed when its predicate class is missing:
  - `category\publicaccess::public_ids()`: the surface's switches (`enabled()`, which folds the showcase's), then
    local_unlistedcourses' `category_discoverability::public_ids()` read through `are_public()` (four statements
    whatever the number of categories);
  - `landing\publicaccess::are_public($courseids)`: `discoverability::are_public()`, the landing page's own gate in bulk.
- **The visitor's sources** (`classes/local/calendar/source/`), no viewer anywhere in them:
  - `public_events`: one statement over `{event}`: `eventtype = 'category'`, `courseid = 0`, `groupid = 0`,
    `visible = 1`, no component, the category among the effective public ids, the start inside the window or the span
    overlapping it. It rides core's `groupid-courseid-categoryid-visible-userid` index. The name and the location are
    `format_string()` in the category's context with the filters off; `userid` is never selected. The description is
    `content_to_text()` of the row (V2: text, no image, no file area). The item's address is the public category page
    (`links::category_page()`), the only category page a visitor can open.
  - `public_courses` (V1): event courses (`dimensions_model = 2`) whose dates meet the window and whose own state row
    says public, at most `event_courses::OPEN_CANDIDATES` read earliest first, then `landing\publicaccess::are_public()`
    over them, at most `event_courses::OPEN_CAP` answered (`truncated` beyond). Key `open`; the address is the landing
    page (`links::page()`); `join` is `open`, so the popover's primary action is "Inscrever-se" (`calendar_join_open`)
    leading there.
- **The answer** (`local\calendar\publicrange::answer()`): `range::answer()`'s shape key for key, so the grid, the
  drawer and the popover draw it unchanged, plus `descriptions` (`[{id, text}]` for the category events that have one).
  The course dictionary is the visitor's: the landing page as `url` and `landingurl`, no image (core's
  `course/overviewfiles` asks for a login under forcelogin), no participant count, `enrolled` false. A category is
  named only when it is one of the effective public ids, with its branding pair. Nothing is cached.
- **The service** `theme_dimensions_get_public_calendar_range` (read, ajax, `loginrequired => false`, through
  `lib/ajax/service-nologin.php`), in `get_public_category_rows`' step order:
  1. `validate_parameters()` (`from`, `to`, `keys[]`, `lang`);
  2. one call per request (a static counter, counted before anything can refuse);
  3. a `cachekey` refused;
  4. a logged-in session refused, the guest included (`isloggedin()`), with the one refusal;
  5. the cheap shape checks: the window in UTC (`window::from_iso()`), the keys against the visitor's two;
  6. the language it is sent forced, given back in a `finally`;
  7. the switch (`visitor::enabled()`), then the site's calendar type (Gregorian only), with the one refusal;
  8. the page's context set to the system's, never `validate_context()`;
  9. the window in the site's timezone and `publicrange::answer()`.

  It never reads `$USER`. The one refusal is `local\calendar\refusal` (`calendarrangeunavailable`), the range's own.
  The returns are `get_calendar_range::execute_returns()` plus `descriptions`.
- **The page** (`page::handle()`): the first step splits. A visitor with the switch off goes to the login page before any
  lookup, as today; with it on, `page::visitor()` serves the page without `require_login()`: a calendar type other
  than the Gregorian goes to the login page too (one refusal); the month, the open day and the keys are read from the
  address alone (`visitor::on_from_request()`: the two keys, an empty `keys[]` member for none, anything else
  dropped); the answer is embedded; `opengraph::for_calendar()` is armed, so the head carries the canonical address and
  `noindex` unless the site is open to search engines. The guest account still goes to core's calendar, and a real
  account is served as before.
- **The renderable** (`calendar_page`) gains a visitor mode: the component `calendar/PublicCalendar`, the two chips,
  no category, no "More filters", no classic link, a no-JavaScript notice without core's calendar, and props without
  the tree, the options or core's calendar address.
- **Navigation.** The theme gives a visitor no navigation of its own: `guestnav` only replaces core's login text with
  the "Log in" and "Create account" actions, and `drawernav` (where the calendar row lives) applies to real accounts.
  So the visitor reaches the page by its address (shared, or linked from an institutional site); no visitor link is
  added in CV. C3r stays as it is: core's `calendar/view.php` keeps a visitor, whose own `require_login()` decides.

### 19.2 Client

- **A shell of its own, `js/esm/src/calendar/PublicCalendar.tsx`**, over the shared components (`MonthGrid`,
  `DayDrawer`, `EventPopover`, `KeyChips`, `layout`, `overlap`, `model`, `days`, `look`, `strings`, `history`), as the
  showcase's `visitor.ts` sits beside `account.ts`. It names one service, `theme_dimensions_get_public_calendar_range`,
  through `fetchOne(..., {loginrequired: false})` with no cachekey, and sends `Config.language`.
- **What moves out of the shared modules,** so the visitor's graph reaches nothing of a session:
  - the account's two services (`RANGE_METHOD`, `REMEMBER_METHOD`) leave `model.ts` for `calendar/account.ts`, which
    `Calendar.tsx` and `block.ts` import;
  - core's event service (`EVENT_METHOD`) moves into `description.ts`;
  - `EventPopover` stops importing `description.ts`: it is handed `describe` (the account passes `description`, the
    one feeder of the trusted sink; the visitor passes `null`) and `plain` (the visitor's text, drawn as text);
  - `DayDrawer`'s link to core's day view is drawn only when it is handed core's calendar address (the visitor hands
    none);
  - `KeyChips` draws the keys it is handed.
- **Behaviour:** the latest request wins (a ticket after the await), a Pending token from the request to the painted
  grid, the address written by `history.ts` (a month pushed, a change of keys replaced, `day=` replaced), Back and
  Forward read it again. A failure loads the address of what was asked for, which the server draws (the showcase
  visitor's rule). Nothing is remembered.

### 19.3 Keyboard and screen reader

Unchanged from 11.3 and 13.3: the same grid, chips, drawer and popover. The visitor's popover has no course page link
and draws its description as a paragraph of text.

### 19.4 Tests and gates

- **PHPUnit:**
  - `tests/local/calendar/public_events_test.php`: an event of an effective public category answered; never answered,
    each beside its control (the same request answered once the one reason is gone): a merely listed category, an
    unlisted one, a hidden one, a public one under a hidden ancestor, a non-category event type, an event hidden
    (`visible = 0`), a group's or a course's id on the row, an event outside the window; the span overlapping the window
    kept; the creator never answered; filters off; the surface's switch and a missing predicate fail closed.
  - `tests/local/calendar/public_courses_test.php`: a public event course answered, with the landing page and `join`;
    a listed, unlisted, hidden one and one under an unlisted category never, each with its control; a plain course
    model never; the cap.
  - `tests/external/get_public_calendar_range_test.php`: the refusal order pinned like the `pws_*` tests (a second
    call, a cachekey, a logged-in account, the guest, the switch never saved, `'0'` and `'yes'`, each the same bytes,
    each beside its control); a malformed window or key refused before any statement; the language spoken and given
    back; the answer equal to `publicrange::answer()`; nothing of a non-public category or course in any string.
  - `tests/local/calendar/page_test.php` (or a new `page_visitor_test.php`): the switch off sends a visitor to the login
    page before any lookup; on, the page is served without `require_login()`, with the visitor's component, the two
    keys and the head tags (noindex, with its control); the guest still goes to core's calendar.
  - `categorypublic_source_test` walks from `calendar/PublicCalendar` too: no session module (the account's shell,
    `account.ts`, `description.ts`, the picker, the filters, the blocks' client) and no logged-in service anywhere in
    its graph, comments included.
  - `calendar_component_source_test` follows the moved constants and the popover's two props.
- **Jest** (`js/esm/tests/calendar/PublicCalendar.test.tsx`): the first paint from the props, the two chips, a chip
  asking the visitor's service with `loginrequired: false` and the page's language, the latest answer winning, a
  failure loading the address, the popover's text description and "Inscrever-se" to the landing page, no classic link.
- **Behat** (`theme_dimensions_calendar_public.feature`): as a visitor with the switch on, a public category's event
  and a public event course on the month, the popover's description; the switch off sends the visitor to the login
  page (the control).
- **Gates:** a "CV public calendar" block in `mutations/gates.conf`, one gate per guard, each with its `#   ->` test.
- **Tier 3**: a nologin web service, SQL and an authentication path. `mdl ci moodle-theme_dimensions --matrix --behat`,
  `security-diff-read` over the diff, and the sweep of the CV gates on both database families.

## 20. CV results (2026-11-02)

Branch `calendar-cv`, built from section 19, uncommitted, no `version.php` bump.

**What exists:**
- the switch `theme_dimensions/calendarpublic` (`classes/local/calendar/visitor.php`: `enabled()`, the two keys, the keys
  read from the address and written back), beside `calendarblocks`, with en and pt_br strings;
- who is public in bulk: `category\publicaccess::public_ids()` and `landing\publicaccess::are_public()`;
- the visitor's sources `classes/local/calendar/source/public_events.php` and `public_courses.php`, and the answer
  `classes/local/calendar/publicrange.php` (`range::days()` became public for it);
- the service `classes/external/get_public_calendar_range.php`, registered in `db/services.php`;
- the page's visitor branch (`page::visitor()`, `page::to_login()`), `calendar_page`'s visitor mode
  (`COMPONENT_PUBLIC`, `visitor_context()`), the template's `{{#visitor}}` branches and one string,
  `opengraph::for_calendar()`;
- the client `js/esm/src/calendar/PublicCalendar.tsx` and `account.ts`, with `EventPopover`, `DayDrawer`, `KeyChips`,
  `description.ts`, `model.ts`, `Calendar.tsx` and `block.ts` changed as 19.2 says, builds under `js/esm/build/calendar/`;
- one SCSS rule (`.dim-cal__popdesc--text`), a README section "The public calendar", a CLAUDE.md bullet;
- tests: `tests/local/calendar/public_events_test.php` (7), `public_courses_test.php` (6), `page_visitor_test.php` (7),
  `tests/external/get_public_calendar_range_test.php` (10), a route case in `tests/route/controller/calendar_test.php`,
  two cases in `categorypublic_source_test`, one in `calendar_component_source_test` and the sink test there extended,
  `readonly_session_test` naming the second session-less read, the showcase fixture resetting the new call counter,
  Jest `js/esm/tests/calendar/PublicCalendar.test.tsx` (11), Behat `theme_dimensions_calendar_public.feature` (3);
- 56 gates in the "CV public calendar" block of `mutations/gates.conf`, every `.pl` dry-applied against its file
  (none dead).

**Where the build left the plan:**
- **The page's test is a file of its own**, `page_visitor_test.php`, beside `page_test.php`.
- **The service refuses a key outside the two as an invalid parameter**, the twin's rule for a malformed request; the
  page drops one, as the logged-in page drops every value outside its vocabulary.
- **A failure goes through `core/location`'s `redirect()`**, not `window.location.assign()`: Jest cannot stub the
  latter in jsdom, and core's module is the mockable door to the same call. `calendar_component_source_test` allows
  the visitor's shell that one redirect, in its catch.
- **The popover's "Course page" link is drawn only when the course's page differs from its landing page.** In the
  visitor's dictionary both are the landing page, which the primary action already opens; on the logged-in page the
  two always differ, so nothing changes there.
- **The visitor's popover shows the modality** of an event course: its props carry the modality field's options.
- **The head tags are the Open Graph registry's**, so the page also prints `og:title` ("Calendar"), `og:site_name`,
  `og:url` and `og:locale` beside the canonical link and `noindex`.
- **A description is cut at 2,000 characters** (`public_events::MAX_DESCRIPTION`), a bound on an anonymous payload.
  `html_to_text()` writes an image as its alternative text in brackets, emphasis as `_x_` and a strong as capitals.
- **One guard was dropped as dead before the sweep**: `publicrange::answer()` filtered the keys again through
  `visitor::on()`, but it asks only the two keys' sources, so a key outside them asked nothing either way.
- **Navigation:** none added for a visitor, as 19.1 says. Core's `calendar/view.php` keeps a visitor (C3r's rule), so
  under forced login it asks for a login even while the public calendar is on.

**Security read:** no blocking finding; the refusal order matches `get_public_category_rows` step for step, and every
"never answered" case has its control. Fixed: a nested `keys[][]` reached `optional_param_array()`, which throws, so an
anonymous page answered 500; `visitor::on_from_request()` now drops a member that is a list (gate `cv_keys_nested`).
Notes kept: category events have no cap per window (an owner decision below); `public_events::items()` trusts its
caller's category list (its one caller passes `publicaccess::public_ids()`, and the service test scans the whole answer
for a listed category's event); the collision order between two refusal reasons is not pinned, as in the twin; no
test asserts `ajax => true` on the new function.

**Gates:** _placeholder: the owner runs the sweep of the CV block and the matrix._

**Fast checks run** (m503b, PHP 8.4, PostgreSQL 18; `mdl ci` on the Mac mini, PHP 8.3, PostgreSQL): every touched or
new PHPUnit file green; the external tests (`get_public_calendar_range_test`, `readonly_session_test`,
`get_public_category_rows_test`) need the service registered, so they ran under a temporary bump to 2026100561,
reverted afterwards with the PHPUnit site re-initialised; `mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE
--only phpcs,phpdoc,phplint,mustache,grunt,jest` passed every step. Six gates were applied by hand and each reddened
its test (`cv_courses_state`, `cv_js_popover_no_core`, `cv_events_filter_off`, `cv_ws_loggedin`,
`cv_ws_returns_descriptions`, `cv_ws_lang_restore`).

**Not verified:**
- Behat: the three scenarios were not run (the new feature needs `mdl behat-init` first).
- The service over HTTP through `lib/ajax/service-nologin.php` under forced login (a curl of a real request), and the
  page by eye, light and dark.
- MariaDB and PHP 8.3 for the PHPUnit cases.
- The mutation sweep beyond the six gates above.
- A screen reader over the visitor's page; the keyboard contract is the shared components' (Jest).

## 21. C4 plan (2026-11-02)

C4 brings the week time grid (decision D4: month and drawer first, week now). The view switch C2 drew as "Month only,
a current marker" becomes two real choices, Month and Week, on the logged-in page and on the public calendar alike.
Decisions settled by the owner (2026-11-02), written here so they are not reopened: the view and the week travel in the
address as navigation, never in the saved state; seven day columns from the viewer's first weekday; an all-day strip,
an hour body with the drawer's overlap layout windowed over the week (no inner scroll) and a "now" line on today; a week
is one range call; a phone draws a 7-day agenda list; C3r's redirect targets are unchanged; the visitor gets the week
under the same rules.

**Core has no week view to map.** Core 5.3's `calendar/view.php` knows `day`, `month` and `upcoming` (and the blocks'
`mini`, `minithree`, `monthblock`, `upcoming_mini` through `calendar_get_view()`); there is no `view=week`, so C3r has
nothing to send and no owner decision is open there.

### 21.1 Server

- **Two address parameters, navigation like `day=`.** `view` (`page::PARAM_VIEW`) is `week` or nothing: any other
  value, `month` included, reads as the month and is dropped from the page's address. `week` (`page::PARAM_WEEK`) names
  the week by a day, YYYY-MM-DD. Neither is in `state::STATE_PARAMS`, so an address naming only the view, the week and
  the day is still bare and reads the saved state, and neither is ever saved.
- **The week is read, never refused.** `page::week_from_request($month, $firstday)`: a real day (`page::is_day()`)
  names the week holding it, written back as that week's first day on the viewer's first weekday
  (`calendar_get_starting_weekday()`), so a link shared by a reader whose week starts on another day still opens the
  week it meant; anything else (malformed, impossible, absent) is the week holding the month's opening day (today when
  the month asked for holds it, else its 1st). `page::week($start)` is the seven days, `[from, to]`.
- **The open day follows the view's window.** `page::day_from_request()` gains the week: inside the week's seven days in
  week view, inside the month grid's window otherwise; dropped elsewhere, as today.
- **The month a week view names** is the open day's when there is one, else the week's first day's: it is what the
  no-JavaScript "Month" link and the client's switch back to the month open.
- **One range call per week.** In week view `page::handle()` and `page::visitor()` ask `range::answer()` (the visitor
  `publicrange::answer()`) for the week's seven days, embedded in the props, so the first paint asks for nothing.
  The page's own address is `view=week&week=<first day>` plus the open day and the state (or the visitor's keys).
  No SQL is added: both answers are the existing range classes over a shorter window.
- **The script's forward** carries `view=week` and a real `week` day as written; the page behind the route judges them.
- **The renderable** (`calendar_page`) gains the view, the week and `now` (the server's clock, the client's "now" line;
  the client never reads the browser's clock except as time elapsed since the paint). Its no-JavaScript layer in week
  view: the caption is the week's span ("2 November to 8 November 2026"), previous and next lead to the adjacent weeks
  (labelled "Previous week", "Next week"), "Today" to the week holding today, the agenda lists the week's seven days
  (not the month's), and the view switch is two links, the current one `aria-current="page"`. The month view gains the
  "Week" link (the week holding the month's opening day). The visitor's layer the same, with its keys.
- **Strings:** `calendar_nextweek`, `calendar_prevweek`, `calendar_timed` (the hour body's row header),
  `calendar_viewweek`, `calendar_weekempty`, in en and pt_br, and in `calendar_page::STRINGS` and `strings.ts` alike.

### 21.2 Client (`js/esm/src/calendar/`)

- **`weeks.ts`, pure and Jest-tested:**
  - the arithmetic: `weekStart(iso, firstday)`, `weekDays(start)` (seven days, timezone-free, as `days.ts` does), and the
    view's address parameters (`VIEW_PARAM`, `WEEK_PARAM`, `inSpan()` writing them into an address the page already
    builds, `readSpan()` reading them back as the server does);
  - **`weekPlan(days, placed, zone)`**: the **all-day strip** (every item that is a bar of the month grid or fills whole
    days, `overlap.isAllDay()`) laid out with `layout.weekLayout()`'s slots, so a multi-day bar spans the week's columns
    and never zigzags, every slot shown; the **hour body**, one column per day, each `overlap.dayPlan()`'s hour items in
    the drawer's columns; and the **hour window** of the whole week, `overlap.hourWindow()` over every day's hour items at
    once (the earliest start and the latest end of the week, an hour of padding either side, at least four hours), so
    every timed item shows without an inner scroll; no window when the week has no timed item;
  - `nowLine(days, now, zone, window)`: the column of the day `now` falls on and its share of the window, or nothing
    when the day is not in the week or the time is outside the window;
  - the keyboard's moves (21.3), `stepWeekFrom()` for the day headers and `moveInWeek()` for the items.
- **`WeekGrid.tsx`**: the time grid on a desktop, the agenda list on a phone. One ResizeObserver, on the frame it
  shares with the drawer (the month grid's rule: the frame, not the grid), never read on the mount path; the phone
  breakpoint is `MonthGrid.PHONE`. Items carry `data-cal-pop` and their href (`itemHref()`), so the shells' delegated
  listener opens the popover as it does for the month's. A timed item says "Overlaps" in words when it overlaps, as the
  drawer's do, and carries its column (`data-column`, `data-columns`). The "now" line is a mark in today's column,
  `aria-hidden`; its time moves with the minutes elapsed since the paint.
- **`ViewSwitch.tsx`**: two toggle buttons (`aria-pressed`) in the toolbar's group, shared by both shells.
- **The shells** (`Calendar.tsx`, `PublicCalendar.tsx`): what is shown becomes a span, a month or a week, with its
  answer. A request asks for the span's days (the month grid's, or the week's seven: one range call either way, the
  latest request winning, a Pending token to the paint). The address is written by `history.ts` as before: a week moved
  and a view switched push an entry, a change of keys or filters and the open day replace it. A view switch keeps the
  selected day (the week holding it, or its month) and keeps the open day when the new span holds it. "Today", the
  arrows and PageUp/PageDown move by the span. The caption is the week's span in week view. Back and Forward read the
  view and the week again. The saved state is unchanged: the view is never sent to `remember_calendar_state`.
- **Phone**: below the breakpoint the week is seven sections, each a day header (a button that opens that day's agenda
  sheet, `DayDrawer`'s sheet) and the day's items as a list (the strip's first, then the timed ones by start), "Nothing
  on this day." for an empty one.
- **Look**: `scss/calendar.scss`, under `body.dim-calendar`: the week grid on the `--dim-cal-*` tokens and the drawer's
  hour rows (2.5rem an hour), the strip's bars as the month's, the "now" line on the overdue edge token; no `:has()`, no
  `!important`, no literal colour, no clamp/min/max in a length.

### 21.3 Keyboard and screen reader (the spec, before code; extends 11.3 and 13.3)

- **The week is `role="grid"`**, labelled by the caption (the week's span, `aria-live="polite"`), in three rows: a header
  row of seven `columnheader` cells, each holding the day's button; the all-day row, a `rowheader` ("All day") and seven
  `gridcell`s holding the bars that start on that day (a bar's label is its name and its whole span, as in the month);
  the hour row, a `rowheader` ("At set times", the hour labels `aria-hidden`) and seven `gridcell`s, each a day's timed
  items. A screen reader's table navigation reads a column's header (the long date) with each cell.
- **One Tab stop**, the selected day's header button (roving tabindex); the items are out of the Tab order (`tabindex
  -1`), reached by the arrows. Tab from anywhere in the grid leaves it.
- **On a day header:**
  - ArrowLeft and ArrowRight move a day; past the week's ends the adjacent week is fetched and the focus lands on that
    day's header;
  - Home and End go to the week's first and last day;
  - PageUp and PageDown go to the previous and next week, on the same weekday;
  - Enter or Space opens the day: the drawer beside the grid (desktop), with the focus on its heading;
  - ArrowDown or F2 moves into the day's items: the first of its strip, else its first timed item; nothing when the day
    has none.
- **On an item:**
  - ArrowDown and ArrowUp move to the next and previous item of the same day, strip first then by start time; ArrowUp
    from the first item goes back to the day's header;
  - ArrowLeft and ArrowRight move to the neighbouring day's item nearest in time (the strip counts as the top of the
    day), or to that day's header when it has none; nothing at the week's ends;
  - Home and End go to the day's first and last item;
  - Enter or Space opens the popover (13.3); Escape returns to the day's header.
- **Labels:** a day header's label is the month's day label (the long date, the item count, "today"),
  `aria-current="date"` on today; a timed item reads its name, its time and "Overlaps" when it does; the "now" line is
  not read.
- **Phone:** a list, every day header and item in the natural Tab order; Enter on a day header opens its sheet.

### 21.4 Tests and gates

- **PHPUnit:**
  - `page_test`: `view` dropped outside its vocabulary; the week snapped to the viewer's first weekday (Sunday and Monday
    first, across a month and a year boundary), a malformed week falling back to the opening day's week; the open day
    kept inside the week alone in week view, beside its month-view control; the forward carrying `view` and `week`; the
    view and the week are not state.
  - `calendar_test` (the route): the week view's first paint embeds `range::answer()` for the seven days, with the view,
    the week and the address keeping `view=week`; the month view's control; a bare week address reads the saved state.
  - `page_visitor_test`: the visitor's week, its seven days from `publicrange::answer()`.
  - `calendar_page_test`: the no-JavaScript layer in week view (the week's caption, the adjacent weeks' links, the
    week's agenda, the switch's links), beside the month's; `now` in the props.
  - `calendar_component_source_test` and `categorypublic_source_test`: what the gates' JS lines read (21.2), one
    ResizeObserver per grid, and the visitor's walk reaching `WeekGrid`, `weeks` and `ViewSwitch` with nothing of a
    session.
- **Jest** (`js/esm/tests/calendar/weeks.test.ts`, `WeekGrid.test.tsx`, and cases in `Calendar.test.tsx` and
  `PublicCalendar.test.tsx`): the columns from each first weekday; the week arithmetic across a month, a year and a
  daylight-saving change (the same wall-clock hour in the same row on both sides); overlaps side by side; the strip
  (multi-day bars across columns, clipped at the week's ends, whole-day items, event courses); the hour window over the
  week; the "now" line on today only and inside the window; the view switch (address, one range call of seven days,
  back to the month); the keyboard contract of 21.3; the phone's agenda.
- **Behat** (`theme_dimensions_calendar.feature`, the page's own regions): the week view shows the two overlapping
  events side by side and keeps `view=week` through a reload; on a phone the week is the agenda list and a day header
  opens its sheet.
- **Gates:** a "C4 week view" block in `mutations/gates.conf`, one gate per guard (the view's vocabulary, the week's
  snapping and fallback, the open day's window in week view, the forward, the week's window in the first paint, the
  visitor's week, the agenda's span, the strip's membership, the week's hour window, the "now" line's guards, the address
  and history writes, the one range call), each with its `#   ->` test; every `.pl` dry-applied against its target.
- **Tier 2** (PHP and JS, no SQL added, nothing PHP-version-specific): one full leg with Behat,
  `mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE --behat`, and the sweep of the C4 block on both database
  families. The anonymous page reads two more address values, so a `security-diff-read` over the visitor branch is
  cheap insurance, not a requirement of the tier.

## 22. C4 results (2026-11-02)

Branch `calendar-c4`, built from section 21 on top of CV (afdff08), uncommitted, no `version.php` bump.

**What exists:**
- **Server:** `page::PARAM_VIEW`, `PARAM_WEEK`, `VIEW_MONTH`, `VIEW_WEEK`; `page::view_from_request()`,
  `week_from_request()`, `week_start()`, `week()`, `opening_day()`, `span()` (the view, the month, the week, the open
  day and the window, once, for both entries), `span_params()` (the page's own address for either view) and
  `scalar_param()`; `day_from_request()` judges the open day against the week in the week view; the script's forward
  carries `view` and `week`. `calendar_page` takes the view and the week, puts `view`, `week` and `now` in the props
  (both modes), and draws the week's toolbar (`toolbar()`: caption, "Today", the adjacent weeks, the switch's two
  links) and the week's agenda. The template's view switch is two links; its form keeps `view` and `week` in the week
  view. Seven strings in en and pt_br (`calendar_nextweek`, `calendar_noscriptweek`, `calendar_prevweek`,
  `calendar_publicnoscriptweek`, `calendar_timed`, `calendar_viewweek`, `calendar_weekempty`), five of them in
  `calendar_page::STRINGS` and `strings.ts`.
- **Client:** `js/esm/src/calendar/weeks.ts` (pure), `WeekGrid.tsx` and `ViewSwitch.tsx` are new; `Calendar.tsx`,
  `PublicCalendar.tsx`, `model.ts` (the props' `view`, `week`, `now`) and `strings.ts` changed; builds under
  `js/esm/build/calendar/`. The week rules in `scss/calendar.scss`, under `body.dim-calendar`.
- **Tests:** `page_test` (48 cases, 25 new: the view's vocabulary, a list dropped, the week snapped from four first weekdays across a
  month and a year with its fallbacks, the span of each view, the open day inside the week, not state, the forward,
  the address of each view, the week's first paint equal to `range::answer()` for the seven days), `page_visitor_test`
  (8, 1 new: the visitor's week equal to `publicrange::answer()`; the props' key list updated), `calendar_page_test`
  (8, 2 new: the week's server layer beside the month's, the week's agenda across a month's edge), the route's
  `calendar_test` (11, 1 new: the week served and kept in the address), `calendar_component_source_test` (17, 1 new and
  three updated), the visitor's walk reaching `WeekGrid`, `weeks` and `ViewSwitch`; Jest `weeks.test.ts` (25) and
  `WeekGrid.test.tsx` (29), the two shells' fixtures given `view`, `week` and `now`; two Behat scenarios in
  `theme_dimensions_calendar.feature`.
- **Helpers the shells share** (`weeks.ts`): `spanLabels()` (the caption and the arrows' labels per view), `dayIn()`,
  `selectedIn()`, `openingOf()`, `stepSpan()`; they keep each shell's component under eslint's complexity cap of 20,
  which the CI leg's `eslint:react` applies and the stack's `mdl grunt` does not.
- **Security read:** one blocking finding, fixed. `is_day()`, `month_from_request()` and `window::from_iso()` matched
`/^...$/`, whose `$` also matches before a final newline, so `week=2026-11-15%0A` passed `is_day()` and
`week_start()` then called `format()` on false: an error page, on the public calendar too. The month had carried the
same flaw since C2. The three patterns now end in `$/D`, with a newline row in each test (gates `c4_day_newline`,
`c4_month_newline`, `c4_window_newline`). Kept as notes: the client's `readSpan()` falls back to the month's 1st
where the server falls back to its opening day, and applies no year bound, so a history step onto an out-of-bounds
week can show a different week than a reload (handled in C5); the toolbar's arrows are not clamped at 1900 or 2999,
and the page then opens the current month or week.

**Gates:** 36 in the "C4 week view" block of `mutations/gates.conf` (20 server and template, 16 client), every `.pl`
  dry-applied against its file (all 36 live). `cal_js_history_push` (C2) re-pointed to the shell's new address line.

**Where the build left the plan:**
- **`view` and `week` are read through `page::scalar_param()`, not `optional_param()`.** Core's `param::clean()` throws
  on a list, so `?view[]=week` would have answered the public calendar's visitor with an error page; a list now reads
  as nothing, like any value outside the vocabulary (gate `c4_page_view_list`). `month` and `day` kept
  `optional_param()` here, as C2 and C3 wrote them; the follow-up below moved them too.
- **The week's year is bounded as the month's is** (1900 to 2999): a week of year 1 would begin in year 0, which the
  range's window cannot spell.
- **Two more strings than planned**, `calendar_noscriptweek` and `calendar_publicnoscriptweek`: the no-JavaScript notice
  said "the month is listed here" over the week's agenda.
- **The strip reuses `Bar.tsx` unchanged** (its row is its slot plus two): the strip's grid starts with a zero-height
  row. The strip is drawn even when empty, a 1.75rem row under its "All day" header, so the hour body never jumps.
- **One ResizeObserver per grid**, so two in the tree (the month's and the week's), each watching the frame the drawer
  shares; `calendar_component_source_test` counts one per grid instead of one in all.
- **Enter and Space on an item are not the week's**: `moveInWeek()` answers nothing for them and the key reaches the
  shell's delegated listener, which opens the popover, as on the month.
- **The header row's corner cell is `role="presentation"`**, not an empty columnheader, so a screen reader announces no
  blank header.
- **`openableDay()`** (exported by `Calendar.tsx`) now takes the span's days instead of a month and a first weekday.
- **The "now" line ticks once a minute** from the server's `now` plus the time elapsed since the paint. `today` is not
  ticked: a page left open past midnight moves the line to the next column while the header still marks the old day
  until a reload.
- **Existing tests that followed the change:** `calendar_component_source_test` (the address line, the history writer,
  the observers), `page_visitor_test` (the props' key list), the two shells' Jest fixtures.
- **A list in the address is never an error page, on either page** (follow-up). `month` and `day` now read through
  `page::scalar_param()` (the readers and the script's forward), the logged-in state's scalars (`categoryid`,
  `recursive`, `city`, `actiononly`, `colour`) through `state::scalar_param()`, and `state::array_param()` drops a member
  that is itself a list (`model[][]=1` threw in `clean()`). Gates `c4_page_month_list`, `c4_page_day_list`,
  `c4_page_forward_month_list`, `c4_page_forward_day_list`, `c4_state_scalar_list`, `c4_state_member_list`. The four dead
  gates listed below were re-pointed in the same change.

**Pre-existing dead gates found by the dry-apply** (all four dead at afdff08 already, none touched here):
`cal_page_visitor_login` and `cal_page_require_login` (`page.php`, since CV and C3r rewrote `handle()`'s first lines),
`navbar_dropdown_hover_answered` (`scss/post.scss`) and `js_linearfooter_near_end` (`amd/src/linearfooter.js`). The
sweep stops at a dead pattern, so they need re-pointing before the next full sweep.

**Not verified:**
- Behat: the two new scenarios were not run (no Behat in this stage's turn). Uncertain: that the "mobile" viewport's
  frame stays under the 576px breakpoint in the week as it does in the month; that Behat sees the agenda only after the
  ResizeObserver's first entry (the month's phone scenario relies on the same). The stylesheet reaches Behat only after
  `mdl behat-init`.
- The page by eye, light and dark (no password typed into a login page), and a screen reader over the week's grid; the
  keyboard contract is Jest's.
- The server and template gates' sweep: the 16 client gates were checked by hand against Jest on a scratch copy (each
  reddened at least one test); the 20 server and template gates were dry-applied only.
- MariaDB and PHP 8.3 for the PHPUnit cases (run on m503b only, PHP 8.4 and PostgreSQL).

**Fast checks run** (m503b, PHP 8.4, PostgreSQL 18, after `mdl phpunit-init m503b`; `mdl ci` on the Mac mini, PHP 8.3,
PostgreSQL): `page_test` 48 tests, `page_visitor_test` 8, `calendar_page_test` 8, the route's `calendar_test` 11,
`calendar_component_source_test` 17, `categorypublic_source_test` 21, `esm_source_test` 4, `calendar_scss_test` 5, all
green; `marketplace_readiness_test` 9 (1 skipped by design: a theme's selectors are not plugin-scoped). Jest: the
calendar's nine suites 175 tests. `mdl ci moodle-theme_dimensions --branch MOODLE_503_STABLE --only
phpcs,phpdoc,phplint,mustache,grunt,jest` passed every step (Jest 30 suites, 381 tests), on its third run: the first
two found eslint's complexity cap on the two shells (above) and a stylelint multi-line declaration in the week's
column rule, both fixed.

**Gates:** _placeholder: the owner runs the sweep of the C4 block and the tier-2 leg with Behat._
