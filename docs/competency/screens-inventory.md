# Competency screens: inventory (round 3, 2026-11-11)

Every screen where a learner or a member of staff meets competencies, plans or evidence, across
core and the family plugins, on Moodle 5.3. Read from source and from each repository's README and
`CLAUDE.md`; no page was rendered for this list. The actions and capabilities behind the core and
hub screens are in `actions-inventory.md`; this file is the list of surfaces a design has to cover.

Personas: **L** learner, **T** teacher or rater in a course, **R** reviewer or mentor (a role in
the learner's user context), **M** plan, template or framework manager, **V** public visitor.

"Theme today": **copied** = the theme overrides the template and restyles it (`scss/competency.scss`,
`dim-comp-` classes); **core** = core's markup, reached only by page-class rules; **own** = the
plugin's own templates.

## 1. Core: learner and staff reading a learner

| # | Screen | Script | Persona | What it shows today | Theme today |
|---|---|---|---|---|---|
| 1 | A user's learning plans | `tool/lp/plans.php` | L, R, M | Table: name, template, status, row menu | core (`plans_page` not copied) |
| 2 | Learning plan | `tool/lp/plan.php` | L, R, M | Status and workflow links in parentheses, progress bar, table of competencies with rating and proficiency, comments | core page, copied parts (`progress_bar`, `competency_plan_navigation`) |
| 3 | Competency in a plan | `tool/lp/user_competency_in_plan.php` | L, R, M | Competency summary, rating, proficiency, review status, evidence list, comments | copied (`user_competency_summary`, `evidence_summary`, `competency_summary`, `related_competencies`, `linked_courses_summary`); the `_in_plan` wrapper is core |
| 4 | Competency of a user (no plan) | `tool/lp/user_competency.php` | L, R | Same body as 3 | copied body |
| 5 | Evidence of prior learning, list | `tool/lp/user_evidence_list.php` | L, R | Table: name, linked competencies, row menu | core |
| 6 | Evidence of prior learning, one | `tool/lp/user_evidence.php` | L, R | Name, description, url, files, linked competencies with status | core |
| 7 | Evidence form | `tool/lp/user_evidence_edit.php` | L, R | moodleform | core |
| 8 | Rating dialogue | `tool_lp/competency_grader` | T, R | Rating select, evidence note | copied |
| 9 | Competency summary dialogue | `tool_lp/competency_summary` | everyone | Description, path, related, linked courses | copied |
| 10 | Learning plans block | `block_lp` summary | L, R | Active plans, competencies to review, plans to review | copied |
| 11 | Competencies to review / plans to review | `block_lp` pages | R, M | Two link lists | copied |
| 12 | Profile: "Learning plans" link | `user/profile.php` | L, T | One link in a profile card | own profile renderer, link only |

## 2. Core: course

| # | Screen | Script | Persona | What it shows today | Theme today |
|---|---|---|---|---|---|
| 13 | Course competencies | `tool/lp/coursecompetencies.php` | L, T | Statistics, list with description, linked activities, the course outcome select (T), the learner's own rating (L) | core page, copied statistics |
| 14 | Competency of a user in a course | `tool/lp/user_competency_in_course.php` | L, T | Jump to user, jump to competency, summary, evidence, Rate | copied body and navigation |
| 15 | Competency breakdown report | `report/competency/index.php` | T | User select, activity filter, table of competencies by rating; a cell opens 14 as a dialogue | copied |
| 16 | Activity form, Competencies section | `course/modedit.php` | T | Picker, outcome on completion | core |

## 3. Core: management

| # | Screen | Script | Persona | Theme today |
|---|---|---|---|---|
| 17 | Frameworks list, framework form, scale configuration | `tool/lp/competencyframeworks.php`, `editcompetencyframework.php` | M | core |
| 18 | Competency tree, competency form, rule dialogue | `tool/lp/competencies.php`, `editcompetency.php` | M | core |
| 19 | Templates list, template form | `tool/lp/learningplans.php`, `edittemplate.php` | M | core, copied `template_statistics` |
| 20 | Template competencies, template plans, template cohorts | `templatecompetencies.php`, `template_plans.php`, `template_cohorts.php` | M | core |
| 21 | Plan form | `tool/lp/editplan.php` | M | core |
| 22 | Import, export, migrate frameworks | admin tree | M | core |

On a site with `local_dimensions`, 17 to 22 are done in the hub (section 5); the core pages stay
reachable and are not the design's priority.

## 4. local_dimensions: learner

| # | Screen | Entry | What it shows today |
|---|---|---|---|
| 23 | Full plan overview | `view-plan.php` | Hero (name, description, due date, template colour and image), filter line, accordion or cards of competencies with rating, proficiency, star, status pills from the hook |
| 24 | Competency detail (in the accordion or a modal) | four tabs | Related content (course cards with progress, activities), Description (taxonomy, path, related), Progress (rating, scale, evidence list), Rules in plain words; pager "5 of 8" |
| 25 | Evidence detail modal | from 24 | Type, date, grade, note, files, actor, pager |
| 26 | Competency tracker | `view-competency.php` | Competency hero, course cards with sections and progress, locked-card variants, enrolment states |
| 27 | Return to plan button | floating, inside a course | Label and colour of the plan |

Metadata the plugin adds (competency and template): background and text colour, background and
card image, `tag1`, `tag2`, `type`, custom SCSS; per template the display mode (23 or 26), the
subline source and the locked-card behaviour. Hook `competency_status_badges` lets another plugin
add a plain-text pill (tone success, warning, danger, info, neutral) per competency.

## 5. local_dimensions: hub (`central.php`), manager

| # | Screen | What it does |
|---|---|---|
| 28 | Context bar | System or category |
| 29 | Structures tab | Frameworks: new, import, export, edit, hide, duplicate, delete |
| 30 | Competencies tab | Tree and detail pane: add, edit, rules, course and activity links, related, move, usage |
| 31 | Learning plans tab | Templates, their competencies, participants (cohorts, users, roles, enrolment methods) |
| 32 | Look and feel fields in the forms | Colour swatch with contrast verdict, icon picker, images |

The owner considers React Flow for 30 (see `react-flow.md`); the same component read-only is a
candidate learner view (board B1 of round 3).

## 6. block_dimensions: learner

| # | Screen | What it shows today |
|---|---|---|
| 33 | Block on the dashboard or a course | Plans section and competencies section, each with a filter line |
| 34 | Dimensions page | `blocks/dimensions/index.php`: the same content full width; can be the start page |
| 35 | Plan card | Cover or colour, tags, name, status, competency count, five-step trail centred on the last proficient competency, button |
| 36 | Competency card | Cover or colour, tags, name, button |

## 7. local_evidencehub

| # | Screen | Entry | Persona | What it shows today |
|---|---|---|---|---|
| 37 | Evidence page | `evidence.php` | L (own), R, M | A card per evidence: type, state badge, who decided and when, issue and expiry, issuer, hours, rejection note, linked competencies with validity; table of shared dossiers |
| 38 | Evidence and verify forms | modal | L (own evidence), R | Type, dates, issuer, hours, code, note |
| 39 | Validity pills | inside 23, 24, 26 through the hook | L, M | Pill and date under the competency title |
| 40 | Hub, list | `index.php` | M | KPI tiles per status, readiness rate, table of worker and competency pairs |
| 41 | Hub, matrix | `index.php?view=matrix` | M | Workers by competencies, coloured cells with days left |
| 42 | Inspection drawer | from 40, 41 | M, R | Evidence in force, policy, timeline of earlier evidence with gaps, audit trail |
| 43 | Policies | `policies.php` | M | Policy per framework or competency |
| 44 | Dossier PDF and ZIP | export | M | One page per worker with a QR code |
| 45 | Public verification | `verify.php` | V | Authentic or revoked, masked name, status per competency |

40 to 42 are the screens closest to a human-resources dashboard. They answer a compliance
question (who is covered today) and keep that job; the design gives the learner's side (37, 39,
45) the journey reading and leaves the matrix as a staff tool.

