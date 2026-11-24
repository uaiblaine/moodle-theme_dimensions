# Competency screens: every action, by persona

Status: research for the competency design round 2 (2026-10-19). Read from Moodle 5.3 core
(`~/dev/moodle-503/public`) and `local_dimensions` main. Nothing was run. `A` is
`competency/classes/api.php`, `TLP` is `admin/tool/lp`. Capabilities are `moodle/competency:*`
unless named. Archetypes: M manager, ET editing teacher, T teacher, U authenticated user, S student.

## 1. What the defaults allow

| Capability | Context | Default holders |
|---|---|---|
| competencymanage | category | M |
| competencyview | category | U |
| competencygrade | course and user | ET, T, M |
| coursecompetencymanage | course | ET, M |
| coursecompetencyconfigure | course (declared at module) | M |
| coursecompetencygradable | course | S (marks who can be rated) |
| coursecompetencyview | course | U |
| templatemanage / templateview | category | M |
| planmanage, planmanagedraft, planview, planviewdraft | user | M |
| planmanageown, planmanageowndraft, planviewowndraft | user | nobody |
| planviewown, planrequestreviewown, plancommentown | user | U |
| planrequestreview, planreview, plancomment | user | M |
| usercompetencyview | user and course | M, ET, T |
| usercompetencyrequestreview / usercompetencyreview / usercompetencycomment | user | M |
| usercompetencyrequestreviewown / usercompetencycommentown | user | U |
| userevidencemanage / userevidenceview | user | M |
| userevidencemanageown | user | U |
| evidencedelete | user | nobody (site admins) |

Consequences that shape the design:

- A default teacher can rate (`competencygrade`) and read user competencies, but cannot start or
  stop a review: both review capabilities are manager-only. A mentor is a custom role in the
  learner's user context; core ships no mentor archetype.
- A learner cannot edit their own plan by default; they view it, request review, comment and
  manage their own evidence.
- Only a manager sets the course rule outcome and edits frameworks and templates.
- Buttons are drawn from exporter booleans (`plan_exporter.php:60-87`,
  `user_competency_exporter.php:75-85`), so a redesign reads those flags and never re-decides.

## 2. Workflow states

### Plan (`plan.php:42-54`)

| From | To | Control | API | Capability |
|---|---|---|---|---|
| new | draft or active | editplan.php status select (create only) | `create_plan` A:2516 | planmanagedraft / planmanage |
| template | active | Create learning plans; Add cohorts | `create_plan_from_template` A:2543, `create_plans_from_template_cohort` A:2611 | template read + planmanage |
| draft | waiting for review | Request review | `plan_request_review` A:2907 | planrequestreview[own]; not template-based |
| waiting | draft | Cancel review request | `plan_cancel_review_request` A:2872 | planrequestreview[own] |
| waiting | in review | Start review | `plan_start_review` A:2943 | planreview or manage |
| in review | draft | Finish review | `plan_stop_review` A:2979 | planreview or manage |
| draft, waiting, in review | active | Make active | `approve_plan` A:3017 | planreview or manage |
| active | draft | Send back to draft | `unapprove_plan` A:3056 | planreview or manage |
| any but complete | complete | Complete this learning plan | `complete_plan` A:3093 (archives user competencies) | manage |
| complete | active | Reopen this learning plan | `reopen_plan` A:3148 (drops the archive) | manage |
| template plan | own plan | Unlink from learning plan template | `unlink_plan_from_template` A:2685 | manage |

Template-based plans skip the review states entirely.

### User competency (`user_competency.php:44-50`)

| From | To | Control | API | Capability |
|---|---|---|---|---|
| idle | waiting | Request review | A:3523 | usercompetencyrequestreview[own] |
| idle | waiting | automatic: an outcome "Recommend the competency" | A:4454 | the originating action |
| idle | waiting | Send competencies for review (evidence list, batch) | A:4023 | userevidencemanage[own] |
| waiting | idle | Cancel review request | A:3492 | usercompetencyrequestreview[own] |
| waiting | in review | Start review (sets the reviewer) | A:3554 | usercompetencyreview |
| in review | idle | Finish review | A:3582 | usercompetencyreview |

Rating (`grade_competency`, `_in_plan`, `_in_course`, A:4878/4932/4991) writes an override
evidence and changes no status.

## 3. Framework manager (core tool_lp)

