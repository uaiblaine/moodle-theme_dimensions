# Review context for theme_dimensions

`theme_dimensions` is a Moodle 5.3 theme, a child of Boost Union (and so of Boost), carrying one
organisation's branding and a few behaviours that need no plugin of their own. It overrides: the login page
(its own `loginform` template and a side panel in place of core's `core/login_panel`, on every page of the login
layout; `auth_loginsteps` draws its own screens on its own shell and gets nothing from this theme); where the navbar
shows the user's name (the name itself is core's `fullname()`); the user profile page; the course category pages (a
`course_renderer` override, a hero and a showcase listing); and an optional reduced "focus mode" shell for
activities. It depends on `theme_boost_union`, `local_unlistedcourses`, `local_dimensions` and `block_dimensions` (the last two
declared, not called yet); `customfield_picture` (the badges' and
the share picture's field type) is optional, asked for at run time. It owns one
table, `theme_dimensions_cat` (the branding of a category; its one personal datum is the
`usermodified` authorship stamp, which the privacy provider exports and clears to 0 on deletion).

## Who is trusted

- Site administrators, and holders of `theme/boost_union:configure` for the settings page, are fully trusted.
- The theme **declares no capabilities** (no `db/access.php`). It borrows core's: the category appearance editor
  (`categoryhero.php`) needs `moodle/category:manage`; the profile and listing rules use staff escapes
  (`moodle/user:viewdetails`, `moodle/user:viewalldetails`, `moodle/course:viewhiddencourses`,
  `moodle/course:viewparticipants`).
- What counts as unlisted or public for a course or a category is decided by `local_unlistedcourses`, never by
  this theme. A course editor cannot publish a course by editing a field here.
- Untrusted: visitors who are not logged in, every authenticated user, course and category names, summaries,
  custom field values, identity provider names, and any url that reaches a `style` attribute.

## Surfaces

- **Anonymous surfaces, the part that matters most.** The site keeps `forcelogin` on, yet three things answer a
  visitor: the public landing page of a course (`landing.php`, a 303 to the route
  `/theme_dimensions/landing/{course}` once the router is configured), the public page of a category
  (`category.php` and `/theme_dimensions/category/{category}`), and the images of both through
  `theme_dimensions_pluginfile()`. Neither route sets `requirelogin`; both decide in `request::handle()`.
  Category pages are on unless the setting is stored as `0`; serving the root of the course index is off unless
  stored as `1`.
- Web services, five in all (`db/services.php`), each `ajax` with an empty `capabilities` entry. Four are
  `loginrequired`: `theme_dimensions_get_category_rows` (read), `theme_dimensions_get_card_details`
  (read), `theme_dimensions_toggle_wishlist` (write: core's own favourite) and
  `theme_dimensions_remember_category_state` (write: the reader's own remembered state of one category, a
  user preference, validated against fixed vocabularies and the site's filter fields). Access is decided in the
  function: `require_login()`, refusal of the guest account, then `guard::category()` or `guard::course()` (context
  derived server side, core visibility, then the unlisted predicate). A raw context id is never accepted. The fifth,
  `theme_dimensions_get_public_category_rows`, is not `loginrequired`: it serves a public category's
  listing to a visitor, refuses every logged-in session, and is closed until the site opens it.
- Page scripts: `categoryhero.php` (`moodle/category:manage`), `focus.php` (`require_login`, `require_sesskey`),
  `unavailable.php`, `landing.php`, `category.php`. An `after_config` hook redirects a user who may not discover a
  course or category (on `/enrol/index.php`, `/course/info.php`, `/course/index.php`); it skips CLI, AJAX and web
  service requests. Other hooks: head tags for share previews, the course form (three callbacks), and an observer
  that drops a deleted category's branding row. One adhoc task, `materialise_focusmode_default`, which never throws.
- File serving, which differs by area. Category areas (`categoryhero`, `categorycover`, `categoryogimage`, three
  badges, `categorycovervideo`) go through `branding::resolve()`: category context, id in the path, content hash
  and filename, and `is_valid_image()`; the cover video is checked by its first bytes. Course areas go through
  `publicfiles::resolve()`, which requires the course to be public now and to carry a landing page model: `hero` is the
  course image GD accepts, matched by hash and filename; `ogimage` is the derivative the plugin made, or 404;
  `summary` serves images only and checks no hash; `badge` is matched by hash
  and filename, is not image-checked by this resolver, and is also served to a logged-in viewer who may see the course.
- Compiled or evaluated input: Boost Union's custom SCSS settings are inherited through its callbacks and compiled
  by core; this theme adds no admin-written SCSS. The focus-mode excluded page types are a `PARAM_RAW`
  textarea that is matched, never rendered. The login notice (`loginpanelfoot`) is stored
  raw and passes through `format_text()` into a triple stash.
- Stored data: the branding table and its cache, and five user preferences (focus width, view, the state left on
  each recent category page, folded header, folded course notice) under a `user_preference_provider`. The rank and war name profile fields belong to `local_rank`; this theme creates
  none.

## Facts that look like findings but are by design

- **Visitor refusals are uniform.** A non-public course, an unlisted one and a nonexistent id all answer a visitor
  with the login page before the course is looked up; web service misses share one exception. A different
  answer per case would be an existence oracle.
- **`publicaccess::is_public()` fails closed; `redirector::is_ghosted()` fails open.** Opposite on purpose: one
  withholds a page from the internet, the other must not strand a user who is entitled.
- **The profile callback can only prevent** (`theme_dimensions_control_view_profile()` never returns
  force-allow); its memo key carries the viewer.
- **The theme carries no username mask.** A CPF mask on the login form, if any, is `auth_loginsteps`' (with its own
  `?nocpf=1` bypass); the theme's form keeps core's hooks (`form#login`, `#username`) for it. Failed
  logins are counted in `$SESSION`, never on the account, so no hint tells a visitor which usernames exist.
- **No identity provider image is fetched from the provider**; only a url under the site's own wwwroot is kept.
- **Style attributes are a CSS context.** Two sinks print a url into `style` through `styleurl::safe()`, which
  refuses an ampersand; a third prints colour channels parsed into integers. A new value in a `style` attribute
  must use one of these.
- **Public pages clean what editors wrote.** On a public course the summary and textarea sections are cleaned
  whoever is looking; a triple stash on a public page is a finding unless its value is cleaned.
- **The navbar shows `fullname()` as core composes it**, which is the service name (the alternate name `local_rank`
  keeps) wherever the site's `fullnamedisplay` names it; during "log in as" core shows the real user's name.

## De-emphasise

- `amd/build/**` is minified output of `amd/src/**`; review the source.
- `docs/**`, `mutations/**`, `lang/**` and `tests/**` carry no production behaviour.
- `scss/**` and the visual detail of templates, unless they expose data the viewer should not see.
- `classes/output/core/course_renderer.php` copies parts of core's renderer on purpose; compare with core.
