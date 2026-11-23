# Navbar and navigation drawer: design record

Status: phase 1 merged (#9, 28c5fb4); phase 2 merged (#10, fee54c5, version 2026100513); phase 3 merged (#11, 82913e5,
version 2026100514); phase 4 built on branch `navbar-phase4-tablet-sheets` (2026-10-19, uncommitted, next version
2026100515; what was and was not verified is at the end of its section). Brainstorm:
`brainstorm.html` (pt-BR, published as a private artifact). States canvas: `canvas/` (Design canvas sources,
14 artboards, one row per phase).

## Goal

Make the navbar invite a visitor instead of describing their state, give the mobile drawer an
identity of its own, and let the navigation get out of the way while a person reads - staying close
to core and Boost Union where that costs nothing, and leaving them where the gain is worth a
template copy.

## Benchmarks (measured in a browser, 2026-10-18)

| Site | What was measured |
|---|---|
| Fandom Community Central, desktop | Visitor sees "Sign in" (outline) beside "Create a free account" (filled); no "you are a visitor" sentence. A fixed left rail. Scrolling swaps the global bar for a 46px community bar (`transform 0.5s`) carrying local menus, a compact search and the signup call. |
| Fandom, mobile profile | Two tiers (global bar, community bar) that merge into one 56px bar showing the page title over the community name once scrolled. Two drawers with separate jobs: personal navigation + appearance + a "New to Fandom?" signup card; the community's menus as collapsible sections whose children slide in with a back control. |
| Domestika, logged in | Headroom pattern: the navbar leaves with `translateY` on scroll down and returns on the first scroll up (`transform 0.25s ease-in-out`). Course tabs with "2 of 4 completed"; a "Go to unit 3" resume call; on a phone the tabs become two selects. |

## Constraints this design keeps (measured in this repository and in core 5.3)

- The navbar is `.navbar.fixed-top`, 60px (`$navbar-height`), with the 3px band as its `::after`.
  Drawers, anchors and the grader derive their offsets from that height.
- The desktop primary navigation is React in 5.3 (`core/nav/PrimaryNav`, with a Mustache fallback).
  The mobile drawer (`theme_boost/primary-drawer-mobile`) is still Mustache and can be overridden.