## 8. Entry points outside the competency pages

| # | Screen | State |
|---|---|---|
| 46 | block_compass | Shows courses and the calendar only; nothing about competencies or plans today |
| 47 | Profile page (theme) | Core's tree as cards; a design study exists in `docs/profile/canvas` ("My path" card for the owner, "Plan competencies" card for a teacher), not implemented |
| 48 | Course card | The shared contract (`moodle-dev/docs/course-card/CONTRACT.md`) has a plugin slot; no competency mark on it today |
| 49 | Calendar, Trilhas view | Trails are course trails, not plans |

## 9. What the list says

- A learner's recognition is recorded in nine places (2, 3, 13, 14, 23, 24, 26, 35, 37) and told
  as a story in none: each prints the current rating and a list.
- Evidence has three unrelated renderings: core's evidence log on a competency (3), core's prior
  learning pages (5, 6) and evidencehub's cards (37). One evidence component, with one anatomy,
  serves all three.
- The teacher's work is spread over 8, 14, 15 and the two link lists of 11; nothing lets a rater
  read the learner's record and rate in the same place, then move to the next.
- The theme copies the inner templates of core and not the page templates (`plan_page`,
  `plans_page`, `course_competencies_page`, `user_evidence_*`, the `_in_plan` and `_in_course`
  wrappers), so the page structure is still core's.

