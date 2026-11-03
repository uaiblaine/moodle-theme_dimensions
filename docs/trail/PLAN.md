# The Trail model, its landing page and the calendar's Trilhas view: plan (2026-11-02)

Status: **second proposal, owner's answers of 2 November folded in; mockups for approval.** Nothing is built. The
mockups are on the calendar design canvas:

- page "Trilha (landing)": desktop (an account, not enrolled, paid enrolment), phone (an enrolled learner on
  17 November), and a visitor's view;
- page "Trilhas no calendário (C6)": the calendar's new view mode, desktop (interactive) and phone.

C6 is no longer a band inside the month (the "C6: trilhas como faixa" artboard of the page "Ajustes (2 nov)" is
superseded): it is a **fourth view mode, "Trilhas"**, beside Mês, Semana and Dia. It reads the trail through the same
reader as the landing page, so a trail is defined once.

## 0. Decisions of 2 November

| # | Question | Owner's answer | What it changes |
|---|---|---|---|
| Q1 | The step's dates | "Expect completed on" (the timeline reminder every activity has); when unset, the referenced course's start and end dates | Section 2: a step runs from the course's start to its due date or its end. Restrict-access dates are not read |
| Q2 | Access to the step courses | Many methods (enrol_apply, enrol_coursecompleted, enrol_self...), none fixed | No meta-link assumption, no editors' note. The trail card shows today's resolver for the trail course; each step course keeps its own enrolment |
| Q3 | Total workload | Always the trail's own field | Section 4 |
| Q4 | Visibility of the steps | Discoverability rules: a visitor sees only what is public, a listed but not public course as a ghost card, an unlisted course never; an account follows the normal access rule; an unlisted course is never shown, not even as a ghost | Section 6 |
| Q5 | Postgraduate credit | Drop | Not built |
| Q6 | Struck price, cart, "Para empresas" | Drop | Not built |
| Q7 | Related contents | Not in the first stage | Later stage |
| Q8 | Provider pill | The referenced course's category | Section 2 |
| Q9 | Dependency | Optional: without mod_subcourse the Trail options do not appear | Section 8 |

Also asked: the trail's landing page keeps today's landing model (hero, chips, emphasis block, video, sticky card),
**no share buttons**, and gains **"Ver no calendário"**, which opens the calendar in Trilhas with this trail selected.

The screenshot's wishlist heart is not in today's landing card, so it is left out with the rest of the commerce; it
can come back with the showcase's `toggle_wishlist` if wanted.

## 1. The model

- `landingfields::MODEL_TRAIL = 3`, a third line "Trail" / "Trilha" appended to the `dimensions_model` options
  (`landingfield_model_trail`). Select values are positions, so the line goes at the END, and a site provisioned
  with two lines gets it from an upgrade step `landingfields::add_trail_option()`, the exact shape of
  `add_hybrid_option()`: append only when the field has exactly the two lines the theme created, plain
  `update_record`, cache reset. A field an administrator edited is theirs. The field's default stays `''`.
- `landingfields::coherent()` treats a trail as a course: format and live classes keep their rules, the event-only
  fields (date line, venue, address) are emptied.
- Without mod_subcourse the line is hidden, not removed (section 8).

## 2. One reader: `classes/local/trail/`

