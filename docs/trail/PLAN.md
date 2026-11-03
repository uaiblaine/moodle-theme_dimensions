# The Trail model, its landing page and the calendar's Trilhas view: plan (2026-11-02)

Status: **approved; T0 (#108) and T1 (#109) merged, T2 (progress) built.** T3 (the calendar's Trilhas view) and T4
(related contents) are to come. The mockups are on the calendar design canvas:

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
| Q4 | Visibility of the steps | Discoverability rules: a visitor sees only what is public, a listed but not public course as a ghost card, an unlisted course never; an account follows the normal access rule. Corrected the same day: an unlisted course shows to an account enrolled in it or with a way to enrol, and to nobody else, never as a ghost; a ghost is for a listed course on a public trail | Section 6 |
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

## 3. Progress, per viewer (built in T2, `local\trail\progress`)

Only for an account enrolled in the trail course (the page's resolution is `enrolled`), over the steps drawn in full:
an account is never shown a ghost. At most two statements, whatever N:

- core's completion data for the whole trail course, one read through `completion_info::get_data(..., true)` (the
  `core/completion` cache), so core's own rules (viewed, grade, the module's custom rules) decide. Filling that cache
  is core's cost, shared with the course page, and asks each activity's own rules: mod_subcourse's reads its row, one
  statement per step, on a cold cache only; the read-count test measures the page on a warm one;
- `{course_completions}` for the viewer and the referenced courses whose activity tracks no completion, only when there
  is one; a row without `timecompleted` (a course begun) is not a completion.

A step is **done** when its activity is complete for the reader, or, when the activity tracks no completion, when the
reader completed the referenced course. Core's answer for the activity wins wherever it has one: an activity that
tracks completion and is not complete is not done, whatever the referenced course says. Then, for a step not done:
**locked** while `cm_info::available` is false for the reader (the line leads with core's own availability text, its
names in the author's spelling); otherwise it is open. The first open step is the reader's **next**: "Continuar" leads
to it, and it is **late** when its end has passed, else **current**. Any later open step is **late** when its end has
passed, else **upcoming**. A step with no end is never late.

"Continuar: <step>" (the card's button) and "Continuar" (the phone's bar) lead to the next step's activity,
`/mod/subcourse/view.php?id=<cmid>`, which logs the view its completion may depend on and forwards to the referenced
course (or links to it, as the activity is set). With no next step (all done, or the rest locked) the card keeps
"Acessar curso". The hero reads "2 de 5 cursos concluídos" and "Até <the trail's last end>"; its segments fill (done
green, the next one white, late in the danger tint). Each card carries its state tag, its number turned into a check or
a lock, and its line: "Concluído em <date>" (core's completion time), the due date with "faltam N dias" for the current
step, core's reason before the dates for a locked one.

**The period** (the owner's rule of 2026-11-03, folded into T2): first start to last end over the full cards; a step
that opens and never closes leaves it open ("A partir de <first start>"), since no earlier step's end may close it. A
learner's hero then says nothing of a due date.

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
  guest, fallback). Enrolled: "Você já está inscrito nesta trilha" + "Continuar: <step>" (T2). Then, for an account
  only (the calendar asks for a login), **"Ver no calendário"**:
  `/r.php/theme_dimensions/calendar?view=trail&trail=<courseid>` (the page reads both through its state vocabulary).
  The button comes with T3: before the Trilhas view exists it would open a month that says nothing of the trail.

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

## 6. Who sees which step (Q4, corrected by the owner on 2026-11-02)

The trail page itself is public exactly when today's rule says so (`publicaccess::is_public($trailid)`). Each step
is then drawn in full, as a ghost, or not at all, decided on the referenced course (`local\trail\display`):

| Referenced course | Visitor (on a public trail) | Account |
|---|---|---|
| public | full card | full card |
| listed (visible, in a visible category, not unlisted), not public | **ghost card** | full card when the showcase would list it to this account, else not drawn |
| **unlisted** (its own state, or an unlisted category on its path) | not drawn | full card for an account enrolled in it or with a way to enrol (local_unlistedcourses' own terms), else not drawn |
| hidden, or in a category the reader cannot see | not drawn | not drawn, unless core lets this account see it |

- An account's answer is the showcase's own gate, `population::admitted()` over the root scope, as the calendar's
  open events use it: core's course and category visibility, then local_unlistedcourses' terms. An account is never
  shown a ghost: a card that says "there is something here you may not see" is the false expectation the owner
  ruled out.
- A **ghost card** is a visitor's, on a public trail, for a listed course only. It carries the position: no name,
  description, dates, hours, cover or link, and the line "Este curso não é aberto ao público" with a login link.
  The activity's name stays back too, since it usually names the course.
- Positions, the "N cursos" chip, the period and progress count the cards drawn, ghosts included for the count; a
  dropped step leaves no gap and no count betrays it. The period reads the full cards only.
- The activity's restriction comes first: a step whose restriction hides it from this reader entirely (not
  available, and no text to show) is not drawn; a restriction that shows its text is the locked state. Read
  from core's availability (`cm_info::$available`, `$availableinfo`), not its course-page visibility, which
  also needs the module's view capability in the trail course and so hides every step from a reader who is not
  enrolled there.
- `trail::many()` is a structure reader and applies none of this itself; its docblock names the contract.
- Covers: the showcase's batch (`cards::images()`), through the theme's `cover` route for a visitor and core's
  `overviewfiles` URL for an account.
- The step card's link: the referenced course's page address (`links::page()`), which is its landing page when it
  has a model and core's enrolment page otherwise.

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
| T1 | landing page: hero block, stepper, ghost and dropped steps, card facts; SCSS in `landing.scss`; Behat smoke (visitor of a public trail, account); read-count test | 3: `--matrix --behat` |
| T2 | progress states, "Continuar", the learner's hero block | 3 |
| T3 | calendar Trilhas view, desktop and phone, filters, popover, and the landing page's "Ver no calendário"; read-count test | 3: `--matrix --behat` |
| T4 | related contents (Q7, later) | 2 or 3 |

T3 touches the calendar's files, which the calendar session is changing on its own branch: it starts after that
branch merges, or is agreed with that session first. Every guard gets a mutation gate (`--fast --changed`, both
database families); `security-diff-read` over T1 and T3 (public page, file route, visibility) before the push; PRs
with `[skip ci]`, merged only on the owner's word. Built in the sibling worktree `~/dev/moodle-theme_dimensions-trail`
(branch `trail`); `mod_subcourse` needs no change.
