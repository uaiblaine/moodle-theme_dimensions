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

## D. Security read of the fixes (security-diff-read over 809ca26..1ba2b05)

No defect. Three notes:

- **A course core hides still answers differently from a missing id**, for a logged-in user: a missing id and a course
  local_unlistedcourses withholds both get the ghost page now, while a core-hidden course the viewer may not see
  reaches `request::handle()`'s `coursehidden` exception. Core's own `course/view.php` and `enrol/index.php` answer the
  same way, and so does the theme's ghost on `enrol/index.php`; closing it would mean ghosting every core-hidden course,
  which is a product decision beyond local_unlistedcourses' promise.
- `population::staff_in_clamped()` applies the rescue cap, not `gate()`'s `PROBE_CAP` on unrelated unlisted probes, so
  the SQL cut can admit more such staff courses than the PHP cut past 50. Every unlisted candidate still goes through
  `access::filter_courses()`, so nothing the plugin refuses is admitted.
- The staff list counts candidates against the cap before the plugin's filter, so refused unlisted courses spend
  places: an under-count, failing closed.

## E. Browser checks on m503b (2026-11-11)

Every setting changed for a check was put back afterwards (Boost Union's `backgroundimage` empty,
`extrascssinheritance` 0, `categorypageenabled` 1, `categoryfilterfields` = modality, format).

- **Q11, transparent grounds.** With Boost Union's page background image set, at 1366 px:
  - Today's rules: the image shows behind the showcase's toolbar, its heading ("Cursos em ...") and the filter
    counters, which sit straight on the image; the cards keep their own white ground; the hero's edge is unchanged.
  - Without the two rules (Boost's `--bs-body-bg` ground back on `.main-inner` and `#region-main`): `.main-inner`
    spans the whole width, so the showcase hides the image completely, like a page with no image.
  - The decision is which of the two the showcase should look like; neither breaks the hero.
  - **Found on the way:** with the default `extrascssinheritance` (inherit), the Dimensions stylesheet carries no
    rule painting Boost Union's page background image at all (Boost's `theme_boost_get_extra_scss()` reads the
    ACTIVE theme's `backgroundimage` setting, and Boost Union's own extra SCSS is pulled in only under "duplicate").
    So on a site left at the defaults, Boost Union's page background image setting does nothing under Dimensions.
- **B1 #23, #25, #36, breadcrumb.** With the showcase off, core 5.3 keeps the full breadcrumb on
  `/course/index.php?categoryid=N` with `&perpage=10` and with `&q=x`. The claim was false; the three comments now give
  the true reason the hero draws its own trail (one rule for both shells, the visitor's naming only public
  ancestors). Not committed yet.
- **B1 #18, hideIf on the number field.** Not settled in the browser: it needs a hideIf added in code on the shared
  mount. The mechanism (a field hidden by hideIf is disabled and not submitted on 5.3) was measured before in a modal
  form; the code avoids hideIf there, so the comment stands and nothing is at risk.
- **B1 #11, filter groups.** With four groups configured, the toolbar stays one line (42 px) at 1280 px: the groups
  live in the Filters panel, where they use about 730 of its ~1,100 px. A fifth short group would fit there; one with
  many options would wrap. The cap of four is a design choice, not a measured limit.
- **B1 #30, phone gutters.** At a 390 px viewport the calendar frame is 374 px, the viewport minus 16: true.
- **B1 #34, week placeholder.** False: the placeholder is about 504 px (a 3.5rem head and a 28rem body), the drawn
  week 724 px (a 64 px head and 660 px of hours), so the week view jumps about 220 px on its first paint. Fix
  candidate: size `.dim-cal__placeholder--week` from the drawn week (4rem head, 41.25rem body at 16 px), and pin the
  pair in calendar_scss_test.

## F. Measurements (B2, 2026-11-11, m503b)

