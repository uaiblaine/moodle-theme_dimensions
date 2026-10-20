# Changelog

All notable changes to `theme_dimensions` are recorded in this file, in the format of
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased (v5.3-r1-dev)

- **Landing page defaults for new courses** (2026100516). A new *Landing pages* tab sets what a course created from now on
  starts with in three of its *Course landing page* fields: the page model, the modality and the study format, each
  offering that field's own options (as the field is configured now) and *None*, the default. The course form of a
  new course shows them preselected and its creator may change them or leave a field empty; every path through
  `create_course()` (the form, an approved course request, `core_course_create_courses`, tool_uploadcourse) gets
  them. Existing courses never change, and changing a setting writes nothing: the three fields keep their own default
  value at `''`, so a course with no data row reads empty whatever the setting says, and the default reaches a course
  once, as a data row written when it is created and only where it holds none (an explicit empty answer is a row and
  wins). Restore and duplication keep what the backup carried.
  - Settings `landingdefaultmodel`, `landingdefaultmodality`, `landingdefaultformat` (tab `theme_dimensions_landing`,
    between Focus mode and Category showcase).
  - `classes/local/landingdefaults.php` (the choices, the clamped value, the one write) and two hook callbacks:
    `local\hook\course\after_form_submission` (core's `after_form_submission`, new courses only) and
    `local\hook\course\after_form_definition_after_data` (the preselection, new courses only). `db/hooks.php`
    registers both. `landingfields::option_lines()` is now the one split of a select's options, which
    `option_label()` reads too.
  - Eight mutation gates (`landingdefault_*`), dry-matched.

- **Tablet and sheets** (phase 4 of the navbar; 2026100515). Between tablet and desktop
  widths, when the site's navigation would send more than two items into its "More" menu, the navbar takes the phone's
  layout: core's menu button, the logo and the icons on the right, with the navigation in the side menu (B5). The
  decision is the browser's, read from what core's own navigation measured, and the navigation is held back until it
  is made, so the first paint never shows items about to go; at tablet width the side menu then gets a scrim, closes
  on a press outside it and stacks above the docked drawers. On phones the notifications popover (D2) and the user menu
  (D3) open as bottom sheets: full width, rounded top, a grab handle, scrolling inside, a scrim, 44px rows, rising only
  without `prefers-reduced-motion`. In the context bar a reader who may edit the page finds core's edit mode switch
  again (D5). Never in the Moodle app; focus mode keeps its own bar. Notifications and messages are not merged into one
  control yet.
  - Setting `navbarcompact` (Identity tab), a checkbox that ships on and reads as on until an explicit `0` is stored.
  - Every decision of B5 is `classes/local/compactnav.php`; the renderer splices `dimensions-navcompact-armed` into the
    layout's body attributes and arms the new `amd/src/compactnav.js`, which writes `dimensions-navcompact-measured`,
    `dimensions-nav-compact` and takes `d-md-none` off core's toggler while compact. New `scss/compactnav.scss` and
    `scss/sheets.scss`. D5 is `readingbar::edit_applies()`, core's `user_allowed_editing()`, with core's
    `edit_switch()` rendered into `theme_dimensions/navbar_readingbar`.
  - Two visual defects of the first build, found in real-Chrome screenshots at 390 and 1024: the scrim of the tablet
    drawer and of both sheets was the design system's navy shadow tint at .45 (the page turned purple), and is now
    the neutral `--dimensions-backdrop` (core's modal black, .5 on a light page and .7 on a dark one, through
    `--bs-black-rgb`; a theme-own token, not part of the family contract). The notifications popover was unreadable
    inside the dark navbar (dark ink on the dark colour mode's `#343a40`, an invisible cog, a white footer): it is now
    one light surface, through the colour mode tokens re-pointed on `.navbar .popover-region-container`, on phones and
    on the desktop dropdown alike, which had the same defect.
  - The tablet drawer opened 58px wide, cut at the left edge, on the standard layouts and on `body.limitedwidth`: from lg
    up core anchors every `.drawer.drawer-left` (zero width, an anchored `right`, `overflow: clip`, a top under the page
    title, rounded corners) for the course index, and the primary drawer is a `drawer-left` too. In compact mode it is
    again the plain overlay drawer core gives it below md (left edge, offscreen when closed, under the navbar, the
    rest of the viewport high), under a selector with the drawer's id that the anchored ones cannot outrank. Gate
    `scss_compactnav_primary_overlay`.
  - Twenty-one mutation gates (`compactnav_*`, `js_compactnav_*`, `scss_compactnav_*`, `scss_sheets_*`,
    `readingbar_edit_*`), dry-matched; `readingbar_layer_appended` follows the renderer's new line. Behat feature
    `theme_dimensions_navcompact.feature` and the step `the site navigation has "N" custom menu items`.

- **The bar that follows the reading** (phase 3 of the navbar; 2026100514). On activity pages and the course page the 3px band is the reading progress: it fills from the left as an
  activity page is scrolled, and shows the reader's completion of the course on the course page (computed on the
  server); a faint track shows behind it, and every other page keeps the full band. Past the page header the navbar's
  content swaps, at the same height, for a context layer: the activity's (or the course's) name, the course and
  "X of Y completed", a read-only "Pending" / "Completed" chip read from core's completion data (core's linear footer
  keeps the button) and a control that opens the course index (B1) where the course format has one. Scrolling up
  brings the global bar back. Optionally the navbar hides on the way down and returns on the first way up, its band
  left at the top, with the drawers, their togglers, anchors, the quiz timer and the grading table moving up with it.
  The swap and the slide animate only without `prefers-reduced-motion`; nothing changes while the focus is in the bar,
  one of its menus is open or a drawer lies over the page. Never in focus mode, on the front page, in the Moodle app,
  or for a visitor while the visitor's actions are shown; auto-hide never on a Behat site or beside Boost Union's menu
  bar. Sticky course tabs (C5) are not part of it.
  - Setting `navbarcontextbar` (Identity tab), a checkbox that ships on and reads as on until an explicit `0` is stored.
  - Setting `navbarautohide` (Identity tab), the page kinds whose navbar hides (activity pages, the course page); none
    by default.
  - Every decision is `classes/local/readingbar.php`; the renderer appends `theme_dimensions/navbar_readingbar` to
    `navbar_plugin_output()` (no copy of Boost Union's navbar), arms `amd/src/readingbar.js` and adds the body classes
    `dimensions-readingbar` and `dimensions-autohide`. New `scss/readingbar.scss`; the band in `scss/post.scss` reads
    `--dimensions-band-progress`. New theme-owned properties `--dimensions-band-progress` and
    `--dimensions-navbar-offset`.
  - Twenty-one mutation gates (`readingbar_*`, `js_readingbar_*`, `scss_readingbar_reduced_motion`, `scss_band_scale`),
    dry-matched; the sweep is still owed. Behat feature `theme_dimensions_readingbar.feature` and the step
    `I show the context bar of the navbar`.

- **The mobile drawer of a logged-in account** (phase 2 of the navbar; 2026100513). On a phone, the side menu of a
  logged-in account (not a visitor, not the guest account, not the Moodle app) opens on who is logged in - core's
  avatar, name and metadata, and a role line ("Site administrator", and the number of courses in progress) - then a
  "Continue where you left off" card (the last activity opened, else the last course entered, with "X of Y
  completed"), the site's navigation under "Browse" and each Boost Union smart menu as a section of its own, with
  icons and the current page marked. A row with children opens a sub-panel that slides in, with a back control; the
  focus follows both ways, Escape goes back one level, hidden panels are inert, and reduced motion swaps without a
  slide. The footer carries Light, Dark and System while core's colour modes let the person choose (core's own
  `theme_boost/colourmode` writes the mode), and the language while core's language menu offers more than one.
  Visitors and the guest account keep Boost Union's menu with the "New here?" card. The course index trigger (B1)
  moves to phase 3's context bar.
  - Setting `navbardrawer` (Identity tab), a checkbox that ships on and reads as on until an explicit `0` is stored.
  - Every decision is `classes/local/drawernav.php`; the renderer writes `dimensionsdrawer` by the context's shape
    (`mobileprimarynav` beside `usermenu.avatardata`) and the override of `theme_boost/primary-drawer-mobile` draws
    `theme_dimensions/drawer_nav` (re-diff note updated). `amd/src/drawer.js` runs the panels; `scss/drawer.scss`.
  - New web service `theme_dimensions_get_drawer_summary` (read-only, `readonlysession`, asked once per page when
    the primary drawer is first shown), delegate `classes/local/drawersummary.php`.
  - Thirteen mutation gates (`drawer_*`, `js_drawer_*`), each reddening its named test on PostgreSQL and MariaDB.

- **The visitor's navbar: "Log in" and "Create account"** (phase 1; 2026100512). A visitor
  who is not logged in, and the guest account, no longer see core's "Log in" link and "You are currently using
  guest access" in the navbar: two actions stand at its right end instead, "Log in" (an outline pill) and
  "Create account" (a filled one), the second only while the site takes sign-ups (core's `signup_is_enabled()`,
  so a plugin that answers core's sign-up page, auth_loginsteps among them, is followed through it). The guest
  account also gets a small "Guest" chip that opens core's sentence and what a guest can and cannot do. Below
  the md breakpoint only "Log in" stays in the bar, and the mobile drawer opens with a "New here?" card offering
  both (the theme now overrides `theme_boost/primary-drawer-mobile`: Boost Union's copy plus that card; re-diff
  it on every Boost Union update). Never in the Moodle app or on the login, maintenance, embedded, popup and
  secure layouts.
  - Setting `navbarguestactions` (Identity tab), a checkbox that ships on and reads as on until an explicit `0`
    is stored, so no upgrade step writes it. Switched off, the navbar is Boost Union's.
  - Every decision is `classes/local/guestnav.php`; the renderer appends the actions to `navbar_plugin_output()`
    and empties the user menu by the context's shape (`usermenu.unauthenticateduser`). Nine mutation gates
    (`guestnav_*`), each reddening a test on PostgreSQL and MariaDB.

- **The brand on dark surfaces** (2026100512). Two file settings on the Identity tab,
  `logodark` and `logocompactdark`, give Boost Union's logo and compact logo a variant for dark surfaces: the
  compact one stands in the navy navbar, in a dark Boost Union navbar colour, in the mobile drawer's heading in dark
  mode and on the theme's login page (its panel and its band are navy in either mode); the full one on Boost
  Union's login page in dark mode. The nearest `data-bs-theme` decides which surface is dark, so a navbar Boost
  Union paints light keeps the light logo in a dark page. A dark file is a variant: without its light partner in
  Boost Union it is not used, and a flavour (or the multi-tenancy extension) with a logo of its own keeps it,
  variant by variant. No template is copied: the light `<img>` keeps its box and its alternative text, its image is
  moved out of the box and the dark file is drawn as that image's own background, so the dark file should keep the
  light file's proportions.
  - Every decision is `classes/local/darklogo.php`: the address (config and file settings only, so the extra SCSS
    callback can ask it on the command line; through `styleurl::safe()`), the logo in force (Boost Union's flavour
    lookup, asked by the renderer only), the body classes `dimensions-darklogo-compact` and
    `dimensions-darklogo-full` the renderer adds in `body_attributes()` (the login layout included), and the two
    file areas `theme_dimensions_pluginfile()` serves from the system context to anybody, as Boost Union serves its
    logos. Seven mutation gates (`darklogo_*`), each reddening a test on PostgreSQL and MariaDB.

- **The filter line previews what a filter would keep** (`2026100511`): the filter-line redesign's stage T4 (contract
  section 6), for both the logged-in and the visitor's live client, through a module of its own (`categorypreview`)
  that the shared line module arms.
  - Pointing a mouse or a pen at a scope cell, the favourites star, an option of the Filters panel (a state, a field's
    value, a quick date) or a suggestion of the search - or reaching one with the keyboard, the arrows included in the
    suggestions - lights the cards on screen it would keep with a 2px ring in the accent and lets the others recede;
    leaving or blurring takes every mark away. A finger never previews (a tap is a press), a filter already in force
    or one that narrows nothing previews nothing, and no request is made: a list the server pages lights only what it
    has drawn.
  - What a filter would keep is decided from facts the server now writes on every card, on the page and in the rows
    answer (so a card the client renders carries them too): the state chip the course counts under, each filter
    field's value and the start day in the site's timezone; the favourite is the card's own star. A card states only
    what its reader may already see on it: a visitor's card carries no chip and no star, only the fields core shows a
    visitor (plus the model and the modality, which the card prints) and a start day only on an event, whose date tile
    prints it. A card that does not state the fact a filter is decided on makes the preview stand down. With the page
    cut in SQL (`categorystatesort` off) the cards state no field. The facts cost no statement (`budget_test`, flat at
    11 reads for four cards and for twenty-four with a field of each visibility).
  - A receding card remaps its own tokens - surface, inks, line, state pills, star, call to action and a film over its
    image - never its opacity, with every fill named with its ink and dark values of its own; the surface eases only
    where motion is welcome.

- **The filter line folds, searches over itself and suggests filters** (`2026100510`): the filter-line redesign's
  stage T3, for both the logged-in and the visitor's live client, through one shared module (`categoryline`).
  - The line measures its own width (never the viewport's) and takes the first step at which it fits: full; Filters
    keeps its icon and badge and loses its word (still its accessible name); the search becomes an icon button
    ("Search courses"); two lines, the scope on the second at full control height, scrolling sideways with a fade on a
    hidden side, and the view toggle moved into the Organise menu as "Show as". It measures with every cell of the scope
    drawn (the server now writes a cell with nothing to offer, hidden) and with no transition, so the step follows the
    width and the language and never the counts. Without JavaScript nothing of this applies and the line wraps as before.
  - From the third step the search grows over the line: the other controls slide out (none under reduced motion), a
    "Close search" button takes the last place, Escape closes it and the focus returns to the control that opened it.
    A term left in force while the field is folded away shows as a chip under the line, built by the server's one chip
    builder (labelled with the term, its address dropping `q` alone) and shown by the stylesheet only then.
  - Suggestions: one listbox under the line (the field is its combobox): every scope cell, the favourites star, every
    option of the Filters panel and its quick dates whose label or group matches the typed text, accent- and
    case-insensitively, word starts first and then by count, each with its group and count; up to five filters with
    the highest counts while the field is empty ("Start with a filter"). Choosing one applies it as pressing that
    control does, the term dropped in the same request, and keeps the search open. The counts are the last answer
    given while the field held no term (the server's, never the browser's).
  - Every floating layer (suggestions, the Filters panel, the Organise menu) is placed under the line's measured
    bottom; on the two-line step the Filters panel is a bottom sheet (full width, at most 80% of the screen, scrolling
    inside itself, the date calendar opening whole in its flow). A half-hidden item of a row that scrolls sideways (the
    scope's line, the chips under the line) scrolls itself into view when pressed or focused. `/` focuses the search
    while the line is on screen and the focus is in no field.

- **The toolbar is one filter line** (`2026100509`): the filter-line redesign's stage T2.
  - The three rows (narrow, organise, count) become ONE server-drawn line in the contract's slot order: the scope
    (the state chip's All, My courses, Open for enrolment and Scheduled as counted cells of a platter, for a logged-in
    reader with the state sort on), the pill search with an eraser ("Clear text", shown with JavaScript while the field
    holds text), the favourites star, Filters (sliders icon, the word "Filters", the badge), Organise (a menu holding the
    sort chips and the no-JavaScript Apply), the view toggle (icons only, List first) and the managers' menu. The line
    wraps where it does not fit; every control still submits without JavaScript, and the name-less default submit is
    still the form's first.
  - Under review and Free access are the panel's "More statuses", the same radio group as the scope, so one
    choice holds across both; `criteria::SCOPE` is the split, mirrored as `SCOPE` in the shared client.
  - The Filters panel is closed on load and is a popover under the line from 576px (inline below). Its badge counts
    what the panel holds: each custom field, the period as one and a "More statuses" state - no longer the search, a
    scope value, the star or the tree.
  - A second row (`dim-line-chips`) shows while anything narrows: the count sentence (kept visually hidden at rest as
    the live region), one chip per criterion the line does not show - each field, the period, a "More statuses" state,
    the tree's category - each a link dropping that one criterion, "Clear all" from two criteria up, "See all as a list"
    and the automatic opening's note. The chips are built by one server builder for the page and both web services
    (`chips`, `clearall` in the rows answer), and the calendar no longer writes a pill of its own.
  - The phone's copy of the view toggle is gone.
  - Fix: the Filters panel no longer scrolls inside itself (no `max-height`, no `overflow-y`), so the date calendar that
    opens inside it is never clipped at any width.
  - Fix: on a phone the hero's "More" menu opened hidden: the tab rail was a scroll container (`overflow-x: auto` below
    768px, which clips the dropdown to the rail's 50px) and the filter line (`z-index: 30`) painted over it; the rail
    is no longer a scroll container and stacks at 31.
  - Fix: a scope cell that was absent and came back was appended at the end of the line; the shared painter now keeps
    the cells in `SCOPE`'s order (and "More statuses" in the answer's), moving only what is out of place.

- **Favourites are a toggle of their own, combined with the state** (`2026100508`): the filter-line redesign's stage T1.
  - The favourites stop being a value of the state chip and become a boolean criterion, `fav=1`, ANDed with the state:
    "my favourites among the courses I am enrolled in" is one request now. It is read only for a real account (never a
    visitor nor the guest), honoured where the state chip is (a logged-in page cut in PHP) and dropped on the request
    elsewhere, counted as one filter on the badge, carried by the pager, the quick dates and a panel's call to action, and
    dropped by "Clear filters".
  - Each criterion is counted with the other applied: every state pill under the favourites in force, the favourites
    under the state chip in force.
  - The toolbar draws a star toggle on the search line, right before the Filter control (`data-region="dim-line-star"`,
    `aria-pressed`, the course card's own star, its accessible name "Favourites" with the number), only for a real
    account where the state group is offered. Without JavaScript it is a submit button posting `fav` with the value a
    press turns it to, and a hidden field carries `fav=1` through every other submission while it is on. The
    favourites pill of the state group, its star and the web service's `isfavourites` key are gone.
  - `get_category_rows` and `remember_category_state` take `fav` (a boolean); `chip` no longer accepts `favourites`.
    The rows answer carries the toggle (`favourites`: `available`, `pressed`, `count`); the visitor's service declares
    no `fav`. The remembered state stores the toggle under its own key (`fav`, true or absent).
  - Older addresses and remembered states keep working: `chip=favourites` on an address, and a remembered entry holding
    the favourites as its state, are read as the toggle with no state.

- **Default category hero colour `#0a4a85`** (`2026100507`) (9.01:1 opaque; 5.08:1 over a white photograph at the default 78% overlay).
  - Replaces `#0f6cbf` (5.36:1 opaque, 3.57:1 over white at 78%): `branding::DEFAULT_BG`, the `bgcolour` column default,
    the templates' examples and the tests' numbers. The folder watermark on the default band is .20 again (was .09). A 60%
    overlay cannot reach 4.5:1 with any colour (3.27:1 here), so it stays a manager's explicit choice that D31 measures.

- **The competency path clears core's floated "Path:" label** (`2026100507`): on the course competencies page the
  "Upon course completion" selector no longer wraps beside the label.

- **The competency framework's pages and dialogues in the theme's design** (`2026100506`).
  - `scss/competency.scss`, concatenated by `lib.php`: every top-level selector scoped to the body classes
    core derives for tool_lp, report_competency and block_lp's scripts (`path-admin-tool-lp`,
    `path-report-competency`, `path-blocks-lp`), to `.block_lp` or to the theme's own `dim-comp-` classes;
    colours from the family tokens `--dimensions-*` and Bootstrap only, radius and spacing from the design
    system; every ground states its ink, and the progress bar's fill is restated for the dark mode (the
    brand ink). Core's proficiency badges, where the template stays core's, get the pill's shape and an
    icon per state.
  - Restyled copies of twenty core templates, each naming the 5.3 file it mirrors: tool_lp's
    `progress_bar`, `competency_summary`, `competency_path`, `evidence_summary`, `user_summary`,
    `linked_courses_summary`, the three navigation templates, `related_competencies`,
    `course_competency_statistics`, `template_statistics`, `competency_grader` (the rating dialogue) and
    `user_competency_summary`; report_competency's `report`, `user_competency_summary` and
    `user_course_navigation`; block_lp's `summary` and its two review pages. Every data-region,
    data-action, data-* attribute, id and form name core's AMD and Behat read is kept. Proficiency is said
    in words with an icon (the breakdown report's green cell is gone); a progress bar is named by its own
    sentence and prints its percentage. No renderer is overridden.
  - One language string, `competency_notproficient`. Tests `competency_scss_test`,
    `competency_templates_test`, `competency_render_test`, the Behat feature
    `theme_dimensions_competency.feature`, and mutation gates `comp_grader_rate_action`,
    `comp_evidence_delete_action` and `comp_report_cell_hook`.
- **Family design tokens `--dimensions-*`; crests answered through block_compass's hook** (`2026100505`).
  - `scss/post.scss` declares the 54 family tokens local_dimensions and block_dimensions will read as
    `var(--dimensions-x, <their own chain>)`: 33 shared (surfaces, line, ink, accent, the brand, the focus
    ring, the five tone families, shadow, scrim) and 21 read by local_dimensions alone (motion and the six
    enrolment states, which are the category showcase's state badges in both modes). Light values on
    `:root`, `body` and `.modal`; dark values on the same roots under `:root[data-bs-theme="dark"]`. One
    new design system token, `--dim-ds-shadow-rgb` (the navy's channels). `docs/tokens.md` is the public
    contract, and `tests/local/family_tokens_test.php` holds the names, the roots, the values and the
    document against each other.
  - block_compass asks for the crests of its cards through its own hook, `\block_compass\hook\course_badges`
    (5.3): `classes/local/hook/block_compass/course_badges.php` answers it from
    `theme_dimensions_course_badges()`, whose viewer filter is unchanged, and reports a fault instead of
    throwing. The block stays optional: the registration in `db/hooks.php` is inert without it, and CI
    installs `block_compass` (`MOODLE_503_STABLE`) only to exercise the callback. Mutation gates
    `compass_hook_viewer_path` and `compass_hook_catch`.
- **Landing pages, English identifiers** (`2026100504`). The theme has no install base yet, so
  `install.xml`, `install.php` and the field definitions change in place, with no upgrade step:
  - "hotsite" is "landing" in code and "landing page" in prose: `classes/local/landing/`,
    `landingfields`, `output\landing_page`, `output\landing_guestentry`, the route
    `/theme_dimensions/landing/{course}` (`route\controller\landing`), the script `landing.php`, the
    shortlink types `landing` and `landingguest`, the AMD module `landingguest`, `scss/landing.scss`
    and its `dimensions-landing__*` classes, the lang keys `landing_*` and `landingfield_*`, the
    recommendation `dimensions_landingaddresses`, the config key `landingcfcategoryid`. The custom
    field category reads "Course landing page".
  - The provisioned course custom fields have English short names prefixed `dimensions_`
    (`dimensions_model`, `dimensions_modality`, `dimensions_workload`, `dimensions_audience`,
    `dimensions_format`, `dimensions_liveclasses`, `dimensions_crest_1` to `_4`,
    `dimensions_shareimage`, `dimensions_publicdescription`, ...), and every Portuguese identifier
    of the code is English ("brasao" is "crest").
  - The category branding column `chamada` is `tagline`.
  - The course notice's custom properties are `--dim-notice-*`; its own page gate is the "course
    home" (`builder::is_course_home()`).
  - The backup elements of the picture rows are named `picture`.
- **Structural de-brand** (`2026100503`):
  - The login page's CPF mask moved to auth_loginsteps (its setting `logincpfmask`, with the same
    `?nocpf=1` bypass). The theme drops `amd/src/logincpf.js`, the setting `logincpfvalidation` and
    its strings, the bypass parameter and its session key, and the module arming in
    `render_login()`; the login page, its panel, the ways in, the failed-login hint and the local
    identity provider marks stay. The local route reads "Sign in with your username".
  - Neutral default palette: the theme assigns no `$primary` (Boost Union's brand colour, a
    flavour's, or Boost's default decide), and an unbranded category's band is Boost's default
    primary `#0f6cbf` with white ink (5.36:1) instead of the institutional navy, in
    `branding::DEFAULT_BG`, the `bgcolour` column default and the templates' examples. The navbar
    style's "navy" option reads "Dark navbar in the brand colour".
  - customfield_picture is optional: no longer in `$plugin->dependencies`. Without it (or with the
    Picture type disabled) the four badges and the share picture are not provisioned and not drawn;
    the recommendation reports the type as recommended (a notice), and the tests that need it skip
    themselves. CI keeps installing it.
  - Design-system tokens namespaced: the 49 unprefixed aliases declared on `:root`
    (`--surface-page`, `--text-body`, `--text-muted`, `--radius-*`, `--space-*`, ...) and focus
    mode's local scale are `--dim-ds-*` now.
  - Neutral copy: "organisation" for "corporation" in both packs, and generic defaults for the
    login panel's title and the identity provider route's audience.
- **Ported to Moodle 5.3** (`2026100502`): requires `2026100500`, supports `[503, 503]`, on Boost
  Union's `upgrade-503` branch until Boost Union tags its 5.3 release; CI runs `MOODLE_503_STABLE`
  with customfield_picture `master` and local_page `main`.
  - Focus mode follows core's linear navigation (topics and weeks, on by default): previous, next
    and the manual completion button are in core's sticky footer, the bar carries the completion
    status core updates from it, and the theme's own previous/next row is drawn only where linear
    navigation is off, so the page never shows two.
  - The focus page's course index keeps its controls, passed in 5.3's `drawercontrols` block.
  - The navbar shows the user's name once: core 5.3's first name beside the avatar is dropped where
    the theme already shows the full name.
  - The course notice reads the switched role through `\core\user::get_user_navigation_info()`;
    `user_get_user_navigation_info()` is deprecated in 5.3. The profile rule's test asks
    `\core\user::can_view_profile()` for the same reason.
  - Tests: Font Awesome's icon names are read from `lib/bundles/fontawesome`, the category page's
    tab rows are asserted per component (`core/primarymoremenu`, `core/secondarymoremenu`), and
    Boost Union's retired `activitynavigation` setting is no longer set; `loginshell_test` declares
    its coverage with `#[CoversClass]`, as PHPUnit 11 deprecates doc-comment metadata.
- **First public import.** `theme_dimensions` is derived from a private Boost Union child theme,
  renamed throughout for its life as a community theme. Its feature set equals that theme's on
  Moodle 5.2: the custom login page, the category showcase, the course landing pages, focus mode,
  the course notice, the profile access rule, guest access, Open Graph tags and the pages strip.
  The private theme's history is not carried over.
- **The version line restarts at `2026100500`**, release `v5.3-r1-dev`, with Moodle 5.3 as the
  next base once Boost Union releases its 5.3 version.
