# Changelog

All notable changes to `theme_dimensions` are recorded in this file, in the format of
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased (v5.3-r1-dev)

- **block_compass's open-for-enrolment line names each category's crest and tells courses from events**
  (2026100553; block_compass ADR-019, stage S4). Two listeners on hooks the block added in its 2026100524.
  - **`category_badges`** answers each category the line names with the category's first crest, in area order
    with gaps closed, and its file name as the text. It answers only categories the reader may see, by core's
    `core_course_category::can_view_category()`. It keeps a small entry per category (visible flag, context record,
    first crest) in the `categorybranding` cache under `compasscrest_<id>`, so `branding::forget_all()` drops it
    with the branding. A warm answer reads nothing; the misses are read in one statement.
  - **`course_kinds`** names the enrolment page model field (`dimensions_model`), with course for 1 and event
    for 2. The nouns come from four new strings (`compass_kind_course(s)`, `compass_kind_event(s)`). It reads
    nothing, and the block uses the field only while it is visible to everyone, as the theme creates it.
  - Both are registered in `db/hooks.php` as optional, as the two earlier block_compass listeners are.
    PHPUnit under `tests/local/hook/block_compass/`.

- **The calendar's range service** (2026100552), stage C1 of `docs/calendar/PROPOSAL.md`.
  `theme_dimensions_get_calendar_range` answers one month grid (at most 42 days, in the viewer's timezone) in one call:
  core's events, with the exact visibility of core's month view (a parity test per role), each event once with its
  span; the event courses the viewer is enrolled in; and the event courses they could join now, admitted by the
  showcase's own decision layer and local_unlistedcourses' next action. Courses and categories come once each, with no
  image data URI, no formatted HTML and no description; day counts per key. Narrowing by a category's whole subtree or
  by one course, keys that spare the vault where they can, and model, modality, city and "only things to do" filters.
  One deliberate difference from core: a category event of a category local_unlistedcourses withholds is dropped. For
  a student with 25 courses: 531 statements, about 140 ms and 109 KB, against core's month service at 675, 1.2 s and
  11.5 MB. Nothing on a page uses it yet (C2). PHPUnit under `tests/local/calendar/`, 35 mutation gates.
- **The filter line reads less layout** (2026100551). An answer that changes a count without changing its number of
  digits no longer remeasures the line (every count is drawn in tabular figures, so the widths cannot move; the star's
  count, visually hidden, no longer counts at all), and the open suggestion list is placed when it opens or when the
  fold, the search or the width moves, not on every key. The date picker makes one `Intl.DateTimeFormat` per language
  and options instead of one per day drawn, and the linear navigation footer reads no layout on a scroll while the
  footer is already shown. Three Jest tests and one source test assertion.
- **A page of cards is announced to core once** (2026100550). The listing inserted each card it rendered on its own,
  and every insertion fires core's filter event, which Boost answers (`enableTooltips()`) with a `document` keydown
  listener it never removes: about 14 permanent listeners a filter press or a load more, each run by every Escape.
  `renderCards()` now inserts the page's cards in one call into a holder the grid takes them from, as the panel's
  transplant already did. Two Jest tests.
- **The look choice page lists the looks evenly** (2026100549). The form group separated its radios with `<br>`, which core's
  flex row reads as an empty item: after a label wide enough to fill the row it wrapped onto a line of its own, a blank
  line between two looks. The group has no separator now, and `scss/look.scss` stacks the radios on that page alone.
  One PHPUnit test per half, two mutation gates.
- **No line under the navbar's band.** Core draws a 1px gray-300 `border-bottom` on `.navbar.fixed-top`, which Boost
  Union only recolours (to the bar's colour, in the light mode alone), so it showed as one more pixel under the 3px
  band. The theme sets it to 0 and keeps core's 61px. One test in `post_scss_test`.
- **The navbar's dropdowns are a dark slate with the brand colour on the row under the pointer.** Every
  `.navbar .dropdown-menu` (the user menu and its submenus, the language and look menus, smart menus in the bar,
  focus mode's "More") is one dark surface in both colour modes, inset by 8px, with 12px corners; the hovered,
  focused or current row is an 8px-rounded pill in `$primary`, so each Boost Union flavour paints its own. The pill
  is lifted until it stands out from the surface (3:1) and moved away from its ink until the label reads 4.6:1; the
  ink is white or the darkest grey. A submenu's title row (the Look and Language panels) was dark on the menu: core's
  back button reads the body's ink through `.text-body` and Boost Union's smart-menu header is a link, and both now
  read the menu's; the back arrow, an image of core's icon, is inverted to white and centred on the title, and the
  row takes one pill, never a second one on the button. Tests `navbar_menu_colour_test` (five brand colours, measured) and two in `post_scss_test`.
- **A drawer sub-panel opens where it should on a phone** (2026100548). Opening the Look or the language panel moved
  the focus to the panel's back control while the panel was still sliding in from the right, and the browser scrolled
  the drawer's viewport (overflow hidden is still a scroll container) to reveal it: the panel was drawn about 213px to
  the left, a sliver of it in view, for as long as a third panel kept the viewport wider than itself. `amd/src/drawer.js`
  now focuses with `preventScroll`; the way back keeps its scrolling focus (the parent comes in from the left, where the
  viewport cannot scroll, and a row far down the parent is still brought into view). A Behat step reads the panel's box
  after the slide, the drawer scenario gains a third panel, and one mutation gate holds the call.
- **The look menus can show each look's favicon** (2026100547). The new setting `theme_dimensions/lookmenufavicons`
  (General settings, *Look*, off by default) puts a mark before each name in the user menu's Look submenu, the drawer's
  Look panel and the choice page: the Boost Union flavour's favicon, else a dot in its brand colour, else an empty box of
  the same size; the site default shows the site's favicon. The image address is the one Boost Union puts in the browser
  tab of the flavour's pages, so the browser serves it from its cache, and it is fetched lazily. The marks ride in the
  `flavourmenu` cache beside the titles, so the menu still reads no table per page. Template `theme_dimensions/look_mark`,
  stylesheet `scss/look.scss`.
- **The course notice no longer names an unlisted category to a viewer who may not discover it** (SEC-03).
  `coursenotice_unlistedcategorytext` printed the category's name to a teacher of the course alone, against the
  theme's own policy for category names. Without the right to discover the category the note now reads the new
  `coursenotice_unlistedcategoryanon`. The hidden-category note is unchanged: hiding cascades. One test, one mutation gate.
- **The editor no longer names an unlisted colour source** (SEC-02). The sentence under "own colours" printed the name of
  the category the colours come from whenever core's visibility allowed, even when `local_unlistedcourses` withholds that
  category from the viewer. It now falls back to the generic sentence there too. One test, one mutation gate.
- **The category appearance page answers a missing id and a forbidden one alike** (SEC-01). `categoryhero.php` threw
  `unknowncategory` for an unused id and `nopermissions` for an existing one, an existence oracle. Both now raise the
  theme's `unavailable_exception` before the category is loaded, from `categoryhero::editable_category()`; every manager
  passes as before. Three tests, three mutation gates.
- **The category tree counts only the courses the viewer may discover** (SEC-04). `coursecat_category()` decided the
  expand arrow from `get_courses_count()`, and in COUNT mode core printed that raw number as "(N)" beside the name
  (the front page's category list; Boost Union a bare badge under its `categorylistingpresentation` setting), so
  both told an outsider that unlisted courses exist. The node now reads the filtered direct courses once, only when
  one of the two branches needs it, rewrites the number in either spelling and drops the span at zero as core does.
  Two tests, four mutation gates.
- **Category branding images are always sent cacheability private** (2026100546; SEC-06). The serve path chose public for a
  visitor, but the URL carries only the content hash, which does not change when a category stops being public, so a
  shared proxy kept serving the image for the whole hashed lifetime. `branding_test::test_the_image_is_always_sent_private`
  reads the route's source. One mutation gate.

- **The card's star joins the listing store** (2026100545; R5 of `docs/vitrine-react/PLAN.md`, the code part).
  `amd/src/categorystar.js` is now `js/esm/src/listing/star.ts`, rule for rule: one delegated listener armed once and
  its own `dim-star-js` mark, an optimistic flip, one round trip at a time, `theme_dimensions_toggle_wishlist` through
  `@moodle/lms/core/ajax`, the answer's `favourite` painted, a refusal put back with core's exception dialogue, and no
  refresh of the favourites count. The account's shell arms it first in `start()`, whatever the page draws, and
  `amd/src/listingaccount.js` only starts that shell; the visitor's page still never loads it. Dead hooks cleaned up:
  the barrel `listing.ts` no longer re-exports four names no shell calls (`SCOPE`, `chipOf`, `favOf`, `LINE`), and the
  templates no longer write four data-region hooks nothing reads (`dim-card-crests`, `dim-card-opens`, `dim-hero`,
  `dim-panel-tile`). No behaviour changes. A source test and three Jest tests of the star; 5 mutation gates
  retargeted.

- **The old AMD listing is removed** (2026100544; R4c of `docs/vitrine-react/PLAN.md`). The ten modules the
  listing store and the React components replaced, imported by nothing since R4b3, are deleted with their builds:
  `categorylisting`, `categorypublic`, `categoryvitrine`, `categorypanel`, `categoryline`, `categorypreview`,
  `categoryareapanel`, `categoryposters`, `daterange` and `categorymark`. No behaviour changes: every class and lang
  string they wrote is still read by the ES port. Their source tests went with them or now read the TypeScript
  (`amd_source_test` reads the store; `categorypanel_source_test` is `categorystar_source_test`, the star's alone);
  comments that named them name their successors. 47 mutation gates retired (the 46 on those files and
  `js_areas_no_amd_panel`), 5 retargeted to modules that exist.

- **An account's live listing runs on the listing store** (2026100543; R4b3 of `docs/vitrine-react/R4b.md`).
  `js/esm/src/listing/account.ts` is the logged-in shell the store is handed: the state remembered per category and the
  remembered organisation, the view preference for a real account, the progress of the enrolled courses on screen, the
  hover peek with one panel open at a time, the automatic opening's note, a panel's request and its progress, and core's
  exception dialogue. `course_renderer.php` arms `amd/src/listingaccount.js`, which arms the star and starts the shell
  through `core/esm!`, in place of `categoryvitrine` and `categorypanel`, which are imported by nothing until the
  removal pull request. The store tells its shell what changed before it writes the address, hands it the cards it
  draws and the ones a replaced grid drops, and asks it what to say of a calendar that could not mount (the account's
  shows core's dialogue, as before; the visitor's says nothing), and now handles the favourites toggle; the visitor's
  shell takes the new hooks as no-ops. No behaviour changes on either page. Two Behat scenarios (a remembered bare
  address written out in full in place, Back with a panel open), 11 Jest tests, a source test of the account's shell,
  24 mutation gates retargeted, 10 retired and 7 new.
- **block_compass's open-courses line links to the filtered showcase** (2026100542; stage 14f of block_compass's
  ADR-017). `local\hook\block_compass\category_link` answers the optional `\block_compass\hook\category_link`
  hook with `?categoryid=N&chip=open&from=Y-m-d&to=Y-m-d` for each asked category and the bare course index for the
  root. It answers nothing when the showcase or the state chip is off, or for a visitor or the guest account, whose
  page draws no chips, so the block keeps core's category page. It adds no visibility rule: the page runs its own.
- **The chosen look's remaining points from the security read, each with a test.** Somebody logged in as another
  user is offered no look and `flavourchoice::choose()` refuses them: the choice, and the cohort it moves, are
  the person's. A page another theme draws drops the look this session was primed with
  (`flavourchoice::unprime()`), so a Boost Union page of the same category no longer shows it. The choice page's
  return address is cleaned in one place (`flavourchoice::back_url()`, local only) and loses its session key, in
  the menu's links too, so the redirect after the POST cannot replay an action. The page's one write is
  `flavourchoice::submitted()`, which `tests/local/flavourchoice_guards_test.php` drives with the page's own form:
  a GET writes nothing, a POST without the session key or of a look the form did not offer is refused. Six
  PHPUnit tests, eight mutation gates.
- **The offered-looks setting lists the flavours again.** `settings.php` handed
  `admin_setting_configmulticheckbox` the array callable `[flavourchoice::class, 'choices']`, and core keeps any
  array as the choices themselves, so the page offered two checkboxes labelled with the class name and `choices`
  (ids 0 and 1) instead of the flavours. The choices are a closure now. Tests and Behat wrote the setting directly,
  so none saw it; `flavourchoice_test::test_the_settings_page_offers_the_flavours` reads it from the admin tree.
  One mutation gate.
- **The visitor's live listing runs on the listing store** (2026100541; R4b2 of `docs/vitrine-react/R4b.md`).
  `js/esm/src/listing/store.ts` holds the page's state, every action, the requests with their tickets and the one path
  from an action to the address; `visitor.ts` is the visitor's shell it is handed (its service without a session, its
  fallback, its posters' links and panel questions). `request.php` arms `amd/src/listingvisitor.js`, which reaches the
  shell through `core/esm!`; `categorypublic.js` is imported by nothing until the removal pull request. The store
  reaches no shell, and Back is heard before the posters' component is mounted. No behaviour changes on the visitor's
  page. A repaint in place now holds one pending from its request until its rows are painted, on both shells
  (`theme_dimensions/listing:rows`, `theme_dimensions/categoryvitrine:rows`): core/ajax's token ended with the answer and
  the render's began after the template load, which RequireJS answers after a timer even for a loaded module (every
  repaint since R4b1's `load('core/templates')`), so Behat could count the rows being replaced under it, the flake of
  the visitor's tree scenario. Two Behat scenarios (Back, Load more on the visitor's page), 14 Jest tests, a source test of the store, 29
  mutation gates retargeted and 7 new.
- **The chosen look, three points from the whole-feature security read.** Core's preference services
  (`core_user_set_user_preferences`, `core_user_update_user_preferences`) may no longer write
  `theme_dimensions_flavour`: its permission callback is `flavourchoice::preference_editable()`, which says no,
  so `flavourchoice::choose()` is the one write path and the look cohorts always follow it. The `cohort_deleted`
  observer records each smart menu or item before it hides it, so a failure part way leaves none hidden unrecorded.
  The choice page says so when a look is refused at submission instead of returning without a word. One PHPUnit
  test, one mutation gate.
- **The README describes the flavour stylesheets compiled ahead of time** (S4 of `docs/flavourcss/PLAN.md`):
  the scheduled task, which flavours it compiles, the child process and its PHP, a flavour that does not compile,
  several web servers, the CLI as a deployment step, and what is stored. `docs/flavourchoice/PLAN.md` points at the
  merged warm-up its release waited on.
- **The flavour sheet builder closes the last window of its stale-write guard** (S4's security read of S1-S3). It
  read the revisions before writing; a cache reset landing between that read and the writes would have left the old
  sheet under Boost Union's revision-less MUC key and in the fallback file. `theme_reset_all_caches()` moves the
  revision before it purges, so the builder now reads the revisions after the writes instead and, if they moved,
  removes what it wrote and answers stale; that one check covers a reset during the compile too. 2 new mutation
  gates replace `flavourcss_stale_guard`, whose check they made redundant.
- **The category showcase's shared listing is TypeScript** (2026100539; R4b1 of `docs/vitrine-react/R4b.md`, the first
  of the listing store's three slices). `amd/src/categorylisting.js` moved to `js/esm/src/listing/`: `events.ts` (every
  event between the listing and its components, and the hooks it reads, spelled once), `address.ts` (the state as the
  address spells it), `history.ts` (the one writer of the history), `mounts.ts` (the calendar, the filter line and the
  posters handed to their components) and `paint.ts` (the painters), under the barrel `listing.ts` with
  `categorylisting`'s export names. The three AMD clients (`categoryvitrine`, `categorypublic`, `categorypanel`) load it
  through `core/esm!@moodle/lms/theme_dimensions/listing/listing`, one import line each; `categorylisting.js` stays
  unimported until the removal pull request. The components import their event names from `events.ts`, and the posters'
  component writes its open area through `history.ts`, still replacing. No behaviour changes; the first paint's module
  list is the same. 29 Jest tests, a new `listing_source_test`, 52 mutation gates retargeted to the TypeScript and 8 new.

- **Smart menus by look** (2026100538; S5 of `docs/flavourchoice/PLAN.md`). `local\lookcohorts` keeps one cohort per
  offered look (system context, idnumber `theme_dimensions_look_<flavour id>`, component `theme_dimensions`, which
  makes core's cohort pages refuse to edit, delete or assign it) holding exactly the people who chose it, so Boost
  Union's own cohort rule restricts a smart menu by look. `flavourchoice::choose()` moves the person, read back
  from the stored preference under a per-person lock; the offered-flavours setting's updated callback provisions under a core lock, renames on a title
  change, never claims an idnumber another owner holds, and empties (never deletes) the cohort of a look taken off
  the list, through the adhoc task `empty_look_cohort` above 500 members. The daily scheduled task
  `reconcile_look_cohorts` makes a missing cohort again and puts membership back in step with the preferences. A
  `cohort_deleted` observer at priority 1000, ahead of Boost Union's, hides every smart menu and item a deleted look
  cohort restricted before Boost Union strips it from the rule (which would show the menu to everyone), and the new
  recommendation `dimensions_lookcohorts` warns until each is shown again. Uninstall releases the cohorts (stamp
  cleared, members kept). The privacy metadata links `core_cohort`. Fourteen PHPUnit tests, twenty-three mutation gates.
- **The flavour stylesheets are compiled before their first visitor, by a scheduled task** (2026100537; S3 of
  `docs/flavourcss/PLAN.md`). `\theme_dimensions\task\warm_flavour_css` runs every minute and hands S2's planner its
  work. That is every sheet of a flavour a page can be served that has no file at the current revisions, built one
  flavour per child process. While every sheet is built, a run is one query and a file check per flavour. Every
  minute, because a theme cache reset has no event to listen to: Boost Union's flavour editor, the theme settings,
  the cache purge and every upgrade reset the caches, and the next run is the soonest the missing sheets are seen.
  2 new mutation gates.
- **Every missing flavour sheet can be built from the command line, each flavour in a process of its own** (S2 of
  `docs/flavourcss/PLAN.md`; the scheduled task that runs it every minute is S3).
  `php theme/dimensions/cli/build_flavour_css.php --all` (with `--dry-run` to list) runs `local\flavourcss\planner`.
  It finds the flavours a page can be served: applied to categories or cohorts with at least one id, or offered by
  `theme_dimensions/selectableflavours`. For each sheet with no localcache file at the current revisions, in each
  direction a language needs (right to left only while one is installed), it starts the same CLI with `--flavourid`
  as a child, one at a time, and the child runs S1's builder. A sheet whose child failed (the builder's exception,
  or a fatal error) is remembered in `theme_dimensions/flavourcss_failed` with the revisions it failed at, and is
  not tried again until a revision moves. The child is started with this process's own PHP, or core's
  `$CFG->pathtophp`; with neither, or with `proc_open()` disabled, nothing is built. 9 new mutation gates.
- **The preferences page links to the choice page** (2026100536; S3 of `docs/flavourchoice/PLAN.md`).
  `theme_dimensions_extend_navigation_user_settings()` adds a Look link to the account section of
  `/user/preferences.php`, for the person's own preferences only, on a page this theme draws and while a look is
  offered; the choice page's form comes back to the preferences page. The theme check is now
  `flavourchoice::drawn_by_this_theme()`, which the prime asks too.
- **The filter line's first measure forces no layout in React's commit** (2026100535; PLAN 5.2.6). The first layout
  effect measured the steps (`contentWidth()`, `stepWidths()`) and placed the layers inside the commit, forcing the
  page's first full layout there (one long frame of 130-150 ms at 4x CPU on the visitor's page, most of it forced
  layout, though the line fitted at the first try). The first measure is now taken at the line's `ResizeObserver`'s
  first entry, which the browser delivers after its own layout and before the paint, with the entry's content box as
  the width: the line is still folded before it is first painted. The layers' placement and the rows' fades, a
  content change and the fonts' remeasure wait for that measure too; a mount again from the kept state gets its own
  first entry. Without a `ResizeObserver` the commit measures as before. `data-line-drawn`, the focus restore and
  `dim-line:mounted` stay in the commit. The line is hidden (`visibility: hidden`, its own inline value given back)
  from the commit to that first fold: a fold in the observer's callback moves nodes the browser has already laid out
  once, which counted as a layout shift (0.027 on the logged-in flat page at 900 px), and hidden nodes count for none,
  while visibility keeps the line's box. A line holding the focus is not hidden, since a hidden ancestor takes the
  focus away. Eight Jest tests, one PHPUnit source assertion, four mutation gates.
- **A flavour's stylesheet can be compiled before its first visitor** (S1 of `docs/flavourcss/PLAN.md`; nothing calls
  it yet). `local\flavourcss\builder` compiles ONE Boost Union flavour in a CLI process and stores it where Boost
  Union's `flavours/styles.php` reads it: the `core/postprocessedcss` entry, the localcache file and the tempdir
  fallback, under the theme's own CSS lock, then removes the older revisions as `styles.php` does. It refuses to
  compile when Boost Union's static flavour record already holds another flavour (measured on m503b: a second flavour
  compiled in the same process came out with the first one's brand colour), discards a sheet whose revision moved
  during the compile (the MUC key carries none), and stores nothing that lacks the theme's own tokens (core falls
  back to the precompiled sheet when the SCSS does not compile). The three `styles.php` helpers it copies have
  snapshots, read from the file's tokens by the new `upstream::FUNCTIONS`. 9 new mutation gates.