- **#6, cold branding cache: holds.** `branding::for_categories()` with the `categorybranding` cache purged costs
  7 statements per category plus 1 per batch (8, 36 and 71 for 1, 5 and 10 categories with no files and their contexts
  loaded); add 1 per category the first time a process loads its context, and about 2-3 per category with files.
- **#19, og image size: holds.** A 1200x630 image of random pixels at quality 82, through the real `cover()`, is
  531.7-532.5 KB (519-520 KiB), under the 600 KiB fallback cap. Real course images on the site land far below it (a
  1280x720 photo: 68 KiB). The owner's note: the ideal would be to convert the image to the right size up front, or to
  warn the editor about WhatsApp's limits; that is part of the tool_imagepicker work in its own session.
- **#20, the 25-megapixel ceiling: partly.** `stored_file::rotate_image()` rotates JPEGs with an EXIF orientation only,
  so a PNG is never rotated: a 25 MP PNG decoded and covered peaks about 186 MB over the baseline, a 25 MP JPEG
  rotated from its EXIF orientation about 214 MB (both images held at once). Both fit the site's 512M. The docblock
  of `publicfiles::MAX_SOURCE_PIXELS` and the repo `CLAUDE.md` now say so.
- **#33, dates per date-picker render: holds.** Opening the picker (two months, 77 days) makes 91 format calls.
- **#39, room for the activity name in focus mode: does not hold.** At 576 px a labelled exit leaves the name 324 px
  (440 with the icon alone), and core's completion button is not in the bar on 5.3; the md breakpoint follows the
  navbar's own label breakpoint. The comment now says so.
- **#46, the template lint and a lower-case constructor: holds.** A copy with `new info(` failed `mustache` with
  ESLint `new-cap` ("A constructor name should not start with a lowercase letter"), a warning that fails the
  zero-warning gate.
- **#34, the week placeholder: fixed** in this change (6rem toolbar, the drawn week's header, strip and hour box
  bounds); `calendar_scss_test::test_the_placeholder_is_as_tall_as_the_drawn_calendar` holds it. Measured equal in the
  browser: week 820 = 820 px at 900 px tall, 620 = 620 at 700, month 1016 = 1016.

Every temporary change made for these checks was undone (focus mode on course 5047, the filter groups, the
showcase switch, the background image and the inheritance setting).

## G. Decided (2026-11-11)

- **Q11:** the showcase covers the page background image. The two transparent grounds are gone from
  `scss/category.scss`, and the same pair from `scss/landing.scss` (the full-width landing page), so every page of the
  theme keeps Boost's `--bs-body-bg` ground on `.main-inner` and `#region-main`.
- **Boost Union's page background image** now reaches the theme's stylesheet under 'Inherit'
  (`classes/local/pagebackground.php`). Checked on m503b with an image: the Dashboard shows it around the content as
  Boost Union does; the showcase, the calendar and the full-width landing page sit on their ground over it.

## H. Decided (2026-11-12)

- **Q3:** `remembered::MAX_LENGTH` rose to 4096. Twenty entries carrying every key measure about 2,240 bytes, so only
  eleven fitted under 1333; now the count of twenty is the bound that applies.
  `related\choice::MAX_LENGTH` stays 1333: there it is a real column bound (customfield_text stores in `charvalue`).
