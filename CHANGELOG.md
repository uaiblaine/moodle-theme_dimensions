# Changelog

All notable changes to `theme_dimensions` are recorded in this file, in the format of
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased (v5.3-r1-dev)

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
