# theme_dimensions: comment audit, items left for the owner (2026-11-10)

Companion to `2026-11-10-report.md`. Everything else the audit reported is fixed on branch `comments-audit`
(commits 0881b15, 1ba2b05, 0c89b61). This file lists what needs a decision, a browser check or a measurement.

## A. Open questions that are product decisions

| # | Where | Question | Options | Recommendation |
|---|---|---|---|---|
| Q3 | `classes/local/category/remembered.php:75` (`MAX_LENGTH = 1333`), twin in `classes/local/related/choice.php:52` | The byte cap on the remembered state is the theme's own (`user_preferences.value` is `text` since 5.0). | Keep 1333 (the 20-category map already bounds the row) / raise or drop it, after measuring 20 entries with every filter. | Keep. |
| Q9 | `js/esm/src/calendar/layout.ts:208-216` | A day whose overflow is only hidden multi-day bars shows "+N" with no dots; the dots stand for timed items only. | Keep (documented meaning) / dots for hidden bars too (`weekLayout` keeps keys per column; Jest updates). | Keep. |
| Q11 | `scss/category.scss:62-72` | `#page.drawers .main-inner` and `#region-main` are made transparent on the showcase. Redundant on a stock page; matters when Boost Union paints a page background image or colour. | Browser check with a page background set: if nothing differs, remove both rules; if the transparent ground looks right over the image, keep them and name that reason in the comment; if the hero's chamfer then mismatches, remove the override. | Needs the browser check (it changes a Boost Union setting on m503, so it was not done without your OK). |

## B. Claims the code relies on that no local source settles

Of the 46 claims, 36 were confirmed against core 5.3, Boost Union 5.3 and the plugin checkouts. Two were false:
#27 (the landing route answers `guest=yes` with a 400, not a 500: comment corrected) and #28 (the sentence no
longer exists). The 8 below need a step.

### B1. Browser checks

| # | Where | Claim | Step |
|---|---|---|---|
| 23, 25, 36 | `classes/output/category_hero.php:401`, `classes/output/core_renderer.php:267`, `scss/category.scss:256` | Core prints no breadcrumb once the category address carries an extra parameter, so the hero draws its own trail. Core's source predicts the opposite (`course/index.php` sets `$PAGE->url` to `?categoryid=N` only; `navigation_node::check_if_active()` compares against `$PAGE->url`); the repo's history records the opposite measurement on 5.2. | With the showcase switched off for one category (or another theme for one request), open `/course/index.php?categoryid=N` and the same with `&q=x`, read `.breadcrumb`. If core keeps the breadcrumb on 5.3, reword the three comments; the hero's trail stays valid either way. Note: `allowthemechangeonurl` is on at m503, so a `&theme=` request changes the session's theme. |
| 18 | `classes/local/hook/course/after_form_definition.php:263` | A hideIf on the customfield_number field stops the course form saving under JavaScript (5.3 disables hidden fields, so a required float refuses the save). | On `/course/edit.php`, hide the number field with a hideIf, switch the course format, save. The code avoids hideIf there, so it is harmless if the claim is false. |
| 11 | `classes/local/category/filterfields_setting.php:33` | A fifth filter group no longer fits the toolbar. | Four, then five filter groups at 1280 and 1366 px; does the toolbar wrap? Layout call. |
| 30, 34 | `js/esm/src/calendar/MonthGrid.tsx:78`, `scss/calendar.scss:755` | Phone gutters 16 px; the week placeholder's 28rem matches the live week. | At 390 px, frame width = viewport - 16 px; first paint of the week view without a jump. Cosmetic. |

### B2. Measurements

| # | Where | Claim | Step |
|---|---|---|---|
| 6 | `classes/local/card/cover.php:128` | A cold branding cache costs 7 statements per category, plus 1. | PHPUnit: `branding::for_categories()` for N categories, `perf_get_reads()` delta; pin it in a test if wanted. |
| 19 | `classes/local/landing/publicfiles.php:105` | WhatsApp drops a preview image past about 600 KB; quality 82 holds a 1200x630 noise image at 519 KB. | Encode the noise image at quality 82 in the stack's GD; share 520 KB and 650 KB og:images from a real WhatsApp account. |
| 20 | `classes/local/landing/publicfiles.php:121` | A 25-megapixel PNG peaks about 200 MB over the request's baseline. | Run the decode, rotate and cover path with `memory_get_peak_usage()` in the stack container. |
| 33 | `js/esm/src/daterange/calendar.ts:156` | About 84 formatted dates per render. | Count `formatter()` calls for one open picker. |
| 39 | `scss/focusmode.scss:384` | The labelled exit at sm leaves about 100 px for the activity name. | Measure at 576-767 px in focus mode. |
| 46 | `templates/tool_lp/user_competency_summary.mustache:40` | The template lint refuses a lower-case constructor name. | `mdl ci --only mustache` with the name lowered. |

### B3. Design rules (only the design record can settle them)

- #43 `templates/category_hero.mustache:39`: 80 px is the smallest a crest reads at.
- #38 `scss/category.scss:1149`: the pixel measures (18 px icon box, 13 px glyph, 41 px text start, 13.12 px chip type).
  The contrast ratios beside them were recomputed and match.

## C. Left on purpose during the fixes

- `cards.php:426` (`has()` before `get_many()`): skipped. `course_image` has a data source, so `get_many()` on a miss
  computes the value per course, which is the cost `has()` avoids.
- `criteria`'s constructor still accepts any field value; only the request path is guarded (the remembered path was
  already). Guarding the constructor needs real fields in the tests that build criteria directly.
- `empty_look_cohort` re-reads the offered looks without a lock (the lock helper is private to `lookcohorts`); a look
  offered again in the middle of an emptying run still loses that run's members.
- `event_courses` judges `held` on the range's clock now; `public_courses.php:156` still reads `time()`.
