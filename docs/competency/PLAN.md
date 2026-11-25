# Competency pages: round 4 plan (2026-11-23)

Supersedes the open questions of `README.md` (rounds 1 and 2) and of the November canvas. Nothing here is
implemented. The mockups of this round are the canvas "Competências no tema: versões finais"
(https://claude.ai/artifact/Mks6WTEiGWSNtwcJjQciSH), whose sources are `final/project/`.

## What the owner decided (2026-11-23)

- The "Fluxo" approach is dropped. No new pages, workspaces or panels in the theme.
- **The theme redraws core's competency pages and nothing else.** New pages and panels belong to the
  companion plugins. Staff screens that core does not have are future `local_dimensions` work; the core pages
  a teacher uses are the theme's.
- The theme requires its companions. **Hard dependencies (D1, decided 2026-11-23): `local_dimensions` and
  `block_dimensions`.** `local_evidencehub` stays optional, asked by class name like the theme's other
  optional plugins. Phase 0 declares both at their `main` numbers of that day (local_dimensions 2026100532,
  block_dimensions 2026100506) and adds `uaiblaine/moodle-local_dimensions,main` and
  `uaiblaine/moodle-block_dimensions,main` to `plugin-dependencies` in `ci.yml`; phases 1b and 2b raise the
  numbers to the versions that carry the card builder and the overview's new parameter.
- Per screen of the November canvas:

| Row | Screen | Decision |
|---|---|---|
| 1 | Learning plans list | Theme. The look of block_dimensions' plan card, horizontal or vertical by a theme setting; a click opens the plan in local_dimensions. The block's state filter moves to the Design System bar |
| 2 | Plan | Sent to local_dimensions' plan overview |
| 3 | Competency in a plan | Sent to local_dimensions. The evidence journey study becomes a third view there, expanding the Progress tab |
| 4 | Evidence | Theme. One central, filterable list, platform evidence included |
| 5 | Course competencies | Theme, teacher and learner, after checking data and cost (below) |
| 6 | A learner's competency in a course | Theme, in the data report's visual grammar |
| 7 | Class | Theme, as a data report |
| 8 to 11, 13 | Staff plan, management, entry points, public check, October references | Dropped |
| 12 | Evidence hub drawer | The evidence hub's own session |

## Who does what

| Plugin | Work |
|---|---|
| theme_dimensions | Template copies and one derived renderer for `plans.php`, `user_evidence_list.php`, `coursecompetencies.php`, `user_competency_in_course.php` and `report/competency/index.php`; the owner's redirect of `plan.php` and `user_competency_in_plan.php`; the settings tab |
| block_dimensions | A public builder of the plan card another plugin can call; the Design System filter bar |
| local_dimensions | The destination of both redirects (a competency opened by a parameter, the plan actions a learner loses); the "Journey" view |
| local_evidencehub | Validity of prior-learning evidence, shown on the theme's evidence page when installed; its drawer is its own work |

## Facts the design rests on (read in the code, 2026-11-23)

1. **Core re-renders its pages on the client after every action**, from a web service whose return structure
   is fixed: `tool_lp_data_for_plans_page`, `tool_lp_data_for_user_evidence_list_page`,
   `tool_lp_data_for_course_competencies_page`, `report_competency_data_for_report`. Whatever a theme renderer
   adds to the first render is gone after the first action. So every extra the theme draws (cover, trail,
   class distribution) is fetched by the theme's own module and put back after core replaces the region
   (pattern E1 below).
2. **Core's modules need their hooks.** `planactions`: `[data-region="plans"]`, one `[data-region="plan-node"]`
   ancestor with `data-id` and `data-userid`, the nested `ul.planactions > li > a + ul` menu.
   `user_evidence_actions`: `[data-region="user-evidence-list"]`, `[data-region="user-evidence-node"]`.
   `competencies`: `[data-region="coursecompetenciespage"]`, `drag-samenode`, `drag-parentnode`,
   `drag-handlecontainer`, `a[data-action="delete-competency-link"][data-id]`,
   `select[data-field="ruleoutcome"][data-id]`. Each template copy keeps them, held by
   `competency_templates_test` as today.
3. **Core's evidence page lists prior-learning evidence only** (`competency_userevidence`). What the platform
   records (activity or course completed, a rating, a parent's rule) is in `competency_evidence`, reachable
   one competency at a time; no core API lists a user's evidence across competencies.
4. **local_dimensions' plan overview is read-only.** No plan workflow (request or cancel a review, complete,
   reopen, edit, add competencies), no rating, no comments, no link back to core's page. It opens for the
   owner and for anybody core lets read the plan.
5. **Core's class report is one learner at a time**, picked in a select; it is not a matrix. Core has no
   Report Builder entity for course-scoped ratings (`competency_usercompcourse`), evidence or module links.
6. **A learner's view of course competencies writes rows**: `api::list_user_competencies_in_course()` inserts
   a `competency_usercompcourse` row per missing competency on a GET. Class figures never call it per user.
7. `competency_usercompcourse` has no index leading with `courseid`; core's own statistics scan it and count
   every row of the course, enrolled or not.
8. The block's plan card is drawn in the browser from `block_dimensions\local\dataset_provider`, whose card
   builders are protected, whose layout is the site setting `block_dimensions/plancard_layout`, and whose CSS
   is scoped under `.block_dimensions`. Drafts and plans on competencies-mode templates get no plan card.

## Pattern E1: extras that survive core's re-render

One mechanism for the three redrawn pages that have actions:

- The template copy draws core's content and hooks from core's context alone, and one placeholder per item
  (`data-region="dim-comp-extra"`, keyed by the item's id).
- A theme web service returns the extras for the page (plans of a user, competencies of a course), with its
  own access check mirroring core's for that page.
- A theme module fills the placeholders on load and again whenever core replaces the region
  (a `MutationObserver` on the region root), under a Pending token.
- Without JavaScript the first render already has the extras, from the derived renderer
  `theme_dimensions\output\tool_lp_renderer` (the name theme_overridden_renderer_factory derives for
  `$PAGE->get_renderer('tool_lp')`).

## Pages

### 1. Learning plans (`admin/tool/lp/plans.php`)

- Cards from block_dimensions' builder, made public first (companion change): every status including
  drafts, a plan card whatever the template's display mode, the orientation handed in, no favourite star on
  another user's list.
- Theme setting "Plan card layout" (horizontal, vertical) on a new "Competencies" settings tab.
- The bar: scope by status (active, in review, completed, drafts where the viewer can read them), count,
  the favourites star, Filters (the template's tags), Organise, search. Core's "Add new learning plan" and
  "Evidence of prior learning" stay.
- Phase 1c (done, 2026100645 with block_dimensions 2026100508): the star keeps the favourites, offered by the block
  only on the viewer's own list with its favourites on; the card's own star is the block's `favourite_star` module,
  and the theme writes no favourite. Filters is a panel over the block's plan tag filters, values from the listed
  plans (OR inside a tag, AND across tags), applied on press, a chip per value in force and "Clear all". Organise is
  "Sort by" alone (Name, core's own `name ASC` order; Due date, undated last; Last change): this page has no
  grouping. Nothing is stored; the choices survive core's re-renders in the module's state, and the dates are core's
  own keys (`duedate`, `timemodified`), so they are there after a client re-render too.
- Core's row actions stay, as the card's menu, inside core's menu structure.
- The re-arm point is the template's js block: tool_lp/planactions replaces the region with the template rendered
  on the client and runs that block again (`templates.runTemplateJS`), so pattern E1 needs no `MutationObserver`
  on this page. The first paint is decorated by `local\competency\plancards` through the derived renderer; the
  module `theme_dimensions/plancards` puts the remembered cards back at once and refills them from
  `theme_dimensions_get_plan_cards`.
- Cost: `api::list_user_plans()` once (as core), the block's caches for cover, tags and trail; cold, one
  statement per plan for the trail.

### 2 and 3. Plan and competency in a plan

- `after_config`, beside the theme's existing redirects: **only the plan's owner is redirected**, to
  `/local/dimensions/view-plan.php?id=` (and, for a competency, with the competency named so the overview
  opens it). Reading `userid` of the plan before core's checks reveals nothing: any plan that is not the
  viewer's own, missing ones included, behaves the same.
- Staff keep core's pages, where rating and the review workflow live.
- `noredirect=1` keeps core's page for the owner (the overview links to it as "Manage plan" while it lacks
  the plan actions).
- Companion change in local_dimensions before the redirect ships: open a competency from a parameter; request
  and cancel a plan review; the link back.
- Core's and the theme's own Behat features that open a plan as its owner change with it.
- Phase 2b (done, 2026100646 with local_dimensions 2026100543): `local\competency\ownerredirect`, called from
  `after_config` on the two scripts. A GET from a logged-in account other than the guest, with the switch
  `planownerredirect` on (the "Competencies" tab, default on) and no `noredirect`, competencies enabled and
  `local/dimensions:view` at the system context (the overview's own gate), reads `competency_plan.userid` by id once
  and redirects only when it is the session user's; the competency page's `competencyid` is carried, its `userid`
  is never read. Everything else is core's page, untouched. The theme's Behat page type "learning plan" opens core's
  page with `noredirect=1`; one scenario holds the redirect for the owner and core's page for a manager.

### 4. Evidence (`admin/tool/lp/user_evidence_list.php`)

- One dated list: the user's prior-learning evidence (from `competency_userevidence`, with what it feeds
  from `competency_userevidencecomp`) and the platform's records (from `competency_evidence`, without the
  prior-learning link rows, which would repeat the first kind).
- A read class in the theme: `user_competency::can_read_user($userid)` once, then the cross-competency query
  with keyset paging on `(timecreated, id)`; a filter by competency goes through the unique
  `(userid, competencyid)` index, by source through the context level.
- Records of other components (the evidence hub's validity log is 59 % of the rows on m503) are folded into
  one quiet line per month.
- Filters: scope (all, mine, from the platform), competency, plan, course, period, search.
- Core's actions stay (add, edit, link, send for review, delete); the selected evidence opens beside the list.
- Validity ("Valid until") comes from local_evidencehub when installed.
- Phase 3 (done, 2026100649): the theme's copy of `tool_lp/user_evidence_list_page` draws core's evidence as dated items
  (core's `timecreated`), keeping every hook of `tool_lp/user_evidence_actions` and the menu inside core's structure;
  the derived renderer puts the first paint newest first and heads it by month, reading nothing more than core's page.
  The platform's records need JavaScript: `theme_dimensions/competencyevidence` asks
  `theme_dimensions_get_user_evidence_feed` (read class `local\competency\evidencelist`), which repeats
  `api::list_evidence()`'s rule (`user_competency::can_read_user()`, once), reads `competency_evidence` joined to
  `competency_usercomp` with keyset paging on (timecreated, id), leaves out core_competency's
  `evidence_evidenceofpriorlearninglinked` rows and folds every other component's rows per month. The re-arm point is
  the template's js block, as on the plans page: `user_evidence_actions` replaces the region through
  `templates.replaceNode()`, which runs it again. The detail beside the list is copied from each item's template
  element; its buttons press the item's own menu entry, so core's handlers stay the only ones.
  Left out: validity (local_evidencehub keeps it per user competency, and its one door, local_dimensions'
  `competency_status_badges` hook, is dispatched by local_dimensions for its own pages), the date button's month
  grids (the layer has quick options and two native day fields), the skeleton's shimmer, a search beyond the titles
  shown (descriptions are composed strings, not searchable in SQL), and a source filter beyond the course (the
  context path).

### 5. Course competencies (`admin/tool/lp/coursecompetencies.php`)

| Element | Source | Cost |
|---|---|---|
| Name, description, path, linked activities, course outcome select, push-ratings setting | core's export | as core |
| Each activity's outcome | `competency_modulecomp.ruleoutcome`, one statement for the course; read-only here, edited in the activity's form; core's `core_competency_list_course_module_competencies` already returns `ruleoutcome` to any viewer of the activity holding `coursecompetencyview`, so showing it beside the activity is parity | 1 |
| Class distribution per competency | one aggregate over `competency_usercompcourse` joined to the enrolled set (`get_enrolled_with_capabilities_join`) | 1, cached |
| Learner: own rating | core's export | as core |
| Learner: state of each activity | `completion_info` for the course, `cm_info` availability | 1 |
| Learner: evidence count and latest date per competency | one grouped statement | 1 |
| Dropped: the level a "complete" outcome grants, who follows the plan, a per-course contribution sentence, "waiting for your reading" | core has no such field, or the status is not course-scoped | |

The class aggregate goes in a MUC application cache keyed by the course with a revision; rating, evidence,
completion, enrolment and review events bump it. A cold node rebuilds it from the database.

Phase 4 (done, 2026100647). What it has:

- The copy `templates/tool_lp/course_competencies_page` renders from core's context alone, with every hook of
  `tool_lp/competencies` and core's Behat selectors; per competency a `dim-comp-extra` slot, per listed activity a
  `dim-comp-cm` slot. The derived renderer's `render_course_competencies_page()` decorates the first paint through
  `local\competency\coursecompetencies`.
- The re-arm point is the template's js block, as on the plans page: core's add, delete, course outcome and ratings
  setting replace the region (`templates.replaceNode` or `replaceWith` with `runTemplateJS`) from
  `tool_lp_data_for_course_competencies_page`, always with `moduleid: 0`, and run the block again; reordering moves the
  rows in place. `theme_dimensions/coursecompetencies` puts the remembered extras back and refills them from
  `theme_dimensions_get_course_competency_extras`. No `MutationObserver`.
- The activity outcome is shown to every viewer, for the activities core's page lists to that viewer (`uservisible`).
- The class distribution goes only to a viewer who may see the whole class (`coursecompetencymanage`, or `competencygrade` in a course not in separate groups mode or with `moodle/site:accessallgroups`; a grader limited to their groups gets none): three
  counts, drawn as the proficient share on the progress bar and the other two as a line (the Design System decides no
  stacked bar). The enrolled set is `get_enrolled_with_capabilities_join()` with
  `moodle/competency:coursecompetencygradable`, suspended enrolments included (core rates them), counted once per user.
- The cache: `coursecompetencyclass` (per course: counts, the course and site revisions, the competency ids, the time
  built) and `coursecompetencyrev`. Events: rated in course, evidence created, enrolment created and deleted, course
  reset ended, role assigned and unassigned, capability assigned and unassigned, role deleted, user deleted. A
  completion rates through its evidence, so `course_module_completion_updated` is not observed; a course competency
  added or removed fires no event and is seen by the competency ids; the review events change no course figure.
- The learner: their state of each listed activity (`completion_info`, done with its date or not yet) and their
  evidence count and latest date per competency in the course's context and its activities' (one grouped statement).

What it left out: the mockups' tiles above the list (core's statistics stay instead), the "Class" tab and the
per-level stacked bar (phase 6 and an undecided component), the side panels (how to read the class, what each outcome
does, the learner's plans with this course), the course outcome sentence for the learner (core shows the course
outcome to managers only), the scale strip of the learner's level, and the dropped items above.

### 6. A learner's competency in a course (`admin/tool/lp/user_competency_in_course.php`)

A template copy (`user_competency_summary_in_course`): who and what first, with "Rate" as the one primary
action; a band of four figures; the evidence of this course as a bounded table; linked activities and plans
below. The list is a page's worth, so it is server-drawn in the data report's look, without its engine.

**Done (phase 5, 2026100648).** `templates/tool_lp/user_competency_summary_in_course` (with its `.upstream`) draws, from
core's export alone: the learner's picture and name and the competency summary, *Rate* as the one `btn-primary` under
core's `cangrade` (core's `#rate_<uniqid>` and inline grader); a band of four tiles, a `dl` that keeps core's Behat
pairs: the rating (`usercompetencycourse.gradename`), proficiency (`proficiencyname`, the status label with its icon),
the evidence of this course (a count, and `evidence.0.userdate` as "Most recent") and the learning plans core lists
(a count); the evidence as a table in a 34rem frame, newest first as core lists it (`description` with `url`,
`userdate`, the rating badge, `actionuser` through `tool_lp/user_summary`, `note`, the delete trigger under
`candelete`), each row carrying the evidence partial's hooks; the linked activities and the plans as two panels. The
two counts need a length, which Mustache lacks: `tool_lp_renderer::render_user_competency_summary_in_course()` writes
`dimband` from the export (no query) for the first paint, and the template's js block counts the rows again on every
render (core's `tool_lp/user_competency_info` replaces the root and runs the block again) and on each removal by
`tool_lp/evidence_delete` (a `MutationObserver` on the rows). No web service, no SQL, nothing shown beyond what core's
page shows the same viewer (the learner's identity fields are left out of the head).
Left out: the mockup's "Activities completed" (core exports no completion state for the linked modules), who rated
and when under the rating (not in the course record core exports), each plan's status label (core's page does not
show it), the course navigation's "11 of 24 learners" (core's navigation template is unchanged), sorting the table
(no engine; core's order is newest first) and the scale strip of the mockup.

### 7. Class (`report/competency/index.php`)

A data report (`moodle-dev/docs/design-system/components/data-report.md`): one row per learner, one column per
course competency (chosen in Columns), the band over the same rows. It needs a Report Builder entity over
`competency_usercompcourse` that core lacks; a spike decides between a system report with one column per
competency and a long form (one row per learner and competency). Core's grading popup stays the row's action.

The spike chose the system report with one column per competency (D6), built per request as enrol_apply's question
columns are. Phase 6 is split in two pull requests:

- **Part one (done, 2026100650): the server, usable alone through core's Report Builder table.** The derived
  renderer `theme_dimensions\output\report_competency_renderer` draws the class mode when the request names no
  `user` and `local\competency\classreport::may_view()` passes: `render_report()` the system report
  `reportbuilder\local\systemreports\class_ratings` instead of core's export (which inserts rows),
  `context_header()` nothing instead of the first learner's heading index.php prints, and the navigation with
  "All learners" chosen (`user=0`) and its group selector on the class address. With `?user=` every call is core's,
  through the untouched copy of `report_competency/report`. The report: `{user}` restricted by core's
  enrolled-with-capability join (`coursecompetencygradable`) as a subquery, in the viewer's groups
  (`classreport::groups()`: separate groups without `accessallgroups` read their own groups, none in no group, decided
  before the join); the learner's name linked to the breakdown, core's identity columns and filters; one column per
  course competency, at most `classreport::MAX_COLUMNS` (30) in the course's order, keyed `comp:<competencyid>`
  (`class_ratings::active_keys()`), each two correlated subqueries on the unique `(userid, courseid, competencyid)`
  index of the entity `reportbuilder\local\entities\coursecompetency`, shown as the rating's name on that
  competency's scale (scales read once per request), sortable, with a select filter per competency (the scale and
  "Not rated"); the grading dialogue's hooks on the cells of a viewer holding `competencygrade`; Report Builder's
  download. `amd/src/classratings.js` opens core's dialogue from a cell and dispatches Report Builder's `tableReload`
  on close. The stored filter values are the theme's personal data (D146, `local\competency\reportpersistents`).
  Statements per page: constant in the learners, nothing per column beyond the subqueries (measured by
  `class_ratings_test::test_the_statements_of_a_page_are_bounded`).
- **Part two, server half (done, 2026100651): the services and the column preference.** The Columns choice is
  `local\competency\classreportpreference`, one user preference per course (`theme_dimensions_classreport_<id>`,
  JSON `{"c": [competency ids], "w": {key: px}}`), read in `class_ratings::initialise()` and re-validated against
  the course's competencies on every read; `classreport::chosen()` keeps the catalogue's ids named, in the course's order whatever order they were posted in, the
  first 30 when none is left, and filters exist for the shown columns only. Three services over
  `local\competency\classreportengine` (copied from local_evidencehub's `reportengine`): `class_report_rows`
  (courseid, moduleid, groupid with -1 for the page's choice, search, page, perpage 30/50/100, sort, dir,
  countfacets), `class_report_summary` (courseid, moduleid, groupid, search) and `set_class_report_preference`
  (courseid, usedefault, columns as `comp:<id>` keys, widths). Each runs `classreportengine::locate()` (the course,
  `validate_context()`, no guest, competencies enabled, `classreport::may_view()`), then builds the report with the
  group and the search as its own parameters (`class_ratings::PARAM_GROUP`, `PARAM_SEARCH`), so the group rule and
  the search are the report's base conditions on every door, the download included. The page is two statements
  (`reportbuilder\local\systemreports\class_ratings_table`), the facet counts and the summary conditional sums of
  one statement each.
- **Part two, client half (done, 2026100651): the data-report client** of `moodle-dev/docs/design-system/components/data-report.md`
  over those services, in `js/esm/src/classreport/` over the shared parts copied from local_evidencehub
  (`js/esm/src/datareport/`, class prefix `dim-comp-rview`, `scss/competencyreport.scss`). The shell
  (`output\class_report_view`, `templates/competency_classreport_view.mustache`) is core's `{{#react}}` mount with
  configuration and strings only (course, activity, the page's group, the report's id, page sizes, search minimum, the
  column limit, the download formats and core's `reportbuilder/download.php`, the "Table view" address, the strings),
  core's table inside `<noscript>`, and `?view=table` for part one's page whole. The grid (rows answer's `columns`,
  `rows[].cells`; `rows[].ratings` for the cards' line), the Columns menu (catalogue keys, at most 30 shown, the last
  one kept, written through `set_class_report_preference`, then the rows and the band read again), the band
  (`class_report_summary`: four tiles and "By competency"), the filters panel (the rows answer's `filters`, written
  through core's `core_reportbuilder_set_filters` with the report's parameters), the search (two characters, 300 ms),
  the page's group posted on every read (never -1), the download through core's `reportbuilder/download.php` with the
  report's parameters, search included. A grader's cell opens core's dialogue through `amd/src/classratings.js`, whose
  close dispatches `theme_dimensions/classratings:rated` on the shell's root; the view reads its rows and band again.
  Behat: the class report scenario of `theme_dimensions_competency.feature` drives the view, a column hidden, a rating
  from a cell, and "Table view".

## Phases

| Phase | Content | Repos | Tier |
|---|---|---|---|
| 0 | Commit `docs/competency/`; dependencies in `version.php`, `ci.yml` and the README | theme | 3 |
| 1a | Public plan-card builder; the Design System bar on the block | block_dimensions (local_dimensions if the builder moves there) | 2 |
| 1b | Learning plans page: cards, scope by status, count, search, with what it is the first reader of: the "Competencies" settings tab and its card layout setting, the derived renderer with its factory test, pattern E1 (a web service) | theme | 3 |
| 1c | Filters by the template's tags, favourites on one's own list, Organise (sort only); done | theme, plus the star outside the block in block_dimensions | 3 |
| 2a | Competency from a parameter, plan review actions, link back | local_dimensions | 3 |
| 2b | The owner's redirects; done | theme | 3 |
| 3 | Evidence page (done, 2026100649) | theme | 3 |
| 4 | Course competencies, teacher and learner; done (2026100647) | theme | 3 |
| 5 | A learner's competency in a course; done | theme | 2 |
| 6 | Class report: spike, then the report. Part one (the server, core's table) done in 2026100650; part two (the services, the column preference, and the data-report client with Columns and the band) done in 2026100651 | theme | 3 |
| C | "Journey" view of the plan overview | local_dimensions | 2 |

Phase 0 carries only what stands on its own. The settings tab, the derived renderer and pattern E1 were
first planned there and moved to 1b: a setting nothing reads, a renderer that renders nothing new and a
refresh mechanism with no page to refresh cannot be shown to work until the plans page uses them.

Each phase is one pull request, gated at its tier on its final head. Phases 3, 4 and 6 add a web service or
SQL: `secure-by-design` before writing, `security-diff-read` before pushing.

## Decisions (owner, 2026-11-23: "go with what is recommended")

| Id | Question | Decided |
|---|---|---|
| D1 | Which plugins become hard dependencies? | local_dimensions and block_dimensions; local_evidencehub stays optional |
| D2 | Who is redirected from core's plan page? | The owner only; staff keep core's page |
| D3 | Which board of row 3 is the journey study? | The dated log (October 2A and November 3 and 4 share it). The link in the request could not be resolved to a board, so the owner may still name another |
| D4 | Where does the "Journey" view sit? | A third value of the overview's view switch, beside list and grid |
| D5 | Who owns the plan-card builder? | block_dimensions, which already owns the card |
| D6 | Class report rows | One row per learner, competencies as columns |
| D7 | Phone and dark boards | After the desktop set is approved |
