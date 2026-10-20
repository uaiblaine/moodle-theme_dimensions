# Focus mode in place: design record

Status: feasibility study and owner decisions (2026-10-20). Nothing implemented. The focus mode this
record changes is the one described in the repository `CLAUDE.md`, section "Focus mode"; read that
section first, because every constraint it lists still holds unless this record says otherwise.

## Goal

On an activity page, a learner switches between the ordinary page and focus mode **without a reload
and without leaving the page**. The one exception is a single activity course whose course switch
forces focus mode: its exit control leaves for the site home page.

## Why the current mechanism cannot do it

Focus mode is a server-side template swap today. `core_renderer::render_from_template()`
(`classes/output/core_renderer.php`, the `theme_boost/drawers` guard) replaces Boost Union's layout
template with `theme_dimensions/focusmode`, a reduced copy of it. The ordinary page and the focused
page are two different documents, so entering is a POST to `focus.php` (a session flag, then a
redirect to the activity) and leaving is the same POST or, on a course that switched focus mode on, a
plain link to `focusmode::close_url()`. No client-side change can turn one document into the other.

## Owner decisions (2026-10-20)

| # | Question | Decision |
|---|---|---|
| D1 | On a course that forces focus mode (any format but single activity), how long does leaving in place last? | **The rest of the session, in that course.** A per-course session flag, the mirror of the manual entry flag. "Forced" therefore means "on by default" outside single activity. |
| D2 | A single activity course that does NOT force focus mode: does it toggle in place like the others? | **Yes.** |
| D3 | The automatic completion conditions in focus mode. | **Stay as the discreet line at the foot of the content card** (the 2026-09-06 decision), accepting a hidden duplicate of core's static badges in the DOM. |
| D4 | Having the feature at all. | **The administrator's decision**: an enable/disable switch, and **while it is off nothing of the feature is loaded** (see "The master switch"). |

Out of scope: Moodle Workplace. The owner runs none, so Boost Union's MWP extension
(`local_boost_union_mwp/drawers`, rendered when `admin/tool/tenant` is present) stays as it is today:
the guard does not match that template and focus mode fails safe to the ordinary page.

## The mechanism: one page, two skins

The feasibility rests on four facts measured on 5.3 (core `moodle-503`, Boost Union `upgrade-503`):

- **The focus shell is a strict subset of Boost Union's `templates/theme_boost/drawers.mustache`.**
  Same drawers, same ids (`#page`, `#topofscroll`, `#page-content`, `#region-main-box`,
  `#region-main`), same `core/activity_header` partial, same `theme_boost/footer`. It only removes
  (navbar, `full_header`, secondary and tertiary navigation, the region main settings menu, the extra
  block regions, slider, advertisement tiles, footnote) and adds (the bar, the completion line, the
  previous/next row).
- **Every rule of `scss/focusmode.scss` is already scoped under `body.dimensions-focusmode`**, and a
  test asserts it. Toggling that class on the client is exactly what the scoping allows.
- **Core's manual completion button survives being moved in the DOM.**
  `course/amd/src/manual_completion_toggle.js:64` listens on `document` and finds the button with
  `closest()`, so the node can travel between the page header and the bar.
- **Core's activity information box already separates the description from the conditions.**
  `course/templates/activity_info.mustache:52-67`: `.activity-information > .activity-details >
  .activity-description#intro` beside `.completion-info`. CSS can show the description alone.

So Boost Union's layout template renders **untouched** on every activity page, and focus mode becomes
a body class plus additions that are visible only under it:

| Piece | Today | In place |
|---|---|---|
| Page template | `templates/focusmode.mustache`, 272 lines copied from Boost Union | Boost Union's own template; **`focusmode.mustache` is deleted** |
| The bar | Inside the shell | Built in `render_from_template()` from Boost Union's layout context (still guarded by `theme_boost/drawers`, now a capture and no longer a swap) and appended by a `standard_top_of_body_html()` override, which the template evaluates later in the same render, on every eligible activity page; hidden unless the body class is present |
| Boost Union's chrome | Not rendered | Rendered and hidden by CSS under the body class |
| Activity description | `headercontent['completion']` rewritten by `description_only()` | Core's box kept; under the body class CSS removes the box chrome and `.completion-info`, leaving `#intro` |
| Completion line (D3) and previous/next row | In the shell | Emitted by the `activity_navigation()` override, wrapped in a focus-only container |
| Header actions (completion button or status) | Rendered into the bar | Rendered once, where the first paint needs them; the AMD module moves the node between the page header and the bar on every toggle. Never duplicated: a hidden second copy would go stale when the visible one is pressed |
| Entering and leaving | POST to `focus.php`, redirect | The AMD module toggles the class and records the choice through a new web service (`ajax => true`). The POST form stays as the fallback without JavaScript |
| First paint | Decided on the server | Still decided on the server (the body class from `body_attributes()`), so there is no flash of the wrong mode |

