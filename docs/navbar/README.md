# Navbar and navigation drawer: design record

Status: phase 1 in implementation (2026-10-18). Brainstorm: `brainstorm.html` (pt-BR, published as a
private artifact). States canvas: `canvas/` (Design canvas sources, 14 artboards, one row per phase).

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

- Phase 2, the drawer: identity header, "Continue where you left off" (recently accessed items, loaded
  when the drawer opens), sections with labels and icons, slide-in sub-levels with a back control,
  appearance and language in the footer; the course index drawer gets its trigger in the context bar.
- Phase 3, reading: the band becomes a reading progress line (C3) first; then the context bar (C2,
  same 60px, layer swap, read-only completion status beside core's linear footer); then auto-hide
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
