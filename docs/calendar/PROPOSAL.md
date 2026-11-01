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
