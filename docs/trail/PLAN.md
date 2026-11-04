# The Trail model, its landing page and the calendar's Trilhas view: plan (2026-11-02)

Status: **approved; T0 (#108), T1 (#109), T2 (#111) and T2b (#113) merged, T3 (the calendar's Trilhas view) built, and
T3b (its continuous months, courses' rows and connectors) built.**
T4 (related contents) is to come. The mockups are on the calendar design canvas:

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
step, core's reason before the dates for a locked one. A current step whose course has not begun says "Começa em <date>"
in place of "Em andamento" and counts no day yet (the owner, 2026-11-03).

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
  The button came with T3, on the month the trail is in (section 7).

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

## 7. The calendar's Trilhas view (C6, built in T3)

The owner's review of T3b, T3c and its follow-up has its own canvas (https://claude.ai/artifact/GPt7eYFoCtgM5jQk49a86Z,
private to the owner), whose source is kept in [`mockups/`](mockups/README.md): the continuous timeline with its zoom and
its courses' dates, the connectors' cases, and the four ways to end a course with no end date (the chip was chosen).

- **The switch**: the view control gains "Trilhas" (`calendar_viewtrail`), `view=trail` in the page's vocabulary
  (`page::VIEW_TRAIL`), offered to a real account while trails are available (`page::trails_offered()`); for the guest,
  a visitor or a site without mod_subcourse the address reads as the month. Like the week and the day it is
  navigation, never remembered; so are its trail (`trail=<courseid>`), "Só as minhas" (`mine=1`) and, since T3c, its
  timeline's zoom (`zoom=week|month`, absent for days: `page::zoom_from_request()`, dropped unless one of the two
  words, carried by `page::trail_params()`, the page's address, the forward to the route and the server's arrows;
  never in `state::STATE_PARAMS`).
- **Rows**: `local\calendar\trails::answer()`. The trail courses (one statement, capped at `trails::CAP`, inside the
  category narrowing) the showcase gate admits (`population::admitted()` over the root scope: an unlisted trail only
  to an account enrolled in it or with a way to enrol), their steps by section 6's account rule
  (`display::many()`, in full or not at all), and the trails whose period meets the month: the landing page's period,
  first start to last end over the steps drawn, left open by a step that starts and never ends. A trail with no date
  is no row. The choice "Trilha" lists every admitted trail with a step drawn, whatever its period.
