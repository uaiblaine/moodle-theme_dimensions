# Navbar and navigation drawer: design record

Status: phase 1 merged (#9, 28c5fb4); phase 2 in review on branch `navbar-phase2-drawer` (2026-10-19, version
2026100513; what was verified is at the end of the phase 2 section). Brainstorm:
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
| B2 | The mobile drawer of a logged-in real account (not a visitor, not the guest account, not the Moodle app) is the theme's own: the identity, labelled sections with icons, the current page marked (`aria-current="page"`, a tint and a 3px mark), and a footer with appearance and language. |
| B3 | A row with children opens a sub-panel that slides in, with a back control naming the parent. Three levels and more. |
| B4 | "Continue where you left off": the first block, filled the first time the drawer is shown. |
| D4 | Light, Dark and System in the footer, through core's own colour mode machinery. |
| B1 | Deferred to phase 3 (below). |

Visitors and the guest account keep Boost Union's list with the "New here?" card of phase 1, unchanged. Giving
them the sections and the sub-panels too (as the A4 artboard draws) is an open decision of the owner's.

Mechanism:

- One class decides and builds, `classes/local/drawernav.php`, renderer-free: the setting `navbardrawer` (default
  on; only a stored `'0'` is off), a logged-in real account, guestnav's excluded layouts, not the Moodle app. Its
  `context()` reads only what core and Boost Union already put in the layout context - `mobileprimarynav` and
  `usermenu` - plus three cheap facts of the session.
- `render_from_template()`, guarded by the SHAPE of a logged-in account's layout (`mobileprimarynav` beside
  `usermenu.avatardata`), writes `dimensionsdrawer`. The override of `theme_boost/primary-drawer-mobile` draws
  `theme_dimensions/drawer_nav` while that key is set and Boost Union's list otherwise; Boost Union's child partial,
  navbar and drawers stay theirs, uncopied.
- The identity is core's: the avatars, `usermenu.userfullname` and the metadata lines (under "log in as", the real
  user's name with core's "You are logged in as" line), printed as core's `user_menu_metadata` prints them. The
  theme composes no name and reads no profile field.
- **The role line's rule**: "Site administrator" while `is_siteadmin()` (a read of `$CFG->siteadmins`, no statement)
  at render time, and the number of courses in progress added when the drawer is first shown, from the deferred
  call - core's own timeline classification, the "In progress" filter of My courses
  (`course_filter_courses_by_timeline_classification()`). A per-course role ("Student") is not a site fact and is
  not shown.
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
  level; closing the drawer puts it back on the root. The slide exists only under
  `prefers-reduced-motion: no-preference`.
- **"Continue where you left off"** is one web service, `theme_dimensions_get_drawer_summary`
  (`classes/external/get_drawer_summary.php`, read-only, `readonlysession`, no parameter, the session's own user
  only, the guest refused), asked once per page on Boost's `theme_boost/drawers:shown` for
  `theme_boost-drawers-primary`, never on page load. Its delegate, `classes/local/drawersummary.php`: the most
  recent item of `block_recentlyaccesseditems` while that block is installed and enabled, decided again on the course
  module it links (`uservisible` now, in a course the reader can access); else the course entered last (core's
  `course_get_recent_courses()`); else no card. Either way "X of Y completed" while completion is enabled and the
  person is tracked, from the counts core's progress percentage divides. The helper records nothing under "log in
  as", so that session gets the course card. A skeleton while loading; a failure removes the card quietly.
- The stylesheet is `scss/drawer.scss`, every rule nested under `.dimensions-drawer`, colours from the family tokens
  with Bootstrap's as the fallback, every control a 44px target with the theme's 3px ring.

**The colour mode finding.** Core 5.3's `\theme_boost\colour_mode` is public: `can_choose_mode()` (the experimental
`theme_boost/enablecolourmodes`, off by default, and a session that can store a preference), `get_current_mode()`,
`get_modes()`, `get_cookie_attributes()` and the preference `theme_boost_colourmode`, which
`theme_boost_user_preferences()` declares so the preference web service may write it. Core's AMD
`theme_boost/colourmode` listens on the document for `[data-action="set-colourmode"]` and writes `data-bs-theme`,
`aria-checked` and `.active` in every `[data-region="colourmode-menu"]` (which must carry
`data-cookieattributes`), the preference and the cookie; it registers only after `init()`. So the drawer's switch is
a radio group carrying those hooks, drawn only while `can_choose_mode()`, and the theme writes nothing: the arrow keys
press core's option. Language follows `\core\output\language_menu` (the `langmenu` setting, a course that forces its
language, fewer than two languages) with the addresses it builds.

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
- Phase 3, reading: the band becomes a reading progress line (C3) first; then the context bar (C2,
  same 60px, layer swap, read-only completion status beside core's linear footer), with the course index
  drawer's trigger in it (B1, deferred from phase 2); then auto-hide
  (C1) behind a `--dim-navbar-offset` variable that drawers, anchors and `scroll-padding-top` read,
  with the band pinned at the top while the bar is away; sticky course tabs (C5) if needed.
- Phase 4: tablet compact mode (B5), notifications and messages as one control with a bottom sheet
  on phones (D2), the user menu as a sheet (D3), role shortcuts in the course context bar (D5).

Practices every phase keeps: never hide the bar while focus or an open menu is inside it; Escape,
focus trap and focus return on every drawer and sheet, with `inert` behind; `prefers-reduced-motion`
swaps without animation; passive scroll listeners decided in `requestAnimationFrame` with distance
and velocity thresholds; off in the Moodle app; off on the Behat site for scroll-driven hiding, with
the behaviour tested through the body class; colours through `navbarcolorclasses` and the token
chain.