- **The Look submenu in the user menu and the mobile drawer, and the choice page** (2026100534; S2 of
  `docs/flavourchoice/PLAN.md`). While `theme_dimensions/selectableflavours` offers a flavour, the user menu of a
  logged-in account gains a Look link before Language (after Preferences where there is no language menu) and a
  submenu built like core's language one: the site default first, then each offered flavour, the one in force
  marked current. The drawer gets the same as a row and a panel. Each item links to `flavour.php` with its flavour
  preselected, which only shows a form (one radio per look, POST with the session key) and writes nothing until it
  is submitted; with JavaScript, `theme_dimensions/flavourchoice` records the choice in place through the new web
  service `theme_dimensions_choose_flavour` and reloads the page. Both go through `flavourchoice::choose()`, which
  accepts the site default or a flavour the setting offers now. The offered titles are cached site-wide (cache
  `flavourmenu`, keyed by the language and the offered ids, emptied by Boost Union's flavour events), so the menu
  costs no statement per page. This replaces the plan's first default (a GET link with the session key): the theme
  never changes state on a GET.
- **The showcase's cost bound no longer flakes on a loaded host** (tests only). `budget_test`'s 0.5 s ceiling
  on one render of two hundred courses failed at 0.508 s and 0.514 s with two `mdl ci --matrix` legs or three
  `mdl mutate --fast` workers sharing the host, and passed alone; one render measured 120 to 200 ms right after
  seeding where the docblock said 50. The time half is now the ratio of the median of nine interleaved renders
  of two hundred courses to that of a root of one full page (48), in the same process, under 2.5 (measured
  0.96 to 1.3): the per-course cost a statement count cannot see, with the host's speed divided out. The read
  ceilings and the memory bound are unchanged.
- **A visitor's flat tree links every child it names, a merely listed one included** (2026100533). On a public
  category page in a flat organisation (A-Z or the recent sort) each child holding a public course but not itself
  public was drawn as `<span class="dim-tree__text">`, though the server already honoured its `cat=`: the tree's entry
  was gated on the population's `linkable`, which says whether the child's OWN page is public (D20). That gate belongs
  to the posters and the accordion, whose links open that page; a tree entry is a filter of the parent's page and
  only regroups rows the page already lists. `category_listing::tree()` now links every entry, its `linkable` key, the
  template's text branch and `.dim-tree__text` are gone, and the visitor's client handles the new links in place as it
  did the others. And the tree a server draws for a visitor under a filter is the tree at rest: it was built from the
  narrowed population, so a chosen child, or its reloaded address, left that child alone in the tree and its sibling no
  choice at all. `population::accordion_tree()` is now `tree_at_rest()` and also feeds a visitor's flat tree, on the
  page's own request only (`criteria::$withposters`): one grouped statement, which the web service's filter fetch, whose
  answer carries no tree, never pays. `category_listing_test::test_a_visitors_flat_tree_links_a_listed_child_as_a_filter_of_this_page`
  pins both with the public child and a child holding no public course as controls, and `budget_test` the +1 on the
  page and the 0 on the service; mutation gates `tree_visitor_listed_link`, `tree_at_rest_flat`,
  `tree_at_rest_service_free`.
- **A person can have a Boost Union flavour as their own look: the choice and how it reaches the page** (2026100532;
  S1 of `docs/flavourchoice/PLAN.md`, no interface yet). The setting `theme_dimensions/selectableflavours` (General
  settings, *Look*) lists the flavours a logged-in person may choose; the preference `theme_dimensions_flavour` holds
  the choice (a flavour id, or 0 for the site default). A `before_http_headers` callback, at priority 1000, writes the
  chosen flavour's record into Boost Union's `flavours` session cache under the page's category key before the layout
  asks which flavour applies, so the stylesheet, the logos, the favicon, the body class, the navbar colour, the
  footer and the dark logo all follow it, over the category and cohort rules (decision F1). The site default writes
  nothing, and nothing of Boost Union's resolver is copied. A session cache, `flavourchoice`, remembers what the
  session was primed with; a different choice, or a flavour leaving the list, purges Boost Union's cache once, and
  Boost Union's invalidation events refresh it. Boost Union's flavour purge flag, which it never clears, is honoured
  once and cleared, or it would erase the choice on every page. The preference is exported by the Privacy API and
  dropped on uninstall.
- **The course dialogue's "Enrolment from" date keeps its day's leading zero** (2026100531). `userdate()` strips it by
  default, so from the 1st to the 9th of a month the button read `1/11/2026` against the string's own `%d/%m/%Y`, and
  `category_card_modal_test` failed on those days only. `category_card::modal_primary()` passes `fixday` false.
- **The visitor's showcase draws the logged-in shell's React components** (2026100531; R4a of
  `docs/vitrine-react/PLAN.md`, the first half of R4). The anonymous live client (`categorypublic`, armed by
  `categorypublicclient`) mounts the calendar (R1), the filter line (R2) and the posters with their panel (R3) through
  the shared listing's mounts, each component named once in `categorylisting`'s `COMPONENTS`; the posters' mount moved
  from `categorypanel` into `categorylisting`'s `mountAreas()`, which hears the component for either client. The
  posters' component takes the visitor's differences as props (`{links: true, quiet: 120, autoopen: false}` on the
  visitor's page, none on the logged-in one): the posters stay the server's links announced as buttons (a modified
  press follows the link, Space opens as Enter does), a panel's question is asked after a quiet and once per opening
  (`dim-areas:question`, named by the visitor's module), a failure is offered to the page before the inline error
  (`dim-areas:unanswered`, which the visitor's module takes by loading the poster's link) and no area opens by itself.
  The visitor answers the components as the logged-in client does, with its own service: `dim-areas:ask` with the
  public call's promise (a public area by its id, a merely listed one as a slice), `dim-vitrine:pending` and
  `:criteria` on the document, the line told every answer (`tellAnswer()`), the field read delegated on the form.
  The visitor's import graph now reaches the three components by design, and nothing it reaches names
  `categoryvitrine`, `categorypanel`, `categorystar` or a logged-in service, comments included
  (`categorypublic_source_test`). `categoryline`, `categorypreview`, `categoryareapanel` and `categoryposters` are
  imported by nothing and stay in the tree until R5; no shell mounts `daterange`'s AMD calendar, whose `period()`
  both clients still read. Jest covers both modes (13 new tests); 22 new mutation gates, 17 retargeted, 4 retired.
  The filter line survives a remount: core's `react_autoinit` unmounts a mounted node that leaves the document and
  mounts it again when it returns, and React's unmount had emptied the line for good (no field, no `dim-line:mounted`,
  the listing's pending held for ever). The unmount now gives the
  adopted frames back to the server's and keeps them with the last state for that node, so the next mount draws the
  line again; a line the component cannot draw sends `dim-line:failed`, which releases the pending too; the calendar is
  told its period on every mount, not the first. Jest runs the three components on the visitor's own markup
  (`js/esm/tests/visitor`, captured from an anonymous page), with two more gates. `mountLine()` reads the toolbar
  form's id as an attribute: on the visitor's script page (`category.php`, what a site without the router serves) the
  form carries a hidden field named `id`, which is what `form.id` returns, so the component was handed `{}`, drew no
  line and held the pending for every visitor client scenario in CI (gate `js_line_form_id_attribute`).
- **The logged-in showcase's posters and the panel between their rows are a React component** (2026100530; R3 of
  `docs/vitrine-react/PLAN.md`). `js/esm/src/areas/Areas.tsx` (`@moodle/lms/theme_dimensions/areas/Areas`) is mounted
  on an empty, hidden child `categorypanel` appends to the areas section, so the mount draws no box and the first paint
  stays the server's. It owns, and is the only writer of, the panel's frame (`areas/panel.ts`: the list item's hidden
  state, its three transition classes through one `settle()`, the five integers of its row, the open poster's colour,
  its DOM order), the posters' result layer (`areas/posters.ts`: pills, counts and shells, the control as a button or
  the link to its page, the open state, zeroed posters by `hidden`, the reveal, "nothing anywhere" and "search all",
  the hint, the live status) and the "nothing in N areas" summary, which React draws through portals into the
  server's `<summary>` and list. The panel's content stays `category_panel`, rendered by `core/templates` through the
  bridge (which now types `replaceNodeContents`/`appendNodeContents`) and inserted whole, never diffed. The component
  calls no service: it asks `categorypanel` for an area's rows on `dim-areas:ask` (the module puts the logged-in
  service's promise in the event), tells it the rows are drawn on `dim-areas:painted` (the progress) and that it is
  drawn on `dim-areas:mounted`; it hears the listing's `dim-vitrine:*` events and sends `dim-panel:autoopened`, and
  `categoryvitrine` no longer writes the posters' shells. The row is counted from the posters shown and the grid's
  tracks through `matchMedia`, never read off a box; nothing is measured at mount, a server-drawn panel keeps its
  no-JavaScript presentation until the component first changes it (the body's `dim-panel-js` redraws its rail), and the
  rail reads its scroll from a ResizeObserver entry. The visitor's shell keeps `categoryareapanel` and
  `categoryposters`, and its import graph is unchanged. Jest pins the component (`js/esm/tests/areas`, 32 tests);
  `areas_component_source_test` pins the AMD half, with ten new `js_areas_*` mutation gates.
- **The logged-in filter line measures its steps as categoryline does: up to the first that fits** (2026100529; R2
  follow-up). `filterline/measure.ts` `stepWidths()` measured all five tries on every measure, five forced layouts in
  the first commit; a CPU profile on m503b (4x throttling) put 407 ms of forced style and layout in one 453 ms frame,
  and the browser probe read the flat page's warm TBT at 303 ms against 98 ms under R1. It now stops at the first try
  that fits the line's width, and a ResizeObserver width the measured tries do not decide (`steps.ts` `settled()`)
  measures the rest; any other width is still a fold alone. Jest pins both.
- **The logged-in showcase's filter line is a React component** (2026100528; R2 of `docs/vitrine-react/PLAN.md`).
  `js/esm/src/filterline/FilterLine.tsx` (`@moodle/lms/theme_dimensions/filterline/FilterLine`) is mounted on the
  server's line itself, which the shared `mountLine()` names for core's `react_autoinit` and puts back where it stands;
  its first render reads the markup it replaces (`filterline/model.ts`) and draws it back hook for hook, with every
  name in the same GET form, so the no-JavaScript submits, the listing's form reading and the Behat steps are
  unchanged. It draws the line's own controls (the scope, the search with its eraser and icon, the star, the Filters
  and Organise summaries, the view toggle, the close button and the listbox) and adopts three frames of server
  markup whole, never diffed: the Filters panel (with R1's calendar inside), the Organise menu and the managers' menu
  (the template's new `dim-line-manage` hook). No painter of the shared listing writes into what the component draws
  (`drawnByLine()`): it tells the component through `dim-line:state` and `dim-line:answer` on the line, and hears
  `dim-line:mounted` and `dim-line:dropterm`, spelled alike in both languages. The steps, the search that grows over
  the line, the suggestions (`filterline/suggest.ts`), the layers, the rows that scroll and the preview
  (`filterline/preview.ts`, imported on first use through `filterline/lazy.ts`) keep `categoryline`'s and
  `categorypreview`'s rules; the width each step needs is measured once per content, and a width change takes its step
  without reading layout. The search's flex basis is now the first step's floor (13rem), so an unmeasured line wraps
  exactly where the first step stops fitting and the line no longer shifts when its client folds it there. The
  visitor's shell keeps `categoryline`, `categorypreview` and `categorymark`, and its import graph is unchanged. Jest
  covers the component and its modules (`js/esm/tests/filterline`, over the template rendered from its example
  context); new `filterline_component_source_test` pins the AMD half and holds the Jest fixture equal to the template,
  twenty new `js_line_*` gates break it, and three gates of the logged-in wiring are retargeted. No database change.
- **The logged-in showcase's calendar is a React component** (2026100527; R1 of `docs/vitrine-react/PLAN.md`).
  `js/esm/src/daterange/DateRange.tsx` (`@moodle/lms/theme_dimensions/daterange/DateRange`) draws the template
  `category_daterange`'s markup hook for hook, over `daterange/model.ts` (the draft, its three ways out, the press
  rules and the clears, function for function as `amd/src/daterange.js`) and `daterange/calendar.ts` (the `{y, m, d}`
  arithmetic through `Date.UTC`; the site's today and first weekday from the server, never the browser's clock). The
  shared `mountCalendar()` appends it to the toolbar's region only when the caller names it, which only
  `categoryvitrine` does; the two talk through `dim-daterange:mounted`, `:pick` and `:set` on the region, and the
  native date fields hide only once the component says it is drawn. The visitor's shell keeps the AMD calendar until
  R4, and its import graph is unchanged. Jest covers the component (`js/esm/tests/daterange`, in a zone west of UTC
  with daylight saving); new `daterange_component_source_test` pins the AMD half and the absence of timezone
  arithmetic, five new `js_calendar_*` gates break it. No database change; the first paint's module and string lists
  are unchanged.
- **The showcase's first paint costs less per card** (2026100526; R0 of the performance plan, after
  a measured review of the server render). A card's landing page values are read for the ten fields a card shows
  (`cards::CARD_SHORTNAMES`, passed to `landingfields::values_many()`'s new `$only`), with one default controller
  per field shared by the courses without a row and the field names formatted once per call while the string
  filters are off; every badge file of the page is one `{files}` statement instead of one per stored crest, so
  the bulk reads are flat in the page size with crests too. `landingfields::get_field()` checks the new
  `FIELD_TYPES` list instead of building the definitions, `option_lines()` and `facets::fields()` are memoised per
  request (reset by `landingfields::reset_field_cache()`), core's `course_image` cache is written only where its
  entry is missing or wrong, and the labels every card spells alike are read once per language. The first eager
  card cover of the page's own first paint asks for `fetchpriority="high"` where the hero paints no picture (the
  card's new `priority` key, declared in the rows service's returns, always false in an answer). The unlisted
  courses the population hands local_unlistedcourses arrive with their contexts preloaded, so the plugin's
  relationship check no longer reads one `{context}` row per probed course (the root of a large subtree was not
  statement-flat for a logged-in reader). No database or JavaScript change.
- **The performance probe of the category showcase** (R0 of `docs/vitrine-react/PLAN.md`, section 5.4; no version
  change: tests and a development script only). `tests/output/core/first_paint_budget_test.php` gates what a first
  paint costs without a browser: the HTML bytes of a card and of the rest of a listing, the hero's bytes, and exactly
  the theme's AMD modules and strings_for_js a page asks for at load, logged in and as a visitor. The browser probe,
  `tests/behat/theme_dimensions_perf.feature` (`@theme_dimensions_perf`), loads a flat page with covers, the course
  index, a grouped page and a visitor's public page five times under 4x CPU and a slow network (Chrome DevTools
  through chromedriver), reads FCP, LCP, TBT, CLS, the INP of one press and the request counts from the browser's own
  entries, and writes the runs and their medians as JSON under the faildumps; it skips unless the site opts in
  (`$CFG->behat_theme_dimensions_perfprobe` or `THEME_DIMENSIONS_PERFPROBE`; the `behat_` prefix is what survives
  core's `behat_clean_init_config()`). `docs/vitrine-react/probe/loadprobe.php`
  (export-ignored) seeds the 5,000-course data set and meters the real server entries, warm and cold.
- **The theme has an ES module tree, `js/esm`, and nothing uses it yet** (2026100525; R0 of `docs/vitrine-react/PLAN.md`,
  the tooling half). `js/esm/src/amd.ts` (`@moodle/lms/theme_dimensions/amd`) is the one door from the theme's ES modules
  to AMD: `load()` and `loadMany()` over core's `@moodle/lms/core/amd`, typed to the two modules R1 needs
  (`core/templates`, `core/notification`); strings and the pending counter are core 5.3's own ES modules and are not
  bridged. `js/esm/src/.eslintrc` is block_compass's, which restores core's AMD jsdoc rules on TypeScript.
  `js/esm/build` is core 5.3's `grunt esm` output (`amd.js`, `amd.dev.js`, `amd.dev.js.map`). Jest tests in
  `js/esm/tests` run under core's own `jest.config.js`. New `tests/local/esm_source_test.php` pins the build against
  the source (the development map byte for byte, the outputs per source, the production build's imports) and that
  only the bridge reaches RequireJS; the visitor's import walk (`categorypublic_source_test`) now follows imports into
  `js/esm` and back, and starts from the bridge as well.
- **The showcase asks for its first paint's images first, and loads three modules on first use** (2026100524; P0 of
  `docs/vitrine-react/PLAN.md`, the performance plan). The covers of the first row of a listing's first page (three,
  `category_listing::FIRST_ROW`) and of the posters that open a grouped page or the root load at once instead of
  lazily (the card's new `eager` key, declared in the rows service's returns and set only on the page's own first
  paint: `category_listing`'s `$firstpaint`, passed by the two page entries, never by a web service answer); the hero's picture is fetched with
  `fetchpriority="high"`; the hero's crests are no longer lazy, and every crest states its box in the markup. The
  cover film is now `theme_dimensions/local/coverfilm`, imported by a small entry (`covervideo`) on the first pointer,
  focus or finger on a cover, which holds a press that arrives before the film and gives it back; the course dialogue
  imports `core/modal` on the first press; the filter line imports its preview on the first pointer or focus on the
  toolbar. The visitor's import walk now follows dynamic imports too.
- **Every copy of core or Boost Union code keeps a snapshot of its upstream** (Boost Union's `Nav.tsx.upstream`
  pattern). The 24 template overrides and the two copied methods carry a verbatim `.upstream` file beside them;
  `classes/local/upstream.php` reads today's upstream as the page does, `tests/local/upstream_test.php` reddens when
  core or Boost Union changes a copied file, and `scripts/upstream-merge.sh` (development only, export-ignored)
  applies the upstream change to the copy with a three-way merge and refreshes the snapshot. No version change.
- **Fixed: a navbar logo capped by Boost Union's *Maximal width of logo in navbar* was squeezed sideways**
  (2026100523). That setting writes `max-width` and `height: auto` on `.navbar-brand .logo`; the 44px height of
  2026100518 outranks the `auto`, so a 300x31 wordmark capped at 200px was drawn 200x44. The logo is
  `object-fit: contain` now and keeps its ratio inside the capped box. Gate `navbar_logo_contain`.
- **The context bar slides in on a phone and comes in with a colour of its own** (2026100522; Fandom's mobile
  navbar is the model). Below md the context layer comes down from above the bar while the global bar's items go down out of it, by
  the bar's height, clipped by the nav while the layer is in and, through a keyframe holding `overflow: hidden` for
  the length of the slide back (Fandom's `overflowHiddenForAnimation`), on the way out; after it the nav overflows
  again for its menus. From md up the swap is still a fade. The layer paints a ground at every width: the new
  *Context bar colour* setting (`theme_dimensions/navbarcontextcolor`, hexadecimal, compiled into the stylesheet by
  the pre SCSS callback) or, left empty, a step of `$primary` towards its ink (30, 20 or 10 %, the most visible that
  still reads) or a quarter step away from it; the ink is white or the darkest grey, and the ground is moved, never
  the ink, until the pair reads 4.6:1. The sub line lost its 82 % opacity, which would have fallen under that
  contrast. New `tests/local/readingbar_colour_test.php` compiles the Sass over Bootstrap's own functions and measures
  it with a WCAG implementation of its own.
- **The phone's navbar keeps four controls.** At 375px an editor's navbar carried seven (menu, brand, edit switch,
  focus mode, notifications, messages, Mail, user menu) and left the brand 14px. Below md the messaging drawer, Mail,
  focus mode and the edit mode switch are entries at the top of the user menu, after the name block
  (`classes/local/phonenav.php`, `templates/navbar_phonemenu.mustache`, `amd/src/phonemenu.js`, `scss/phonenav.scss`);
  an entry presses the hidden control itself and repeats its unread count, and a control is hidden only once its
  entry is in the menu, so without the module the navbar stays as it was. The context layer carries the way into
  focus mode as well, and focus mode's module moves the focus to the first counterpart on the screen.
- **Fixed: the context bar stayed as it was after the course index was closed from it.** Boost's drawers module gives
  the focus back to the toggle that opened the drawer, which is the layer's own control, and the bar held still for any
  focus inside it until a press elsewhere. Only a keyboard reader's focus (`:focus-visible`) holds it now.
- **Core's linear navigation footer comes back near the end of the page on a phone.** Below 768px Boost hides every
  sticky footer on the way down the page, so a reader reached the end of an activity with "Next" and the completion
  button hidden. `amd/src/linearfooter.js` leaves Boost's behaviour along the page and shows that footer again within a
  quarter of the screen's height of the end, after Boost's own scroll listener and in Boost's own way; armed by core's
  predicate (`linearnavigationsettings::show_navigation_footer()`), never on other sticky footers.

- **The course notice says why a course is not public** (2026100521). A course set to public under an unlisted
  category read "Login only", with a note telling the editor to make it public. The promotion section now names the
  course's own state - public, "Public, not in effect", unlisted, or login only - and, below it, what withholds it:
  the course hidden, the nearest unlisted category on its path (with the link to that category's discoverability
  page for whoever may change it) and the nearest hidden category (with the link to edit it). The category notes are
  said whatever the course's state, because both also withhold the course from listings. An unlisted course says who
  still learns that it exists, with the link to change it for whoever holds `moodle/course:visibility`. The verdict
  is still local_unlistedcourses' predicate; a refusal the notice cannot name still reads "not in effect". Six
  PHPUnit cases and ten mutation gates.
- **The design system's semantic aliases turn with the colour mode.** Core 5.3's dark mode turns Bootstrap's surfaces
  and ink and none of the `--dim-ds-*` aliases, so the login page drew its heading and "Forgotten your password?" in
  navy on a dark card (1.28:1), and the profile cards, the landing page card and the "not available" card stayed white
  under the page's light ink (1.30 to 2.35:1). The aliases now have a dark half on the html element's mode: surfaces
  from core's dark greys, inks lifted from their light primitive by core's `dark-readable()` (4.6:1 on a raised surface,
  7:1 for a link's hover, 3:1 for a line), the tint mixed into the dark page, the focus ring the family ring's dark
  stop. The inverse tokens, the accents and the primary fill stay the same in both modes. The primitives are written
  once, as Sass variables, which the `:root` block declares. Two aliases replace inks that read a primitive directly:
  `--dim-ds-text-on-tint` (the login's lead route) and `--dim-ds-text-success` (the landing page's held place and the
  card's learning list); the landing page's offer icon reads the heading ink. The notifications popover follows the
  page's mode instead of forcing a light scheme. New `tests/local/dark_tokens_test.php` (every alias has a dark half,
  every dark pair measured on core's compiled values, no ink reads a primitive but the fixed plates) and four mutation
  gates. No version bump: stylesheet only.
- **Plugins directory readiness** (2026100520; the directory's submission guidelines, after a reviewer's four
  findings on local_groupdist).
  - Fixed: the branding table's `usermodified` stamp is personal data and was only declared. The privacy
    provider is now a request and userlist provider as well: a data request exports, per category whose
    branding the person last saved, that they saved it and when (read from the request's subject, never the
    session user); a deletion request clears the stamp to 0 and keeps the branding. Every method asks that the
    context is a category's first. `timemodified` is declared beside it.
  - Fixed: the README said the table held no personal data and never named it; it now has "Data stored",
    "Capabilities" and "Translations" sections matching the provider and the code.
  - Added: `LICENSE` (a byte copy of `COPYING.txt`) and `tests/marketplace_readiness_test.php` (the fleet
    template; the settings test reads the `$name` assignments of `settings.php`, and the CSS scope test is
    skipped because a theme styles the whole site). Six provider tests and twelve mutation gates.
  - Changed: `lang/pt_br` leaves the release package (`export-ignore`); the Brazilian Portuguese translation
    stays in git and reaches sites through AMOS.
- **The category rows web service loads `filelib.php` for the card excerpt.** `category_card::excerpt()` calls
  `file_rewrite_pluginfile_urls()`, and with `courseoverviewfileslimit` at 0 and no textarea landing fields nothing
  else on the `theme_dimensions_get_category_rows` path had loaded `lib/filelib.php`, so the call died with "Call to
  undefined function". The method now requires it. A PHPUnit case exports a card in a child process that has not
  loaded it.
- **The context layer's progress follows a completion toggle** (2026100519). The sub line's "X of Y completed" was
  printed once by the server, so marking the activity done in core 5.3's linear navigation footer moved the status to
  "Completed" and left "0 of 1 completed". `readingbar::context()` now hands the course's name and the progress apart,
  the progress with its two numbers, and `amd/src/readingbar.js` moves the completed one by one on core's
  `manualCompletionToggled` (an activity's footer, or any activity's button on the course page) and says it again in
  the server's own string. A Behat scenario marks the activity done and back in the footer; one mutation gate.
- **The phone navbar** (owner's request, 2026-10-22; nothing in it needs a version bump of its own). The
  brand shows beside the hamburger below md: Boost Union's navbar partial hides it with `d-none` (`!important`), so
  the core renderer edits the rendered layout's one brand class list (`brand_on_phones()`, a test reads Boost Union's
  partial for the exact string), 8px from the hamburger; a site name without a logo is cut with an ellipsis. The logo
  keeps 8px of the bar above and below it (44px where core gave 52px), in the navbar and in the drawer's header. The navbar's dividers on a dark
  bar take its white at 15%. The navbar's colour mode menu (and the divider after it) leaves the navbar below md and
  in the compact navbar wherever the drawer carries the appearance switch. Three mutation gates.
- **Three defects found in a browser** (2026100518; measured on m503, 2026-10-22). The compact navbar's drawer dimmed the
  page twice from 768px to 990px: core 5.3 shows its drawers' backdrop below `core/pagehelpers`' `Sizes.medium`
  (991px), not below md, so the drawer's own shadow now stands down while that backdrop is on the page. The reading
  bar counted its travel from the top of the page, so the frame the page header left already held more than 96px and
  the context swap and the hide landed together; the travel now starts when the header has gone (swap at 24px, hide
  at 96px, back at 16px up). And a docked course index or block drawer held the bar for good between 992px and
  1399px: `busy()` read core's `isLarge()` (1400px) where the drawers dock from `isSmall()`'s 991px, which it reads
  now. Three mutation gates; `amd/build/readingbar` rebuilt.
- **The primary drawer opens over the navbar** (the navbar canvas; no version bump). Below md and in the compact
  navbar the drawer covers the whole screen, navbar included: the navbar steps down to `$zindex-sticky` while the
  drawer is open, so core's backdrop dims it and closes the drawer on a press (raising the drawer instead would
  raise core's shared backdrop over the course index drawer). The drawer's header is the navbar's band: an element
  carrying the navbar's own colour classes paints it (Boost Union's colour and tint, a flavour's, the dark mode's
  repaint), the logo sits where the navbar holds it (the navbar's logo height, 12px from the edge) and takes the
  navbar's dark variant, and the close button takes the navbar's ink. Auto-hide's Boost Union condition moved
  inside `:where()`, so its block region ids no longer add specificity to the scope's rules.
- **Boost Union's new navbar contract** (Boost Union `upgrade-503` at 3b534e30, 2026-10-20; no version bump:
  no schema, service or JavaScript change). Boost Union dropped the navbar's `databstheme` key and colours a
  dark bar through `.navbar-dark` instead, so the theme's renderer now writes `navbar-dark bg-primary` (its
  "coloured, light font" choice) and nothing else; the focus bar copies only `navbarcolorclasses`. The pill
  tokens and the dark logo key on `.navbar.navbar-dark`; the navy dropdowns answer Boost Union's hover
  properties (`--bu-navbar-bg`, `--bu-navbar-dropdown-hover-color`) on the menu, or a hover would be navy on
  navy. Focus mode hides Boost Union's new `theme-block-region-header-wrapper` (with its spacers) instead of
  the header region alone. Auto-hide stands down on the limited-width pages where Boost Union pins the drawers
  flush with the window edges (an outside or header block region shown), whose `!important` rules it cannot
  move. In the dark colour mode Boost Union now paints the navbar with the body background, brand colour
  included: Boost Union's decision, kept.
- **Focus mode switches in place, without a reload** (2026100517; `docs/focusmode/README.md`, decisions D1-D5).
  Every activity page that can be focused carries both skins: Boost Union's layout renders untouched and the
  theme adds the bar (after core's top of body) and the foot of the content (the completion line and the
  previous/next row), shown only under the `dimensions-focusmode` body class. The entry button and the exit
  switch the class, move the header actions (the manual completion button or its status) between the page
  header and the bar, move the focus, announce the mode and record the choice for the course through a new
  web service, `theme_dimensions_set_focus_mode`; without JavaScript they post to `focus.php` and reload the
  activity. Leaving a course that switched focus mode on lasts the rest of the session in that course (D1);
  a single activity course that switched it on leaves for the site home page instead (D2). The copied
  shell `templates/focusmode.mustache` and `focusmode_description.mustache` are deleted; core's activity
  information box is kept and reduced to the description in focus mode.
  - **The master switch ships off (D5) and, off, loads nothing (D4)**: no bar, no button, no module, no
    stylesheet (`scss/focusmode.scss` is compiled only while it is on; the setting rebuilds the theme's
    CSS), no course form box, and the service refuses. A site that stored a value keeps it. The tab's other
    settings hide while it is off.
  - Strings `focusmode_close_title_site`, `focusmode_status_on`, `focusmode_status_off`,
    `focusmode_unavailable`; `focusmode_close_title` and `focusmode_desc` reworded.
  - readingbar and compactnav skip the focus bar when they look for the navbar, and auto-hide stands down
    under focus mode.
  - Mutation gates: six retired with the shell (`focusmode_template_swap`, `focusmode_footer_partial`,
    `focusmode_blockdrawer`, `focusmode_description`, `focusmode_drawercontrols_block`,
    `r6s21_focus_guest_login`), two rewritten, twenty-three added; every line of the spec dry-matched.

- **The landing page's hero takes the brand colour.** The hero and the guest entry's card paint a gradient of
  `$primary` (a head 18% darker, the colour, a tail 12% lighter: the stops of `branding::stops()`) instead of the
  fixed navy, and the guest entry's button writes its label in that colour. Boost Union compiles the sheet once per
  flavour, so each flavour's brand colour gets its own hero, an anonymous visitor's included. A brand colour on whose
  lightest stop white text reads under 4.5:1 keeps the navy; Boost's default blue is one, so a site with no brand
  colour looks as before. Stylesheet only: no version bump; a site picks it up after a cache purge.
  - `tests/local/landing_scss_test.php` compiles `scss/landing.scss` against Bootstrap with four brand colours.

- **The Brazilian Portuguese pack calls the landing page "Página de apresentação do curso".** Every "hotsite"
  in `lang/pt_br` (37 strings: the course notice chips, the field descriptions, the recommendations, the settings
  tab) now uses the name the custom field category already had, with articles and agreements rewritten to the
  feminine noun. The settings tab is named after that category in English too ("Course landing page", formerly
  "Landing pages"). Values only: no key changed, so no version bump; a site picks it up after a cache purge.

- **Landing page defaults for new courses** (2026100516). A new *Course landing page* tab sets what a course created from now on
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