## 10. Learning outcomes (Moodle 5.3, MDL-89106) and access points (added 2026-11-11)

Read from the 5.3 checkout against 5.2. "Learning outcomes" are the gradebook's outcomes renamed,
not a new subsystem: the scale became optional (a scale-less outcome is informational, mapped to
activities through the new table `grade_outcomes_modules`, and is never rated), and a course page
`course/learningoutcomes.php` lists each outcome with its mapped activities. Any enrolled user
opens it from the course menu, beside Grades and Competencies; it shows no result of the learner.
Off by default (`enableoutcomes`). **No code links outcomes to competencies** (no mapping, no
evidence, no shared scale); "outcome" in `tool_lp` is the unrelated rule outcome.

| # | Screen | Script | Persona | Theme today |
|---|---|---|---|---|
| 50 | Learning outcomes of a course | `course/learningoutcomes.php` | L, T | core |
| 51 | Manage learning outcomes | `grade/edit/outcome/*` | T, M (`moodle/grade:manageoutcomes`) | core |
| 52 | Learning outcomes report | `grade/report/outcomes/index.php` | T, M | core |
| 53 | Activity form, Learning outcomes section | `course/moodleform_mod.php` | T | core |

**Out of scope, decided by the owner on 2026-11-11.** This design does not touch learning
outcomes: no board, no link from the competency pages, and no outcome-to-competency mapping
(`local_dimensions` creates no table, as a rule of the plugin). The facts above stay as a record
for a later round.

Access points, as read from source (boards "Acesso · aluno", "Acesso · professor e equipe"):

- A learner reaches their own plans in core only through the profile node, `block_lp` and a plan
  comment notification; nothing in the primary navigation or the user menu.
- `local_dimensions`' `view-plan.php` and `view-competency.php` have no inbound link outside
  `block_dimensions` (cards, trail, start page), the return button and their own pages.
- `user_competency_in_plan.php` has no link in core's templates: a plan row opens a dialogue. It
  is reached from its own jump select, messages, events and evidencehub's drawer.
- A teacher has seven routes to one learner's competency (course menu then "View details", the
  breakdown report from Reports and from an activity's menu, the profile, `block_lp`, a comment
  message, evidencehub's drawer); the design ends them all at the review desk (board 11).
- No plugin replaces a core page. `local_evidencehub` adds a banner to core's evidence pages; the
  hub edits the same records as core's management pages. `$CFG->core_competency_url_resolver` is
  the one place a plugin could redirect core's links, and the design does not use it.