- **Continuous months (T3b)**: the view always holds the month in view and the next. The first paint embeds the trails of
  both (`trails::month_window($month, trails::OPENING)`, two months); the service takes `months`, 1 to 3 (`trails::MONTHS`;
  anything else is the request's shape, refused before login, and `window::whole_months()` refuses it too), so the first
  paint and a month asked for share one service, and the read count does not grow with the window. On a desktop a scroll
  near the end of what is held asks for the next month (one call) and appends its days; by months, where a month is 4px
  a day, every load asks for a batch of up to three months in one request (`trails.ts` `batchOf()`, `BATCH_MONTHS`: never more
  than the cap leaves room for, so a wide screen reaches twelve months in four requests, not eight one after another), by
  days and weeks one month as before; the previous arrow, or a scroll back to the
  start, prepends the month before (the batch before, by months) and moves the position by its width (every month of the batch) (`trails.ts` `keptLeft()`, arithmetic over the
  last measure). The months stay contiguous, at most six by days and weeks and twelve by months (`trails.ts` `capOf(zoom)`, T3c follow-up), the far side dropped with the same compensation; the answers
  merge by trail id (a trail's steps are the same in every answer), the list kept to the trails whose period meets what
  is held and sorted as the server sorts. A band above the day numbers has one segment per month held, its name
  sticky at the start of the days while the segment is in view and a button that scrolls (smoothly unless the reader
  asked for less motion) to its 1st; a month on its way is a quiet skeleton at the end. The toolbar's title names the
  months in view ("novembro – dezembro de 2026", "dezembro de 2026 – janeiro de 2027", Intl's range); its arrows move a
  month from the first day in view (back to that month's 1st, or the month before from a 1st), asking for it when
  needed, and "Hoje" scrolls to today, asking for its month (and the next, when it is not beside what is held). The
  address's `month=` is the month of the first day in view, replaced when a scroll settles (navigation, never state;
  the history module writes it). The phone's agenda lists the trails of the months held, and moves by the page's
  request as before.
- **Bars**: from each step's start to its end in the viewer's timezone (`calendar/trails.ts` `barOf()`), placed by
  grid columns with no layout read; a milestone at an expected date inside the days held; a step that began earlier is
  marked at the left edge, one that runs past what is held is cut there (it goes on once the month is held), an open
  one (a start and no end, no expected date) is drawn as its first 30 days (`OPEN_DAYS`, the start day and 29 more),
  then a quiet decorative chip, "→ sem término" (`calendar_trailopenchip`), .375rem after the bar's last day at every
  zoom (`chipLeft()`, in rem by the bars' day arithmetic, none where the 30 days reach the last day held or run past
  it: it appears once the next month is held); its connectors leave the end of the 30 days, and the trail's line ends
  there too, with no chip. The bar's accessible name and the popover's dates line say it in full, "Sem término
  definido" (`calendar_trailopenfull`), a step with an end or an expected date not. Rows are 56px with 24px bars (52px until T3c, which made room for the dates line), the trail column
  sticky; a bar too narrow for its name (`trails.ts` `namedBar()`: under one day by days, so a one-day bar keeps it
  there, and under 3.5rem by weeks and months) draws none and keeps its accessible name. For a
  learner of the trail (`classifier::enrolments()` active) the bar carries the state of section 3 (`progress::many()`)
  and the next step is drawn heavier; for anyone else the trail colour, schedule only. The colours are the view's own
  tokens (`--dim-cal-trail*`), copies of the measured default pairs, never an administrator's key colour.
- **Filters**: "Trilha" (one trail, preset by the landing page's button; "Todas as trilhas" otherwise), "Só as minhas"
  (enrolled trails), and the category tree, applied to the trail course. "Tipos de evento", "Mais filtros" and "New
  event" are not drawn in this mode. "Limpar filtros" puts the three back.
- **Popover** of a step (`TrailPopover.tsx`): category, name, "Curso 3 de 5 · <trail>", the dates and where they come
  from (expected date, or the course's dates), the referenced course's name when the activity's differs, the state
  ("Começa em" for a current step not begun), core's availability text when locked, "Abrir curso" and "Ver trilha".
  It is drawn beside the scroller, never inside it, so the scroller's overflow cannot cut it.
- **The courses' rows (T3b)**: a course in the "Trilhas e cursos" column is a button ("<course>: mostrar na linha do
  tempo"). It scrolls the timeline (animated; at once under reduced motion) so the course's bar starts two days inside
  the days area (`targetLeft()`), then opens that bar's popover once the scroll has ended (`scrollend`, or a fallback),
  the focus on the popover; a course whose month is not held asks for it first; a course with no dates opens its
  popover on the row, which says "Sem datas". Under a mouse a row lights its bar and a bar its row (`data-lit`; a touch
  never does). The trail's fold is core's course-section toggle (`btn btn-icon icons-collapse-expand`, `collapsed` when
  folded) with core's `t/expandedchevron`, `t/collapsedchevron` and `t/collapsedchevron_rtl`, rendered by the server
  (`calendar_page::fold_icons()`), so core draws the box and swaps the glyph.
- **The zoom (T3c)**: "Dias", "Semanas", "Meses" (`calendar_trailzoomday`, `-week`, `-month`, the group named by
  `calendar_trailzoom`), a segmented control of real buttons with `aria-pressed` in the filters' row, a desktop's only
  (the phone's agenda is unchanged). A day is 3rem by days, 1rem by weeks and .25rem by months (`trails.ts` `TRACK`, the
  stylesheet's `--dim-cal-trail-day` under the section's `--week` and `--month` modifiers: the approved mockup's
  30/10/4px, the three months fitting about 400px by months). Every position follows it from `TRACK` (`dayWidth()`):
  the bars' columns, the connectors, today, the band, the scroll arithmetic and the map; nothing is read to place
  them. A switch keeps the day at the start of the view: the scroller's last measure at the new width (`zoomedLeft()`),
  at once, then measured where that left it, as after an arrow; nothing is asked, the months held stay. A measure
  records its zoom, so a new set of months opened at a new zoom in the same commit (Back or Forward across a zoom) is
  not rescaled. When the days held grow the scroller is measured again where it is: where they fit it (by months on a
  wide screen) the next month is asked for, up to the zoom's cap (six, twelve by months), and none past it while every day held is in view; a switch to a smaller cap keeps the months until the next load drops the far side to it. By days the
  header is as T3b drew it (weekday letters, day numbers, today's pill, the weekends hatched); by weeks its second row
  is the weeks from the viewer's first weekday (core's calendar setting, `calendar_get_starting_weekday()`, as the
  month grid; `weekCells()`, the first and the last cut at the edges of what is held), each a short month over its
  days ("nov." over "2–8"; a week across a month names the end's month too, "out." over "26–1 nov.", `weekLabel()`; a cell under 2.5rem, a one or two day partial week, draws no label and keeps its line,
  `weekLabelled()`), the week holding today tinted; by months the band alone names the months (the month's name
  without its year, the mockup's) and the second row draws only the weeks' lines. Off the days no weekend is shaded
  and a quiet line marks each week's start. At every zoom a small "Hoje" tag (`calendar_today`) tops today's line,
  in the current step's pair. The toolbar's arrows and "Hoje" still move a month; the arrows, the band and every write
  of a Trilhas address keep the zoom (`withTrail()`'s third argument), and its own change replaces the address.
- **The courses' dates (T3c)**: under each course's name in the trail column, a quieter line says its dates from the
  answer's start and end, in the viewer's timezone and language through Intl (`trails.ts` `datesOf()`, Intl's range,
  compacted as the agenda's dates are, a date's own words only: the range's separator stays, a word in some
  languages): "2–20 nov. 2026", "16 nov. – 12 dez. 2026", "15 dez. 2026 – 20 jan. 2027";
  a start alone "A partir de 1 dez. 2026" (`calendar_trailfrom`), an end alone "Até 20 nov. 2026" (`calendar_until`;
  an expected completion date is the end), none "Sem datas" (`calendar_trailnodates`). The row's accessible name
  carries them ("<course>, <dates>: mostrar na linha do tempo").
- **Connectors (T3b)**: consecutive steps are joined in the trail's order, never by what their enrolment needs
  (`trails.ts` `linksOf()`, from the Conectores board): the next starting after the previous ends is an elbow out of the
  previous bar's right end into the next's left end with an arrowhead; starting while the previous runs, a drop on the
  next's start day (a dot on the previous bar's base, a short arrow down); starting before the previous starts, a detour
  on the left into the next's start. An end in a month not held runs the line to that edge; a step with no dates breaks
  the chain (nothing goes into or out of it, and its neighbours are not joined); a folded trail draws none, and the
  phone's agenda has none. They are an aria-hidden layer inside the steps' rows, placed in rem by the same day
  arithmetic (no layout read), under the sticky trail column, in the view's own neutral grey `--dim-cal-trail-link`
  (the palette's gray-700 in the light mode and its gray-500, lifted to 3:1, in the dark: the mockup's #6a737b is the
  locked step's edge, which a connector must not be mistaken for). T3c draws them at the zoom's day width: over a
  narrow gap the elbow comes half way, never under 3px (`LINK.tight`), and the line into the next bar shrinks to
  nothing rather than run backwards, the arrowhead kept past the elbow (two courses on following days included, at
  every zoom); the drop's offset into its day is half a narrow day, never under 2px (`LINK.least`); the detour's turn
  keeps its 9px before the next bar, half the gap over a narrow one and no more than the room the days held leave
  before it, never under 3px; no piece has a negative length.
- **Phone**: the trail as an agenda, a "Hoje" line before the first step not done whose end has not passed
  (`todayIndex()`). The phone decision is the frame's width, from the view's one ResizeObserver, as the grids'.
- **No JavaScript**: the server's layer lists the trails of the month and the next (the first paint's answer) and their
  steps with their dates and states; its toolbar's links keep the view, the trail and the zoom and move by the month.
- Core's `expectcompletionon` events of these activities stay in Mês, Semana and Dia as today: Trilhas is its own
  mode, so nothing is drawn twice.
- **Cost**: the candidates, the gate, `trail::many()`'s one `{subcourse}` statement, `display::many()`'s one statement
  for the steps' courses with their gate, the enrolments and at most one
  `{course_completions}` statement; each trail's modinfo and completion data are core's caches. Pinned by
  `trails_test::test_six_trails_cost_what_two_do` (2 and 6 trails, a learner and an account enrolled in none, over one
  month and over the month and the next, which cost the same).
- **The landing page's "Ver no calendário"** (`landing_page::calendar_url()`), an account's only: the Trilhas view with
  the trail kept, on today's month while the trail runs, its first month before it starts, its last once it has ended.

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
