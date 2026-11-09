# Related contents: plan (2026-11-08)

Status: **decisions D1-D11 approved by the owner on 2026-11-08; R0 (the finder, its tests and its 22 mutation gates)
merged as uaiblaine/moodle-theme_dimensions#129; R1 (the band, the field, the setting) as #135, drawn with the trail's step
card until the course card stages C2-C3 hand it the contract's card. R2 (manual mode, `local\related\choice`) merged as
#138. R3 (the `[relatedcourses]` shortcode, `local\related\shortcode`) in progress on branch `related-r3`.**

Related contents are stage T4 of the trail plan ([../trail/PLAN.md](../trail/PLAN.md), Q7: "not in the first stage").
They are courses related to a parent course through the competencies linked to both, shown at the end of a landing
page and, through a shortcode, on any page that runs `filter_shortcodes`.

## 1. The model

A **candidate** is any course other than the parent with at least one competency linked to it
(`{competency_coursecomp}`). Three signals relate it to the parent's set of competencies P:

- **Parity**: its set equals P.
- **Overlap**: it shares part of P; candidates are ordered by Jaccard, shared / (|P| + its own count - shared).
- **Proficiency**: one of the shared competencies is linked to it with the outcome "Complete the competency"
  (`\core_competency\course_competency::OUTCOME_COMPLETE`): completing the candidate makes the learner proficient.

**Automatic mode** (the default), in tiers:

1. parity;
2. shares a competency and grants proficiency in one it shares;
3. shares a competency, higher overlap first.

Inside a tier: higher overlap, then open enrolment first (R1, where the card already reads the enrolment state),
then the nearest start date (upcoming first, then the most recent past), then the name. Deterministic, never random.

**Manual mode**: an editor picks some of the competencies linked to the parent (or, in a shortcode with no parent,
any competencies of one framework). A candidate is a course linked to any of them (`match="all"`: to all of them),
ordered by how many it covers, then as above.

A **trail** contributes the union of its own competencies and its steps' competencies; its steps are never
related contents, since the landing page already lists them under "Cursos da trilha".

Nothing is related when competencies are disabled on the site (`\core_competency\api::is_enabled()`), or when P
is empty.

## 2. Who sees what

The trail's rule (section 6 of the trail plan) **without ghosts**: a ghost keeps a position in a sequence, and a list
of suggestions has none, so it would only announce a course the reader cannot reach.

| Reader | Sees |
|---|---|
| Visitor (nobody logged in, or the guest account) | public courses only (`publicaccess::are_public()`) |
| Account | what the showcase's gate admits (`population::admitted()` over the root scope: core's course and category visibility, then local_unlistedcourses' terms; an unlisted course only to an account enrolled in it or with a way to enrol), minus the courses it is enrolled in or has completed (D5) |

Every reader: never a hidden course (D11), and never a frozen one while core's context freezing is on, the course's
own context or any above it locked (D10): core refuses every write capability there, self-enrolment included.

**Where the rule is applied.** Every refusal the database can tell is a term of the statement that reads the
candidates, before the ranking: hidden, frozen, for a visitor a course state other than public or a hidden or unlisted
category on its path, for an account its own courses. The gate above is still asked of every card shown, and stays the
authority (it fails closed where the terms cannot, a missing ancestor category). So a block is never emptied by
courses the reader may not see at the head of the ranking; what the gate refuses after the statement is what depends
on the account (a category closed to it, an unlisted course it has no way into), and three slices of the ranking reach
past those. The first draft gated a reader-independent ranking slice by slice, and a visitor's block came out empty
whenever 36 refused courses outranked the public ones.

No result: the whole block is absent, its heading included.

## 3. Where it appears

