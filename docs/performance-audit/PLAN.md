# theme_dimensions: client-side performance audit

Template: `moodle-dev/docs/client-performance-audit/README.md` (the method, the four axes, the instruments and the
phases; this file only instantiates it). Owner's decision of 2026-10-28: the audit runs once the category
showcase's React port has closed (`docs/vitrine-react/PLAN.md`, stages P0-R5), and what it teaches goes back into
the template before block_compass's audit.

**Starts when**: R5 has merged. Phase 0 re-lists the scope and recounts the signals below on that commit.

## Scope

In scope, everything of the theme's JavaScript that survives R5:

- **The React islands**, `js/esm/src`: `daterange/` (DateRange, calendar, model), `filterline/` (FilterLine, measure,
  steps, preview, suggest, model, mark, lazy), `areas/` (Areas, panel, posters, rail, model, mark), the `amd.ts`
  bridge, and the listing store R4b brings.
- **The AMD modules outside the showcase's port**, expected to be: `readingbar`, `focusmode`, `phonemenu`,
  `compactnav`, `drawer`, `linearfooter`, `coursenotice`, `covervideo` with `local/coverfilm`, `categoryhero` (the
  category appearance editor), `categorycoursemodal`, `landingguest`.

Out of scope:

- the AMD modules R4b and R5 replace or delete: `categoryvitrine`, `categorypublic`, `categorylisting`,
  `categoryline`, `categorypreview`, `categorymark`, `categoryareapanel`, `categoryposters`, `categorypanel`,
  `categorystar`, `daterange` (about 9,000 lines today); auditing them now would be work thrown away;
- core's code and `first.js` (core's combo of every non-lazy AMD module, 10.8 MB raw on m503b): its weight is
  core's and the vendored libraries' (the `-lazy` work), not the theme's;
- the server side: the statement and byte budgets already gate it (`first_paint_budget_test`, the budget tests).

## Scenarios

All on m503b, over the 5,000-course data set kept there (never delete it), at 4× CPU, warm cache, 3-5 runs, at
1358 px and at a true 390 px (emulated: section 2.1 of the template). The probe's four scenarios
(`tests/behat/theme_dimensions_perf.feature`, `@theme_dimensions_perf`) are the A/B gate; the meters give the
attribution.

| Id | Page | Viewer | Interaction | Axes |
|---|---|---|---|---|
| S1 | A flat category page with covers (`/course/index.php?categoryid=15&sort=name`) | student, admin | load; a filter press on the line; type a search term; load more ×10 | A, B, C, D |
| S2 | The course index, the directory of areas | student | load; open and close an area ×10 | A, C, D |
| S3 | A grouped category page, its areas as posters | student | load; open a poster's panel; Escape; ×10 | A, C, D |
| S4 | The visitor's public category page (`/theme_dimensions/category/8?sort=name`) | visitor, forcelogin on | load; a filter press; load more ×10 | A, B, C, D |
| S5 | A course page with the reading bar, the course notice and the linear footer | student | load; scroll the page top to bottom and back; open the course index drawer | B, C |
| S6 | An activity in focus mode | student | switch focus mode on and off ×10 | C, D |
| S7 | The category appearance editor (`categoryhero.php`) | manager | type a colour; pick the quick colours ×10 | B, C |
| S8 | A public landing page with a cover film | visitor | load; play and stop the film | A, C |
| S9 | The login page | visitor | load (control: the theme draws little there) | control |

S5-S8 have no probe scenario yet: phase 0 fixes their URLs on m503b and writes them here.

## Targets

The port's own (`docs/vitrine-react/PLAN.md` section 5.1): FCP ≤ 1.8 s, LCP ≤ 2.5 s, TBT ≤ 200 ms, CLS ≤ 0.1 and 0
at a React mount, INP of a filter press ≤ 200 ms. Added here: no lifecycle interaction (S1-S4, S6, ×10) leaves
detached DOM or a heap that grows past the warm-up after a forced garbage collection.

## Known items carried in

- **R2's leftover blocking time**: the flat warm page is about 30 ms of TBT above R1, and the scope press's INP is
  96 ms against 48-64 before (PLAN 5.2.4). Axis A or C; the profile of the press is the first measurement.
- **The phone layout shift**: 0.038-0.041 when the filter line folds to two lines, measured at Chrome's 500 px
  window floor, not at 390 (PLAN 5.2.4). Re-measure at a true 390; if it stands, axis C.
- **Lighthouse's "render-blocking requests"** on S4, reported by the owner on 2026-10-28: the stylesheet and the
  synchronous scripts in the head. The owner chose then not to change the CSS during the port; phase 0 measures
  it again as loading context, and the owner decides whether it enters the audit.

## Starting signals (counts on 23be220, before R4a; not findings)

Over `amd/src` (23 files, 12,912 lines) and `js/esm/src` (18 files, 6,905 lines), much of it out of scope:

| Signal | Count | Axis |
|---|---|---|
| `scroll`/`resize`/`mousemove`/`pointermove`/`wheel`/`input` listeners | 17 | B |
| `debounce`/`throttle` | 3 | B |
| `requestAnimationFrame` | 10 | B, C |
| `ResizeObserver` / `IntersectionObserver` / `MutationObserver` | 11 / 7 / 3 | B, D |
| Layout reads (`getBoundingClientRect`, `offset*`, `client*`, `scrollHeight`) | 50 | C |
| `removeEventListener` / `disconnect()` | 20 | D |
| `memo` / `useMemo` / `useCallback` | 0 | C (measure before adding any) |
| `setTimeout` / `setInterval` | 24 | A, D |

## Baseline

To be filled in phase 0.

## Findings

To be filled in phases 1 and 2.

## Fixes

To be filled in phase 3: one row per pull request, with its before/after numbers and the gate it left.

## After

To be filled in phase 4, with what went back into the template.
