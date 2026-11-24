# Competency pages: design record

Status (2026-11-23): **round 4 restarted the work with a narrower scope; `PLAN.md` is the current plan**
and `final/project/` the current mockups. What follows is the record of rounds 1 and 2 (2026-10-19), kept
for the reasons behind them; its open decisions R1 to R6 and C2 to C7 are superseded by `PLAN.md`. Nothing
here is implemented. Scope: Moodle 5.3 and later only, like the theme.

## What is here

| Path | What |
|---|---|
| `brainstorm.html` | Rounds 1 and 2 in pt-BR: principles, vocabulary, the plan views, data sources, detection of `local_dimensions`, implementation notes, decisions C1-C8 and R1-R6. Published as a private artifact: https://claude.ai/artifact/MmmMorGUJd4G8YWn9ZyDrx |
| `canvas/project/` | The Design canvas sources (22 `.dc.html` artboards, `dim.css`, `canvas.json`). Published: https://claude.ai/artifact/U2x5eLkNqwXMMpgaCzPtw8 |
| `actions-inventory.md` | Every action on the core competency screens and in the `local_dimensions` hub, by persona, with capabilities, archetype defaults and the plan and user-competency state machines. |
| `strings.md` | Draft language strings (en, pt_br) for the round 2 screens, one key per text area. |
| `react-flow.md` | Feasibility of React Flow (`@xyflow/react`) for the hub on 5.3, with the open risks. |
| `PLAN.md` | Round 4 (2026-11-23), the current plan: what the theme redraws and what the companion plugins do, the code facts behind it, each page's data and cost, the phases and the owner's decisions. |
| `final/project/` | The round 4 mockups (nine artboards, `comp.css`, `canvas.json`), in pt-BR. Published: https://claude.ai/artifact/Mks6WTEiGWSNtwcJjQciSH |
| `screens-inventory.md` | Round 3 (2026-11-11): the 49 competency surfaces of core and the family's plugins, and who reaches each. |
| `lines/assets/` | Captures of core's ten competency pages under Boost, taken on m503 for round 3. The round 3 canvas itself is kept in local_dimensions (`docs/proposals/2026-11-12-competency-canvas/`), without these images. |


## Round 1 (2026-10-18)

Boards 0-8: a shared vocabulary (plan rose, competency stars, scale as steps with the proficiency
flag, journey records, colour chain), three plan maps (constellation, growth rings, trails), the
competency journey log and timeline, plans atlas, evidence notebook, course competencies, class
report, block_lp and the page without `local_dimensions`.

Owner's answers (2026-10-19):

- The trails map was the clearest, but without its playful vocabulary (stations, trails, stops):
  it became the neutral "Plans map" of round 2.
- The journey log (2A) stays, and its "neighbourhood" section makes sense.
- The rose / radar breaks down with mixed scales (2 to n levels) and many competencies: it needs
  interactive controls, in the spirit of block_compass's calendar strip.
- Every text area gets its own language string so a site can adjust each place.
- React Flow was already considered for the hub; a node model is more complete than the
  constellation.
- The screens must also cover teachers, plan managers and other staff, from a map of every
  administrative action.

Still open from round 1: C2 (whether `local_dimensions`' own learner pages are restyled too),
C4 (how the proficient count is shown), C5 (class view in this phase), C6 (colour without the
plugin), C7 (area from `tag1` or from the framework tree). C8 (Moodle App) is out with the 5.3+ web
scope.

## Round 2 (2026-10-19)

Boards with the "R2" tag:

| Board | Persona | Idea |
|---|---|---|
| PlanMap | learner | Areas as rows, competencies in plan order, linked courses and activities; combine up to 3 plans; highlight next steps / proficient / not rated; a range strip under the map moves a 5-wide window through long plans; side panel per competency. |
| RadarRange | learner | At most 8 axes, chosen on the same strip, or one axis per area. Mixed scales on one ruler: every scale's proficient level sits on the same dashed ring, lower levels spread up to it, higher ones to the edge; ticks show each scale's own levels. |
| HubNodes | framework manager | React Flow concept for the hub tree: framework, areas, competencies, rule children with points, related edges, linked courses, minimap, controls, detail panel with the `local_dimensions` fields. The same component read-only could be a fourth plan view. |
| ReviewDesk | reviewer, mentor, manager | Queue of review requests (why each arrived), the log since the last rating, neighbourhood, scale as a radio list, rating note, "rate and open the next". |
| PlanWorkflow | plan manager | The five core plan states as a stepper with only the allowed transitions as buttons that state their effect; template-based plans skip review. |
| CourseTeacher | teacher | Course competencies with the course and activity outcomes, the class distribution per competency, and the course rating setting. |
| TemplateManager | plan manager | A template in the hub: plans by status, where people are per competency, synced cohorts, individual plans, review requests. |

## Decisions waiting for the owner

| Id | Question | Recommendation given |
|---|---|---|
| R1 | Does React Flow go into `local_dimensions` main (5.3)? | Yes, after a short spike that proves packaging, CSS, accessibility and Behat. |
| R2 | Where do the staff screens live? | Review desk and template manager as hub tabs in `local_dimensions`; plan workflow and teacher course page as core template copies in the theme. |
| R3 | Control limits | Up to 3 combined plans, 5 competencies per map range, 8 radar axes; configurable later. |
| R4 | Does rating on the review desk also finish the review? | Yes, one click does both, and the label says so. |
| R5 | Own strings even where core has the same text? | Yes, as in `strings.md`. |
| R6 | Default plan view | Map, remembering each person's last choice per plan. |

## How to resume

1. Read this file, then the decisions in `brainstorm.html` (rounds 1 and 2).
2. Open the canvas; the round 2 boards are at the bottom (titles "Rodada 2").
3. With the owner's answers: write the English design record here (decisions and phases), then
   plan the implementation in phases (theme template copies and renderer; `local_dimensions`
   React Flow spike; staff screens), following the fleet rules.
