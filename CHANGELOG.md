# Changelog

All notable changes to `theme_dimensions` are recorded in this file, in the format of
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased (v5.3-r1-dev)

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