- **Q9:** kept. The dots stand for timed items only, and hidden multi-day bars count in "+N".
- **C, all four fixed:**
  - `public_courses` judges `held` against one instant per answer.
  - `empty_members()` settles each person under their lock, from their recorded choice.
  - The `criteria` constructor keeps only the site's filter fields with an option's value. Both listing services
    already refused other field ids, so this closes no live leak; it moves the rule to where every listing is built.
  - A course core withholds is ghosted (D's first note): `redirector::is_ghosted()` now also asks enrol/index.php's
    two guards, so a hidden course or one in an unviewable category answers like a missing id on the landing page,
    `/enrol/index.php` and `/course/info.php`.
    The listing services already refused such a course with their one refusal, because core's
    `can_view_course_info()` checks visibility. `course/view.php` keeps core's answer.

## I. The tests report's section 4, settled (2026-11-12)

The 29 claims of `2026-11-11-tests-report.md` section 4, checked against core 5.3, Boost Union 5.3 and the plugin
checkouts, measured through `mdl ci` on an isolated clone, and in the stack's Selenium Chromium. 17 hold, 9 hold in
part, 1 is false, 2 numbers drifted. Every comment that was wrong or loose is reworded on branch `audit-unverified-claims`.

- **Hold:** the zone.ts reason (a TZ set inside a Jest test leaves the offset at 0; set at process start, 300); the
  site course admitted by `can_view_course_info()`; the public rows ceilings (exactly 17, 19 and 1); tiny_media's
  unguarded options; `candidates()` paging (it orders by `c.sortorder`, which follows the category tree); round() on
  PHP 8.3 against 8.4 (26 against 25 with alpha 0.9, 26 on both with an integer percent); the render-time ratio (1.05
  and 1.25); `perfdebug` above 7 switches the cache statistics on; the script entry's hidden `id` field
  (`links.php`, anonymous and without the router only); the visitor's posters; MDL-84465; the backup's two halves
  (dropping the file annotation or the picture rows each reddens `test_a_hidden_badge_survives_a_course_copy`);
  enrol_apply's queue; `getDrawerZIndex()`; PHPUnit's sequence offsets; tool_lp's proficiency selector; Boost Union's
  `d-none` brand.
- **False:** the WebM fixture plays in today's Selenium Chromium (150, headed and headless) and in Chrome 156: the
  feature's comment no longer gives that as the reason for the MP4.
- **Drifted:** the month and agenda blocks cost 536 and 229 statements at 25 courses (533 and 226 when the budgets
  were set; both are still under them). The range probe: see below.
- **In part, reworded:** the dialogue's flat count (8 statements a course; the split between the landing values and
  the contacts was never shown); the Font Awesome variables (reached through `lib/bundles/fontawesome.scss` since
  MDL-88766); the overflow of #region-main's ancestors (the body takes `overflow: hidden` while a drawer is open at md
  and below or a modal locks the scroll); the course notice (a missing category row, the course's own or an
  ancestor's); the ESM tree check (mdl ci only: GitHub's reusable workflow has none, being added in its own task);
  the competency hooks (a few are read by nobody and are kept to mirror core); Boost Union's navbar (Bootstrap's
  navbar schemes and the `--bs-navbar-*` properties, not "every element onto .navbar-dark"); the router (a router
  built before the request registers every route twice, but a second request fails for another reason: core's "The
  theme has already been set up for this page", so `CLAUDE.md` and `category_test` are corrected); filter_emoticon
  (the debugging call is shown; "the router turns it into a failure" was not).

### Decided

- **#11:** the cap of four filter groups stays, named as the design's choice: the groups live in the Filters panel,
  where a fifth short group would still fit at 1280 px.
- **#43:** 80x88 is the full band's size at every width (it wraps rather than shrinks), not the smallest a crest
  reads at: the compact band draws 36x40 (26x29 on a phone) and the cards 56x62. The template's comment says so.
- **#38:** no change. Measured on m503: the glyph is 13 px at 15 px from the field's edge, the typed text starts at
  41 px, the box is 40 px tall, the chips are `.82rem` (13.12 px).
- **#8, the range probe's budget:** the answer did not grow. Measured with the same probe at C1 (1252c05, where the
  bounds were set) and today: 108,957 and 109,311 bytes at 25 courses, so the "floor of 55,189 bytes" was never this
  seed's weight and the 110,378-byte bound had about 1 % of headroom from the start. The only new key is `editing`
  (343 bytes); events are nine tenths of the answer. Statements went from 117/536 (5/25 courses) at C1 to 123/562
  today. The bounds are now today's cost plus a tenth (136 and 29,302 bytes at 5 courses, 619 and 120,243 at 25), the
  rule the docblock always stated.
