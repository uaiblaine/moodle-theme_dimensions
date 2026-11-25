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

### 5. Course competencies (`admin/tool/lp/coursecompetencies.php`)

| Element | Source | Cost |
|---|---|---|
| Name, description, path, linked activities, course outcome select, push-ratings setting | core's export | as core |
| Each activity's outcome | `competency_modulecomp.ruleoutcome`, one statement for the course; read-only here, edited in the activity's form (core has no web service for it) | 1 |
| Class distribution per competency | one aggregate over `competency_usercompcourse` joined to the enrolled set (`get_enrolled_with_capabilities_join`) | 1, cached |
| Learner: own rating | core's export | as core |
| Learner: state of each activity | `completion_info` for the course, `cm_info` availability | 1 |
| Learner: evidence count and latest date per competency | one grouped statement | 1 |
| Dropped: the level a "complete" outcome grants, who follows the plan, a per-course contribution sentence, "waiting for your reading" | core has no such field, or the status is not course-scoped | |

The class aggregate goes in a MUC application cache keyed by the course with a revision; rating, evidence,
completion, enrolment and review events bump it. A cold node rebuilds it from the database.

### 6. A learner's competency in a course (`admin/tool/lp/user_competency_in_course.php`)

A template copy (`user_competency_summary_in_course`): who and what first, with "Rate" as the one primary
action; a band of four figures; the evidence of this course as a bounded table; linked activities and plans
below. The list is a page's worth, so it is server-drawn in the data report's look, without its engine.

### 7. Class (`report/competency/index.php`)

A data report (`moodle-dev/docs/design-system/components/data-report.md`): one row per learner, one column per
course competency (chosen in Columns), the band over the same rows. It needs a Report Builder entity over
`competency_usercompcourse` that core lacks; a spike decides between a system report with one column per
competency and a long form (one row per learner and competency). Core's grading popup stays the row's action.

## Phases

| Phase | Content | Repos | Tier |
|---|---|---|---|
| 0 | Commit `docs/competency/`; dependencies in `version.php`, `ci.yml` and the README | theme | 3 |
| 1a | Public plan-card builder; the Design System bar on the block | block_dimensions (local_dimensions if the builder moves there) | 2 |
| 1b | Learning plans page: cards, scope by status, count, search, with what it is the first reader of: the "Competencies" settings tab and its card layout setting, the derived renderer with its factory test, pattern E1 (a web service) | theme | 3 |
| 1c | Filters by the template's tags, favourites on one's own list, Organise (sort only); done | theme, plus the star outside the block in block_dimensions | 3 |
| 2a | Competency from a parameter, plan review actions, link back | local_dimensions | 3 |
| 2b | The owner's redirects | theme | 3 |
| 3 | Evidence page | theme | 3 |
| 4 | Course competencies, teacher and learner | theme | 3 |
| 5 | A learner's competency in a course | theme | 2 |
| 6 | Class report: spike, then the report | theme | 3 |
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