| Screen | Action (string) | Does | Capability |
|---|---|---|---|
| Frameworks list | Add new competency framework (`addnewcompetencyframework`) | editcompetencyframework.php | competencymanage |
| Frameworks list, row menu | Edit, Duplicate, Delete (`deletecompetencyframework`; refused while in use) | edit page; `duplicate_competency_framework` A:605; `delete_competency_framework` A:673 | competencymanage |
| Framework form | shortname, description, idnumber, scale + Configure scales, visible, taxonomies per level | `create_framework` A:577 / `update_framework` A:732 | competencymanage |
| Scale configuration dialogue | Default and Proficient per scale item | writes `scaleconfiguration` | competencymanage |
| Competency tree | Search | `search_competencies` | competencyview |
| Competency tree, selected menu | Edit, Relocate (`movetonewparent`), Delete, Move up, Move down, Linked courses, Add cross-referenced competency, Competency rule | edit page; `set_parent_competency` A:368; `delete_competency` A:224; `move_up/down_competency`; `list_courses_using_competency`; `add_related_competency` A:3751; `update_competency` (rule) | competencymanage (Linked courses: competencyview) |
| Related competency chip | delete icon (no confirmation) | `remove_related_competency` A:3775 | competencymanage |
| Rule dialogue | Outcome (None / Attach an evidence / Recommend / Mark as complete); When (all children / points); per child points + required; total required | `update_competency` | competencymanage |
| Tree footer | Add (taxonomy term), confirms if the parent has a rule | editcompetency.php?parentid | competencymanage |
| Tree | drag a node onto another (Relocate is the keyboard path) | `set_parent_competency` | competencymanage |
| Competency form | parent picker, shortname, description, idnumber, scale (locked once rated), Configure scales | `create_competency` A:192 / `update_competency` A:437 | competencymanage |
| Site admin > Import competency framework | file, delimiter, encoding, then column mapping | creates a framework | competencymanage |
| Site admin > Export competency framework | framework select, Export | CSV | competencymanage |
| Site admin > Migrate frameworks | from, to, limit to courses, exclude courses, start date | remaps course and module links | tool/lpmigrate:frameworksmigrate |

Import, export and migrate are linked from the admin tree only, never from the frameworks page.

## 4. Template and plan manager

| Screen | Action | Does | Capability |
|---|---|---|---|
| Templates list | Add new learning plan template | edittemplate.php | templatemanage |
| Templates list, row menu | Edit, Duplicate, Create learning plans, Add cohorts to sync, Delete (with plans: delete them or unlink them) | edit; `duplicate_template` A:1803; template_plans.php; template_cohorts.php; `delete_template` A:1844 | templatemanage |
| Template form | shortname, description, visible, due date | `create_template` / `update_template` | templatemanage |
| Template competencies | Add competencies, delete link, drag to reorder | `add_competency_to_template` A:2189; `remove_competency_from_template` A:2225; `reorder_template_competency` A:2259 | templatemanage |
| Template plans | Select users + Create learning plans | `create_plan_from_template` per user | template read + planmanage |
| Template cohorts | Select cohorts + Add cohorts; Stop syncing cohort | `create_template_cohort` A:2316 then plans; `delete_template_cohort` A:2352 | templatemanage + planmanage |
| A user's plans | Add new learning plan | editplan.php | planmanage or planmanagedraft |
| A user's plans, row menu | Edit, Reopen, Complete, Request review, Cancel review request, Start review, Finish review, Make active, Send back to draft, Unlink from template, Delete | the plan workflow above; `delete_plan` A:2829 | manage / review per row flags |
| Plan page | Edit (cog), Add competency, delete row, drag to reorder, workflow links, Unlink | `add_competency_to_plan` A:3358; `remove_competency_from_plan` A:3400; `reorder_plan_competency` A:3434 | plan can be edited (manage, not complete, not template-based) |
| Plan page, row menu | Request review, Cancel review request, Delete, Find courses | user competency workflow; `list_courses_using_competency` | request-review caps; manage |
| Plan page | comments | core comment area | plancomment[own] |
| Plan page | Jump to competency | navigation | read |

## 5. Reviewer, mentor, rater