- The theme copies neither `navbar` nor `user_menu`; it changes the layout context in
  `render_from_template()` (by the context's shape) and splices into `navbar_plugin_output()`.
- Core prints "You are currently using guest access" only for the guest account and a "Log in" link
  for any visitor; there is no signup control in the navbar.
- Blur is allowed only over a card image or over the navy (the design system's rule): a translucent
  navbar is out.
- On 5.3, core's linear navigation footer owns the manual completion button on activity pages of
  topics and weeks; a bar that repeats it would show it twice.
- Boost Union `upgrade-503` removed the smart-menu bottom bar on 2026-10-08 (its CHANGES.md, commit
  9e2a0cc7) until it is reimplemented over the React smart menus; it exists on 5.2.

## Decisions (the owner, 2026-10-18)

| Id | Decision |
|---|---|
| N1 | "Create account" follows the site: it exists only when `signup_is_enabled()` answers a plugin, and goes to `/login/signup.php`. auth_loginsteps answers `can_signup()` from its own `enableregistration` and redirects core's signup page to its flow (classic or steps, its `registrationflow` setting), so the theme needs no coupling to it. |
| N2 | The guest notice becomes a minor note: a "Guest" chip whose dropdown carries core's `loggedinasguest` sentence. Logged-out visitors get no chip, as in core. |
| N3 | The context bar (C2) is on by default on activity pages; auto-hide (C1) is a site option. |
| N4 | Auto-hide is chosen by the administrator per page type, off by default. |
| N5 | Tablets use the compact mode (B5): decided by what fits (the primary navigation's overflow), not by a fixed width. |
| N6 | The bottom bar (B7) becomes a restyle of Boost Union's smart-menu bottom bar on phones, later; it waits for Boost Union to bring the location back on 5.3. |
| N7 | A states canvas before any code. Done: `canvas/`. |

## Phase 1: the visitor's navbar

| Idea | What ships |
|---|---|
| A1 | "Log in" (core string, outline pill) and "Create account" (filled pill, N1) at the right end of the navbar, replacing core's sentence and link. Below `md` only "Log in" stays. |
| A2 | For the guest account, a "Guest" chip before them, opening a dropdown with core's sentence, one explanatory sentence and the same two actions. Hidden below `md`. |
| A4 | A "New here?" card at the top of the mobile drawer for the same visitors, with the two actions; the guest account also reads core's sentence there. |
| A5 | Deferred. Core already returns the person to the page they came from after logging in (`login/index.php` takes the local referer as `wantsurl`); the toast that says why needs a login observer and a session flag, which is a decision of its own. |

Mechanism:

- One predicate, `classes/local/guestnav.php`: the setting `navbarguestactions` (default on; only a
  stored `'0'` is off), a visitor (`!isloggedin() || isguestuser()`), not the Moodle app, and a
  layout that draws the navbar. The same class builds the template data.
- `navbar_plugin_output()` appends `theme_dimensions/navbar_guest`; Boost Union prints that output
  right before the user menu container.
- `render_from_template()`, guarded by the shape `usermenu.unauthenticateduser`, sets `usermenu` to
  false and adds `dimensionsguest` for the drawer.
- `theme_boost/primary-drawer-mobile` is overridden: a copy of Boost Union's with the card's partial
  at the start of the drawer content. Re-diff it against Boost Union's copy on every Boost Union
  update.

## Phase 2: the drawer of a logged-in account

| Idea | What ships |
|---|---|
| B2 | The mobile drawer of a logged-in real account (not a visitor, not the guest account, not the Moodle app) is the theme's own and holds the site's navigation alone: labelled sections with icons, the current page marked (`aria-current="page"`, the brand's tint and ink, no edge mark). The person's things (the identity, the colour mode, the Look, the language, the phone's navbar entries) are the profile layer's, which the user button opens (D72 of the fleet Design System, `moodle-dev/docs/design-system/plans/user-menu-theme.md`). |
| B3 | A row with children opens a sub-panel that slides in, with a back control naming the parent. Three levels and more. |
| B4 | "Continue where you left off": the first block, filled the first time the drawer is shown. |
| D4 | Light, Dark and System through core's own colour mode machinery: in the profile layer's "Display" group, not in the drawer. |
| B1 | Deferred to phase 3 (below). |

Visitors and the guest account keep Boost Union's list with the "New here?" card of phase 1, unchanged. Giving
them the sections and the sub-panels too (as the A4 artboard draws) is an open decision of the owner's.

Mechanism:

- One class decides and builds, `classes/local/drawernav.php`, renderer-free: the setting `navbardrawer` (default
  on; only a stored `'0'` is off), a logged-in real account, guestnav's excluded layouts, not the Moodle app. Its
  `context()` reads only the primary navigation core and Boost Union already put in the layout context
  (`mobileprimarynav`) and the page (for the calendar row), and answers `rootid`, `sections` and `panels`.
- `render_from_template()`, guarded by the SHAPE of a logged-in account's layout (`mobileprimarynav` beside
  `usermenu.avatardata`), writes `dimensionsdrawer`. The override of `theme_boost/primary-drawer-mobile` draws
  `theme_dimensions/drawer_nav` while that key is set and Boost Union's list otherwise; Boost Union's child partial,
  navbar and drawers stay theirs, uncopied.
- **The drawer holds nothing about the person** (D72): no name, no role line, no colour mode, Look or language, and it
  reads nothing of the user menu (`drawernav_test::test_the_drawer_holds_nothing_about_the_person`,
  `drawer_scss_test::test_nothing_about_the_person_is_styled`). Those are the profile layer's
  (`classes/local/profilelayer.php`, printed by the core renderer's `standard_after_main_region_html()`), whose title
  and further lines are the fields the administrator chose (`classes/local/identity.php`); the theme's `CLAUDE.md`,
  sections "The identity fields" and "The profile layer", holds its rules.
- **Sections**: core's primary nodes and the custom menu are "Browse"; each Boost Union smart menu in submenu mode
  is a section of its own, labelled with the menu's title, its items as the rows (inline smart menus are already
  merged into the top level by Boost Union). **Icons**: a key-to-Font Awesome map for core's nodes (`home` house,
  `myhome` gauge, `mycourses` graduation cap, `siteadminnode` gear, `calendar` calendar - the glyphs core's own pix
  icons map to), a smart menu item's own icon (already in its text, so no second glyph), `fa-circle-dot` otherwise;
  a sub-panel's rows carry no default glyph.
- **No-JavaScript fallback**: every panel is server-rendered, flat, after the root, as a block with an anchor of its
  own; a row with children is a link to that anchor and the back control a link to the parent's. Without
  `amd/src/drawer.js` every level is reachable by scrolling or following the links (the drawer itself needs Boost's
  JavaScript to open, so this is the case of the theme's module failing). The module turns the blocks into layers:
  the current one visible, every other one `inert`, moved aside and hidden once its slide has ended; the focus goes
  to the new panel's back control and returns to the row that opened it; Escape inside a sub-panel goes back one
  level, and Escape at the root (or on the drawer band's close button) closes Boost's primary drawer through Boost's
  own instance (`drawer.js` `closeOnEscape()`, `Drawers.getDrawerInstanceForNode().closeDrawer()`), which gives the
  focus back to the menu button: Boost's drawers module listens for no key of its own (D71); closing the drawer puts
  it back on the root. Boost's drawer holds no focus trap, and the theme adds none. The slide exists only under
  `prefers-reduced-motion: no-preference`.
- **"Continue where you left off"** is one web service, `theme_dimensions_get_drawer_summary`
  (`classes/external/get_drawer_summary.php`, read-only, `readonlysession`, no parameter, the session's own user
  only, the guest refused), asked once per page on Boost's `theme_boost/drawers:shown` for
  `theme_boost-drawers-primary`, never on page load. Its delegate, `classes/local/drawersummary.php`: the most
  recent item of `block_recentlyaccesseditems` while that block is installed and enabled, decided again on the course
  module it links (`uservisible` now, in a course the reader can access); else the course entered last (core's
  `course_get_recent_courses()`); else no card. Either way "X of Y completed" while completion is enabled and the
  person is tracked, from the counts core's progress percentage divides. The helper records nothing under "log in
  as", so that session gets the course card. A skeleton while loading; a failure removes the card quietly. The
  service answers the card alone (`resume`) and counts nothing about the person (D77).
- The stylesheet is `scss/drawer.scss`, every rule nested under `.dimensions-drawer`, colours from the family tokens
  with Bootstrap's as the fallback. The rows are the side layer's: 40px, 44px under a coarse pointer, at 0.875rem; the
  current row in the brand's tint and ink, with no mark on its edge; every control carries the theme's 3px ring.
  A row, the back control and the parent's link answer the pointer with the family's hover (the Design System's
  `hover.md`, D103): `--dimensions-hover` and `--dimensions-pressed`, the brand's ink, the leading icon 2px and a
  chevron 3px towards the end (mirrored under `.dir-rtl`), 150ms in and 250ms out, no motion under
  `prefers-reduced-motion: reduce`; the current row stays as it is.
- **The card is the sparkle ground** (`sparkle-ground.md`, D104, D106): a gradient from `--dimensions-ground-warm`
  through the surface to the brand's tint inside a 1px tint edge at radius lg, three inline-SVG sparkles hidden from
  assistive technology, a 36px brand tile with the glyph of the course's kind
  (course, event or trail: the summary's `kind`, shown through the card's `data-kind`), the kicker in the brand's ink, the title at 1rem / 600,
  the detail muted, an arrow at its end, and a 4px rail on the inset ground under the brand's fill; hovered, its edge
  takes the brand's fill and elevation sm, and its arrow moves 3px. Every `data-region` `drawer.js` fills and the
  `--norail` and `--ready` classes are unchanged, so the module did not change. The count stays in the detail line
  ("X of Y completed"), which the service already composes.
- **The drawer is a `nav` landmark named "Site navigation"** (`drawer_nav`), keeping `id="dimensions-drawer"` and
  `data-region="dimensions-drawer"`, which Behat and the module find it by.
- **The drawer's width is the theme's `$dimensions-drawer-width`** (20rem, 320px, at most 85vw), set in
  `scss/post.scss` on `#theme_boost-drawers-primary.drawer-primary.drawer-left` below md and read by the compact
  navbar's copy of the geometry (`scss/compactnav.scss`, phase 4). Core's `$drawer-left-width` (285px) is never
  redefined: it also places the course index's docked drawer from lg up
  (`post_scss_test::test_the_navigation_drawer_is_the_side_layers_width`).

**The colour mode finding.** Core 5.3's `\theme_boost\colour_mode` is public: `can_choose_mode()` (the experimental
`theme_boost/enablecolourmodes`, off by default, and a session that can store a preference), `get_current_mode()`,
`get_modes()`, `get_cookie_attributes()` and the preference `theme_boost_colourmode`, which
`theme_boost_user_preferences()` declares so the preference web service may write it. Core's AMD
`theme_boost/colourmode` listens on the document for `[data-action="set-colourmode"]` and writes `data-bs-theme`,
`aria-checked` and `.active` in every `[data-region="colourmode-menu"]` (which must carry
`data-cookieattributes`), the preference and the cookie; it registers only after `init()`. So a switch of the theme's
is a radio group carrying those hooks, drawn only while `can_choose_mode()`, and the theme writes nothing: the arrow
keys press core's option. That switch is the profile layer's (`profilelayer::appearance()`), not the drawer's.
`amd/src/profilelayer.js` calls core's `init()` where the layer carries it and then writes
`dimensions-profilelayer-modes` on the body, by which `scss/post.scss` hides the navbar's own colour mode menu and the
divider after it at every width, since the user button opens the layer everywhere; a page the module never ran on
keeps the navbar's menu. The drawer carries no colour mode control, so no rule keys on it
(`post_scss_test::test_the_phone_navbar_is_quiet_and_carries_no_twin_control`). Language follows
`\core\output\language_menu` (the `langmenu` setting, a course that forces its language, fewer than two languages)
with the addresses it builds, in the layer's Language sub-view (`profilelayer::language()`).

**B1 is deferred to phase 3.** The course index already has core's trigger, the floating toggle at the page's left
edge (`drawer-toggles` in Boost Union's `theme_boost/drawers`). Its place in this design is the context bar (C2),
which phase 3 builds as a layer swap of the same 60px bar. A trigger now would be a third position, to be moved
again in phase 3, or a copy of Boost Union's navbar or drawers. And there is no room for it: a logged-in learner's
bar on an activity page at phone width (Boost Union's `theme_boost/navbar` markup, and the Behat capture at Selenium's
mobile size) already carries the drawer toggle, then in `#usernavigation` the colour mode menu (when enabled), the focus
mode entry, the notification bell, the messages toggle and the avatar, with dividers between the groups.

Verified on 2026-10-19: the Behat feature `tests/behat/theme_dimensions_drawer.feature` (2 scenarios, 39 steps) on m503;
captures of the drawer at mobile size taken from throwaway Behat scenarios (the root in light and dark, a sub-panel),
which found one defect, fixed: a sub-panel shorter than the drawer ended in a band of the drawer's grey; the thirteen
`drawer_*` and `js_drawer_*` gates swept with `mdl mutate --fast` on PostgreSQL and MariaDB, each reddening its named
test on both. Not verified: the slide's motion itself (a capture shows a settled state) and a real phone.

Boost Union's "Dynamic courses" smart menu item with no condition lists the site course too (its query selects every
row of `{course}`); the drawer shows what Boost Union hands it, so that is Boost Union's to filter.

## Phase 3 as built: the bar that follows the reading

| Idea | What ships |
|---|---|
| C3 | The 3px band is the reading progress. On an activity page it fills from the left with the share of the page scrolled; on the course page it shows the reader's completion of the course, static and computed on the server; everywhere else it stays full, as before. A faint track (the band's own colour at 28 %) shows behind the fill on those two kinds of page. |
| C2 | The context bar, on by default: past the page header the navbar's content swaps, at the same 60px, for a layer carrying the activity's name (the course's on the course page), then "course name · X of Y completed", a read-only status chip ("Pending" / "Completed") and, where the course index exists, a control that opens it (B1). Scrolling up restores the global bar. |
| C1 | Auto-hide, off by default: an administrator ticks the page kinds (activity pages, the course page). There the navbar slides up on the way down and comes back on the first way up; its 3px band stays at the top, still showing the progress, and every offset under the bar moves with it. |
| C5 | Out of this phase. |

Mechanism:

- **One class decides, `classes/local/readingbar.php`, renderer-free.** `kind()` names the two page kinds: an activity
  page (the incourse layout, a module context and the course module set, as focus mode anchors it) and the course page
  (the course layout under a `course-view-*` page type, so `course/section.php` is left alone); never the site course,
  whose activities and home belong to the front page. `applies()` is the base of all three (the band): a page kind, not
  the Moodle app, not focus mode. `context_applies()` adds the setting `navbarcontextbar` (only a stored `'0'` is off)
  and refuses a visitor while `guestnav::applies()`: the layer would cover the "Log in" and "Create account" the bar is
  there for, and the decision is guestnav's own, so a site with those actions off gives its visitors the layer.
  `autohide_applies()` reads `navbarautohide` (an `admin_setting_configmulticheckbox`, empty by default; an unknown key
  is dropped) and is false on the Behat site. `body_classes()`, `client_config()` and `context()` are what the renderer
  hands on. The category showcase is excluded by `kind()` (its layout is `coursecategory`).
- **The layer is not a copy of Boost Union's navbar.** `navbar_plugin_output()` appends
  `theme_dimensions/navbar_readingbar` after the visitor's actions, so it is printed inside `#usernavigation`, and
  `scss/readingbar.scss` positions it absolutely over the whole nav. That works because nothing between
  `#usernavigation` and the nav is positioned: verified on the compiled sheet of m503 (core, Boost Union and the
  theme set no `position` on `#usernavigation`, `.navbar-nav` or `.container-fluid`), and the nav itself is fixed, so
  it is the containing block. The layer is transparent (the nav's own fill shows through, whatever the navbar colour)
  and keeps off the band's 3px.
- **The swap is one class on the nav, `dimensions-ctx-on`, written by `amd/src/readingbar.js`.** The global bar's items
  (`.container-fluid`'s children but `#usernavigation`, and `#usernavigation`'s children but the layer) get
  `visibility: hidden; opacity: 0` in place, so nothing reflows; the layer gets the reverse. The transition (opacity
  and visibility, `--dim-ds-motion-fast`) exists only under `prefers-reduced-motion: no-preference`; otherwise it is a
  swap. Visibility goes with the opacity, so the hidden half leaves the tab order and the accessibility tree.
- **The module.** One passive `scroll` listener (and `resize`) that only books an animation frame; `decide()` reads the
  layout first (the scroll, the page header's box, whether the bar is busy) and writes after. Thresholds by distance
  and direction: down travel past `#page-header` of 24px swaps to the layer, 96px hides the bar (auto-hide), 16px up
  restores the global bar and brings a hidden bar back; above the page header the global bar always stands and no travel
  is counted (until 2026-10-22 it was counted from the top, so the swap and the hide landed together the frame the
  header left). The travel resets when the direction flips. Nothing changes while the focus is in the nav, an element of the nav is
  `aria-expanded="true"` or a `.dropdown-menu.show` is in it, the message drawer is open, or a fixed drawer lies over the
  page (any one below core's `isSmall()` boundary, 991px; from there the course index and block drawers are docked and
  are no reason, the others still are - core's `isLarge()`, 1400px, held the bar for good between the two until
  2026-10-22). The keyboard reaching a hidden bar (`focusin` on the nav) brings it back. The band's fill is
  `--dimensions-band-progress` on the nav, the one style the module writes.
- **The band**: `scss/post.scss`'s `::after` reads `transform: scaleX(var(--dimensions-band-progress, 1))` with the origin
  at the left (the RTL build flips it), so an unset property is the full band of every other page; `--dimensions-navbar-band`
  is still the colour. The track is a `::before` of the main navbar (never Boost Union's menu bar, also a
  `.navbar.fixed-top`) under `body.dimensions-readingbar`, the band's colour at `opacity: .28` (the palette has no tint of
  it and stylelint refuses `color-mix()`).
- **The status chip is read-only and core's.** `readingbar::activity_status()` asks `cm_completion_details` (as core's
  activity header does): pending or complete for a tracked logged-in account, nothing for a visitor, the guest account,
  an activity without completion or a person completion does not track. It is shown whether or not linear navigation is
  on: a read-only status cannot duplicate the footer's button. The module follows core's own
  `core_course:manualcompletiontoggled` event for the same cmid and flips `data-state`; both words are in the markup and
  the stylesheet shows one, so the client composes no text. **It must not carry core's `data-region="completion-status"`**:
  core's `activity_header` module replaces the FIRST such element of the page with `querySelector`, and the navbar comes
  before the page header, so core would overwrite the chip with its own indicator. The "X of Y completed" of the sub line
  follows the same event (since 2026-10-22): the server hands its two numbers on `data-region="dimensions-ctxbar-progress"`,
  the module moves the completed one by one per toggle and says it again with the server's `drawer_progress` string.
- **B1, the course index control**, carries core's toggler attributes (`data-toggler="drawers" data-action="toggle"
  data-target="theme_boost-drawers-courseindex"`) and is drawn only where `readingbar::has_course_index()` holds:
  `core_course_drawer()`'s clauses (the page did not hide the index, and `course_format::uses_course_index()`).
  **The module removes `data-toggler` while the layer is hidden and puts it back when the layer shows.** Boost's
  `drawers.js` finds a drawer's toggle with `querySelector` (`getDrawerOpenButton()`), the first in the document - this
  control, the navbar coming first - and focuses it when the drawer closes; hidden, the control cannot take the focus,
  which would be lost (closing the course index with Escape on a phone after opening it from the floating toggle).
  Without the attribute Boost finds its own floating toggle. On the Behat site, where the layer never swaps, the control
  keeps the attribute.
- **C1's offsets.** `--dimensions-navbar-offset` is declared under `body.dimensions-autohide` (written by the server only
  where the setting allows, never on the Behat site): 60px at rest (`$navbar-height`), none while the module has put
  `dimensions-navbar-away` on the body. Away, the nav takes `transform: translateY(calc(3px - 100%))`, so its last 3px -
  the band - stay on the screen; the transform is set only while away, because a transform makes the nav the containing
  block of every fixed element inside it. Every `$navbar-height` consumer of core's `drawer.scss`, `layout.scss` and
  `modules.scss` that sits under the bar on these pages reads the property, each rule a copy of core's with the offset in
  place of the height: the drawers below `lg` and from `lg`, the anchored drawers of `limitedwidth` and
  `pagelayout-standard` (the course page and most activity pages are `limitedwidth`) and their togglers (from `md`; below
  `md` core puts them at the bottom), the message drawer, `#quiz-timer-wrapper` and `.path-mod-assign .gradingtable thead
  tr`. **Core has no `scroll-padding-top`**: it spells the anchor offset as `scroll-margin-top` on `:target, :focus`
  (`drawer.scss`), so that is what reads the property. `#page`'s margin does not change: the content must not jump. Plugin
  CSS precedence: the scope `body.dimensions-autohide:not(:has(.navbar.boost-union-menubar))` is (0,3,1) before the rule's
  own selector, above every core rule it copies and Boost Union's menu-bar rules; no `!important`. The zero is
  `calc($navbar-height * 0)` because a bare zero needs a unit in `calc()` and stylelint warns on `0px`.
- **Auto-hide stands down while Boost Union's menu bar is on the page** (the CSS scope's `:not(:has(...))` and the
  module's check alike): Boost Union moves the navbar, the page and the drawers down by the menu bar's height under
  `.theme-boost-union-smartmenu`, and folding that height into these rules would mean a `0px` fallback stylelint warns on.
- **Off on the Behat site, in two places.** The server never writes `dimensions-autohide` there
  (`readingbar::autohide_applies()`, which takes the answer as a parameter so PHPUnit can test both sides), and the
  module never swaps or hides with the scroll when `config.behat` is set: a scenario that scrolled past the page header
  would otherwise find the global bar's controls (the edit switch, the user menu) hidden under the layer. The Behat
  feature shows the layer with the theme's step `I show the context bar of the navbar`, which writes the class the
  module writes. The band still follows the scroll there; it moves nothing.

Tests: `tests/local/readingbar_test.php` (every clause with its control: page kinds, the setting's default and explicit
`'0'`, focus mode, the app, the site course, the visitor, auto-hide's kinds and the Behat switch, the body classes,
`uses_course_index`, the status from core, the title and sub line, the course page's floored percentage);
`tests/output/readingbar_render_test.php` (the real header through Boost Union's layout for an activity page and the
course page: the layer inside `#usernavigation`, the body classes, the module's arguments read from the requirements
manager; the setting off, a visitor, focus mode, auto-hide on one kind); `tests/local/readingbar_source_test.php` (the
module's invariants by spelling and the build's currency); `tests/local/readingbar_scss_test.php` (scopes, no literal
colour, reduced motion, the swap, the band's scale, every offset reading one property); the Behat feature
`tests/behat/theme_dimensions_readingbar.feature` (`@theme_dimensions_readingbar`: the layer's markup on an activity page
and its control opening the course index, at mobile size); twenty-one mutation gates (`readingbar_*`, `js_readingbar_*`,
`scss_readingbar_reduced_motion`, `scss_band_scale`), dry-matched, not swept.

Verified on 2026-10-19: the PHPUnit files above and the neighbouring ones on m503, the static gates, and an in-process
compile of the theme's CSS on m503 (the rules above read back from the compiled sheet). **Not verified**: anything in a
browser (the swap, the slide, the thresholds' feel, the track's contrast on a light navbar and in dark mode, the chip's
colours on the navy bar, a real phone), the Behat feature (written, not run; the Behat site's CSS needs `behat-init`
first), the mutation sweep, and the matrix.

## Phase 4 as built: tablet and sheets

Built on branch `navbar-phase4-tablet-sheets` (2026-10-19, uncommitted, next version 2026100515; what was and was not
verified is at the end of this section).

| Idea | What ships |
|---|---|
| B5 | Between lg and xl (md and xl before core 5.3.1+, see the note under this table), while core's primary navigation sends more than two items into its "More" menu, the navbar takes the phone's pattern: core's hamburger, the brand, the right cluster; the primary navigation is hidden and the mobile drawer (phase 2) holds the navigation. Setting `navbarcompact`, on by default. |
| D2 | Below sm, message_popup's notifications popover opens as a bottom sheet: fixed to the bottom edge, full width, rounded top corners, a grab handle, 70vh with the list scrolling inside, never over the navbar, and a scrim. CSS only. |
| D3 | Below sm, Boost Union's user menu opens as the same kind of sheet, with the full name at the top and 44px rows. CSS only. |
| D5 | A reader core lets edit the page (`$PAGE->user_allowed_editing()`) finds core's edit mode switch at the end of the context layer, which covers the navbar's own switch while it is shown. Students get nothing new. |

**Core 5.3.1+ moved the collapse (MDL-90101, Boost Union v5.3-r3).** Core and Boost Union now hide the primary
navigation and show the hamburger below lg (992px), not below md: the toggler is `d-lg-none` and the brand
`d-none d-lg-flex`. From 768px to 991px the navbar is therefore core's own collapsed bar and the compact mode has
nothing to decide there; it lives between lg and xl. The brand rules and the primary drawer's width in `post.scss`
follow the collapse (below lg). The phone menu (`phonenav.scss`), the guest chip and the name in the user menu stay on
md: they are about the room of the right cluster, not about the collapse.

### B5: what is measured, and how

- **The count is core's own.** Core's React navigation (`core/nav/Nav`, mounted as `core/nav/PrimaryNav`, and Boost
  Union's copy for smart menus) renders one `li` per visible pill in `ul.more-nav` and one `li.dropdownmoremenu`, and
  writes its `measuredclass` (`primarynav-measured`) on the mount point once its first split has converged. Its
  overflow count is `rest.length - visible`, where `rest` is the top-level items that are neither dividers nor forced
  into "More". `amd/src/compactnav.js` reads exactly that: the candidates from the mount point's `data-react-props`
  (the same rule as `compactnav::candidates()` on the server), less the pills it still draws. A forced item is in
  "More" at every width and is no sign of overflow. Nothing is read before the measured class is there.
- **What triggers a decision.** A `ResizeObserver` on the navbar's `.container-fluid` (the room changes) and a
  `MutationObserver` on the mount point (core commits a new split from its own observers; a width change can be read
  before it has), plus the `change` of the range's media query. Each only books one animation frame, under core's
  pending tracker; the frame reads the count and the range, then writes. No timer.
- **No flip-flop.** Compact hides the primary navigation with `visibility: hidden`, never `display: none`: its box
  stays, so core goes on measuring the same room with the hamburger shown. With `display: none` core reads a 0-high
  container, sends nothing to "More", and the module would read that as a fit and leave the mode, every frame.
- **The hold (2026100585).** Hiding in place is not enough, because the two modes lay the bar out differently: the
  hamburger takes about 40px, and, when this was measured, the colour mode menu and its divider left the compact
  navbar alone, giving about 60px back (today they leave at every width wherever the profile layer carries the colour
  mode, so they no longer tell the two layouts apart; the hamburger still does). On the calendar page at 1089px (an
  admin, three candidates) the compact layout let the navigation fit two items in "More", the full bar wrapped and sent three, and the module
  flipped between them about 45 times a second, so a reader saw the menu button and the navigation come and go after a
  resize, and two looks a moment apart caught a compact body with a hidden button. A split the navigation makes while
  compact therefore says nothing of the full bar, and the module does not act on it: once compact, the bar stays so at
  any width up to the widest an overflow was seen at (`heldwidth`), and only a wider container or leaving the range
  ends the mode. Widening through the few pixels where only the compact layout fits tries the full bar once and comes
  back (one try at 1096px, measured from 1000 to 1160 in 8px steps); every other width is decided once.
- **The hamburger is shown by the module, not by the stylesheet.** Core's toggler is `d-lg-none`, which compiles to
  `display: none !important` (measured in the compiled sheet of m503); no selector outranks an `!important` utility
  without `!important` of its own, which the fleet forbids. So the module takes `d-lg-none` off the toggler while
  compact and puts it back after; the brand stays (`d-none d-lg-flex`). The range check is in the module
  (`RANGE`, `(min-width: 992px) and (max-width: 1199.98px)`, the stylesheet's `media-breakpoint-between(lg, xl)`;
  `compactnav_source_test` holds the two together) and every rule of `scss/compactnav.scss` sits inside it.
- **Before the first paint.** Decided on the server where it can be: a navigation with two candidates or fewer can
  never cross the line, so such a page gets nothing (no class, no module, no hold) - `compactnav::arms()`, reading the
  layout context's `primarymoremenu`. Where it can, the server splices `dimensions-navcompact-armed` into the layout's
  body attributes (Boost Union builds them before the one `render_from_template()` call that carries the primary
  navigation, so the login page's splice is reused) and arms the module. Between lg and xl the stylesheet holds the
  primary navigation back (`visibility: hidden`) under `body.jsenabled:not(.dimensions-navcompact-measured)` until
  the module has decided: core writes `jsenabled` at the top of the body, before the first paint, so without JavaScript
  the navbar is core's; and core itself already hides the navigation (`opacity: 0`) until its own measurement. So the
  first paint never shows items about to go. What can still move at load is the brand, by the hamburger's width, when
  the decision is compact. A fail-open animation lets the navigation show after two seconds if the module never runs,
  instead of losing it with its hamburger hidden.
- **The drawer at tablet width.** Core's `isSmall()` is below `Sizes.medium`, 991px of body width (not md): below it core
  gives the drawer its backdrop, the press outside that closes and `drawercloseonresize`; from it, everything a phone
  gets is withheld, and closing the other drawers from lg up. While compact the theme gives it a scrim (the drawer's
  own `box-shadow`, which stands down while core's backdrop is on the page, or the page is dimmed twice - measured
  2026-10-22 at 800px), a stack above the docked
  course index and blocks drawer (`$zindex-sticky + 2`, under the navbar), a press outside that closes it (on the page
  the press is the scrim's and goes no further; on the navbar it goes on to its control, as core lets it on a phone),
  another drawer opening that closes it, and the bar leaving the mode closing it unless the focus is inside (its
  hamburger is about to go).
- Off in the Moodle app; focus mode keeps its own bar (its template prints no navbar, and `compactnav::applies()` says
  no there); never on the login, embedded, maintenance and other layouts without the navbar (`guestnav::EXCLUDED_LAYOUTS`).

### D2 and D3: what was measured first

Measured on 2026-10-19 on main (82913e5), an editing teacher on an activity page, the header rendered through Boost
Union's layout on m503's PHPUnit site and the theme's compiled sheet loaded in headless Chromium at 390x844 (and 360,
320, 500, 575, 700 as controls):

- **Nothing overflows sideways at 390.** The bar fits (`#usernavigation` 108.6 to 378 of 390; still fits at 320, edge to
  edge with the hamburger), the notifications popover is 0 to 390 and the user menu 143 to 378.
- **The popover's real defect at 390 is vertical.** Core's rule below 481px pins it `position: fixed; top: 46px; bottom:
  0`, written for a 46px navbar: its first 15px lie over the 61px navbar and its band, across the bottom of the bar's
  controls, and the base rule's `max-height: 80vh` stops it at 721px of 844, leaving 123px of page uncovered below
  it, with no scrim. From 481px to 767px core keeps the 380px dropdown (`right: -70px`), which fits at 500, 575 and 700.
- After: the notifications sheet is 0 to 390 by 253 to 844 (70vh), its header actions and footer 44px; the user menu
  sheet is 0 to 390 by 366 to 844, its name block first, every row 44px. Core's `max-width: calc(100vw - 1rem)` for the
  user menu below sm is overridden (`max-width: none`).

Both are CSS only (`scss/sheets.scss`), scoped to `.navbar.fixed-top .popover-region-notifications` and
`.navbar.fixed-top .usermenu`, every rule under `media-breakpoint-down(sm)`. The scrim is the sheet's own `box-shadow`
(`--dimensions-backdrop`, see below), so a press outside reaches core's handlers: the popover region controller's document click (and Escape) for the
popover, Bootstrap's outside click (and Escape, with the focus back on the toggle) for the user menu. The popover rises
by core's own height transition, dropped under `prefers-reduced-motion: reduce`; the user menu rises by an animation
that exists only under `no-preference`.

### The scrim and the notifications surface (corrected 2026-10-20, from real-Chrome screenshots at 390 and 1024)

- **The scrim was lavender.** The tablet drawer's and both sheets' `box-shadow: 0 0 0 100vmax` read
  `rgba(var(--dim-ds-shadow-rgb), .45)`, and that channel triple is the design system's navy (7, 0, 147), meant for a
  faint elevation at a tenth: at .45 it turned the page purple. The three now read `--dimensions-backdrop`, declared in
  `scss/post.scss` (`rgba(var(--bs-black-rgb, 0, 0, 0), .5)`, and .7 under the html element's dark mode), through that
  fallback chain. It is a name of the theme's own (`family_tokens_test::OWN`), outside the contract with the sibling
  plugins, whose `--dimensions-scrim` is another thing (a veil of the page's own surface).
- **The notifications popover mixed two palettes.** It is rendered inside `nav.navbar[data-bs-theme="dark"]`, so
  Bootstrap's dark mode repainted the container (core paints it `var(--bs-white)`, which the dark mode makes `#343a40`),
  while the ink stayed the page's (`rgb(29, 33, 37)`, from the body: the title, the empty state, the items, 1.41:1), and
  Boost Union's own rules for it (`.navbar.bg-primary[data-bs-theme="dark"] .popover-region-container a .icon`, the
  footer and the "See all" link, all `!important`) assume a LIGHT surface: icons `#343a40` (so the cog and "mark all as
  read" were `#343a40` on `#343a40`, 1.00:1, an empty outlined square on focus), a white footer, a `#0f6cbf` link. The
  desktop dropdown measured the same at 1280, so it was not the sheet's rules. Those rules cannot be outranked
  (`!important`), so the surface is light, and `scss/post.scss` re-points, on the container where Bootstrap reads them,
  the colour mode tokens it uses (`--bs-white`, `--bs-body-bg`, `--bs-body-color`, `--bs-heading-color`,
  `--bs-tertiary-bg`, `--bs-link-color`, `--bs-link-hover-color`) to the design system's light ones, with `color` and
  `color-scheme: light`. Title, empty state, items, the close button and the footer then follow without a colour of
  their own. The user menu sheet stays the navbar's navy dropdown.
- Measured (the real header and the compiled sheet in headless Chromium, 390 and 1280): title and empty state 1.41:1
  before, 16.48:1 after; the cog and "mark all as read" glyph 1.00:1 before, 11.51:1 after; the close glyph 4.89:1
  (light blue, on the dark body) before, 14.68:1 after; the "See all" link 2.15:1 on the dark body before (5.36:1 on its
  white strip), 5.36:1 on a white one after; the unread item 14.85:1.

### D5

`readingbar::edit_applies()` is core's predicate, the one `core_renderer::edit_switch()` asks; `context()` carries it as
`canedit`, and the renderer adds `edit_switch()`'s output as `editswitch` (trusted, triple-stashed). `edit_switch()`
mints a new random id per call, so the layer's switch is a second form with its own React mount and its own
`core/edit_switch` binding, and both work. The navbar's switch comes first in the document, so a lookup by the
"Edit mode" label (Behat's `I turn editing mode on`) still finds that one.

### Tests and gates

`tests/local/compactnav_test.php` (the setting's default and an explicit `'0'`, the app, focus mode, the layouts, the
candidates, the threshold, the splice), `tests/output/compactnav_render_test.php` (the real header: armed with four
custom menu items, nothing with a short navigation, with the setting off, in the app and in focus mode, each beside its
control), `tests/local/compactnav_source_test.php` (the module by spelling: the armed guard, the observers and no timer,
the frame, the count, the range, the toggler, the hold, the press outside, the build), `tests/local/compactnav_scss_test.php` and
`tests/local/sheets_scss_test.php` (scopes, ranges, no `!important`, reduced motion, the sheets' parts and 44px
targets), the D5 cases in `readingbar_test` and `readingbar_render_test`; the Behat feature
`tests/behat/theme_dimensions_navcompact.feature` (`@theme_dimensions_navcompact`, with the theme's step `the site
navigation has "N" custom menu items`, and for the hold `the compact navbar gives its navigation more room than the
full navbar` and `the navbar keeps its mode for a second, and its menu button agrees`, which counts the mode's flips
while the browser waits); twenty-three mutation gates (`compactnav_*`, `js_compactnav_*`, `scss_compactnav_*`,
`scss_sheets_*`, `readingbar_edit_*`), dry-matched; `readingbar_layer_appended` repointed at the renderer's new line.

**The tablet drawer's geometry (fixed 2026-10-20).** From lg up core restyles every `.drawer.drawer-left` on
`.pagelayout-standard` and `body.limitedwidth` (`boost/scss/moodle/drawer.scss`, "Anchored drawers": `left: auto`,
`right: $drawer-anchor-none`, `width: 0`, `overflow: clip`, an anchored `top`, a border radius, a width transition) for
the course index. `#theme_boost-drawers-primary` carries `drawer-left`, so in compact mode it opened 58px wide, cut at
the left edge. `scss/compactnav.scss` makes it the theme's below-md overlay drawer again (`left: 0` open and
`calc(-$dimensions-drawer-width - 10px)` closed, `right: auto`, the theme's drawer width, 320px, rather than core's
285px `$drawer-left-width`, the full height of the viewport, no radius, no clip, core's slide), under
`#theme_boost-drawers-primary.drawer-primary.drawer-left`, which outranks the anchored selectors without `!important`.
The measurement below is of the earlier geometry (core's 285px, under the navbar) and has not been taken again since.
Measured at 1024x768 (the real incourse header and the compiled sheet in headless Chromium, the compact classes applied
by hand, other layouts by swapping the body class): open, x=0 and 285 wide, y=60, 708 high (before, on the standard and
limitedwidth layouts: x=-216, 285 by 238, y=100, radius 6px, clipped; closed: 0 wide); closed, x=-295, fully offscreen,
hidden.

**Not verified**: anything in a real browser driving the module (the compact mode's decision against core's React
navigation, the drawer's scrim and press outside, the sheets' motion), the Behat feature (written, not run), the
mutation sweep, and the matrix. The compact states, the hold and the sheets' geometry were measured on static renders
(the real header and the compiled sheet in headless Chromium, the module's class writes applied by hand), not with the
module running.

### Later

- **Notifications and messages as one control** (the canvas's D2 shows one bell carrying both counts, and a sheet with
  two tabs). Not in this phase: the messages toggle opens core's message drawer (`core_message/message_popover`), not
  a popover, and one control would need core's notification and conversation services behind it, and the drawer's
  own state, from a template of the theme's. To be decided as its own piece.

## The brand on dark surfaces (owner's request, 2026-10-18)

Where the site name stands as the brand, an uploaded logo must have a version for dark surfaces.
Neither core 5.3 nor Boost Union `upgrade-503` has one (no setting, no file area, no renderer method
names a dark logo), so the theme adds it until Boost Union does.

- Two file settings beside Boost Union's: `logodark` (pairs with Boost Union's `logo`) and
  `logocompactdark` (pairs with `logocompact`). A dark file is a variant: with no light logo the
  navbar shows the site name, as today, and the dark file is not used.
- "Dark" is decided by the SURFACE, not only by the site's colour mode: the navy navbar carries
  `data-bs-theme="dark"` in light mode, and Boost Union writes `data-bs-theme="light"` on one of its
  navbar colours. The nearest `data-bs-theme` wins, which inherited custom properties give for free:
  `[data-bs-theme="dark"]` sets the dark image and hides the light one, `[data-bs-theme="light"]`
  sets them back, and the logo rules read the two properties.
- The navbar's `<img>` stays Boost Union's (its template is not copied) and keeps its box and its `alt`. On a
  dark surface its image is moved out of that box (`object-position`, the replaced content being clipped to the
  box) and the dark file is painted as the `<img>`'s own background, contained in the same box. Two simpler
  spellings were dropped on reading the rules that size the brand: opacity on the `<img>` hides its background
  with its image, and a background on the brand link is larger than the image (Boost Union sizes the link and the
  image at 100% of the bar, core caps the image at the bar less its padding, 52px of 60). So the dark file should
  keep the light file's proportions; the settings say so. The mobile drawer heading and the theme's login page use
  the same rule with no template change; the login page's panel and band are navy in either mode, so they take the
  dark variant whatever `data-bs-theme` says. Boost Union's login page shows the full logo (`#logoimage`) on the
  page's own surface, so it swaps in dark mode only.
- Each variant is decided on its own (`classes/local/darklogo.php`): two body classes,
  `dimensions-darklogo-compact` and `dimensions-darklogo-full`, because a flavour sets `look_logo` and
  `look_logocompact` separately and Boost Union resolves each logo on its own field.
- The URLs reach the stylesheet through the extra SCSS callback, as Boost does with its background image:
  theme CSS is rebuilt when a file setting changes. They are pluginfile URLs the theme mints, absolute, passed
  through `styleurl::safe()` like every url printed in a CSS context, and declared on the root with the shift
  that moves the light image, both only while the variant can be painted; `scss/post.scss` hands them to the
  dark surfaces.
- A Boost Union flavour or the multi-tenancy extension that overrides the logo wins: the dark rule
  is armed by a body class written only while the logo in force is the site's own, so a flavour's
  logo is never replaced by the site's dark one. A flavour cannot carry a dark logo of its own until
  Boost Union adds one.

## Later phases (from the canvas)

- Phase 2, the drawer: shipped, see its section above.
- Phase 3, reading: built, see "Phase 3 as built" above (the offset variable is `--dimensions-navbar-offset`, and core's
  anchor offset is `scroll-margin-top`, not `scroll-padding-top`). Sticky course tabs (C5) were left out of it.
- Phase 4: built, see "Phase 4 as built" above. The merge of notifications and messages into one control is left
  for later (its section says why).

Practices every phase keeps: never hide the bar while focus or an open menu is inside it; Escape,
focus trap and focus return on every drawer and sheet, with `inert` behind; `prefers-reduced-motion`
swaps without animation; passive scroll listeners decided in `requestAnimationFrame` with distance
and velocity thresholds; off in the Moodle app; off on the Behat site for scroll-driven hiding, with
the behaviour tested through the body class; colours through `navbarcolorclasses` and the token
chain.
