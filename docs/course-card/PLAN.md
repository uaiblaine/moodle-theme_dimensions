# The course card in theme_dimensions: plan

The theme's half of `moodle-dev/docs/course-card/CONTRACT.md` (section 13, step 1), in four stages, each its own pull
request on `main`. The design record is `mockup.html` beside this file; the owner accepted D1 to D15 on 2026-11-08.

## C1. Server: Boost Union's settings and the no-photo image

- `classes/local/card/boost_union.php`, the one reader of `courseoverviewshowcourseprogress`,
  `courseoverviewimagesource` and `courseoverviewshowcourseimages` (contract section 8): Boost Union in the request's
  theme chain and installed, a missing value read as Boost Union's default. The theme is always a child of Boost Union,
  so the chain check holds here; the helper keeps it for the copies the compass and local_dimensions make.
- `core_renderer::get_generated_image_for_id()` (D14): `courseplusfallback` with an uploaded fallback answers Boost
  Union's image (the parent); otherwise an SVG data URI of the course category's cover, from `branding::colours()`
  (the nearest category with a pair, else the theme's primary; D3).
- `cards::images()` keeps its one statement for uploaded images and asks the renderer for the rest, so the showcase's
  no-photo cards stop falling back to `--dim-fallback` (#070093).
- The card's export gains the three flags the templates need (cover, list thumbnail, progress).
- README: a "Boost Union settings that change the course cards" section (the owner, 2026-11-08).
- Tests: the helper (each setting, missing values, the default), the renderer override (both sources, the band's
  colour from an ancestor, the primary without one), `cards::images()` with and without an upload.
- Gate: tier 3, because the rows service's `imageurl` became `PARAM_RAW` to carry a `data:` URI:
  `mdl ci moodle-theme_dimensions --matrix --behat`, and the sweep of the changed gates on both database families.

## C2. The card's look: tokens, states, buttons

- `scss/post.scss`: the 30 `--dimensions-card-*` names in the light and dark family blocks; `family_tokens_test` gains
  the "Card tokens" section and its counts.
- `scss/category.scss`: `--dim-card-*` readers on `.dim-card`, every card rule painted through them; the stretched
  title link (D9); hover as shadow and underline only; the four uppercase rules go (kind, datetile, over, the panel
  eyebrow's goes in C3); crests at 56 x 62 (D2); equal heights in a row; "Mais informações" as
  `btn btn-sm btn-outline-secondary` (D10); the card without its cover and the row without its thumbnail (section 8).
- The grouped panel draws the flat list's card: the `.dim-panel__rail .dim-card` restyle goes, with
  `dark_tokens_test`'s allow-list entry (moved here from C3: the contract test's colour-literal arm reads those rules).
- `tests/local/course_card_contract_test.php`, copied from `moodle-dev/templates/tests/`, every arm mutation-checked.
- Gate: tier 1 and 2 (CSS and markup), one full leg with `--behat`, and a browser pass at 390, 800 and 1200 px.

## C3. The showcase's behaviour

- The visitor's one button: "Mais informações" is not drawn for an anonymous viewer.
- The panel header: no "Cursos da área" eyebrow (`category_panel_eyebrow` leaves both lang files); the category link
  underlined on hover and focus only.
- The progress row on the flat list (`account.ts` `paintProgress()` sets `--dim-progress`, the bar replaces the text
  line), hidden when Boost Union's progress setting is off.
- The completed state: `get_card_details` returns `completed` (course completion), the card trades its bar for the
  seal and the green base, and the main action reads "Rever" (a new `category_cta_review` string).
- Gate: tier 3 (a web service changes), `mdl ci moodle-theme_dimensions --matrix --behat`.

## C4. The trail popover

- The step gains the course image (`cards::images()` over the trail's rows, the renderer for the rest).
- D11: `local\trail\progress` computes no percentage per step (only state, completion time and `next`), so the
  popover shows the state alone; no new query.
- The dialog variant (contract section 9): the 96 px cover, the close disc at 32 px where the star would be, the
  plugin slot "Curso n de N · trilha", the completed seal and base, the main action by state ("Rever" when done),
  the calendar's buttons kept (D12), and the cover-less form when Boost Union's card images are off.
- As built: `get_calendar_trails` answers each step's `image` (PARAM_RAW), one `cards::images(..., true)` batch over the
  steps' rows (one `{files}` statement, the covers from the warm branding cache), none while Boost Union's card view is
  off; the page hands the client that view as `trails.covers`. The close disc is a button over Bootstrap's white
  `.btn-close`, its colours the card tokens read on `.dim-cal__trailpop` (`--dim-card-close-*`, `-done*`, `-extra-*`,
  `-focus-*`); "Rever" reuses `category_cta_review`. The cover-less popover keeps a 4px top edge in the category's
  colour, green when completed.
- Gate: tier 3, `--matrix --behat` (the trail feature).
