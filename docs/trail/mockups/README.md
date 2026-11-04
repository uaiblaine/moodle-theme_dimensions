# Trilhas view mockups

The owner-approved mockups of the calendar's Trilhas view, stages T3b and T3c and the T3c follow-up
(`docs/trail/PLAN.md` section 7). The live canvas is private to the owner:
https://claude.ai/artifact/GPt7eYFoCtgM5jQk49a86Z. These files are its source, kept here so the design
survives the canvas.

| File | Artboard |
|---|---|
| `canvas.json` | The canvas's index: the two artboards, their frames and order. |
| `Main.dc.html` | "Trilhas: linha do tempo contínua" (interactive). The continuous months and the month band; a course row that scrolls to its bar and opens its popover; core's bordered chevron; the connectors; the Days / Weeks / Months zoom; each course's dates under its name; a course with no end drawn as its first 30 days followed by a "→ sem término" chip. |
| `Conectores.dc.html` | "Conectores: os casos de alinhamento". The connector's four routes (an elbow with a gap, a drop on overlap, a detour on the left, a line to the edge of what is loaded) and its rules (a neutral grey of its own, never over a name, broken by a course without dates). |
| `FimAberto.dc.html` | "Fim aberto: variantes". Four ways to say that a course with no end goes on (shrinking fragments, a dotted trail, a "→ sem término" chip, fading chevrons) and the recommendation. The owner chose the chip (C), 2026-11-04. |

- **The copy is Brazilian Portuguese, the site's language**, as the calendar's own mockups are (`docs/calendar/PROPOSAL.md`
  section 9.1). The colours are the theme's tokens as measured on m503b, written as literals. Sizes are in px at the
  day zoom's 30px a day; the theme builds the same in rem through `trails.ts` TRACK.
- **They are Design Component files** (`.dc.html`): one HTML page per artboard, its logic in a `class Component extends
  DCLogic` script, run by the canvas's own `support.js`, which is not in this repository. To see them, open the canvas
  above, or publish these files as a new Design canvas. Opened as plain HTML they show only their unrendered markup.
- **The mockups show intent, not the contract.** Where the built view differs (the connector grey, the week label of a
  narrow week, the twelve-month cap at month zoom), `docs/trail/PLAN.md` and the tests are the authority.