What becomes simpler: the copied shell disappears, and with it the re-diff against every Boost Union
release and the "footer partial is the JavaScript" hazard (the shell's most dangerous line). What the
coupling becomes instead: a list of Boost Union selectors hidden under the body class. Its failure
mode is milder (a new Boost Union element shows inside focus mode) but the list must track Boost
Union.

### State

The predicate in `focusmode::evaluate()` keeps its clauses and gains one session state:

- Manual entry on a course that did not switch focus mode on: the existing
  `$SESSION->theme_dimensions_focus[courseid]` flag.
- Leaving in place on a course that did (D1): a second per-course session flag, opt-out. The
  `course` clause then reads "switched on and not opted out, or opted in". The vocabulary of `why()`
  is pinned by a test and changes with it.
- Both flags are written by one web service (one class under `classes/external/`, the fleet's web
  service rules: `validate_parameters()`, `require_login()` on the cm's course, `validate_context()`
  from the cmid, sesskey through `core/ajax`). It refuses while the master switch is off.
- Editing, the Moodle app, excluded page types and `?focusoff=1` stay server-side clauses. The edit
  switch reloads the page anyway.

### Single activity

- Not forced (D2): toggles in place like every other format.
- Forced: no in-place exit. "Exit focus mode" is a link to the site home page,
  `new moodle_url('/', ['redirect' => 0])`. `redirect=0` stops `defaulthomepage` from sending a
  logged-in user to the Dashboard instead. Under `forcelogin` a visitor or the guest account is sent
  on to the login page by core itself. This replaces the single activity branch of
  `focusmode::close_url()` (today `/my/courses.php`, or the login page for a visitor or the guest);
  its docblock must be rewritten with it.

### The master switch (D4)

The switch exists today (`theme_dimensions/focusmode`, `admin_setting_configselect`, shipped Yes,
read by `focusmode::master_enabled()` as the first clause, so no field or session read happens behind
it). What still loads while it is off, and what the implementation changes:

| Loaded while off today | Change |
|---|---|
| `scss/focusmode.scss`, appended unconditionally by `theme_dimensions_get_main_scss_content()` in `lib.php` | Append it only while the switch is on; the setting gains `set_updatedcallback('theme_reset_all_caches')`, because the compiled sheet is cached per theme revision |
| The course settings form shows the focus mode checkbox and its category header | The `after_form_definition` hook removes both elements while the switch is off. Stored values are untouched: `data_controller::instance_form_save()` returns early for an element absent from the submission (`customfield/classes/data_controller.php:202`), so turning the switch back on restores every course's answer |
| The other settings of the focus mode tab | Hidden with `hide_if()` on the master switch |

Already absent while off, and kept that way: the bar, the entry button (`offers_entry()` answers
`master`), the AMD module (required only by `layout_context()`), the course field read, and the web
service (refuses). The lang strings stay in the component's string file; Moodle loads that file whole
and offers no per-feature split. The stored width preference and any session flags stay inert;
nothing reads them.

## Risks to design for

- **Reflow.** The reading column changes width (830/1200px against Boost Union's measure). Dispatch
  a `resize` after each toggle so video players, H5P and iframes recompute.
- **Keyboard focus.** The button that was pressed disappears with the chrome it lives in. Move focus
  to its counterpart (the entry button and the bar's exit control) and announce the new mode in an
  `aria-live` region.
- **Open menus and tooltips** inside the chrome being hidden: close them before toggling.
- **Navigating before the web service answers** opens the next page in the previous mode. Hold
  `core/pending` while the request is in flight; accept the rest.
- **Back/forward cache** can restore a page in the mode it was left in. Reconcile on `pageshow`
  with `persisted`, or accept it.
- **Page weight.** Every eligible activity page carries the bar and the focus-only additions. The
  rest is what Boost Union's layout already renders.
- **Boost Union flavours** are compatible and need no work: a flavour changes CSS and look
  settings, never the layout template, and the bar already takes its colours from the layout
  context's `navbarcolorclasses` and `databstheme`, where a flavour's `look_navbarcolor` lands
  (`classes/output/focusmode_bar.php`). Nothing in the pipeline measures a colour, so check the bar
  under a flavour with its own navbar colour and raw SCSS in a browser.

## Tests and gates

Most of the work is in the tests. Today many assertions and mutation gates read "the bar is absent
from the output"; in place, the bar is present on every eligible page and the assertions move to the
body class and to what is hidden. To add:

- PHPUnit: the body class in each state of the predicate, including both session flags; the web
  service (through `call_external_function()`, refused with the switch off); the single activity
  close URL; the SCSS absent from the compiled sheet with the switch off; the course form without
  the field with the switch off.
- Behat: a toggle that does not reload (a marker set on `window` survives the toggle), and the
  forced single activity exit landing on the site home page.
- A browser pass at 320/375/1280px. Nothing in the pipeline measures colour or layout.
- The gates of the deleted shell (`focusmode_footer_partial`, `focusmode_blockdrawer` and the others
  in `mutations/gates.conf`) are retired or rewritten for the new mechanism, and the mutation sweep
  runs before the commit that adds the new gates.
- The change touches a state machine and a web service: run the `security-diff-read` skill over the
  diff before the push.

## Open

- O1: the switch ships **Yes** today. D4 says having the feature is the administrator's decision;
  whether a fresh install should ship **No** (opt-in) is for the owner. If it flips,
  `master_enabled()` must read an unset value as off too, or a fresh install disagrees with its own
  settings page.