- **Landing page**: a full-width band after the textarea sections, where the sticky card ends. A course field
  `dimensions_related` (select: Automatic, Off, Manual; empty reads as Automatic, the field's default staying empty as
  every select of the theme's; Manual is the third line, appended by R2's upgrade step on a site provisioned with two,
  since a select stores its line's position) and, for Manual, the chosen competencies (D3) in the hidden text field
  `dimensions_relatedpairs`: a JSON list of [framework idnumber, competency idnumber] pairs, written by an autocomplete
  of the course's competencies that takes the field's place in the course form and shows only for Manual. The band
  keeps the chosen competencies still linked to the course (for a trail, to the trail or its courses), matches any of
  them, and draws nothing while none is chosen. A site setting for the number of cards
  (`theme_dimensions/relatedcount`, 4 by default, 1 to 12).
- **Shortcode** `[relatedcourses]` (filter_shortcodes allows letters and digits only; on a name conflict only one
  plugin wins):

  | Attribute | Default | Meaning |
  |---|---|---|
  | `mode="auto\|manual"` | `auto` | the mode |
  | `framework="FW01"` | none | the framework's idnumber; required with `competencies` |
  | `competencies="LID01,LID02"` | none | competency idnumbers, read inside `framework`; implies manual |
  | `course="shortname"` | the page's course | the parent course |
  | `match="any\|all"` | `any` | manual mode: any or all of the competencies |
  | `limit="4"` | the site setting | number of cards, capped |

  Identifiers, never ids: the filter's README warns that ids break on backup and restore. A competency idnumber is
  unique only inside its framework; a framework idnumber is unique on the site (D9).

- Uses the shortcode opens: a `local_page` page ("Formação em liderança") as a catalogue curated by competency with
  no parent course; a label at the end of a course ("Próximos passos"); a category description.

## 4. Technical points

- **Recursion**: a card shows another course's summary, which may carry the shortcode. The summary is formatted
  without the shortcode filter, and a re-entrancy guard returns '' inside a related block.
- **Unrestricted usage**: anybody who writes text (a forum post) can use the shortcode. The output shows only what
  the reader may already discover, so nothing leaks; the cost is capped (instances per request, `limit`).
- **Competency names**: no "shared competencies" chips for a visitor (the framework may not be visible to them); for
  accounts only, after R1 (D7).
- **Another theme**: the block's CSS is the theme's; a page drawn by another theme (course or category theme)
  gets ''.
- **Soft dependency**: without filter_shortcodes `db/shortcodes.php` is ignored; with the filter disabled the raw
  `[relatedcourses]` text shows. A Boost Union recommendation item says which.
- **Invalid reference** (unknown framework or competency, or a competency outside the framework given): nothing for a
  reader; a short notice naming what was not found for a reader who may edit that context.
- **Cost**: one statement for the parent's competencies, one for the candidates (an aggregate over
  `{competency_coursecomp}` carrying the reader's refusals), then per slice of ranked candidates the rows and the gate;
  covers in one batch (`cards::images()`). Pinned by a read-count test, constant in the number of candidates.
- **No cache across requests.** The ranking is the same for every reader, but it is one aggregate statement, and core
  fires no event when a competency is linked to a course or unlinked (local_dimensions' `template_courses` cache
  lives with a one-hour TTL for that reason). A visitors' list would be a cache of local_unlistedcourses' answers,
  which needs the owner's sign-off in both repositories (the theme's D39 and OD-5); an accounts' list would be wrong,
  since what an account sees depends on its enrolments, cohorts and roles, which change without events. If a
  measurement ever asks for one, it narrows the candidates only and the gate is still asked of every card, so a stale
  entry hides a course for a while and never shows one. Measure first on the 5,000-course set of m503b.
- **Gate**: the diff reaches SQL, so tier 3 (`mdl ci --matrix`).
- **Out of scope**: the Moodle App, which renders the filter's output without the theme's CSS.

## 5. Decisions of 8 November

| # | Question | Owner's answer |
|---|---|---|
| D1 | Parity: identical set only, or ranked overlap? | Ranked overlap, identical sets first |
| D2 | Proficiency and tier order | `ruleoutcome = OUTCOME_COMPLETE`; parity, then grants, then shares |
| D3 | Where a landing's manual choice is stored | A text course custom field holding framework + competency idnumber pairs (travels with backup), written by an autocomplete of the course's competencies on the course form |
| D4 | A shortcode with no parent course | Allowed, manual mode only, any competencies of one framework |
| D5 | Courses the account is enrolled in or completed | Excluded |
| D6 | Visitor | Public courses only, no ghosts |
| D7 | "Shared competencies" chips | Accounts only, after R1 |
| D8 | Count and layout | 4 cards on the landing (site setting), the showcase's card, server-side Mustache (no React) |
| D9 | Competency idnumbers are unique per framework only | The shortcode takes `framework` (required with `competencies`); one framework per shortcode; the landing field stores pairs |
| D10 | Frozen courses (context freezing) | Never suggested while freezing is on, frozen themselves or under a frozen category, for every reader |
| D11 | Hidden courses, and where the reader's rule applies | Never suggested, not even to staff who may open them; every refusal the database can tell filters the candidates before the ranking, the gate still asked of every card; no cache |

## 6. Stages

| Stage | What | Gate |
|---|---|---|
| R0 | `local\related\finder`: competencies of a parent (trail union), idnumber pairs resolved, candidates ranked, the reader's gate; PHPUnit with a read count | tier 3 |
| R1 | Landing band, automatic mode, `dimensions_related` field, site setting; mockups on the design canvas first. The card is a "Card" surface of the fleet's course card contract (`moodle-dev/docs/course-card/CONTRACT.md`, uaiblaine/moodle-dev#157, sections 1 and 13): its ten-slot anatomy, `--dim-card-*` tokens reading `--dimensions-card-*` first, the cover from the theme renderer's `get_generated_image_for_id()` (Boost Union's settings, no category colour of its own), progress and cover under `courseoverviewshowcourseprogress` and `courseoverviewshowcourseimages`, and a copy of `moodle-dev/templates/tests/course_card_contract_test.php`; a plugin-slot sentence needs that contract's definition first. No markup, CSS or card states of its own before the contract is merged | tier 3, browser look |
| R2 | Manual mode on the landing: the pairs field and its autocomplete | tier 3 |
| R3 | `[relatedcourses]`: `db/shortcodes.php`, recursion guard, theme check, Boost Union item; mount filter_shortcodes on m503 and m503b | tier 3, browser look |
