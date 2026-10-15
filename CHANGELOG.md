# Changelog

All notable changes to `theme_dimensions` are recorded in this file, in the format of
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased (v5.3-r1-dev)

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