`trail::of(\stdClass $course): ?trail` and `trail::many(int[] $courseids): trail[]` (the calendar's entry). A trail
is an ordered list of `step`s; nothing is stored by the theme, nothing is cached beyond core's modinfo.

- **Which activities are steps**: `get_fast_modinfo($course)->get_cms()` in course order (section, then sequence),
  `modname === 'subcourse'`, not `deletioninprogress`, `visible` and `visibleoncoursepage`. Per-user availability is
  a state (section 3), never a filter.
- **A step**: `cmid`, `name` (the activity's name, `format_string`), `intro` (when "Display description on course
  page" is on), `refcourseid`, the referenced course's `fullname`, `summary`, `category` and `visible`, and the
  window:
  - `start` = the referenced course's `startdate`;
  - `end` = the activity's `completionexpected` when set (`endsource = expected`, drawn as a milestone), otherwise
    the referenced course's `enddate` (`endsource = course`);
  - a course with no end date and no expected date has an open window: "A partir de 2 nov" on the page, a bar that
    fades out at the edge of the calendar's month.
- **`refcourse`** is not in the modinfo (`subcourse_get_coursemodule_info()` keeps only display flags in
  `customdata`), so it costs ONE statement per trail page: `{subcourse}` joined to `{course}` by `refcourse`, for
  the course's instances, carrying the fields above, the dates included. A step whose `refcourse` is 0 or points
  at a deleted course is not drawn.
- **The category pill** (Q8) is the referenced course's category name, `core_course_category::get_many()` (cached).

## 3. Progress, per viewer

Only for an account enrolled in the trail course. Two statements, whatever N:

- `{course_modules_completion}` for the viewer and the steps' cmids;
- `{course_completions}.timecompleted` for the viewer and the referenced course ids.

A step is **done** when its activity is complete, or, when the activity tracks no completion, when the referenced
course is complete. Then, in order: **late** (not done, `end` passed), **locked** (not done, `cm_info::available`
false for the viewer; the reason is core's own availability text, "Disponível depois de concluir ..."), **current**
(the first step that is neither), **upcoming** (the rest). Progress is done over the steps drawn (section 6).

"Continuar" leads to the current step's activity, `/mod/subcourse/view.php?id=<cmid>`, which forwards to the
referenced course and logs the view its completion may depend on.

## 4. Workload

The trail course's own `dimensions_workload` (Q3), as for any course; nothing is summed. Each step card shows its
own course's hours when the viewer may see that course (section 6), in one
`landingfields::values_many($refids, false, [SHORTNAME_WORKLOAD])` statement.

## 5. The landing page

`request::handle()` is unchanged up to the model check; a trail is a model, so it gets the landing page and every
existing guard. Same template and stylesheet, three additions:

- **Hero**: today's kicker (category), title and chips ("Trilha de cursos", "5 cursos", "96 horas", the format), and
  a trail emphasis block in today's glass: "5 cursos em sequência", the period, one segment per course. For an
  enrolled learner it reads "2 de 5 cursos concluídos" and the segments fill (green done, blue current).
- **Content column**: video, summary, then "Cursos da trilha" (partial `landing_trail_steps.mustache`): numbered
  cards with cover, category, name, description, hours and the date line, on a connector; states and "Continuar"
  for the enrolled learner. Then the textarea sections as today.
- **Card**: today's facts, plus "Cursos" and "Período" (first start to last end); today's enrolment area through
  `resolver::resolve()` on the trail course, every method it already knows (self, apply, fee, coursecompleted,
  guest, fallback). Enrolled: "Você já está inscrito nesta trilha" + "Continuar: <step>". Then, for an account
  only (the calendar asks for a login), **"Ver no calendário"**:
  `/r.php/theme_dimensions/calendar?view=trail&trail=<courseid>` (the page reads both through its state vocabulary).

Statements the trail adds to today's page:

| Read | Statements | Note |
|---|---|---|
| steps and expected dates | 0 | the course's modinfo, cached |
| `{subcourse}` ⋈ `{course}` | 1 | refcourse, names, summary, category, visibility, start and end |
| step hours | 1 | `values_many` with `$only` |
| step covers | 1 | one `{files}` statement over the referenced courses' `overviewfiles` |
| visibility of the steps | 4 (visitor) or the showcase gate's cost (account) | section 6 |
| progress (enrolled only) | 2 | section 3 |
| categories | 0-1 | cached |

Constant in N, pinned by a read-count test (`perf_get_reads()`) for a trail of 2 and of 8 steps, for a visitor, an
account and an enrolled account.

## 6. Who sees which step (Q4)

The trail page itself is public exactly when today's rule says so (`publicaccess::is_public($trailid)`). Each step
is then one of three, decided on the referenced course:

| Referenced course | Visitor | Account |
|---|---|---|
| **unlisted** (local_unlistedcourses state, or under an unlisted category) | not drawn | not drawn, whoever the viewer is |
| public | full card | full card |
| listed, not public, visible to the account | **ghost card** | full card |
| hidden (not visible), or not visible to this account | not drawn | not drawn |

- A **ghost card** carries the position only: no name, description, dates, hours, cover or link, and the line "Este
  curso não é aberto ao público" with a login link. Nothing read from a course reaches a viewer who may not see it,
  and the activity's name stays back too, since it usually names the course.
- "Unlisted never shows" is read strictly: the step is dropped even for a learner whom local_unlistedcourses lets
  discover the course (enrolled in it, say). The step stays on the trail course's own page, which is core's.
- Positions, the "5 cursos" chip and progress count the cards drawn, ghosts included, so a dropped step leaves no gap
  and no count betrays it.
- The activity's own per-user visibility comes first: a step whose activity core hides from this reader
  entirely (`cm_info::$uservisible` false with no availability text, a restriction set to hide) is not drawn,
  as core would not list it on the course page; a restriction that shows its text is the locked state.
- `trail::many()` is a structure reader and applies none of this itself; its docblock names the contract.
- Covers: the theme's public file route (area `hero`) for a visitor, which already fails closed; core's
  `overviewfiles` URL for an account.
- The step card's link: the referenced course's landing page when it has a model, the course itself for someone
  enrolled in it.

## 7. The calendar's Trilhas view (C6)

- **The switch**: the view control gains "Trilhas" (`calendar_viewtrail`), `view=trail` in the page's vocabulary,
  kept with the other preferences.
- **Rows**: the trails the viewer may discover by the parent course's rule (the showcase gate for an account, never
  an unlisted one), whose window meets the month shown, and under each its steps by section 6's account rule.
  Previous and next move by month, as in Mês.
- **Bars**: from each step's start to its end, a milestone at an expected date. For an enrolled viewer the bar
  carries the state of section 3 (done, current, upcoming, locked, late); for anyone else the trail key's colour,
  schedule only. What passes the month's end continues in a "dez →" column.
- **Filters**: "Trilha" (one trail, preset by the landing page's button; "Todas as trilhas" otherwise), "Só as
  minhas" (enrolled trails), and the category tree, applied to the trail course. "Tipos de evento" is hidden in this
  mode. "Limpar filtros" as in the other modes.
- **Popover** of a step: category, name, "Curso 3 de 5 · <trail>", the dates and where they come from (expected date,
  or the course's dates), the state, core's availability text when locked, "Ver trilha" and "Abrir curso".
- **Phone**: the trail as an agenda, a "Hoje" line between the past and the next steps.
- Core's `expectcompletionon` events of these activities stay in Mês, Semana and Dia as today: Trilhas is its own
  mode, so nothing is drawn twice.
- **Cost**: `trail::many()` reads each trail's modinfo (cached), one `{subcourse}` statement for all of them, the
  visibility batch and two progress statements. Pinned by a read-count test at 2 and 6 trails.

## 8. The dependency on mod_subcourse (Q9)

Optional, by plugin presence, not `$plugin->dependencies` (a hard dependency would block the theme's installation
and upgrades on any site without it). `trail::available()` = mod_subcourse installed and enabled (both cached).
Without it:

- the course form removes the Trail line from the model select (`core_course\hook\after_form_definition`), and the
  landing defaults setting leaves it out of its choices;
- the calendar has no "Trilhas" button;
- a course saved as Trail before the plugin went away renders as a course page, with no steps.

The line stays in the field's configuration, because select values are positions. The README names the optional
dependency; the marketplace readiness test holds the README.

## 9. Stages, once the mockups are approved

| Stage | Content | Tier and gate |
|---|---|---|
| T0 | MODEL_TRAIL, upgrade step, lang en + pt_br, hiding without mod_subcourse, `trail` reader + PHPUnit (order, windows from the expected date and from the course dates, refcourse, deleted and hidden cms, absent plugin) | 3: `mdl ci <worktree> --matrix` |
| T1 | landing page: hero block, stepper, ghost and dropped steps, card facts, "Ver no calendário"; SCSS in `landing.scss`; Behat smoke (visitor of a public trail, account); read-count test | 3: `--matrix --behat` |
| T2 | progress states, "Continuar", the learner's hero block | 3 |
| T3 | calendar Trilhas view, desktop and phone, filters, popover; read-count test | 3: `--matrix --behat` |
| T4 | related contents (Q7, later) | 2 or 3 |

T3 touches the calendar's files, which the calendar session is changing on its own branch: it starts after that
branch merges, or is agreed with that session first. Every guard gets a mutation gate (`--fast --changed`, both
database families); `security-diff-read` over T1 and T3 (public page, file route, visibility) before the push; PRs
with `[skip ci]`, merged only on the owner's word. Built in the sibling worktree `~/dev/moodle-theme_dimensions-trail`
(branch `trail`); `mod_subcourse` needs no change.