| Screen | Action | Does | Capability |
|---|---|---|---|
| User competency (alone, in plan, in course) | Request review, Cancel review request, Start review, Finish review | user competency workflow | request: usercompetencyrequestreview[own]; start/finish: usercompetencyreview |
| same | Rate: "Choose a rating..." + "Evidence notes", Rate / Cancel | `grade_competency[_in_plan|_in_course]` | competencygrade (user context, or course for the course page) |
| same | comments | core comment area | usercompetencycomment[own] |
| Evidence row | delete (confirm) | `delete_evidence` A:4544 | evidencedelete (admins) |
| Completed plan | none: the competency is shown frozen | | |
| User competency in course | Jump to user, Jump to competency | navigation | read |
| Block "Learning plans" | Competencies to review, Plans to review (lists only, links out) | `list_user_competencies_to_review` A:1379, `list_plans_to_review` A:2424 | data filtered by review capabilities |
| Breakdown report | user select, activity filter, rating cell opens the course summary with Rate | `report_competency_data_for_report` | coursecompetencyview; rate by competencygrade |

## 6. Teacher in a course

| Screen | Action | Does | Capability |
|---|---|---|---|
| Course competencies | Add competencies to course | `add_competency_to_course` A:1625 | coursecompetencymanage |
| same | delete link, drag to reorder | `remove_competency_from_course` A:1664; `reorder_course_competency` A:1701 | coursecompetencymanage |
| same | "Upon course completion:" None / Attach an evidence / Recommend the competency / Mark as complete | `set_course_competency_ruleoutcome` A:1757 | coursecompetencymanage |
| same | settings cog: ratings affect only the course / also the learners' plans | `update_course_competency_settings` A:5226 | coursecompetencyconfigure |
| same | View details, activity filter, statistics (read) | navigation | usercompetencyview |
| Activity settings, Competencies section | Course competencies picker, "Upon activity completion:", "Override existing competency grade when completed." (only with Mark as complete) | add/remove module link, set module outcome | coursecompetencymanage (module) |
| Rating from the course or the report | see section 5 | | competencygrade |

## 7. Learner (for completeness)

Request and cancel review on a draft plan and on a competency; comments on plans and
competencies; own evidence: Add new evidence, Edit, Link competencies, Send competencies for
review, Delete, delete a link (`userevidencemanageown`). Read-only everywhere else.

## 8. Site administrator

Enable competencies (`core_competency/enabled`), Push course ratings to user plans
(`core_competency/pushcourseratingstouserplans`), the Competencies admin tree (frameworks,
templates, import, export, migrate), the category links to frameworks and templates, and evidence
deletion.

## 9. local_dimensions Competency hub (`central.php`)

Three tabs: Structures, Structure (tree), Learning plans. The hub checks only core
capabilities; its own are `local/dimensions:view` (learner pages) and
`local/dimensions:editcustomscss`. 10,791 lines of AMD across 36 modules; no React.

| Tab | Actions | Core equivalent |
|---|---|---|
| Context bar | System / Category, Refresh | category settings links |
| Structures | New structure, Import, Export, Edit details, Toggle visibility, Duplicate, Delete, scale link, show hidden | frameworks list, lpimportcsv (the hub links import and export where core does not) |
| Structure | Add competency, Expand / Collapse all, Display options, Search, Load more, resize panes, Usage, open related, Edit, Add child, Rules, Courses & activities (course and module links with outcomes), Related competencies, Move to position, Delete | competency tree, rule dialogue, course competencies page and activity form (the hub edits course links centrally) |
| Learning plans | Import templates, Export templates, display options, search, New template, filter by competency, open competency detail, edit competency, move up / down / to, remove, Edit details, Add competency, Manage participants, Duplicate template, Delete template | templates list and template competencies (import and export are hub-only) |
| Participants dialogue | Cohorts (add, sync, remove), Users (add individual plan, unlink, delete plan, filters), Roles (cohort roles), Enrolment methods (apply, remove, toggle status, queue) | template cohorts, template plans; role and enrolment pages linked out |
| Custom fields | competency and template field definitions (core customfield UI) | none in core |
| Settings | 24 keys under `local_dimensions/` (return button, image handler, custom SCSS, cards, filters, evidence submit) | none |

## 10. What the redesign takes from this

- Actions group naturally into five persona workspaces: framework editing, template and
  participants, plan workflow, review desk, course teaching. Each screen of round 2 maps to one.
- The review queue exists only as two link lists in block_lp; nothing lets a reviewer rate and
  move to the next request. That is the largest gap for staff.
- Plan workflow controls are text links in parentheses on plan.php and a caret menu on plans.php;
  a status stepper with the allowed transitions as buttons covers both.
- Course outcomes are set in two places (course page, activity form); the hub's "Courses &
  activities" already centralises them per competency.
- Every redesigned label gets its own language string (see the round 2 notes); no string is
  reused across two places, so a site can rename one place without changing another.
