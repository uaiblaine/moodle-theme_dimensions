# Changelog

All notable changes to `theme_dimensions` are recorded in this file, in the format of
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased (v5.3-r1-dev)

- **The navbar follows Moodle 5.3.1+ and Boost Union v5.3-r3** (2026100638). Core now collapses the primary navigation
  into the drawer below 992px instead of below 768px (MDL-90101), and Boost Union's navbar follows. The theme's logo
  beside the menu button, the width of the navigation drawer and the compact navbar ("Compact navbar on tablets", now
  from 992px to 1199px) follow the same breakpoint; without this the logo was missing from 768px to 991px and the
  compact navbar hid the navigation without showing the menu button. Needs Boost Union v5.3-r3
  (2026100503), which is made for Moodle 5.3.1+ (2026100501.01, or 5.3.2 once released); on plain 5.3.1 the navigation
  and the menu button show together from 768px to 991px, as Boost Union's own notes say.
- **The category showcase and the full-width landing banner reach the window's edges again on Moodle 5.3.1+.** Core now
  reserves a wider gutter beside the page from 992px up (MDL-90206), which left 9px of page on each side of the navy
  band. The theme reads that gutter from Boost instead of repeating a number.
- **The Filters button draws the funnel** (2026100637). The filter bar's Filters button (category pages and the visitor
  page) shows core's filter icon, `fa-filter`, in place of the sliders, as decision D107 of the fleet Design System
  asks. Nothing else about the button changes.
- **The dashboard's month block no longer fetches the month's events twice.** core's block_calendar_month builds the
  month before the theme draws it; the theme now counts the month's core events from that work, applying its own rules
  to it (withheld categories, keys switched off), and fetches only the days of the neighbouring months the grid shows.
  A saved calendar state narrowed to a category or a course filter keeps the theme's own fetch. The calendar feed
  (iCal) reads the subscriber's groups and its courses once each instead of once per course. Nothing shown changes.
- **Fewer statements on four busy pages** (the performance audit of 2026-11-20). A visitor's landing page reads the
  course's enrolment methods once instead of three times; the course notice's enrolment hints share one read; the
  calendar feed builds each course's activity information once instead of once per event, which matters to a
  subscriber with more than ten courses; and the course index in "count" mode asks which courses a viewer may
  discover once for all the categories it lists, instead of once per category. Nothing a page shows changes.
- **Course cards without an image no longer read their course's category on every request** (2026100636). The cover
  of such a card (My courses, the Dashboard) is painted from the course's category, which is now kept in a new shared
  cache, `coursecategory`; creating, restoring, updating (moving included) or deleting a course drops its entry.
- **The profile rule has a batch form for pages of people.** `\theme_dimensions\local\profileaccess::control_view_profile_many()`
  answers what the profile callback answers for a list of users and one course, in three statements instead of about
  three per person, so a plugin listing many people (enrol_apply's approval queue) can ask it once per page. Nothing
  changes for anybody: the callback and its answers are as before.
- **A visitor is no longer told about an enrol_apply method whose card is hidden.** A method that restricts to a cohort
  and has "Hide this card from people outside the cohort" on no longer shows its seats and deadline above the login
  button to a visitor, and no longer makes that button appear on its own. The theme asks `hides_card_from()` of
  enrol_apply (when the installed build has it); logged-in users and the category showcase already agreed.

- **The calendar feed compares its token as a string.** The theme's copy of core's iCal export checked the
  `authtoken` with `==`, as core does, so a request token `0` matched any account whose token happens to be `0e`
  followed by 38 digits (about one token in 1.5e10). It now compares with `hash_equals()`; every real address keeps
  working.
- **The Compass block draws this theme's kind icons and an event's dates** (2026100634). The theme's answer to
  block_compass's course kinds now gives each kind the glyph the theme's own cards draw (a graduation cap for a course,
  a calendar for an event, a route for a trail) and says an event is dated, so the block's cards show the icon in the
  kind's pill, as the cover's watermark where the course has no image, and the event's dates under its name, as the
  Vitrine's cards do. It needs block_compass 2026100550 or later; with an older block the kinds still show, without
  icons or dates.
- **A new card token, `--dimensions-card-watermark`** (2026100634), the colour of the kind's icon drawn on a cover with
  no image (the course card contract's slot 1), white at 16 % in both colour modes. The theme declares it for every
  plugin's cards; its own Vitrine cards do not draw the watermark yet.
- **The user menu's layer, the phone's side menu and the category's menus answer the pointer in one way**
  (2026100633). A row, a menu item, a small icon button and the profile layer's two buttons take a pale ground of the
  brand's colour and its ink when hovered, a deeper one when pressed, and a row's or an item's leading icon (and a
  row's arrow) moves a step towards the end of the line, the left in a right-to-left language; the change arrives
  quickly and leaves slowly, and with reduced motion asked for the colours change at once and nothing moves. It covers
  every row, icon button and choice of the profile layer, the phone side menu's rows, back button and "Go to" link,
  the filter bar's menus and the menus of the category's row of pills (a pill on the row keeps its own hover). Log out
  stays red, and the page you are on stays as it is. The profile layer's header and the side menu's "Continue where
  you left off" card are drawn on a quiet gradient from a warm white through the page to the brand's tint, with three
  small decorative sparkles; the picture is ringed, the two profile buttons sit on the page's colour, and the card
  shows a tile in the brand's colour with the icon of the kind of course it leads into (course, event or trail), its
  title at 1rem, an arrow at its end and a thinner progress rail in the
  brand's colour. Under "log in as" the notice is now the sentence on its own lines, then the way back as a button the
  notice's width. The phone's side menu is a navigation landmark named "Site navigation" (new string `drawer_nav`).
  Web service: `theme_dimensions_get_drawer_summary` returns one more value in `resume`, `kind` (course, event or
  trail, from the course's page model; empty without a card), which the card shows as its tile's icon.
  Two family colours are added for the sibling plugins, `--dimensions-hover` and `--dimensions-pressed`, and the
  theme's own `--dimensions-ground-warm`, `--dimensions-ground-sand` and `--dimensions-ground-spark`.
- **The category's destination pages draw the same row of pills** (2026100632). The pages the row leads to (the
  category's settings, permissions, cohorts, competency frameworks and the rest) and the category page with the
  showcase off show the row after their page header, where Moodle's tabs were, start-aligned on the page's ground,
  with the page itself marked current inside its group; Moodle's tabs are no longer drawn there. A page whose plugin
  draws a navigation of its own keeps it. The index pages of programs, certifications, catalogue sections, home pages
  and cohort management (the tool_mu plugins), which switch Moodle's tabs off, now draw the row too. Only the current
  page's own link is marked as the current page for a screen reader: the pill of the group or of "More" that holds it
  keeps its selected look and its dot, without the attribute. With JavaScript, "More" makes room for its dot when it
  takes the current page. Programs, certifications, catalogue sections and home pages have an icon of their own in
  the menus (a diagram, an award, a list and a house, from the icon font), where they had the generic link. The map
  of the pages to their groups and icons is one table in the theme, and the hero and the destination pages build the
  row through one function. The root of the course index no longer draws a row: it is not a category's page. Moodle's
  Behat steps "I navigate to ... in current page administration" and "I select ... from secondary navigation" follow
  the destination on the row. A new template (`category_navpills_page`) draws the row's track after the header, and
  the theme maps four icons (`theme_dimensions_get_fontawesome_icon_map()`).
- **The category hero shows no count at all** (2026100631). The panel of numbers under the description is gone, on a
  category page and at the root of the course index, for a signed-in reader and for a visitor: it no longer shows the
  courses you are enrolled in, the hidden courses or the site's total. The list's own count stays in the filter bar
  under the hero. An administrator or teacher loses the count of hidden courses: it is no longer shown anywhere on the
  category page (the hidden courses themselves are still listed, with their state), and the root of the course
  index no longer shows a total. The three strings of the panel are removed.
- **The category hero no longer shows the course count** (2026100630). The filter bar under it
  already shows the list's count, so a category page's panel keeps only the viewer's enrolments and, for staff, the
  hidden courses, and is not drawn when neither applies. The root of the course index keeps its total: its toolbar
  is the search line alone and carries no count.
- **The category showcase's navigation is a row of pills on the hero's lower edge** (2026100629). Moodle's
  tabs under the hero give way to a capsule of pills that straddles the band's edge, at every width and with the hero
  folded: "Category", the current page, is a plain pill with its dot; "Settings", "User and access", "Courses and
  content" and "Competencies" each open a menu of their destinations, every one with its icon, in alphabetical order;
  a group holding one destination for the viewer is that destination's own pill. A destination the theme does not
  know is listed under "More", the row's last pill, which is drawn only when it has something to hold. A viewer whose
  only destination is the category page itself gets no row, and the hero ends plainly; note that on a site with
  competencies enabled every account can read the category's competency frameworks, so a learner's row there is
  "Category" and "Competency frameworks". The row works without JavaScript: every group is a disclosure that opens by
  itself and the capsule wraps when it does not fit. With JavaScript, for a row that has a menu to open, the menus
  behave as menus: the arrow keys, Enter or Space open one on its first item, the arrows, Home and End move inside it,
  Escape closes it and puts the focus back on its pill, Tab or a press outside closes it, and only one is open at a
  time. The capsule then keeps one line, and what does not fit moves into "More" from the row's end, "Category" last:
  a group as a section headed by its name, a pill as an item with its icon, a separator between sections; "More"
  takes the selected look when it holds the current page. The row is measured again when the fonts arrive and when
  its width changes, never while a menu is open. A row of plain pills loads no script. Moodle's more menu is no longer
  loaded on the showcase. A new template (`category_navpills`) draws the row and a new module
  (`theme_dimensions/navpills`, over the ES modules `navpills/row` and `navpills/fit`) arms it; the anonymous shell
  draws none, as before.
- **The theme requires `local_unlistedcourses` 2026100506 (v5.3-r3) or later** (2026100628); it required 2026100505.
  Since 2026100627 the theme leaves the contexts of the unlisted courses it asks about to the plugin's own bulk read,
  which arrived in that version; with the older one every such course cost a statement on the category showcase.
- **On a phone the Organise menu no longer leaves the screen, and a menu item's focus ring no longer covers its
  neighbours** (2026100627). On the filter bar's two-line step the Organise and View menus hang from the line's end, as
  the date layer does; under its own button Organise started 21 px before the screen's edge at 375 px. The ring of a
  menu item is drawn inwards, since the items touch. The theme no longer preloads the contexts of the unlisted courses
  it asks `local_unlistedcourses` about: the plugin reads them in one statement itself (since its v5.3-r3), so the
  theme's loops and their two mutation gates held nothing. `identity_test` starts from a site with no custom profile
  field, so it passes on a site where another plugin creates fields on install.
- **The side menu is the site's navigation alone, and Escape closes it** (2026100626). The side menu of a logged-in
  account no longer shows the person's name, picture and role line, nor the colour mode switch, the look and the
  language: those are in the profile layer the user button opens. It keeps the "Continue where you left off" card,
  the site's navigation and the smart menus in their sections, and their sliding sub-panels. Its rows follow the
  Design System's side layer: 40px (44px on a touch screen) at a smaller text size, a muted icon, the current page in
  the brand's tint and ink without the mark on its edge, and a sub-panel's head is a back button beside its title.
  The menu is 320px wide (at most 85% of a phone's screen) instead of Moodle's 285px, the same in the tablet navbar.
  Escape at the menu's root closes it and gives the focus back to the menu button; inside a sub-panel it still goes
  back one level. The navbar's own colour mode menu is no longer hidden where the side menu used to carry the switch;
  it is hidden wherever the profile layer carries it, as before. Web service: `theme_dimensions_get_drawer_summary`
  returns the card alone (`resume`); `inprogress` and `inprogresstext` are gone, and with them the statement that
  counted the courses in progress. The language strings `drawer_siteadmin`, `drawer_inprogress_one` and
  `drawer_inprogress_many` are removed.
- **The category showcase's navigation is sorted into groups, ready for the row of pills that will draw it**
  (2026100625). Nothing changes on the page yet: the hero still shows Moodle's tabs. A new class reads the category's navigation as Moodle
  builds it for the viewer and sorts every destination into "Category", then "Settings", "User and access", "Courses
  and content" and "Competencies", in that order, each listing its destinations alphabetically in the viewer's
  language; a destination the theme does not know (Insights, or a plugin's new page) goes under "More". A group with
  one destination for the viewer will be that destination's own pill, a group with none is left out, and a viewer
  whose only destination is the category page itself (a learner) will get no row. Five new strings name the
  navigation and its four groups; "More" is Moodle's own word.
- **The category showcase's Organise and View are menus: "Group by" and "Sort by" are two choices, and View is one
  button** (2026100624). Organise opens a menu with two sections, "Group by" (Area, No grouping) and "Sort by" (Name,
  Most recent), each item with its icon and a tick on the chosen one, and choosing in one section keeps the other:
  grouped by area, "Sort by" is the order of the area panel that is open and of the next one opened, also before any
  panel is open, and it stays when the panel closes; the panel's own Recent and A-Z buttons and the menu follow each
  other. View is one button showing the view in force with a chevron, opening "Show as" with Cards and List. Both menus
  are keyboard menus: an arrow key on the button opens it on its chosen item, the arrows, Home and End move through
  the items, Escape and a choice return to the button, and a press outside closes them like the bar's other layers.
  Without JavaScript every item is a button that applies at once, and "Apply" is gone. Every address and remembered
  state written before keeps meaning what it meant (`sort=category`, `sort=name`, `sort=recent`, `psort=name`); the
  addresses the theme writes keep that spelling and do not grow for the default state, and the toolbar's form and the
  listing services also take the grouping by name (`group=category` or `group=none`, with the order in `sort`).
  The A-Z order of a grouped page is now remembered per category and kept in the address with no panel open.
- **The user button opens the profile layer** (2026100623). A new setting on the *Identity* tab, `profilelayer`
  (on by default), prints on every page of a logged-in account a side layer built from Moodle's own user menu: the
  person's picture with the title and further lines chosen in the identity settings (masked on the server), "View
  profile" and "Edit profile" by Moodle's own rule, a notice with the way back under "log in as", the site's user menu
  items under "My area", Preferences and the role switch under "Account", the colour mode, the look and the language
  under "Display", and Log out at its foot; on a phone it is a bottom sheet. A new script (`amd/src/profilelayer.js`)
  takes the user button over and opens it as a dialog: the focus stays inside, Look and Language open as sub-views,
  Escape leaves a sub-view and then closes the layer, the focus returns to the user button, and the opening slides in
  unless the person asked for reduced motion. On a phone the navbar's controls that make room there are the layer's
  first group, and wherever the layer carries the colour mode the navbar's own colour mode menu is hidden. Boost
  Union's user menu stays on the page and opens as before whenever the script does not run. The layer is printed
  once, after the page's content, and asks for nothing more per page: the look menu is asked once for the user menu
  and the layer together. Visitors, the guest account and the Moodle app never get it. Behat: core's "I follow ... in
  the user menu" goes through the layer on this theme.
- **The category showcase's filter bar hides Filters when it has nothing to open, and its layers and tooltips behave**
  (2026100622). A category with no custom field in the filter list and no "More statuses" option shows no Filters
  button, for a visitor and for a signed-in reader alike; the button comes back as soon as one of the two exists, also
  after a search or a filter changes what is offered, and an open panel goes with it. A press anywhere outside an open
  layer of the bar (Filters, the date button, Organise) now closes it, also on a phone where the layer is a sheet,
  without stealing the press from a card or link and without moving the keyboard focus; Escape still returns to the
  button. The tooltips of the bar open below their button, where the category's tab row no longer covers them, and the
  ones at the far end open towards the middle of the bar. While the search is open, in place or over the bar, its capsule
  holds the field and a "Close search" button where the "More actions" menu sits at rest; the menu waits out of the way
  (an open one is closed) and returns when the search folds.
- **The administrator chooses what identifies a person in the navbar** (2026100621). Four new settings on the
  *Identity* tab: the field shown beside the avatar (`identitynavbar`), the field that will be the title of the
  profile layer (`identitytitle`), the further lines under it (`identitylines`) and the display patterns
  (`identitypatterns`). A field is the full name in the site's format, the alternate name, the first name, the
  username, the email address, the ID number or a custom profile field of type text, menu or social; a custom field
  is shown only while Moodle's own visibility rule shows it to the person. An empty field falls back to the full
  name. A display pattern formats or partly hides a field wherever these settings show it, one `field=pattern` per
  line: `#` shows a character, `*` hides it (as •), anything else is printed as written, and only the value's letters
  and digits fill the pattern, so `username=***.###.###-**` shows `12345678909` as `•••.456.789-••`. The pattern is
  applied on the server, and a value of another length is shown as stored. The defaults change nothing: the full
  name, no further line, no pattern. One difference without touching a setting: while an administrator is logged in
  as somebody, the navbar names the account logged in as (Moodle's "You are logged in as" line and the open menu
  still name the administrator). The title and the further lines have no surface yet; the profile layer that draws
  them comes next. The custom fields' definitions are kept in a new shared cache (`identityfields`), emptied when a
  profile field or category changes, and read only while a setting names a custom field.
- **The category showcase's start date has a button of its own on the filter bar** (2026100620). A calendar button
  follows Filters in the capsule; its name says the period ("Start date: 17 November 2026 to 4 December 2026") and a
  small dot sits on its corner while one is set. It opens its own layer, under the bar's end or as a sheet from the
  bottom of a phone, which closes on Escape, on a press outside it or on its "Show 16", and never stands open beside
  the Filters panel: the heading, the quick options ("Today", "This week", ...), the "From" and "To" boxes, the two
  months (one on a narrow screen) turned with chevrons, and a foot with "Clear period" and "Show 16". A press on a day
  sets the box in use and moves to the other one, and the list is refreshed at once while the layer stays open:
  there is no "Done" any more. Each box clears its own day. Days are 32 x 28px; today has a thin ring, the period's two
  ends are filled squares in the brand colour and the days between a pale band, and while a first day is set the band
  follows the pointer. The Filters panel holds the option groups only, and its badge no longer counts the period,
  which stays a chip under the bar. Without JavaScript the button opens the same layer with the two date fields, the
  quick options, "Clear period" and "Filter".
- **The category showcase's filter bar is the fleet Design System's compact bar** (2026100619). After the scope, the
  bar's end holds a grey cluster: the number of courses found, then a white capsule with the favourites star, Filters,
  Organise and the two view buttons; the search sits in a capsule of its own with the managers' menu. Every control
  in the capsules is a 32px icon button with no border, grey at rest, light grey under the pointer and in the brand
  colour's pale tint while pressed or open; the bar is 40px tall, 44px wherever the screen is touched, whatever its
  width. Filters carries no word at any width, its badge always on its corner. The search is a magnifier button at
  every width: a press opens the field in its place (or over the bar where the bar has no room for it), Escape or
  leaving it empty folds it back, and its eraser clears the text and keeps it open; without JavaScript the field is
  open as before. Each icon button names itself in a small dark tooltip after a tenth of a second on the pointer or
  the keyboard's focus, never on a touch screen and never over its own open menu; Escape puts the tooltips away. The
  sentence under the bar ("16 courses for ...") leaves the screen, its number now on the bar, and is still read to
  screen readers; it stays visible where it says how the results are spread across the areas, beside "See all as a
  list". The Filters panel ends with "Clear filters" and "Show 16" (the bar's count), which closes the panel. On a
  phone the view buttons stay on the bar instead of moving into the Organise menu.
- **The category showcase's filter bar wears the fleet Design System's measures** (2026100618). Its controls sit 8px
  apart. The scope ("All", "My courses" and the others) is a segmented control: 32px cells at 0.875rem on a grey
  platter with 12px corners, the chosen cell raised in white at weight 600 with no border, its count quiet. The star,
  Filters, Organise, the view switch and the managers' menu keep their own 1px line and white ground but take 8px
  corners, a light grey on hover and, pressed or open, the brand colour's pale tint with its edge and ink. The Filters
  badge is a 16px counter at 0.75rem / 700, 4px off the button's corner where the button is an icon. The Filters
  panel has 12px corners and 12px of padding on white, with a popover's shadow, and the phone's bottom sheet 16px
  corners. Its options and the quick date options are 28px rectangles at 0.8125rem / 500, no longer pills; the chosen
  one takes the brand colour's tint, edge and ink instead of a solid fill, and a group's options wrap onto further
  lines instead of scrolling sideways. The date boxes are as tall as those options. The chips under the line are
  28px rectangles in the same brand tint, their cross 12px, and the row wraps instead of scrolling; "Clear all" is
  0.8125rem / 600, underlined, in the body ink. On a phone every control keeps its 44px.
- **A trail draws Font Awesome's route, on the course card and in the calendar** (2026100617). A trail's card in the
  showcase tags its kind ("Trail") with the route, no longer with the course's graduation cap; a course keeps the cap
  and an event the calendar day. The card's icon now comes from the server with its label, so the cards a page loads
  as the reader scrolls or filters draw the same icon as the first page (the rows services return it as `kindicon`).
  The Trilhas view's trail picker and the trail popover's position line draw the same route glyph, 14px in the 16px
  box the hand-drawn icon had.
- **A course card's state label is a rectangle with an edge, its icon the size of its words** (2026100616). The
  label under a card's title ("Enrolled", "Open for enrolment", "Hidden from students" and the other states) and its
  copy on the course dialogue's cover are 6px rectangles, 2px 8px at 0.75rem / 600, with a 1px edge in the state's
  colour in both colour modes, no longer pills; the icon inside is the words' size, in their ink, 4px from them.
  "Completed" keeps the rectangle and its check, with a green edge. The dialogue's copy of the label is the card's own
  size, no longer larger. A card's kind on its cover ("Course", "Event") stays a pill, its icon 4px from the word,
  2px 8px. A receding card in the filter line's preview greys its label's edge with its fill. The course dialogue's
  "Continue course" button draws an arrow like its other actions, no longer a triangle: a direction is an arrow or a
  chevron, never a caret.
- **Small labels read in sentence case, and status labels are rectangles with an edge** (2026100615). No stylesheet
  of the theme sets text in capitals any more: the filter groups' names, the category hero's trail, the course
  dialogue's kicker and facts, the competency pages' headings, the drawer's labels, the landing page's kicker and
  facts, the profile's field labels, the calendar's weekday names, the focus bar's course line and the contrast
  panel's caption read as their strings are written, without letter-spacing; the small labels among them are
  0.75rem at weight 600. The labels that state something - a competency's rating, core's proficiency badges, the
  course notice's chips, an event's kind, a learning step's state and hours, an enrolment deadline, the contrast
  panel's verdict and standard, and the reading bar's activity status - are 6px rectangles at 0.75rem with a 1px
  edge in their tone, no longer pills; the course notice's chips drop their dot. Where a tone had no edge the theme mixes its ink into its ground at 30%, in both colour modes; the
  current Trilhas step's label and the landing page's current step take a rim that differs from their fill, the
  contrast panel's neutral tone takes Bootstrap's secondary border (the page's line did not show on it), and the
  landing page's deadline label takes the pending state's colours, which now follow the dark mode. A course card's
  kind on its cover stays a pill.
- **A ratchet holds the theme's SCSS to the fleet Design System's scales** (tests only; no version change).
  `tests/local/scale_ratchet_test.php` reads every sheet of `scss/` and records today's off-scale uses per sheet and
  value: 128 font sizes, 10 weights, 235 spaces, 20 radii and 28 durations off the scales, 17 `text-transform:
  uppercase` (rule 3, sentence case) and 55 pill radii (rule 2, no pill on a control). The counts are exact, so a new
  use fails and so does one brought onto the scale until its count is lowered in the same change; a new sheet fails
  until it is named. The scales are block_compass's constants, unchanged. Eight new mutation gates (`ds_scale_*`).
- **The theme declares the fleet Design System's scales, and buttons take Moodle's own 8px corners.** Ten new
  family tokens, on the same three roots and in both colour modes as the others: `--dimensions-control-line` (the
  border of an outlined control, Bootstrap's gray-500), `--dimensions-focus-width` (the ring's 3px),
  `--dimensions-radius-xs` to `-xl` (4, 6, 8, 12 and 16px) and `--dimensions-elevation-sm`, `-md` and `-lg` (the
  theme's navy-tinted shadows; black on the dark page, with the same offsets and blurs). No component of the theme
  reads them yet. The six `--dimensions-state-*-edge` tokens are no longer their pill's own ground: each is a line
  that shows around it, darker on the light page and lighter on the dark one, mixed from the pill's ink and ground
  at 30 % (the solid "enrolled" fill takes the palette's next stop), so a status label drawing its 1px edge has one;
  under the theme only local_dimensions' locked-card disc draws it today. Bootstrap's `$border-radius` moves from 6px
  to 8px, Boost's value, so buttons, form fields and every other control drawn at the default radius are two pixels
  rounder (2026100614).
- **block_compass's crests cost the same for one course or a batch of 24.** The theme's answer to the block's course
  crests hook read each course's category context on its own, through Moodle's `can_view_course_info()`: one
  database statement per category of every batch, on every first paint (`get_attention`) and every card batch
  (`get_card_details`), 6 to 24 extra reads a call. The answer's one course statement now brings those contexts
  too, so the hook costs the same few statements whatever the number of courses and categories (four, two more
  when local_unlistedcourses has an unlisted course among them to decide), and answers exactly as before
  (2026100613).
- **A trail gets its kind in block_compass, as an event does.** The theme's answer to the block's course kinds hook
  named the course and the event models only, so a trail drew no kind in the All courses view and was counted as a
  course in the open-for-enrolment line. It now names the trail model too (`trail` / `trilha`), after course and
  event: the line reads "6 courses, 4 events and 2 trails start by ...", and a trail's row carries the Trail pill
  (2026100612).
- **A profile no longer names a course local_unlistedcourses withholds from the reader.** Moodle's "Course
  profiles" list on `user/profile.php` and `user/view.php` named every course of the profile's owner, with a link, to
  anybody who may open the profile. The theme now builds that list again from Moodle's own query with the plugin's
  listing rule applied before the "View more" cut, and prints it in place of Moodle's whenever Moodle's names a
  withheld course; a list with no course left is not printed, and a profile naming nothing withheld is printed as
  Moodle built it. Costs one statement and the plugin's batch read per profile page. Not covered, because no
  renderer draws them: the navigation tree's *Courses* branch under a person, the forum posts, notes and grade
  overview pages of a person, global search, and the web services.
- The category showcase's stylesheet drops three rules keyed on `body.drawer-open-left`, a class no script of Boost
  5.3 or Boost Union sets: a drawers layout marks an open drawer on `#page` instead. Nothing on the page moves. Its
  comments now say how Moodle 5.3.1 lays out the page beside an open side drawer.
- **The first page after a cohort change keeps the chosen look, with Boost Union's purge flag fix too.** Boost Union
  2026100501 clears its flavour purge flag itself, after the one purge it still does on the first page that flag is
  seen; that purge runs after the theme wrote the choice, so the theme keeps clearing the flag first. A new test
  draws that first page through a real header, and the test that waited for Boost Union to clear the flag, red now
  that it does, is gone.
- **A course core hides gets the ghost page, as a missing or unlisted course does.** For a logged-in reader, the
  landing page, `/enrol/index.php` and `/course/info.php` answered a hidden course the reader may not see, or one in a
  category whose listing the reader may not view, with core's "This course is currently unavailable", where a missing id
  and an unlisted course get the "not available" page: the two answers told which ids hold a course. Both now get the
  ghost page. A site without local_unlistedcourses keeps core's answers.
- **A listing request keeps only the site's filter fields, however it is built.** The two listing services refused a
  field outside the filter setting, but the request itself took any field id, and the listing narrows by whatever it is
  given. The request now drops a field that is not one of the showcase's filters, or a value none of its options has,
  so no future caller can narrow by a custom field the reader is not shown.
- **Emptying a look's cohort spares whoever chose the look again.** A long emptying, run after a look left the list,
  removed everyone it found at the start; a look offered again in the meantime lost the people who had chosen it until
  the daily reconcile. Each person is now settled under their own lock, from their recorded choice.
- **The showcase remembers twenty categories however many filters each holds.** The remembered state's cap rose from
  1,333 to 4,096 bytes: twenty entries with every filter set came to about 2,300 bytes, so only eleven were kept.
- The public calendar judges whether an event course is over against one instant per answer, as the logged-in calendar
  does.

- **The flavour stylesheets are no longer recompiled by a cron machine that lacks the files.** The compiled file of
  a flavour lives in each machine's local cache directory, and only `core/postprocessedcss` is shared, so a cron
  container started anew for each run found every file missing and compiled every flavour, in each direction, every
  minute, holding the theme's lock all web servers share. Each sheet the theme compiles now starts with a comment
  naming the theme revisions it was compiled at; a machine without the file writes it from the shared cache when that
  comment names the current revisions, in the task's own process, with no child process and no compile. A sheet Boost
  Union compiled itself, or one from before a theme build, is compiled again, as before. The command-line builder's
  new exit status 6 says a sheet was written from the shared cache.

- **The image fields' crop dialog offers a choice of aspect ratios, the ideal one recommended.** With tool_imagepicker
  2026111101 or later, a badge offers 1:1 (recommended) or a free crop; the background image 16:9 (recommended), 21:9,
  4:3 or free; the card cover 16:9 (recommended), 2:1, 4:3 or free. The recommendations follow the boxes measured on
  the page: the poster that shows a card cover runs from 1.30 to 2.13 times as wide as it is high across window widths,
  and the background image is also the category's 1200 x 630 link preview. The share picture keeps its one fixed shape.
  An older picker gets no ratio at all, so a field meant to be free is never locked. **Core's course image** (the course
  settings' *Course image*) is the picker too, with 2:1 recommended (16:9, 4:3 or free offered) and at most
  1600 x 900: it covers course cards 2.4 to 3.1 times as wide as they are high, a trail step's 1.6 and the 1.91 of the
  course's link preview. A site that allows more than one course image, or a type that is not a web image, keeps core's
  field (2026100609).

- **Image fields crop with tool_imagepicker where it is installed.** The landing page's share picture and four badges
  (customfield_picture's fields in the course settings) and the category editor's background image, card cover and
  three badges are the image picker's element: one web image with a crop dialog, under the same name and draft area,
  with the field's own limit and types. The share picture is cropped to the 1200 x 630 shape of a link preview and
  stored at that size; a badge at most 400 x 400, the background image 1920 x 1080 and the card cover 1280 x 600. The
  plugin stays optional and is asked for by class name: without it the fields are core's file manager, as before. The
  background image's help says it is also the category's shared-link picture, cut from its centre to 1200 x 630 and
  sent under 600 KB, the most WhatsApp accepts (2026100608).

- **Boost Union's page background image works under Dimensions.** With the theme's SCSS inheritance left at its default
  (*Inherit*), the image set in Boost Union's *Look > Page > Background image* never reached this theme's stylesheet:
  Boost prints it from the active theme's own setting, and Boost Union's copy for its child themes asks the page,
  which the stylesheet's compile does not have. The theme now prints it from Boost Union's setting. The category
  showcase and the full-width landing page keep Boost's page ground, as every other page does, so they cover the image
  instead of laying their text over it (2026100607).

- **The calendar no longer jumps on its first paint.** The placeholder drawn while the calendar loads was shorter
  than the calendar: its toolbar left out the rows' margins (24 px in both views), and the week view's was a fixed
  28rem where the drawn week follows the window's height. It now takes the drawn calendar's height in the month and
  the week, at every window height (2026100606).

- **The comment audit's improvements, and code left behind removed** (2026100605).
  - *Guest home page.* On a site whose home page is a URL and whose Dashboard is not the home page (the 5.3
    default), a guest left on the "site home page" option no longer loops between the site root and `/my/`: core's own
    URL home page applies.
  - *Category showcase.* A filter address naming a custom-field value no option has (a mistyped link, or one shared
    before the option was deleted) no longer empties the listing with no chip to undo it: the value is ignored. A
    course card whose category the reader must not discover drops the screen reader's "Course category" label with
    the name. The hero's active tab text follows the brand colour, like the call to action.
  - *Calendar.* A visitor's answer no longer carries an event course whose course row was deleted mid-request; the
    agenda links only to http(s) addresses, as the upcoming block does; a trail step's completion time is 0 for a step
    that is not done.
  - *Smaller fixes.* An Alt-modified press on a Look item is no longer intercepted; the category page builds its
    selectors with `CSS.escape()` and runs its progress batch timer only while cards wait for progress; the hex colour
    fields of the category editor use tabular figures.
  - *Removed.* `band::wanted()`, three landing field helpers only tests called (`add_hybrid_option()`,
    `place_format_fields()`, `retire_public_field()`) and the retired field's constant, the calendar's unused
    `openableDay` export, and five CSS rules and custom properties nothing emitted or read.
  - Four more copies of core's course renderer are tracked by `upstream_test`; the trails web service's description
    names the view "Trails".

- **Six defects the comment audit found, fixed** (2026100604).
  - *Focus mode default for new courses.* The queued task now reconciles the field with the setting as it stands when
    it runs, so flipping the setting back before cron no longer writes the stale value, and a refused provisioning
    lock queues a retrying task instead of dropping the change.
  - *Landing page.* A logged-in user asking for a course id that does not exist gets the same unavailable-course
    redirect as for a course withheld from them, so signed-in users can no longer tell which course ids exist.
  - *Category showcase, state sort off.* The page lists to a viewer with staff standing at a course the courses of a
    category local_unlistedcourses withholds, as the state-sorted page does, and a search reaches the viewer's own
    courses past the first fifty in a withheld category.
  - *Look cohorts.* A look cohort queued for emptying (more than 500 members) is not emptied by the adhoc task when the
    administrator offered the look again before cron ran it.
  - *Calendar.* Back and Forward adopt the filter state the address names, so the toolbar and the next change start
    from what the grid shows instead of snapping back.
  - *Category showcase colours.* The text on the call-to-action fill (checked chips, quick options, count badges,
    calendar ends, poster buttons) is derived from the site's primary colour, so a light brand colour gets dark text
    instead of white below 4.5:1.

- **Comments say what the code does.** A review of every comment in the shipped code (PHP, JavaScript, templates,
  stylesheets) against the code it describes: 211 comments that no longer matched were corrected, and history,
  measurements and notes about the development environment were taken out. No code changed; the JavaScript builds
  were rebuilt because they carry the source text (2026100603).

- **The login page is Boost Union's.** The theme's own login page (the institutional panel, the two ways in, the
  local identity provider marks and the reset hint), retired once auth_loginsteps took over the sign-in, is removed
  with its *Login page* tab: the login page, its arrangement and every login setting are Boost Union's again, on
  every site. The upgrade deletes the six settings (`loginpage`, `loginpanelkicker`, `loginpaneltitle`,
  `loginpaneltext`, `loginpanelshowmonitoring`, `loginpanelfoot`); the guest home page moves to *General settings*.
  auth_loginsteps' shell still carries the second factor and the privileged session while it is the site's door
  (2026100602).

- **On a landing page, `[relatedcourses]` takes the band's place after both columns.** A shortcode written in the
  course's own summary or sections was drawn inside the description column. The page now draws the first one in the
  related courses band's place, below the description and the enrolment card, instead of the automatic band (with
  the notice for an editor of what it does not find), and leaves a section or a summary that held only the shortcode
  out. A shortcode in any other text (a label, another course's page) is drawn where it is written.

- **Calendar: the Trails view is a site setting, and a trail's line opens it.** *Trails view* (`calendartrails`, under
  the calendar's settings, on by default) offers the view beside Month and Week; off, the view switch shows Month and
  Week only, an address naming the view opens the month, and course landing pages offer no "Ver no calendário" link.
  On the desktop timeline, a press on a trail's span (the line of its whole period) or on its end's diamond folds and
  unfolds its courses as the chevron does, with a hit area taller than the line (2026100601).

- **The cover video size setting keeps its default on a site with a small upload limit.** Core's upload sizes leave
  out every size at or above the site's limit, so under 20 MB the 20 MB default was no choice at all: the install
  never stored it, and *Cover video size limit* stayed among the new settings on every upgrade. The limit in force is
  now always one of the choices; the site's own limit still applies on top of it.

- **The related courses band keeps its cards inside a landing page section.** The panel of the audience, programme
  and certification sections draws the editor's bullet lists as a checklist, and its rules reached the band's own list
  too, so `[relatedcourses]` in a section drew a check beside each card. The checklist now leaves that list alone.

- **A public landing page cleans its text sections before the text filters.** The audience, programme and
  certification sections of a public course were cleaned after the filters had run, which stripped what a filter adds:
  the `[relatedcourses]` shortcode drew its band without covers, sections or data attributes there. They are now read
  with the cleaning forced inside `format_text()`, where Moodle cleans every text, before its filters; an editor's
  trusted HTML is still cleaned for every visitor.

- **Related contents, stage R3: the `[relatedcourses]` shortcode** (docs/related/PLAN.md). With filter_shortcodes, the
  shortcode draws the landing page's related courses band in any text the theme draws (a page, a label, a category
  description): by default for the course the text belongs to, or for `course="short name"`, or in manual mode over
  `framework` and `competencies` ID numbers (in a course, only those linked to it), with `match="all"` and `limit`.
  Each reader sees only what the band would show them. It draws nothing on a page another theme draws, while
  competencies are off, inside another shortcode's band, past four shortcodes a request, or for a reference the site
  does not have, of which an editor of the text's course or category is told. A recommendation in Boost Union's
  overview says when filter_shortcodes is missing or off. The landing page's band and the shortcode draw one partial,
  `related_band` (2026100600).

- **Related contents, stage R2: manual mode on the landing page** (docs/related/PLAN.md). The *Related courses* field
  gains a third line, Manual: the band then relates by the competencies an editor chose among the course's own (for a
  trail, the trail's and its courses'), the courses covering more of them first, and draws nothing while none is
  chosen. The choice is kept in a new course field, *Competencies for related courses* (`dimensions_relatedpairs`),
  as framework and competency ID number pairs so it survives a backup; the course settings form shows an autocomplete
  of the course's competencies in its place, only while Manual is chosen. A competency unlinked from the course later
  drops out of the band by itself. The upgrade appends the line and creates the field (2026100599).

- **Course cards, stage C4: the trail popover.** The Trilhas view's step popover is the course card's dialog: a 96px
  cover with the step's course image (its own, else the theme's cover in its category's colour or Boost Union's
  fallback image), the category over the title, the dates as the meta line, "Course n of N · trail" on a quiet ground
  with the trail's icon, the state pill, core's reason for a locked step, and the actions on the right. The close
  button is the course dialogue's dark disc at 32px on the cover, darker on hover and ringed on keyboard focus; Escape,
  Tab past the last control and a press outside still close it and return the focus to the bar. A completed step wears
  the green "Completed" seal and a green base on its cover, and its main action reads "Review"; the calendar's own
  buttons stay. No progress bar: the trails service computes no percentage per step. With Boost Union's card images
  off the popover draws no cover, keeps a top edge in the category's colour (green when completed), and puts the
  category and the standard close button in a row. `theme_dimensions_get_calendar_trails` answers each step's `image`
  (a URL or a data URI), read in one batch for the whole answer. The calendar page sets its context before its
  answers, since the trails' answer reads Boost Union's settings through the page's theme. The popover's date line
  wraps instead of cutting its source short. Seventeen mutation gates (2026100598).

- **Course cards, stage C3: the showcase's behaviour.** A visitor's card draws one button, "Learn more", to the
  course's landing page, in the flat list and the panel; "More information" is a signed-in reader's, since its
  dialogue is never armed for a visitor, and a course without a landing page has no button. The panel's heading loses
  its "Courses in the area" line (the `category_panel_eyebrow` string goes), and its category link is underlined on
  hover and keyboard focus only, in dark mode too. An enrolled card shows its progress on a line of its own under the
  state pill: a bar and the percentage in tabular figures, announced as a progress bar; a course without completion
  tracking shows none, and Boost Union's "Show course progress" set to No removes the row. A course the reader has
  completed trades the bar for a green "Completed" seal, a green base on its cover and "Review" as its main action,
  and keeps them with the progress row off. `theme_dimensions_get_card_details` answers `completed` (the reader's
  course completion, one statement per batch) and `progresspct`; the rows services' card carries `hasdetails`,
  `hasprogress` and `done`. Seventeen mutation gates (2026100597).

- **Course cards, stage C2: the card's look.** The theme declares the course card's 30 family tokens
  (`--dimensions-card-*`, light and dark, listed in `docs/tokens.md`), and the showcase's card paints through its own
  readers of them. The whole card is one link and one hover: the title's link covers a card the reader can open, its
  buttons and star stay pressable over it, the hover is a larger shadow and the title underlined, nothing moves, and
  the keyboard ring is drawn around the whole card. "More information" is Bootstrap's outlined secondary button. The
  kind, the date tile and the category are in sentence case, the crests are 56 x 62 at every width, and a card
  Boost Union draws without its cover (or a row without its thumbnail) keeps an edge in its category's colour, with
  the kind, the crests and the star in a row at the top of the card. The grouped panel draws the flat list's card
  instead of a smaller restyle of it. In dark mode the title keeps no underline at rest, against core's dark-mode
  underline of the main region's links, and the hover underlines it as in light mode. The rows services' card carries
  three new keys (`band`, `hasband`, `hastop`) (2026100596).

- **Related contents, stage R1: the landing page's band** (docs/related/PLAN.md). A landing page ends with the
  courses related to its course through their competencies, in the order and for the readers R0's reader decides,
  and draws nothing, heading included, when there is none. A trail's page reads the trail's and its courses'
  competencies and never lists its own courses. A new course field, *Related courses* (`dimensions_related`:
  Automatic, Off; empty reads as Automatic), turns the band off per course; the setting
  `theme_dimensions/relatedcount` (4 by default, 1 to 12) caps it. The upgrade creates the field (2026100595).
  `docs/related/demo/seed.php` seeds a demonstration category on a development stack.

- **The landing page banner can run full width** (setting `landingheroformat` on the *Course landing page* tab). In the
  content column, the default, the banner stays the card it was. Full width, it is drawn as the page header, from edge
  to edge under the navbar, with the category showcase hero's geometry: its text keeps the column's edges, the course
  image is shown whole under the brand colour at the category hero's default overlay (78%), the chamfer sits at the
  window's lower left and the kicker takes the category eyebrow's bar. One choice for the whole site, the public page
  included; the page and the header read one export of the page (2026100594). Nine mutation gates.

- **Course cards, stage C1: Boost Union's course overview settings and the cover of a course without an image.** A
  course with no image of its own now shows its category's cover on the showcase's cards instead of the fixed navy:
  an SVG in the category's colour (inherited from the nearest category with one, else the site's brand colour) under
  the cover gradient, which the theme's renderer also answers to every page that asks core for a course's generated
  image. Boost Union's "Course image source" with an uploaded fallback image shows that image instead; "Show course
  progress" set to No leaves the card details service without a percentage; "Show course images" marks each card
  for the view whose images are unchecked, and draws no media band when both the card and the list view are. The
  rows services' card image may now be a data URI and the card carries four new flags (`showcover`, `showthumb`,
  `showmedia`, `showprogress`). The README explains the three settings (2026100593).

- **Related contents, stage R0: the reader behind them** (docs/related/PLAN.md). `local\related\finder` answers
  which courses relate to a parent through the competencies linked to both: in automatic mode the parent's whole set
  (a trail's with its steps'), ranked by an identical set, then a course whose completion grants a shared competency,
  then overlap; in manual mode competencies named by their framework's and their own idnumbers, ranked by how many a
  course covers. The reader is shown the trail's gate without ghosts: a visitor the public courses, an account what
  the showcase lists to it, less the courses it is enrolled in or has completed. A hidden course is never suggested,
  nor, while context freezing is on, a frozen one (itself or under a frozen category). Every refusal the database
  can tell is a term of the candidates' statement, so courses the reader may not see never fill the head of the
  ranking and empty the block; the gate is still asked of every card. Nothing draws it yet; the landing page band
  (R1) and the `[relatedcourses]` shortcode (R3) will. Constant statements in the number of candidates; 22 mutation
  gates.

- **A site with no Boost Union flavour no longer stalls on "New settings - Dimensions"** (2026100592). Core's
  multi-checkbox setting draws and saves nothing while it has no choice, so "Looks a person may choose"
  (`selectableflavours`) was never stored on such a site, and every visit to the notifications page sent the
  administrator to an upgrade settings page with no field whose Save brought it back. The install now stores the
  setting's empty list, and an upgrade step stores it where it is missing. Found installing the theme on a clean
  5.3 site, its dependencies fetched from the plugins directory.

- **Trail model, T3d follow-up: no calendar link to a trail with nothing to draw.** The landing page's "View in the
  calendar" now also asks which of the trail's courses the Trilhas view would draw for the reader: a learner of a trail
  whose every course is withheld from them (unlisted, with no tie and no way in) was offered a link to an empty view,
  since the view lists such a trail nowhere. One mutation gate.

- **Trail model, stage T3d: who is shown a trail, and folded trails** (2026100591). The calendar's Trilhas view shows a
  trail only to an account enrolled in it or on the way (an enrolment that starts later, an application awaiting a
  decision, a waiting-list place), one that may join it (an open method, guest access, a conditional enrolment, by
  local_unlistedcourses' own answer), any account when the trail is public, and whoever may edit the trail course; a
  suspended or ended enrolment with no way in left hides it. The trail picker follows the same rule, the cap counts the
  trails shown, and the landing page's "View in the calendar" is offered exactly when the view would list the trail,
  by the same gate and rule (no link on a page the listing withholds, such as a trail in an unlisted category).
  The steps keep their rule: a public trail's unlisted course stays hidden from an account with no tie to it. The rule
  reads at most two batches more a page of candidates, whatever the number of trails. With more than one trail the
  timeline opens with every trail folded, and one alone opens; beside the "Trails and courses" heading a toggle expands
  or folds them all; a trail chosen alone opens even if the reader had folded it, and a course pressed in a trail folded
  before its scroll ends opens no popover. Two new strings. Twenty-seven new mutation gates, three retargeted.

- **Trail model, T3c follow-up: a partial first week and the month zoom's cap** (2026100590). By weeks, a first week of
  one or two days (a month that starts just before the viewer's first weekday) is narrower than its label, which spilled
  into the next week: a cell under 2.5rem now draws no text and keeps its line. By months the timeline holds twelve
  months instead of six, so a wide screen is filled; by days and weeks the cap stays six, and a switch down keeps the
  months held until the next load drops the far side. Five mutation gates.

  By months the view now asks the trails service for up to three months in one request (never more than the cap
  leaves room for), appended or prepended, so a wide screen reaches twelve months in four requests instead of eight
  one after another; by days and weeks a load is still one month, and the first paint stays the month and the next.
  The service accepts `months` 1 to 3 (`trails::MONTHS`; `trails::OPENING` is the first paint's two) at the same read
  cost. Eleven mutation gates.

  A course with a start and no end is no longer drawn to the edge of the months held: it is its first 30 days
  (the start day and 29 more), then a quiet "→ no end date" chip after a small gap, at every zoom, so its end reads as
  open; the chip waits for the next month when the 30 days reach the edge. The bar's accessible name and the step's
  popover say "No end date set" in full. An expected completion date still counts as the end; the connectors leave
  the end of the 30 days; the trail's thin line ends there. The phone's agenda is unchanged. Two new strings.

  Ten trails of ten courses cost the statements two do, at one, two and three months: the steps' modinfo is now
  read from the trail courses' records, because past the ten modinfos core keeps in memory get_fast_modinfo() by id
  read each further trail's course again (13 statements against 11 for ten trails). The view keeps one request in
  flight; only a filter change during a load leaves the earlier one to finish, its answer dropped.
- **Trail model, stage T3c: a zoom and the courses' dates in the calendar's Trilhas view** (2026100589),
  `docs/trail/PLAN.md` section 7. The desktop timeline has a zoom, "Days", "Weeks" and "Months", in the filters' row:
  by weeks the header shows the weeks from the viewer's first weekday, a short month over each week's days, today's
  week tinted; by months the band alone names the months. Switching keeps the day at the start of the view; the zoom
  is written to the address (`zoom=week|month`) like the trail, never remembered, and kept by the arrows and the month
  band, which still move a month. A "Today" tag tops today's line at every zoom; a bar too narrow for its name draws
  none (a one-day bar keeps it by days); the connectors, the detour included, narrow with the days and never run
  backwards. Where the months held fit a wide screen (by months), the next ones are loaded up to six. Each course in the trail column says its dates
  under its name ("2–20 Nov 2026", "From 1 Dec 2026", "Until 20 Nov 2026", "No dates"), and its row's accessible name
  carries them. Rows are 56px. New strings: `calendar_trailzoom`, `calendar_trailzoomday`, `calendar_trailzoommonth`,
  `calendar_trailzoomweek`.
- **The landing page's summary opens cut after its third paragraph** (2026100588). A long course summary pushed the
  trail and the programme far down the page; it now ends at its third paragraph in a fade, with a "See more" toggle
  that opens it whole and "See less" that closes it. The page always opens cut and remembers nothing. A paragraph ends
  at a leaf block or a line break, because the editor writes a summary as often one block broken by `<br>` as a run
  of `<p>`; a summary of three paragraphs or fewer shows no toggle, and without JavaScript it is whole. The trail's
  "View in the calendar" no longer touches the card's button above it, and the line joining the trail's courses ends
  in the last course's circle instead of running past it: it is drawn as one segment per course, circle to circle.
  New module `theme_dimensions/landingsummary`, strings `landing_summary_more`, `landing_summary_less`; eight
  mutation gates.
- **Trail model, stage T3b: continuous months in the calendar's Trilhas view** (2026100586), `docs/trail/PLAN.md`
  section 7. The timeline holds the month in view and the next from its first paint (`theme_dimensions_get_calendar_trails`
  takes an optional `months`, 1 or 2), asks for the next month as the scroll nears its end and for the previous one on
  the way back to its start or from the "previous" arrow, keeping the view still, up to six months held. A band above
  the days names each month, its name a button that scrolls to its 1st; the "Dec →" column is gone. The toolbar names
  the months in view ("November – December 2026"), its arrows move a month and "Today" scrolls to today; the address
  follows the month in view. A course in the trail column is a button that scrolls its bar into view and then opens its
  popover; a row and its bar light each other under the mouse. The fold is core's course-section toggle. Consecutive
  courses are joined by grey connectors in the trail's order (an elbow, a drop on an overlap, a detour for one that
  starts earlier), broken by a course with no dates, which says "No dates". Rows are 52px. New strings:
  `calendar_trailmonths`, `calendar_trailnodates`, `calendar_trailshow`; `calendar_trailnext` is gone and
  `calendar_trailsempty` reads "No trail runs in these months."
- **The compact navbar keeps its mode after a resize** (2026100585). Between md and xl the navbar could flip between
  compact and full on every frame once the window was resized (the calendar page at 1089px after 700 and 1300): the
  compact layout gives the primary navigation more room than the full bar where the colour mode menu moves into the
  drawer, so the navigation fitted while compact, the module showed the full bar, which overflowed again. The module
  no longer leaves the compact mode on a split made while compact: the bar stays compact up to the widest width an
  overflow was seen at, and leaves it on a wider window or outside the range. A Behat scenario resizes through narrow,
  wide and medium and asserts the mode holds and the menu button agrees with it; two mutation gates hold the guard.
- **The course dialogue's close button is white on its dark disc again** (2026100585). Core 5.3's Bootstrap (5.3.4)
  reads the close button's filter from `--bs-btn-close-filter`, set by its `btn-close-white()` mixin, and no longer
  declares the `--bs-btn-close-white-filter` the showcase's dialogue read, so its cross was drawn dark on the dark disc
  over the cover. A test reads core's Bootstrap for both names.
- **Audit of 2026-11-04** (2026100583). A logged-in user whom the theme would keep off an unlisted course now gets
  the same "not available" page for a course id or shortname that does not exist (`/enrol/index.php`,
  `/course/info.php`), so the answer no longer tells an unlisted course from no course. The crests answered to
  block_compass's category hook now pass local_unlistedcourses' category discoverability as well as core's
  visibility. Comments no longer point at development notes that do not ship, nor carry decision ids, dates or
  measurements; eleven stylesheets gain the GPL header; Mustache example contexts match their templates. The
  plugins-directory readiness test is back in step with the fleet template, and a new test holds the language
  strings the settings build from a key.
- **Calendar R3b: two fixes from R3's first live look** (2026100582), `docs/calendar/PROPOSAL.md` section 33. A scroller
  arrow at its end is hidden, so it no longer covers the first or the last day's header, and the last column's chip
  ("→ 12 dez.") fits its 76px column instead of ending in an ellipsis.
- **Calendar R3: the mockup's look, and the Trilhas view's days scroller** (2026100581), `docs/calendar/PROPOSAL.md` section 33.
  The Trilhas view on a desktop is the Design canvas's CalTrilhas: a 12px card, a 340px trail column with a fold per
  trail and its "Enrolled · 2 of 5 done" line, numbered steps in their state's colours, days of a fixed 48px with the
  weekday's letter, today as the filled pill and a line down the rows, weekends hatched, the trail's own span, a diamond
  on an expected date, a chip in the "Dec →" column for a step that runs on, and a legend. Only the days scroll, in one
  scroller drawn as block_compass's calendar strip: no scrollbar, round arrows that page by the days in view, a fade on
  a side with more days, and under the grid a map whose thumb is the days in view (a press jumps, the thumb drags);
  it opens with today in view. The trail choice is a button with the trail's icon, "Only mine" a toggle button, and the
  row ends with "1 trail · 5 courses". The step popover has a band in its category's colours, the state as a pill, the
  trail's icon on "Course 3 of 5 · trail", and "View trail" before "Open course". On a phone each trail is a card with
  one progress segment per step, steps with their date, a colour bar and the state's pill, and "Today" with its date.
  Everywhere in the calendar the buttons are the mockup's (36px, 8px round, white on the default border, the primary
  navy), the event types menu has 40px rows and 18px boxes, the side drawer is sectioned under a 20px title, the
  popover is 360px with an 18px title and the description in a quiet box, the panels' fields are 36px, and on a phone
  the arrows are 44px, the view switch spans the row and the filters are pills. New strings: `calendar_trailcountmany`,
  `calendar_trailcountone`, `calendar_trailearlier`, `calendar_trailenrolled`, `calendar_trailheading`,
  `calendar_traillater`, `calendar_trailnotenrolled`, `calendar_trailtoggle`.
- **Calendar R2: the owner's answers to section 26** (2026100580), `docs/calendar/PROPOSAL.md` section 32. The
  visitor's names and locations run core's multilang filter alone while it is on for the site, so a name in two
  languages reads in one, as on the logged-in page. A visitor's window answers at most 200 category events, earliest
  first, and says "There are more events than the calendar shows." past them. Core's "Go to calendar..." no longer
  sits under the theme's upcoming block, which already ends with its "Full calendar". A visitor's public category page
  offers "Calendar" in its hero while the public calendar is on. In dark mode the calendar's cards, bars and times are
  no longer underlined by core's link rule (the description's prose links still are), and the popover names a course
  once: an item with no location says no "Where". The public event courses already followed the course rule (a public
  course in an unlisted category asks for a login): pinned, unchanged.
- **Trilhas view: two layout fixes** (2026100579). The state pill in a step's popover keeps its own width instead of
  stretching across the popover, and on a phone the trail's heading stacks its name over its progress instead of
  running the two together ("Trail0 of 3 completed").
- **Boost Union 5.3 is released** (no version bump). Boost Union published v5.3-r1 on its new `MOODLE_503_STABLE` branch
  and deleted `upgrade-503`, which CI checked out as the parent theme: the 5.3 job now pins `MOODLE_503_STABLE`, and the
  README and CLAUDE.md name that branch. The release differs from the last `upgrade-503` head (143e7497) only in when
  the smart menus kept outside the "More" menu collapse into it (last, instead of never); no file of this theme changes.
- **The calendar's Trilhas view, stage T3** (2026100578), `docs/trail/PLAN.md` section 7. A fourth view, "Trails"
  ("Trilhas"), beside Month, Week and Day, for an account while mod_subcourse is enabled: each trail the showcase would
  list to the account whose period meets the month, its steps under it (section 6's account rule) as bars from start to
  end, a milestone on an expected completion date, a bar that runs on continued in a "next month" column, today marked
  down the rows; a learner's bars carry their states and the trail counts what is done. "Trail" keeps one trail, "Only
  mine" the account's own, the category picker narrows the trail course; the event types and their colours stay out of
  the view. A step opens a popover (its category, "Course 3 of 5 · trail", its dates and where they come from, its state,
  core's availability text when locked, "Open course" and "View trail"); on a phone the view is an agenda with a "Today"
  line. One new service, `theme_dimensions_get_calendar_trails`, whose statements do not grow with the trails. The
  landing page's card gains "View in the calendar" for an account, on the month the trail is in.
- **A trail's next course that has not begun says when it starts** (2026100577), `docs/trail/PLAN.md` section 3. A
  learner's current step whose course starts later reads "Starts on <date>" in place of "In progress", and its line
  counts no days left until it begins; "Continue" still leads to it. The owner's call of 2026-11-03.
- **A trail's progress, stage T2** (2026100576), `docs/trail/PLAN.md` section 3. A learner enrolled in the trail course
  reads each course's state on its card: done (by the activity's completion wherever it tracks one, else by the
  learner's own completion of the referenced course), locked (core's availability, its reason leading the card's line),
  late (its end passed), current (the first open step) or up next. The hero counts the courses done ("2 of 5 courses
  completed") and says when the trail is due, its segments filled by state; the card says "You are enrolled in this
  trail" and continues at the next step's activity ("Continue: <course>", "Continue" on the phone's bar). At most two
  statements, constant in the number of steps. The trail's period no longer ends before a step that opens and never
  closes: such a step leaves it open ("From <first start>"), the owner's rule of 2026-11-03.
- **A trail's landing page, stage T1** (2026100575), `docs/trail/PLAN.md` sections 5 and 6. A course of the Trail model
  says its courses in the hero's glass (how many, their period, one segment each) and draws them in order after the
  summary: numbered cards with the referenced course's cover, category, hours and dates (the activity's expected
  completion date, else the course's own). Who sees which step is `local\trail\display`, as the owner ruled on
  2026-11-02: an account sees what the showcase would list to it (an unlisted course only where it is enrolled or
  may enrol), never a ghost; a visitor of a public trail sees the public courses in full and a listed course as a
  ghost card that names nothing; a step its restriction hides is nobody's. The card adds the number of courses and
  the period. Constant in the number of steps. Progress and "Continuar" are T2; "Ver no calendário" comes with the
  calendar's Trilhas view (T3).
- **Calendar R1d: no desktop flash on a phone's first paint** (2026100574), `docs/calendar/PROPOSAL.md` section 31.6.
  The first render guesses the phone layout from a media query (`max-width: 591px`, the phone width plus the page's
  gutters) instead of waiting for the first measure, so a phone no longer first draws the desktop month and drawer, and
  a `view=day` address opens the Day view at once; the measure of the calendar's frame still decides and replaces the
  guess. The public calendar too. Needs a purge for the ESM build.
- **Calendar R1d: the Day view on a phone** (2026100574), `docs/calendar/PROPOSAL.md` section 31. On a phone (the
  calendar's frame narrower than 576px) the view switch offers Month / Week / Day: the Day view is one day at the full
  width, its name and date in the toolbar's caption, whose arrows and "Today" move it a day at a time; under it the
  drawer's own body (the all-day items, then the whole day's hour column in its scroll box, opened on the earliest item
  or centred on the working hours), an item opening its popover as a bottom sheet, and, while "New event" is offered, the
  "+" button, a double-tap or a touch held on an hour opening core's form at that hour. The address is
  `view=day&day=YYYY-MM-DD` (navigation, never saved); a malformed or out-of-years day shows the month's opening day.
  A desktop never shows a day view: such an address opens the month with that day's drawer, and a phone turned wide
  does the same. The server answers `view=day` with the day's month grid (so the phone's first paint asks for nothing);
  the Day view's arrows ask for one day. The public calendar gets the same. New strings `calendar_nextday`,
  `calendar_prevday`, `calendar_viewday`. No swipe between days. PHPUnit, Jest, two Behat scenarios and new mutation
  gates (R1d block). Needs a purge for the stylesheet and the ESM build.
- **Calendar R1c: the owner's second look** (2026100574), `docs/calendar/PROPOSAL.md` section 30. Fixed a freeze of the week view
  (an endless grid/agenda flip): the week read the width of its own drawer-narrowed box for the phone decision; it now reads
  the frame's alone, and the grids report their height only when it changes (section 30.7). The day drawer's and
  the week's hour column is the whole day (00:00-24:00) in a scroll box of its own, opening on the day's items (or on
  the working hours of an empty day), with no native scrollbar and a fade at an end while it can still scroll there
  (block_compass's calendar strip); this reverses C3's windowed column. "More filters" starts with every model and
  modality checked, as the event types do: unchecking narrows, checking everything again saves "all", and the badge
  counts only what narrows. An event course's popover offers a button named after the course's model ("Event page",
  "Course page", or any other model's own option). The event types' colours are settings on the Calendar tab (one
  hexadecimal picker per type, defaulting to today's colours), from which the stylesheet derives a fill and a text
  colour readable in both colour modes; the calendar page, its blocks and the public calendar follow. New strings
  `calendar_eventpage`, `calendar_hoursbox`, `calendar_modelpage`, `calendarcolours(_desc)` and
  `calendarcolour<type>(_desc)` for the nine types (and `calendarcolourpicker`). The colour setting is drawn as a hex
  field behind a clickable swatch that opens the browser's own colour input (a Mustache template, `amd/src/keycolour.js`),
  not core's gradient picker; the validation is unchanged. PHPUnit, Jest, Behat and new mutation gates (R1c block). Needs an
  upgrade for the settings' defaults and a purge for the stylesheet.
  The day drawer beside the grid is never taller than the grid (nor than the viewport): the grids' observer writes the
  grid's height to the calendar, which renders it on the frame as a style prop it owns (`--dim-cal-gridheight`, so a re-render never drops it) and the drawer's cap takes the smaller of the two (section 30.7). The hour box re-places its opening's position when that
  height arrives after the first placing (an empty day opened by the address ends centred), until the reader's first
  wheel, touch or key on the box.
- **The landing page in the brand's colour, its sections in a panel, and a way in for managers** (2026100572).
  The hero, the section titles and the checks are drawn in the theme's primary colour (Boost Union's brandcolor, or the
  flavour's): a colour on whose lightest stop white text reads under 4.5:1 is shaded in 4% steps until it does,
  instead of falling back to the design system's navy (Boost's default blue moves one step). The summary and a new
  panel take the hero's inline padding, so every text starts where the course title does (2.5rem from md up, 1.75rem
  on a phone); the sections after the summary (target audience, programme, certification) share that panel, in a soft
  border of the brand colour, with bullet lists drawn as a checklist (a check in the brand's ink; lifted to read on
  the dark card in the dark mode). A reader who manages the course without an enrolment (core's
  `moodle/course:view` or `moodle/course:update`, and let in by `can_access_course()`) gets "Access the course" (or
  "Access the event") under the enrolment, with a note that only managers see it. Links and buttons keep Boost
  Union's `linkcolor` and `buttonbrandcolor`. New strings `landing_manage_course`, `landing_manage_event`,
  `landing_managenote_course`, `landing_managenote_event`. PHPUnit, a Behat smoke and mutation gates
  (`landing_ink_dark`, `landing_panel_ink`, `landing_panel_wrap`, `landing_manage_*`; `landing_ground_floor` and
  `landing_ground_brand` rewritten).
- **The course landing page carries no block region and no course index** (2026100571). Both entries of the page,
  the public page a visitor is served and the page a logged-in reader gets, draw no block, no block drawer or toggle,
  no "Add a block" button in edit mode and no course index drawer, decided on the server in `request::handle()`: the
  calendar's mechanism (no block loaded, block editing locked), now one helper both pages call
  (`local\blockless::apply()`), and core's own switch for the index (`$PAGE->set_show_course_index(false)`, the first
  thing `core_course_drawer()` reads). The guest entry's interstitial needed nothing: the embedded layout draws fake
  blocks alone and no drawer. PHPUnit (`request_drawers_test`, each case against a control page left to core), a
  Behat smoke (the course page as the control) and mutation gates (`landing_blocks_wired`,
  `landing_courseindex_shown`, `cal_r1b_blocks_wired`; `cal_r1b_blocks_shown` and `cal_r1b_blocks_unlocked` moved to
  the shared helper).
- **The Trail page model, stage T0** (2026100569), `docs/trail/PLAN.md`. The page model select gains a third line,
  *Trail* (`landingfields::MODEL_TRAIL`), appended by the branch's first upgrade step on a site provisioned with two
  lines (`landingfields::add_trail_option()`). A trail's steps are its Subcourse activities, read by one reader for
  the landing page and the calendar (`local\trail\trail`, `local\trail\step`): course order, the window from the
  referenced course's start date to the activity's expected completion date or the course's end date, one statement
  for the references of any number of trails. mod_subcourse is optional: without it the Trail line is left out of the
  course form, the default for new courses and the calendar's model filter. The showcase's cards tag a trail as one.
  Nothing draws a trail yet (T1).
- **Calendar R1b: the category events you create, and the first paint** (2026100568),
  `docs/calendar/PROPOSAL.md` section 29. The site view's categories include every category where the viewer may
  create category events (core's form asks `moodle/category:manage` with doanything; core's view asks it without, so
  an administrator with no role in a category never saw the events they created). Core's `calendar_categories` session
  cache is no longer read by the theme (it holds core's narrower answer). The calendar page no longer flashes the
  no-JavaScript list before the grid: under core's `body.jsenabled` the server's layer is replaced by a placeholder
  with the grid's footprint until the client marks the root `dim-cal--mounted`, and the list comes back by itself
  after four seconds if the client never mounts. The gap between the navbar and the calendar's title is halved (83px to
  41px at 1440x900, measured, section 29.3), and the page carries no block region, block drawer or "Add a block" button
  (section 29.4: no block loaded, block editing locked, on both the account and the visitor page).
  PHPUnit, Jest and new mutation gates (R1b block).
- **The calendar after the owner's review of 2026-11-02** (2026100567), round R1 of `docs/calendar/PROPOSAL.md` (sections 27-28).
  The "Classic calendar" links are gone, with the `classic=1` marker and its session flag: every view of core's
  `calendar/view.php` now leads a real account to the theme's page (the day view to that day's drawer, a course
  calendar to the course's category when the reader may open the course). Core's iCal feed
  (`calendar/export_execute.php`) is answered by the theme's copy of it (`local\calendar\ical`, tracked by a whole-file
  snapshot) under the same setting, which leaves out the events of the categories the page withholds. The page takes
  the whole width; weekends carry a hatch; the day drawer holds still beside the grid under the navbar (it sat above
  the viewport when a day of the lower weeks was opened); the event types are one dropdown of checkboxes; "Clear
  filters" moved to the toolbar and resets the types, the filters and the category scope; a click anywhere on a day
  opens it (the day under the mouse is marked), and a double-click on a day or an hour opens core's form where "New
  event" is offered; a personal event's popover offers no "Open" to the
  Dashboard; an event course's popover opens with its cover and a date tile. The three calendar settings moved to
  their own "Calendar" tab. New strings `calendar_eventtypes*`, `calendar_selectall`, `calendartab`; strings
  `calendar_classic` and `calendar_classicday` removed. PHPUnit, Jest, Behat and new mutation gates (R1 block).
- **Behat beside tool_mulib**: `tests/behat/blacklist.json` keeps tool_mulib's file upload context out of the
  theme's suite, whose upload steps it would define a second time (every scenario failed before its first step on a
  site with tool_mulib 5.3 installed). Tests only; no version change.
- **tool_courserating's ratings on the showcase** (2026100566), stage R1 of `docs/vitrine-ratings/PROPOSAL.md`
  (card variant A, the dialogue's rating block, decision 4). The plugin stays optional, asked by class name: absent,
  or rating no course anywhere, nothing of it is read. Present, the page reads its summary table once, over the page's
  ids (`cards::ratings()`: `ratingmode <> RATEBY_NOONE AND cntall > 0`, the plugin's own rule for showing a rating),
  and a rated card opens its meta line with an amber star, the average to one decimal in the reader's language and the
  count, with one sentence for a screen reader; on both shells, as the plugin shows a rating to whoever may see the
  course listed. The rows answers gain `rating` and `hasrating`. For an account, `theme_dimensions_get_course_dialogue`
  also returns the course's rating block (the average, five stars, the count, the distribution as `<progress>` rows,
  and, where the plugin lets the viewer read reviews, its own trigger of its reviews popup, on whose press the
  theme's dialogue closes). New token `--dim-ds-accent-rating`, derived from the palette (3.9:1 on the white card,
  3:1 on a dark surface). New strings `category_rating_*`. tool_courserating joins `ci.yml`'s
  `plugin-dependencies` (`MOODLE_405_STABLE`, which supports 5.3). PHPUnit, Behat (a rated course on the card and in
  the dialogue) and new mutation gates.
- **The showcase card's "More information" is a link, and its dialogue is fetched when it opens** (2026100565), stage
  L2 of `docs/vitrine-ratings/PROPOSAL.md` (decisions V1, open at once, buttons on the card). The card no longer
  draws its details panel nor an inert copy of its dialogue: "More information" links to the card's own page (the
  course, the landing page or the enrolment page; no link where the card has none), which is the whole of it without
  JavaScript, for the guest and for a visitor. For a real account `categorycoursemodal` opens the dialogue at once
  with the cover, the pill, the title and the two buttons the card holds (the page's decision, written onto the
  link), shows a skeleton, and fills it from the new account-only web service `theme_dimensions_get_course_dialogue`
  (one course per call; the excerpt, the facts, the programme; the guest refused before any lookup, discoverability
  as `enrichment::details()` asks it, one refusal for every miss). The request starts on intent (180 ms of pointer or
  focus on the link) and each answer is kept for the page's life; a failure keeps the header and the buttons and
  says so in one sentence. The page no longer reads the course contacts, the summary, the venue or the programme,
  and the rows answers lose `detailsid`, `excerpt`, `facts`, `learn` and `learnlabel`. The visitor's page no longer
  loads the dialogue's module. New string `category_modal_failed`. PHPUnit (a new service test), Jest, Behat (the
  dialogue scenario and a no-JavaScript link scenario), 12 new mutation gates, 3 retired.
- **The category showcase card has no hover peek** (2026100564), stage L1 of `docs/vitrine-ratings/PROPOSAL.md`. The
  copy of the card's details panel that opened beside a card after 180 ms of hover is gone, with its timer, its
  styles and the `dim-card--peeking` class. The "More information" disclosure and the course dialogue are
  unchanged, and the cover film keeps its own 180 ms intent delay. Jest and the source tests no longer pin the
  peek; the `js_mini_no_peek` gate keeps its name and now guards the observer.
- **Create, edit, delete and move events from the theme's calendar** (2026100563), stage C5 of
  `docs/calendar/PROPOSAL.md`. Everything stays core's: "New event" opens core's event form (in the toolbar, and in
  the drawer for its day), shown where core's month view shows it (`calendar_user_can_add_event()`); the popover's
  Edit and Delete appear for the events core's exporter marks editable and deletable, Delete through core's own
  confirmation; an event core marks draggable moves to another day by drag on the month grid and the week's all-day
  strip, through `core_calendar_update_event_start_day`, inside core's own limits. The page refreshes its window
  after each change. An iCal link to core's export appears where core's calendar footer offers it. The public
  calendar has none of it. The week's history step now falls back to the week the server would show.
  PHPUnit (every flag checked against core's own month export), Jest, Behat, 40 mutation gates.
- **The calendar's week view** (2026100562), stage C4 of `docs/calendar/PROPOSAL.md` (decision D4). The view switch
  offers Month and Week; `view=week` and `week=YYYY-MM-DD` travel in the address as navigation, never as saved state,
  and any real day names the week holding it on the viewer's first weekday. The week is seven day columns with an
  all-day strip (multi-day bars, event courses), an hour body laid out like the drawer's (overlapping events side by
  side, windowed to the week's items, no inner scroll) and a line at now; on a phone it is a seven-day agenda. The
  public calendar has it too. A list in the address (`?month[]=`, `?view[]=`, `?model[][]=` and the rest) is now
  dropped like any value outside its vocabulary instead of answering an error page, on the page and in the saved
  state. Four mutation gates that no longer matched their code are re-pointed. PHPUnit, Jest, two Behat scenarios,
  42 mutation gates.
- **A public calendar of public categories** (2026100561), stage CV of `docs/calendar/PROPOSAL.md`. With the new
  setting `theme_dimensions/calendarpublic` (off by default) a visitor who is not logged in opens the theme's calendar
  page in a visitor mode: the category events of categories local_unlistedcourses serves anonymously (every ancestor
  visible, none unlisted) and the public event courses, whose "Inscrever-se" leads to the public landing page.
  Descriptions travel as plain text; no creator, participant or image is answered. A new service,
  `theme_dimensions_get_public_calendar_range`, answers the month through `service-nologin.php` in the order of the
  showcase's visitor service: it refuses a session (the guest included), a cache key and a second call, and speaks
  the language it is sent. The page is `noindex` unless the site is open to search engines. PHPUnit, Jest, Behat as
  a visitor, 57 mutation gates.
- **Core's calendar blocks drawn with the theme's calendar** (2026100560), stage C3b of `docs/calendar/PROPOSAL.md`.
  `theme_dimensions\output\core_calendar_renderer` replaces the content of `block_calendar_month` (a mini month with
  dots per key; a day opens the page's drawer, the arrows move the month in place through the range service) and
  `block_calendar_upcoming` (the next events by core's own lookahead and count preferences, event courses included,
  ending in "Full calendar"), drawn by the server from the range classes, so the blocks show only what the page would
  show the reader and work without JavaScript. Core's output stays on a course page, for a visitor or the guest, on a
  non-Gregorian calendar and with the new setting `theme_dimensions/calendarblocks` (on by default) switched off.
  The README says what a renderer cannot reach (the app, the iCal export, block_timeline). PHPUnit, Jest, two Behat
  scenarios, 28 mutation gates.
- **Core's calendar month and day views lead to the theme's calendar page** (2026100559), stage C3r of
  `docs/calendar/PROPOSAL.md` (decision D1). `calendar/view.php?view=month|day` answers a 303 to the page with the same
  month, day (the drawer opens on it) and category, decided in the `after_config` callback before core's view does
  its work. Core keeps its upcoming view, course calendars, visitors and the guest, non-Gregorian calendar types and
  any site whose theme is another one. The page's "Classic calendar" links carry `classic=1`, which keeps that
  account on core's calendar for the session until it opens the page again. A new setting,
  `theme_dimensions/calendarcoreview` (on by default), is the site's way back. PHPUnit, Jest, four Behat scenarios,
  21 mutation gates.
- **The current page's row in the navbar's "More" menu reads on the dark menu** (2026100558). Core's
  `moremenu.scss` paints that row (`.active` or `aria-current`, in the React mount and the legacy markup alike) in
  `--mds-text-default` or `$dropdown-link-color`, dark inks for a light menu, so on the theme's dark surface the
  row and its tick were dark on dark, and under the pointer its pill took Bootstrap's compiled pair instead of the
  flavour's. The row keeps core's clear ground and tick in the menu's own ink, and takes the pill every other row
  takes under the pointer or the keyboard. Pinned in `post_scss_test`, against core's rule as the control.
- **The calendar's day drawer and event popover** (2026100557), stage C3 of `docs/calendar/PROPOSAL.md`. A day
  opens a drawer beside the month (an agenda sheet under it on a phone, where the month shows one dot per key) with
  an all-day strip and an hour column that lays overlapping events side by side. An event opens a popover (a sheet
  on a phone) with its kind, time in the viewer's timezone, place, modality, a warning naming the events it
  overlaps, its course, its description and its actions: core's action when it has one, joining for an event
  course, else Open. The description comes from core's `core_calendar_get_calendar_event_by_id` when the popover
  opens, once per event. The open day is kept in the address (`day=`, only inside the month's grid), so a reload
  reopens the drawer. Answer addresses reach a link only when they are http(s) or relative. The links C2 drew stay
  as the fallback. PHPUnit, Jest, four Behat scenarios, 18 mutation gates.
- **Navbar menus: rows apart, a rounded open toggle, a readable submenu in "More"** (CSS only, no version bump).
  The rows of the navbar's dropdowns stand 2px apart, so the chosen row and the hovered one no longer read as one
  block. The user, language and colour mode toggles lose the white square an open Bootstrap button drew round them
  (Moodle's `.btn` never sets `--bs-btn-active-border-color`, so the border fell back to the ink) and show a rounded
  pill in the navbar pills' hover tint, with the keyboard's ring round it. A smart menu collapsed into "More" takes
  the menu's ground instead of core's gray-100 (white ink on a light row) and its toggle's width, and core's square
  focus box-shadow on a clicked `a.dropdown-toggle` gives way to the row's pill, with the theme's ring restored for
  the keyboard. The user menu's "Log out" is a red pill with core's logout glyph at its end: the design system's
  reds, darkened at rest and brighter under the pointer or the keyboard, each moved by the menu's own functions until
  the label reads 4.6:1 (measured 9.8:1 and 5.3:1). Pinned in `post_scss_test` and `navbar_menu_colour_test`.
- **The user menu's Messages entry opens the messaging drawer on a phone again** (2026100556). The entry clicked the
  hidden navbar toggle while its own click was still bubbling, and core_message's drawer, which closes on any
  activation outside itself and its toggle, closed again on that same click. The entry now opens the drawer once its
  click has finished.
- **The calendar page** (2026100555), stage C2 of `docs/calendar/PROPOSAL.md`. A page of the theme's own
  (`/theme/dimensions/calendar.php`, answered by a route where the router is on) draws the month on the range
  service: events spanning several days as bars that continue into the next week, a day cell that adapts how much it
  shows to the space it has, key chips, the category tree (the whole subtree), and "More filters" (model, modality,
  city, only things to do). The first paint carries the month's data; without JavaScript the page shows the month as
  an agenda. The viewer's last keys, category and filters are kept in a seventh user preference,
  `theme_dimensions_calendar`, written through `theme_dimensions_remember_calendar_state`, exported and deleted by
  the privacy provider and removed on uninstall. Full keyboard contract on the grid; a calendar row in the theme's
  drawer. Core's calendar is untouched (decision D1). PHPUnit, Jest, six Behat scenarios, 27 mutation gates.
- **block_compass's open-for-enrolment line names each category's crest and tells courses from events**
  (2026100554; block_compass ADR-019, stage S4). Two listeners on hooks the block added in its 2026100524.
  - **`category_badges`** answers each category the line names with the category's first crest, in area order
    with gaps closed, and its file name as the text. It answers only categories the reader may see, by core's
    `core_course_category::can_view_category()`. It keeps a small entry per category (visible flag, context record,
    first crest) in the `categorybranding` cache under `compasscrest_<id>`, so `branding::forget_all()` drops it
    with the branding. A warm answer reads nothing; the misses are read in one statement.
  - **`course_kinds`** names the enrolment page model field (`dimensions_model`), with course for 1 and event
    for 2. The nouns come from four new strings (`compass_kind_course(s)`, `compass_kind_event(s)`). It reads
    nothing, and the block uses the field only while it is visible to everyone, as the theme creates it.
  - Both are registered in `db/hooks.php` as optional, as the two earlier block_compass listeners are.
    PHPUnit under `tests/local/hook/block_compass/`. Behat: `theme_dimensions_compass.feature` (`@block_compass`)
    draws the line in a browser with a crested and a plain category, a course, an event and the pill's tooltip,
    under axe, through a new step that gives a category a crest.
- **The calendar's range service** (2026100552), stage C1 of `docs/calendar/PROPOSAL.md`.
  `theme_dimensions_get_calendar_range` answers one month grid (at most 42 days, in the viewer's timezone) in one call:
  core's events, with the exact visibility of core's month view (a parity test per role), each event once with its
  span; the event courses the viewer is enrolled in; and the event courses they could join now, admitted by the
  showcase's own decision layer and local_unlistedcourses' next action. Courses and categories come once each, with no
  image data URI, no formatted HTML and no description; day counts per key. Narrowing by a category's whole subtree or
  by one course, keys that spare the vault where they can, and model, modality, city and "only things to do" filters.
  One deliberate difference from core: a category event of a category local_unlistedcourses withholds is dropped. For
  a student with 25 courses: 531 statements, about 140 ms and 109 KB, against core's month service at 675, 1.2 s and
  11.5 MB. Nothing on a page uses it yet (C2). PHPUnit under `tests/local/calendar/`, 35 mutation gates.
- **The filter line reads less layout** (2026100551). An answer that changes a count without changing its number of
  digits no longer remeasures the line (every count is drawn in tabular figures, so the widths cannot move; the star's
  count, visually hidden, no longer counts at all), and the open suggestion list is placed when it opens or when the
  fold, the search or the width moves, not on every key. The date picker makes one `Intl.DateTimeFormat` per language
  and options instead of one per day drawn, and the linear navigation footer reads no layout on a scroll while the
  footer is already shown. Three Jest tests and one source test assertion.
- **A page of cards is announced to core once** (2026100550). The listing inserted each card it rendered on its own,
  and every insertion fires core's filter event, which Boost answers (`enableTooltips()`) with a `document` keydown
  listener it never removes: about 14 permanent listeners a filter press or a load more, each run by every Escape.
  `renderCards()` now inserts the page's cards in one call into a holder the grid takes them from, as the panel's
  transplant already did. Two Jest tests.
- **The look choice page lists the looks evenly** (2026100549). The form group separated its radios with `<br>`, which core's
  flex row reads as an empty item: after a label wide enough to fill the row it wrapped onto a line of its own, a blank
  line between two looks. The group has no separator now, and `scss/look.scss` stacks the radios on that page alone.
  One PHPUnit test per half, two mutation gates.
- **No line under the navbar's band.** Core draws a 1px gray-300 `border-bottom` on `.navbar.fixed-top`, which Boost
  Union only recolours (to the bar's colour, in the light mode alone), so it showed as one more pixel under the 3px
  band. The theme sets it to 0 and keeps core's 61px. One test in `post_scss_test`.
- **The navbar's dropdowns are a dark slate with the brand colour on the row under the pointer.** Every
  `.navbar .dropdown-menu` (the user menu and its submenus, the language and look menus, smart menus in the bar,
  focus mode's "More") is one dark surface in both colour modes, inset by 8px, with 12px corners; the hovered,
  focused or current row is an 8px-rounded pill in `$primary`, so each Boost Union flavour paints its own. The pill
  is lifted until it stands out from the surface (3:1) and moved away from its ink until the label reads 4.6:1; the
  ink is white or the darkest grey. A submenu's title row (the Look and Language panels) was dark on the menu: core's
  back button reads the body's ink through `.text-body` and Boost Union's smart-menu header is a link, and both now
  read the menu's; the back arrow, an image of core's icon, is inverted to white and centred on the title, and the
  row takes one pill, never a second one on the button. Tests `navbar_menu_colour_test` (five brand colours, measured) and two in `post_scss_test`.
- **A drawer sub-panel opens where it should on a phone** (2026100548). Opening the Look or the language panel moved
  the focus to the panel's back control while the panel was still sliding in from the right, and the browser scrolled
  the drawer's viewport (overflow hidden is still a scroll container) to reveal it: the panel was drawn about 213px to
  the left, a sliver of it in view, for as long as a third panel kept the viewport wider than itself. `amd/src/drawer.js`
  now focuses with `preventScroll`; the way back keeps its scrolling focus (the parent comes in from the left, where the
  viewport cannot scroll, and a row far down the parent is still brought into view). A Behat step reads the panel's box
  after the slide, the drawer scenario gains a third panel, and one mutation gate holds the call.
- **The look menus can show each look's favicon** (2026100547). The new setting `theme_dimensions/lookmenufavicons`
  (General settings, *Look*, off by default) puts a mark before each name in the user menu's Look submenu, the drawer's
  Look panel and the choice page: the Boost Union flavour's favicon, else a dot in its brand colour, else an empty box of
  the same size; the site default shows the site's favicon. The image address is the one Boost Union puts in the browser
  tab of the flavour's pages, so the browser serves it from its cache, and it is fetched lazily. The marks ride in the
  `flavourmenu` cache beside the titles, so the menu still reads no table per page. Template `theme_dimensions/look_mark`,
  stylesheet `scss/look.scss`.
- **The course notice no longer names an unlisted category to a viewer who may not discover it** (SEC-03).
  `coursenotice_unlistedcategorytext` printed the category's name to a teacher of the course alone, against the
  theme's own policy for category names. Without the right to discover the category the note now reads the new
  `coursenotice_unlistedcategoryanon`. The hidden-category note is unchanged: hiding cascades. One test, one mutation gate.
- **The editor no longer names an unlisted colour source** (SEC-02). The sentence under "own colours" printed the name of
  the category the colours come from whenever core's visibility allowed, even when `local_unlistedcourses` withholds that
  category from the viewer. It now falls back to the generic sentence there too. One test, one mutation gate.
- **The category appearance page answers a missing id and a forbidden one alike** (SEC-01). `categoryhero.php` threw
  `unknowncategory` for an unused id and `nopermissions` for an existing one, an existence oracle. Both now raise the
  theme's `unavailable_exception` before the category is loaded, from `categoryhero::editable_category()`; every manager
  passes as before. Three tests, three mutation gates.
- **The category tree counts only the courses the viewer may discover** (SEC-04). `coursecat_category()` decided the
  expand arrow from `get_courses_count()`, and in COUNT mode core printed that raw number as "(N)" beside the name
  (the front page's category list; Boost Union a bare badge under its `categorylistingpresentation` setting), so
  both told an outsider that unlisted courses exist. The node now reads the filtered direct courses once, only when
  one of the two branches needs it, rewrites the number in either spelling and drops the span at zero as core does.
  Two tests, four mutation gates.
- **Category branding images are always sent cacheability private** (2026100546; SEC-06). The serve path chose public for a
  visitor, but the URL carries only the content hash, which does not change when a category stops being public, so a
  shared proxy kept serving the image for the whole hashed lifetime. `branding_test::test_the_image_is_always_sent_private`
  reads the route's source. One mutation gate.

- **The card's star joins the listing store** (2026100545; R5 of `docs/vitrine-react/PLAN.md`, the code part).
  `amd/src/categorystar.js` is now `js/esm/src/listing/star.ts`, rule for rule: one delegated listener armed once and
  its own `dim-star-js` mark, an optimistic flip, one round trip at a time, `theme_dimensions_toggle_wishlist` through
  `@moodle/lms/core/ajax`, the answer's `favourite` painted, a refusal put back with core's exception dialogue, and no
  refresh of the favourites count. The account's shell arms it first in `start()`, whatever the page draws, and
  `amd/src/listingaccount.js` only starts that shell; the visitor's page still never loads it. Dead hooks cleaned up:
  the barrel `listing.ts` no longer re-exports four names no shell calls (`SCOPE`, `chipOf`, `favOf`, `LINE`), and the
  templates no longer write four data-region hooks nothing reads (`dim-card-crests`, `dim-card-opens`, `dim-hero`,
  `dim-panel-tile`). No behaviour changes. A source test and three Jest tests of the star; 5 mutation gates
  retargeted.

- **The old AMD listing is removed** (2026100544; R4c of `docs/vitrine-react/PLAN.md`). The ten modules the
  listing store and the React components replaced, imported by nothing since R4b3, are deleted with their builds:
  `categorylisting`, `categorypublic`, `categoryvitrine`, `categorypanel`, `categoryline`, `categorypreview`,
  `categoryareapanel`, `categoryposters`, `daterange` and `categorymark`. No behaviour changes: every class and lang
  string they wrote is still read by the ES port. Their source tests went with them or now read the TypeScript
  (`amd_source_test` reads the store; `categorypanel_source_test` is `categorystar_source_test`, the star's alone);
  comments that named them name their successors. 47 mutation gates retired (the 46 on those files and
  `js_areas_no_amd_panel`), 5 retargeted to modules that exist.

- **An account's live listing runs on the listing store** (2026100543; R4b3 of `docs/vitrine-react/R4b.md`).
  `js/esm/src/listing/account.ts` is the logged-in shell the store is handed: the state remembered per category and the
  remembered organisation, the view preference for a real account, the progress of the enrolled courses on screen, the
  hover peek with one panel open at a time, the automatic opening's note, a panel's request and its progress, and core's
  exception dialogue. `course_renderer.php` arms `amd/src/listingaccount.js`, which arms the star and starts the shell
  through `core/esm!`, in place of `categoryvitrine` and `categorypanel`, which are imported by nothing until the
  removal pull request. The store tells its shell what changed before it writes the address, hands it the cards it
  draws and the ones a replaced grid drops, and asks it what to say of a calendar that could not mount (the account's
  shows core's dialogue, as before; the visitor's says nothing), and now handles the favourites toggle; the visitor's
  shell takes the new hooks as no-ops. No behaviour changes on either page. Two Behat scenarios (a remembered bare
  address written out in full in place, Back with a panel open), 11 Jest tests, a source test of the account's shell,
  24 mutation gates retargeted, 10 retired and 7 new.
- **block_compass's open-courses line links to the filtered showcase** (2026100542; stage 14f of block_compass's
  ADR-017). `local\hook\block_compass\category_link` answers the optional `\block_compass\hook\category_link`
  hook with `?categoryid=N&chip=open&from=Y-m-d&to=Y-m-d` for each asked category and the bare course index for the
  root. It answers nothing when the showcase or the state chip is off, or for a visitor or the guest account, whose
  page draws no chips, so the block keeps core's category page. It adds no visibility rule: the page runs its own.
- **The chosen look's remaining points from the security read, each with a test.** Somebody logged in as another
  user is offered no look and `flavourchoice::choose()` refuses them: the choice, and the cohort it moves, are
  the person's. A page another theme draws drops the look this session was primed with
  (`flavourchoice::unprime()`), so a Boost Union page of the same category no longer shows it. The choice page's
  return address is cleaned in one place (`flavourchoice::back_url()`, local only) and loses its session key, in
  the menu's links too, so the redirect after the POST cannot replay an action. The page's one write is
  `flavourchoice::submitted()`, which `tests/local/flavourchoice_guards_test.php` drives with the page's own form:
  a GET writes nothing, a POST without the session key or of a look the form did not offer is refused. Six
  PHPUnit tests, eight mutation gates.
- **The offered-looks setting lists the flavours again.** `settings.php` handed
  `admin_setting_configmulticheckbox` the array callable `[flavourchoice::class, 'choices']`, and core keeps any
  array as the choices themselves, so the page offered two checkboxes labelled with the class name and `choices`
  (ids 0 and 1) instead of the flavours. The choices are a closure now. Tests and Behat wrote the setting directly,
  so none saw it; `flavourchoice_test::test_the_settings_page_offers_the_flavours` reads it from the admin tree.
  One mutation gate.
- **The visitor's live listing runs on the listing store** (2026100541; R4b2 of `docs/vitrine-react/R4b.md`).
  `js/esm/src/listing/store.ts` holds the page's state, every action, the requests with their tickets and the one path
  from an action to the address; `visitor.ts` is the visitor's shell it is handed (its service without a session, its
  fallback, its posters' links and panel questions). `request.php` arms `amd/src/listingvisitor.js`, which reaches the
  shell through `core/esm!`; `categorypublic.js` is imported by nothing until the removal pull request. The store
  reaches no shell, and Back is heard before the posters' component is mounted. No behaviour changes on the visitor's
  page. A repaint in place now holds one pending from its request until its rows are painted, on both shells
  (`theme_dimensions/listing:rows`, `theme_dimensions/categoryvitrine:rows`): core/ajax's token ended with the answer and
  the render's began after the template load, which RequireJS answers after a timer even for a loaded module (every
  repaint since R4b1's `load('core/templates')`), so Behat could count the rows being replaced under it, the flake of
  the visitor's tree scenario. Two Behat scenarios (Back, Load more on the visitor's page), 14 Jest tests, a source test of the store, 29
  mutation gates retargeted and 7 new.
- **The chosen look, three points from the whole-feature security read.** Core's preference services
  (`core_user_set_user_preferences`, `core_user_update_user_preferences`) may no longer write
  `theme_dimensions_flavour`: its permission callback is `flavourchoice::preference_editable()`, which says no,
  so `flavourchoice::choose()` is the one write path and the look cohorts always follow it. The `cohort_deleted`
  observer records each smart menu or item before it hides it, so a failure part way leaves none hidden unrecorded.
  The choice page says so when a look is refused at submission instead of returning without a word. One PHPUnit
  test, one mutation gate.
- **The README describes the flavour stylesheets compiled ahead of time** (S4 of `docs/flavourcss/PLAN.md`):
  the scheduled task, which flavours it compiles, the child process and its PHP, a flavour that does not compile,
  several web servers, the CLI as a deployment step, and what is stored. `docs/flavourchoice/PLAN.md` points at the
  merged warm-up its release waited on.
- **The flavour sheet builder closes the last window of its stale-write guard** (S4's security read of S1-S3). It
  read the revisions before writing; a cache reset landing between that read and the writes would have left the old
  sheet under Boost Union's revision-less MUC key and in the fallback file. `theme_reset_all_caches()` moves the
  revision before it purges, so the builder now reads the revisions after the writes instead and, if they moved,
  removes what it wrote and answers stale; that one check covers a reset during the compile too. 2 new mutation
  gates replace `flavourcss_stale_guard`, whose check they made redundant.
- **The category showcase's shared listing is TypeScript** (2026100539; R4b1 of `docs/vitrine-react/R4b.md`, the first
  of the listing store's three slices). `amd/src/categorylisting.js` moved to `js/esm/src/listing/`: `events.ts` (every
  event between the listing and its components, and the hooks it reads, spelled once), `address.ts` (the state as the
  address spells it), `history.ts` (the one writer of the history), `mounts.ts` (the calendar, the filter line and the
  posters handed to their components) and `paint.ts` (the painters), under the barrel `listing.ts` with
  `categorylisting`'s export names. The three AMD clients (`categoryvitrine`, `categorypublic`, `categorypanel`) load it
  through `core/esm!@moodle/lms/theme_dimensions/listing/listing`, one import line each; `categorylisting.js` stays
  unimported until the removal pull request. The components import their event names from `events.ts`, and the posters'
  component writes its open area through `history.ts`, still replacing. No behaviour changes; the first paint's module
  list is the same. 29 Jest tests, a new `listing_source_test`, 52 mutation gates retargeted to the TypeScript and 8 new.

- **Smart menus by look** (2026100538; S5 of `docs/flavourchoice/PLAN.md`). `local\lookcohorts` keeps one cohort per
  offered look (system context, idnumber `theme_dimensions_look_<flavour id>`, component `theme_dimensions`, which
  makes core's cohort pages refuse to edit, delete or assign it) holding exactly the people who chose it, so Boost
  Union's own cohort rule restricts a smart menu by look. `flavourchoice::choose()` moves the person, read back
  from the stored preference under a per-person lock; the offered-flavours setting's updated callback provisions under a core lock, renames on a title
  change, never claims an idnumber another owner holds, and empties (never deletes) the cohort of a look taken off
  the list, through the adhoc task `empty_look_cohort` above 500 members. The daily scheduled task
  `reconcile_look_cohorts` makes a missing cohort again and puts membership back in step with the preferences. A
  `cohort_deleted` observer at priority 1000, ahead of Boost Union's, hides every smart menu and item a deleted look
  cohort restricted before Boost Union strips it from the rule (which would show the menu to everyone), and the new
  recommendation `dimensions_lookcohorts` warns until each is shown again. Uninstall releases the cohorts (stamp
  cleared, members kept). The privacy metadata links `core_cohort`. Fourteen PHPUnit tests, twenty-three mutation gates.
- **The flavour stylesheets are compiled before their first visitor, by a scheduled task** (2026100537; S3 of
  `docs/flavourcss/PLAN.md`). `\theme_dimensions\task\warm_flavour_css` runs every minute and hands S2's planner its
  work. That is every sheet of a flavour a page can be served that has no file at the current revisions, built one
  flavour per child process. While every sheet is built, a run is one query and a file check per flavour. Every
  minute, because a theme cache reset has no event to listen to: Boost Union's flavour editor, the theme settings,
  the cache purge and every upgrade reset the caches, and the next run is the soonest the missing sheets are seen.
  2 new mutation gates.
- **Every missing flavour sheet can be built from the command line, each flavour in a process of its own** (S2 of
  `docs/flavourcss/PLAN.md`; the scheduled task that runs it every minute is S3).
  `php theme/dimensions/cli/build_flavour_css.php --all` (with `--dry-run` to list) runs `local\flavourcss\planner`.
  It finds the flavours a page can be served: applied to categories or cohorts with at least one id, or offered by
  `theme_dimensions/selectableflavours`. For each sheet with no localcache file at the current revisions, in each
  direction a language needs (right to left only while one is installed), it starts the same CLI with `--flavourid`
  as a child, one at a time, and the child runs S1's builder. A sheet whose child failed (the builder's exception,
  or a fatal error) is remembered in `theme_dimensions/flavourcss_failed` with the revisions it failed at, and is
  not tried again until a revision moves. The child is started with this process's own PHP, or core's
  `$CFG->pathtophp`; with neither, or with `proc_open()` disabled, nothing is built. 9 new mutation gates.
- **The preferences page links to the choice page** (2026100536; S3 of `docs/flavourchoice/PLAN.md`).
  `theme_dimensions_extend_navigation_user_settings()` adds a Look link to the account section of
  `/user/preferences.php`, for the person's own preferences only, on a page this theme draws and while a look is
  offered; the choice page's form comes back to the preferences page. The theme check is now
  `flavourchoice::drawn_by_this_theme()`, which the prime asks too.
- **The filter line's first measure forces no layout in React's commit** (2026100535; PLAN 5.2.6). The first layout
  effect measured the steps (`contentWidth()`, `stepWidths()`) and placed the layers inside the commit, forcing the
  page's first full layout there (one long frame of 130-150 ms at 4x CPU on the visitor's page, most of it forced
  layout, though the line fitted at the first try). The first measure is now taken at the line's `ResizeObserver`'s
  first entry, which the browser delivers after its own layout and before the paint, with the entry's content box as
  the width: the line is still folded before it is first painted. The layers' placement and the rows' fades, a
  content change and the fonts' remeasure wait for that measure too; a mount again from the kept state gets its own
  first entry. Without a `ResizeObserver` the commit measures as before. `data-line-drawn`, the focus restore and
  `dim-line:mounted` stay in the commit. The line is hidden (`visibility: hidden`, its own inline value given back)
  from the commit to that first fold: a fold in the observer's callback moves nodes the browser has already laid out
  once, which counted as a layout shift (0.027 on the logged-in flat page at 900 px), and hidden nodes count for none,
  while visibility keeps the line's box. A line holding the focus is not hidden, since a hidden ancestor takes the
  focus away. Eight Jest tests, one PHPUnit source assertion, four mutation gates.
- **A flavour's stylesheet can be compiled before its first visitor** (S1 of `docs/flavourcss/PLAN.md`; nothing calls
  it yet). `local\flavourcss\builder` compiles ONE Boost Union flavour in a CLI process and stores it where Boost
  Union's `flavours/styles.php` reads it: the `core/postprocessedcss` entry, the localcache file and the tempdir
  fallback, under the theme's own CSS lock, then removes the older revisions as `styles.php` does. It refuses to
  compile when Boost Union's static flavour record already holds another flavour (measured on m503b: a second flavour
  compiled in the same process came out with the first one's brand colour), discards a sheet whose revision moved
  during the compile (the MUC key carries none), and stores nothing that lacks the theme's own tokens (core falls
  back to the precompiled sheet when the SCSS does not compile). The three `styles.php` helpers it copies have
  snapshots, read from the file's tokens by the new `upstream::FUNCTIONS`. 9 new mutation gates.
- **The Look submenu in the user menu and the mobile drawer, and the choice page** (2026100534; S2 of
  `docs/flavourchoice/PLAN.md`). While `theme_dimensions/selectableflavours` offers a flavour, the user menu of a
  logged-in account gains a Look link before Language (after Preferences where there is no language menu) and a
  submenu built like core's language one: the site default first, then each offered flavour, the one in force
  marked current. The drawer gets the same as a row and a panel. Each item links to `flavour.php` with its flavour
  preselected, which only shows a form (one radio per look, POST with the session key) and writes nothing until it
  is submitted; with JavaScript, `theme_dimensions/flavourchoice` records the choice in place through the new web
  service `theme_dimensions_choose_flavour` and reloads the page. Both go through `flavourchoice::choose()`, which
  accepts the site default or a flavour the setting offers now. The offered titles are cached site-wide (cache
  `flavourmenu`, keyed by the language and the offered ids, emptied by Boost Union's flavour events), so the menu
  costs no statement per page. This replaces the plan's first default (a GET link with the session key): the theme
  never changes state on a GET.
- **The showcase's cost bound no longer flakes on a loaded host** (tests only). `budget_test`'s 0.5 s ceiling
  on one render of two hundred courses failed at 0.508 s and 0.514 s with two `mdl ci --matrix` legs or three
  `mdl mutate --fast` workers sharing the host, and passed alone; one render measured 120 to 200 ms right after
  seeding where the docblock said 50. The time half is now the ratio of the median of nine interleaved renders
  of two hundred courses to that of a root of one full page (48), in the same process, under 2.5 (measured
  0.96 to 1.3): the per-course cost a statement count cannot see, with the host's speed divided out. The read
  ceilings and the memory bound are unchanged.
- **A visitor's flat tree links every child it names, a merely listed one included** (2026100533). On a public
  category page in a flat organisation (A-Z or the recent sort) each child holding a public course but not itself
  public was drawn as `<span class="dim-tree__text">`, though the server already honoured its `cat=`: the tree's entry
  was gated on the population's `linkable`, which says whether the child's OWN page is public (D20). That gate belongs
  to the posters and the accordion, whose links open that page; a tree entry is a filter of the parent's page and
  only regroups rows the page already lists. `category_listing::tree()` now links every entry, its `linkable` key, the
  template's text branch and `.dim-tree__text` are gone, and the visitor's client handles the new links in place as it
  did the others. And the tree a server draws for a visitor under a filter is the tree at rest: it was built from the
  narrowed population, so a chosen child, or its reloaded address, left that child alone in the tree and its sibling no
  choice at all. `population::accordion_tree()` is now `tree_at_rest()` and also feeds a visitor's flat tree, on the
  page's own request only (`criteria::$withposters`): one grouped statement, which the web service's filter fetch, whose
  answer carries no tree, never pays. `category_listing_test::test_a_visitors_flat_tree_links_a_listed_child_as_a_filter_of_this_page`
  pins both with the public child and a child holding no public course as controls, and `budget_test` the +1 on the
  page and the 0 on the service; mutation gates `tree_visitor_listed_link`, `tree_at_rest_flat`,
  `tree_at_rest_service_free`.
- **A person can have a Boost Union flavour as their own look: the choice and how it reaches the page** (2026100532;
  S1 of `docs/flavourchoice/PLAN.md`, no interface yet). The setting `theme_dimensions/selectableflavours` (General
  settings, *Look*) lists the flavours a logged-in person may choose; the preference `theme_dimensions_flavour` holds
  the choice (a flavour id, or 0 for the site default). A `before_http_headers` callback, at priority 1000, writes the
  chosen flavour's record into Boost Union's `flavours` session cache under the page's category key before the layout
  asks which flavour applies, so the stylesheet, the logos, the favicon, the body class, the navbar colour, the
  footer and the dark logo all follow it, over the category and cohort rules (decision F1). The site default writes
  nothing, and nothing of Boost Union's resolver is copied. A session cache, `flavourchoice`, remembers what the
  session was primed with; a different choice, or a flavour leaving the list, purges Boost Union's cache once, and
  Boost Union's invalidation events refresh it. Boost Union's flavour purge flag, which it never clears, is honoured
  once and cleared, or it would erase the choice on every page. The preference is exported by the Privacy API and
  dropped on uninstall.
- **The course dialogue's "Enrolment from" date keeps its day's leading zero** (2026100531). `userdate()` strips it by
  default, so from the 1st to the 9th of a month the button read `1/11/2026` against the string's own `%d/%m/%Y`, and
  `category_card_modal_test` failed on those days only. `category_card::modal_primary()` passes `fixday` false.
- **The visitor's showcase draws the logged-in shell's React components** (2026100531; R4a of
  `docs/vitrine-react/PLAN.md`, the first half of R4). The anonymous live client (`categorypublic`, armed by
  `categorypublicclient`) mounts the calendar (R1), the filter line (R2) and the posters with their panel (R3) through
  the shared listing's mounts, each component named once in `categorylisting`'s `COMPONENTS`; the posters' mount moved
  from `categorypanel` into `categorylisting`'s `mountAreas()`, which hears the component for either client. The
  posters' component takes the visitor's differences as props (`{links: true, quiet: 120, autoopen: false}` on the
  visitor's page, none on the logged-in one): the posters stay the server's links announced as buttons (a modified
  press follows the link, Space opens as Enter does), a panel's question is asked after a quiet and once per opening
  (`dim-areas:question`, named by the visitor's module), a failure is offered to the page before the inline error
  (`dim-areas:unanswered`, which the visitor's module takes by loading the poster's link) and no area opens by itself.
  The visitor answers the components as the logged-in client does, with its own service: `dim-areas:ask` with the
  public call's promise (a public area by its id, a merely listed one as a slice), `dim-vitrine:pending` and
  `:criteria` on the document, the line told every answer (`tellAnswer()`), the field read delegated on the form.
  The visitor's import graph now reaches the three components by design, and nothing it reaches names
  `categoryvitrine`, `categorypanel`, `categorystar` or a logged-in service, comments included
  (`categorypublic_source_test`). `categoryline`, `categorypreview`, `categoryareapanel` and `categoryposters` are
  imported by nothing and stay in the tree until R5; no shell mounts `daterange`'s AMD calendar, whose `period()`
  both clients still read. Jest covers both modes (13 new tests); 22 new mutation gates, 17 retargeted, 4 retired.
  The filter line survives a remount: core's `react_autoinit` unmounts a mounted node that leaves the document and
  mounts it again when it returns, and React's unmount had emptied the line for good (no field, no `dim-line:mounted`,
  the listing's pending held for ever). The unmount now gives the
  adopted frames back to the server's and keeps them with the last state for that node, so the next mount draws the
  line again; a line the component cannot draw sends `dim-line:failed`, which releases the pending too; the calendar is
  told its period on every mount, not the first. Jest runs the three components on the visitor's own markup
  (`js/esm/tests/visitor`, captured from an anonymous page), with two more gates. `mountLine()` reads the toolbar
  form's id as an attribute: on the visitor's script page (`category.php`, what a site without the router serves) the
  form carries a hidden field named `id`, which is what `form.id` returns, so the component was handed `{}`, drew no
  line and held the pending for every visitor client scenario in CI (gate `js_line_form_id_attribute`).
- **The logged-in showcase's posters and the panel between their rows are a React component** (2026100530; R3 of
  `docs/vitrine-react/PLAN.md`). `js/esm/src/areas/Areas.tsx` (`@moodle/lms/theme_dimensions/areas/Areas`) is mounted
  on an empty, hidden child `categorypanel` appends to the areas section, so the mount draws no box and the first paint
  stays the server's. It owns, and is the only writer of, the panel's frame (`areas/panel.ts`: the list item's hidden
  state, its three transition classes through one `settle()`, the five integers of its row, the open poster's colour,
  its DOM order), the posters' result layer (`areas/posters.ts`: pills, counts and shells, the control as a button or
  the link to its page, the open state, zeroed posters by `hidden`, the reveal, "nothing anywhere" and "search all",
  the hint, the live status) and the "nothing in N areas" summary, which React draws through portals into the
  server's `<summary>` and list. The panel's content stays `category_panel`, rendered by `core/templates` through the
  bridge (which now types `replaceNodeContents`/`appendNodeContents`) and inserted whole, never diffed. The component
  calls no service: it asks `categorypanel` for an area's rows on `dim-areas:ask` (the module puts the logged-in
  service's promise in the event), tells it the rows are drawn on `dim-areas:painted` (the progress) and that it is
  drawn on `dim-areas:mounted`; it hears the listing's `dim-vitrine:*` events and sends `dim-panel:autoopened`, and
  `categoryvitrine` no longer writes the posters' shells. The row is counted from the posters shown and the grid's
  tracks through `matchMedia`, never read off a box; nothing is measured at mount, a server-drawn panel keeps its
  no-JavaScript presentation until the component first changes it (the body's `dim-panel-js` redraws its rail), and the
  rail reads its scroll from a ResizeObserver entry. The visitor's shell keeps `categoryareapanel` and
  `categoryposters`, and its import graph is unchanged. Jest pins the component (`js/esm/tests/areas`, 32 tests);
  `areas_component_source_test` pins the AMD half, with ten new `js_areas_*` mutation gates.
- **The logged-in filter line measures its steps as categoryline does: up to the first that fits** (2026100529; R2
  follow-up). `filterline/measure.ts` `stepWidths()` measured all five tries on every measure, five forced layouts in
  the first commit; a CPU profile on m503b (4x throttling) put 407 ms of forced style and layout in one 453 ms frame,
  and the browser probe read the flat page's warm TBT at 303 ms against 98 ms under R1. It now stops at the first try
  that fits the line's width, and a ResizeObserver width the measured tries do not decide (`steps.ts` `settled()`)
  measures the rest; any other width is still a fold alone. Jest pins both.
- **The logged-in showcase's filter line is a React component** (2026100528; R2 of `docs/vitrine-react/PLAN.md`).
  `js/esm/src/filterline/FilterLine.tsx` (`@moodle/lms/theme_dimensions/filterline/FilterLine`) is mounted on the
  server's line itself, which the shared `mountLine()` names for core's `react_autoinit` and puts back where it stands;
  its first render reads the markup it replaces (`filterline/model.ts`) and draws it back hook for hook, with every
  name in the same GET form, so the no-JavaScript submits, the listing's form reading and the Behat steps are
  unchanged. It draws the line's own controls (the scope, the search with its eraser and icon, the star, the Filters
  and Organise summaries, the view toggle, the close button and the listbox) and adopts three frames of server
  markup whole, never diffed: the Filters panel (with R1's calendar inside), the Organise menu and the managers' menu
  (the template's new `dim-line-manage` hook). No painter of the shared listing writes into what the component draws
  (`drawnByLine()`): it tells the component through `dim-line:state` and `dim-line:answer` on the line, and hears
  `dim-line:mounted` and `dim-line:dropterm`, spelled alike in both languages. The steps, the search that grows over
  the line, the suggestions (`filterline/suggest.ts`), the layers, the rows that scroll and the preview
  (`filterline/preview.ts`, imported on first use through `filterline/lazy.ts`) keep `categoryline`'s and
  `categorypreview`'s rules; the width each step needs is measured once per content, and a width change takes its step
  without reading layout. The search's flex basis is now the first step's floor (13rem), so an unmeasured line wraps
  exactly where the first step stops fitting and the line no longer shifts when its client folds it there. The
  visitor's shell keeps `categoryline`, `categorypreview` and `categorymark`, and its import graph is unchanged. Jest
  covers the component and its modules (`js/esm/tests/filterline`, over the template rendered from its example
  context); new `filterline_component_source_test` pins the AMD half and holds the Jest fixture equal to the template,
  twenty new `js_line_*` gates break it, and three gates of the logged-in wiring are retargeted. No database change.
- **The logged-in showcase's calendar is a React component** (2026100527; R1 of `docs/vitrine-react/PLAN.md`).
  `js/esm/src/daterange/DateRange.tsx` (`@moodle/lms/theme_dimensions/daterange/DateRange`) draws the template
  `category_daterange`'s markup hook for hook, over `daterange/model.ts` (the draft, its three ways out, the press
  rules and the clears, function for function as `amd/src/daterange.js`) and `daterange/calendar.ts` (the `{y, m, d}`
  arithmetic through `Date.UTC`; the site's today and first weekday from the server, never the browser's clock). The
  shared `mountCalendar()` appends it to the toolbar's region only when the caller names it, which only
  `categoryvitrine` does; the two talk through `dim-daterange:mounted`, `:pick` and `:set` on the region, and the
  native date fields hide only once the component says it is drawn. The visitor's shell keeps the AMD calendar until
  R4, and its import graph is unchanged. Jest covers the component (`js/esm/tests/daterange`, in a zone west of UTC
  with daylight saving); new `daterange_component_source_test` pins the AMD half and the absence of timezone
  arithmetic, five new `js_calendar_*` gates break it. No database change; the first paint's module and string lists
  are unchanged.
- **The showcase's first paint costs less per card** (2026100526; R0 of the performance plan, after
  a measured review of the server render). A card's landing page values are read for the ten fields a card shows
  (`cards::CARD_SHORTNAMES`, passed to `landingfields::values_many()`'s new `$only`), with one default controller
  per field shared by the courses without a row and the field names formatted once per call while the string
  filters are off; every badge file of the page is one `{files}` statement instead of one per stored crest, so
  the bulk reads are flat in the page size with crests too. `landingfields::get_field()` checks the new
  `FIELD_TYPES` list instead of building the definitions, `option_lines()` and `facets::fields()` are memoised per
  request (reset by `landingfields::reset_field_cache()`), core's `course_image` cache is written only where its
  entry is missing or wrong, and the labels every card spells alike are read once per language. The first eager
  card cover of the page's own first paint asks for `fetchpriority="high"` where the hero paints no picture (the
  card's new `priority` key, declared in the rows service's returns, always false in an answer). The unlisted
  courses the population hands local_unlistedcourses arrive with their contexts preloaded, so the plugin's
  relationship check no longer reads one `{context}` row per probed course (the root of a large subtree was not
  statement-flat for a logged-in reader). No database or JavaScript change.
- **The performance probe of the category showcase** (R0 of `docs/vitrine-react/PLAN.md`, section 5.4; no version
  change: tests and a development script only). `tests/output/core/first_paint_budget_test.php` gates what a first
  paint costs without a browser: the HTML bytes of a card and of the rest of a listing, the hero's bytes, and exactly
  the theme's AMD modules and strings_for_js a page asks for at load, logged in and as a visitor. The browser probe,
  `tests/behat/theme_dimensions_perf.feature` (`@theme_dimensions_perf`), loads a flat page with covers, the course
  index, a grouped page and a visitor's public page five times under 4x CPU and a slow network (Chrome DevTools
  through chromedriver), reads FCP, LCP, TBT, CLS, the INP of one press and the request counts from the browser's own
  entries, and writes the runs and their medians as JSON under the faildumps; it skips unless the site opts in
  (`$CFG->behat_theme_dimensions_perfprobe` or `THEME_DIMENSIONS_PERFPROBE`; the `behat_` prefix is what survives
  core's `behat_clean_init_config()`). `docs/vitrine-react/probe/loadprobe.php`
  (export-ignored) seeds the 5,000-course data set and meters the real server entries, warm and cold.
- **The theme has an ES module tree, `js/esm`, and nothing uses it yet** (2026100525; R0 of `docs/vitrine-react/PLAN.md`,
  the tooling half). `js/esm/src/amd.ts` (`@moodle/lms/theme_dimensions/amd`) is the one door from the theme's ES modules
  to AMD: `load()` and `loadMany()` over core's `@moodle/lms/core/amd`, typed to the two modules R1 needs
  (`core/templates`, `core/notification`); strings and the pending counter are core 5.3's own ES modules and are not
  bridged. `js/esm/src/.eslintrc` is block_compass's, which restores core's AMD jsdoc rules on TypeScript.
  `js/esm/build` is core 5.3's `grunt esm` output (`amd.js`, `amd.dev.js`, `amd.dev.js.map`). Jest tests in
  `js/esm/tests` run under core's own `jest.config.js`. New `tests/local/esm_source_test.php` pins the build against
  the source (the development map byte for byte, the outputs per source, the production build's imports) and that
  only the bridge reaches RequireJS; the visitor's import walk (`categorypublic_source_test`) now follows imports into
  `js/esm` and back, and starts from the bridge as well.
- **The showcase asks for its first paint's images first, and loads three modules on first use** (2026100524; P0 of
  `docs/vitrine-react/PLAN.md`, the performance plan). The covers of the first row of a listing's first page (three,
  `category_listing::FIRST_ROW`) and of the posters that open a grouped page or the root load at once instead of
  lazily (the card's new `eager` key, declared in the rows service's returns and set only on the page's own first
  paint: `category_listing`'s `$firstpaint`, passed by the two page entries, never by a web service answer); the hero's picture is fetched with
  `fetchpriority="high"`; the hero's crests are no longer lazy, and every crest states its box in the markup. The
  cover film is now `theme_dimensions/local/coverfilm`, imported by a small entry (`covervideo`) on the first pointer,
  focus or finger on a cover, which holds a press that arrives before the film and gives it back; the course dialogue
  imports `core/modal` on the first press; the filter line imports its preview on the first pointer or focus on the
  toolbar. The visitor's import walk now follows dynamic imports too.
- **Every copy of core or Boost Union code keeps a snapshot of its upstream** (Boost Union's `Nav.tsx.upstream`
  pattern). The 24 template overrides and the two copied methods carry a verbatim `.upstream` file beside them;
  `classes/local/upstream.php` reads today's upstream as the page does, `tests/local/upstream_test.php` reddens when
  core or Boost Union changes a copied file, and `scripts/upstream-merge.sh` (development only, export-ignored)
  applies the upstream change to the copy with a three-way merge and refreshes the snapshot. No version change.
- **Fixed: a navbar logo capped by Boost Union's *Maximal width of logo in navbar* was squeezed sideways**
  (2026100523). That setting writes `max-width` and `height: auto` on `.navbar-brand .logo`; the 44px height of
  2026100518 outranks the `auto`, so a 300x31 wordmark capped at 200px was drawn 200x44. The logo is
  `object-fit: contain` now and keeps its ratio inside the capped box. Gate `navbar_logo_contain`.
- **The context bar slides in on a phone and comes in with a colour of its own** (2026100522; Fandom's mobile
  navbar is the model). Below md the context layer comes down from above the bar while the global bar's items go down out of it, by
  the bar's height, clipped by the nav while the layer is in and, through a keyframe holding `overflow: hidden` for
  the length of the slide back (Fandom's `overflowHiddenForAnimation`), on the way out; after it the nav overflows
  again for its menus. From md up the swap is still a fade. The layer paints a ground at every width: the new
  *Context bar colour* setting (`theme_dimensions/navbarcontextcolor`, hexadecimal, compiled into the stylesheet by
  the pre SCSS callback) or, left empty, a step of `$primary` towards its ink (30, 20 or 10 %, the most visible that
  still reads) or a quarter step away from it; the ink is white or the darkest grey, and the ground is moved, never
  the ink, until the pair reads 4.6:1. The sub line lost its 82 % opacity, which would have fallen under that
  contrast. New `tests/local/readingbar_colour_test.php` compiles the Sass over Bootstrap's own functions and measures
  it with a WCAG implementation of its own.
- **The phone's navbar keeps four controls.** At 375px an editor's navbar carried seven (menu, brand, edit switch,
  focus mode, notifications, messages, Mail, user menu) and left the brand 14px. Below md the messaging drawer, Mail,
  focus mode and the edit mode switch are entries at the top of the user menu, after the name block
  (`classes/local/phonenav.php`, `templates/navbar_phonemenu.mustache`, `amd/src/phonemenu.js`, `scss/phonenav.scss`);
  an entry presses the hidden control itself and repeats its unread count, and a control is hidden only once its
  entry is in the menu, so without the module the navbar stays as it was. The context layer carries the way into
  focus mode as well, and focus mode's module moves the focus to the first counterpart on the screen.
- **Fixed: the context bar stayed as it was after the course index was closed from it.** Boost's drawers module gives
  the focus back to the toggle that opened the drawer, which is the layer's own control, and the bar held still for any
  focus inside it until a press elsewhere. Only a keyboard reader's focus (`:focus-visible`) holds it now.
- **Core's linear navigation footer comes back near the end of the page on a phone.** Below 768px Boost hides every
  sticky footer on the way down the page, so a reader reached the end of an activity with "Next" and the completion
  button hidden. `amd/src/linearfooter.js` leaves Boost's behaviour along the page and shows that footer again within a
  quarter of the screen's height of the end, after Boost's own scroll listener and in Boost's own way; armed by core's
  predicate (`linearnavigationsettings::show_navigation_footer()`), never on other sticky footers.

- **The course notice says why a course is not public** (2026100521). A course set to public under an unlisted
  category read "Login only", with a note telling the editor to make it public. The promotion section now names the
  course's own state - public, "Public, not in effect", unlisted, or login only - and, below it, what withholds it:
  the course hidden, the nearest unlisted category on its path (with the link to that category's discoverability
  page for whoever may change it) and the nearest hidden category (with the link to edit it). The category notes are
  said whatever the course's state, because both also withhold the course from listings. An unlisted course says who
  still learns that it exists, with the link to change it for whoever holds `moodle/course:visibility`. The verdict
  is still local_unlistedcourses' predicate; a refusal the notice cannot name still reads "not in effect". Six
  PHPUnit cases and ten mutation gates.
- **The design system's semantic aliases turn with the colour mode.** Core 5.3's dark mode turns Bootstrap's surfaces
  and ink and none of the `--dim-ds-*` aliases, so the login page drew its heading and "Forgotten your password?" in
  navy on a dark card (1.28:1), and the profile cards, the landing page card and the "not available" card stayed white
  under the page's light ink (1.30 to 2.35:1). The aliases now have a dark half on the html element's mode: surfaces
  from core's dark greys, inks lifted from their light primitive by core's `dark-readable()` (4.6:1 on a raised surface,
  7:1 for a link's hover, 3:1 for a line), the tint mixed into the dark page, the focus ring the family ring's dark
  stop. The inverse tokens, the accents and the primary fill stay the same in both modes. The primitives are written
  once, as Sass variables, which the `:root` block declares. Two aliases replace inks that read a primitive directly:
  `--dim-ds-text-on-tint` (the login's lead route) and `--dim-ds-text-success` (the landing page's held place and the
  card's learning list); the landing page's offer icon reads the heading ink. The notifications popover follows the
  page's mode instead of forcing a light scheme. New `tests/local/dark_tokens_test.php` (every alias has a dark half,
  every dark pair measured on core's compiled values, no ink reads a primitive but the fixed plates) and four mutation
  gates. No version bump: stylesheet only.
- **Plugins directory readiness** (2026100520; the directory's submission guidelines, after a reviewer's four
  findings on local_groupdist).
  - Fixed: the branding table's `usermodified` stamp is personal data and was only declared. The privacy
    provider is now a request and userlist provider as well: a data request exports, per category whose
    branding the person last saved, that they saved it and when (read from the request's subject, never the
    session user); a deletion request clears the stamp to 0 and keeps the branding. Every method asks that the
    context is a category's first. `timemodified` is declared beside it.
  - Fixed: the README said the table held no personal data and never named it; it now has "Data stored",
    "Capabilities" and "Translations" sections matching the provider and the code.
  - Added: `LICENSE` (a byte copy of `COPYING.txt`) and `tests/marketplace_readiness_test.php` (the fleet
    template; the settings test reads the `$name` assignments of `settings.php`, and the CSS scope test is
    skipped because a theme styles the whole site). Six provider tests and twelve mutation gates.
  - Changed: `lang/pt_br` leaves the release package (`export-ignore`); the Brazilian Portuguese translation
    stays in git and reaches sites through AMOS.
- **The category rows web service loads `filelib.php` for the card excerpt.** `category_card::excerpt()` calls
  `file_rewrite_pluginfile_urls()`, and with `courseoverviewfileslimit` at 0 and no textarea landing fields nothing
  else on the `theme_dimensions_get_category_rows` path had loaded `lib/filelib.php`, so the call died with "Call to
  undefined function". The method now requires it. A PHPUnit case exports a card in a child process that has not
  loaded it.
- **The context layer's progress follows a completion toggle** (2026100519). The sub line's "X of Y completed" was
  printed once by the server, so marking the activity done in core 5.3's linear navigation footer moved the status to
  "Completed" and left "0 of 1 completed". `readingbar::context()` now hands the course's name and the progress apart,
  the progress with its two numbers, and `amd/src/readingbar.js` moves the completed one by one on core's
  `manualCompletionToggled` (an activity's footer, or any activity's button on the course page) and says it again in
  the server's own string. A Behat scenario marks the activity done and back in the footer; one mutation gate.
- **The phone navbar** (owner's request, 2026-10-22; nothing in it needs a version bump of its own). The
  brand shows beside the hamburger below md: Boost Union's navbar partial hides it with `d-none` (`!important`), so
  the core renderer edits the rendered layout's one brand class list (`brand_on_phones()`, a test reads Boost Union's
  partial for the exact string), 8px from the hamburger; a site name without a logo is cut with an ellipsis. The logo
  keeps 8px of the bar above and below it (44px where core gave 52px), in the navbar and in the drawer's header. The navbar's dividers on a dark
  bar take its white at 15%. The navbar's colour mode menu (and the divider after it) leaves the navbar below md and
  in the compact navbar wherever the drawer carries the appearance switch. Three mutation gates.
- **Three defects found in a browser** (2026100518; measured on m503, 2026-10-22). The compact navbar's drawer dimmed the
  page twice from 768px to 990px: core 5.3 shows its drawers' backdrop below `core/pagehelpers`' `Sizes.medium`
  (991px), not below md, so the drawer's own shadow now stands down while that backdrop is on the page. The reading
  bar counted its travel from the top of the page, so the frame the page header left already held more than 96px and
  the context swap and the hide landed together; the travel now starts when the header has gone (swap at 24px, hide
  at 96px, back at 16px up). And a docked course index or block drawer held the bar for good between 992px and
  1399px: `busy()` read core's `isLarge()` (1400px) where the drawers dock from `isSmall()`'s 991px, which it reads
  now. Three mutation gates; `amd/build/readingbar` rebuilt.
- **The primary drawer opens over the navbar** (the navbar canvas; no version bump). Below md and in the compact
  navbar the drawer covers the whole screen, navbar included: the navbar steps down to `$zindex-sticky` while the
  drawer is open, so core's backdrop dims it and closes the drawer on a press (raising the drawer instead would
  raise core's shared backdrop over the course index drawer). The drawer's header is the navbar's band: an element
  carrying the navbar's own colour classes paints it (Boost Union's colour and tint, a flavour's, the dark mode's
  repaint), the logo sits where the navbar holds it (the navbar's logo height, 12px from the edge) and takes the
  navbar's dark variant, and the close button takes the navbar's ink. Auto-hide's Boost Union condition moved
  inside `:where()`, so its block region ids no longer add specificity to the scope's rules.
- **Boost Union's new navbar contract** (Boost Union `upgrade-503` at 3b534e30, 2026-10-20; no version bump:
  no schema, service or JavaScript change). Boost Union dropped the navbar's `databstheme` key and colours a
  dark bar through `.navbar-dark` instead, so the theme's renderer now writes `navbar-dark bg-primary` (its
  "coloured, light font" choice) and nothing else; the focus bar copies only `navbarcolorclasses`. The pill
  tokens and the dark logo key on `.navbar.navbar-dark`; the navy dropdowns answer Boost Union's hover
  properties (`--bu-navbar-bg`, `--bu-navbar-dropdown-hover-color`) on the menu, or a hover would be navy on
  navy. Focus mode hides Boost Union's new `theme-block-region-header-wrapper` (with its spacers) instead of
  the header region alone. Auto-hide stands down on the limited-width pages where Boost Union pins the drawers
  flush with the window edges (an outside or header block region shown), whose `!important` rules it cannot
  move. In the dark colour mode Boost Union now paints the navbar with the body background, brand colour
  included: Boost Union's decision, kept.
- **Focus mode switches in place, without a reload** (2026100517; `docs/focusmode/README.md`, decisions D1-D5).
  Every activity page that can be focused carries both skins: Boost Union's layout renders untouched and the
  theme adds the bar (after core's top of body) and the foot of the content (the completion line and the
  previous/next row), shown only under the `dimensions-focusmode` body class. The entry button and the exit
  switch the class, move the header actions (the manual completion button or its status) between the page
  header and the bar, move the focus, announce the mode and record the choice for the course through a new
  web service, `theme_dimensions_set_focus_mode`; without JavaScript they post to `focus.php` and reload the
  activity. Leaving a course that switched focus mode on lasts the rest of the session in that course (D1);
  a single activity course that switched it on leaves for the site home page instead (D2). The copied
  shell `templates/focusmode.mustache` and `focusmode_description.mustache` are deleted; core's activity
  information box is kept and reduced to the description in focus mode.
  - **The master switch ships off (D5) and, off, loads nothing (D4)**: no bar, no button, no module, no
    stylesheet (`scss/focusmode.scss` is compiled only while it is on; the setting rebuilds the theme's
    CSS), no course form box, and the service refuses. A site that stored a value keeps it. The tab's other
    settings hide while it is off.
  - Strings `focusmode_close_title_site`, `focusmode_status_on`, `focusmode_status_off`,
    `focusmode_unavailable`; `focusmode_close_title` and `focusmode_desc` reworded.
  - readingbar and compactnav skip the focus bar when they look for the navbar, and auto-hide stands down
    under focus mode.
  - Mutation gates: six retired with the shell (`focusmode_template_swap`, `focusmode_footer_partial`,
    `focusmode_blockdrawer`, `focusmode_description`, `focusmode_drawercontrols_block`,
    `r6s21_focus_guest_login`), two rewritten, twenty-three added; every line of the spec dry-matched.

- **The landing page's hero takes the brand colour.** The hero and the guest entry's card paint a gradient of
  `$primary` (a head 18% darker, the colour, a tail 12% lighter: the stops of `branding::stops()`) instead of the
  fixed navy, and the guest entry's button writes its label in that colour. Boost Union compiles the sheet once per
  flavour, so each flavour's brand colour gets its own hero, an anonymous visitor's included. A brand colour on whose
  lightest stop white text reads under 4.5:1 keeps the navy; Boost's default blue is one, so a site with no brand
  colour looks as before. Stylesheet only: no version bump; a site picks it up after a cache purge.
  - `tests/local/landing_scss_test.php` compiles `scss/landing.scss` against Bootstrap with four brand colours.

- **The Brazilian Portuguese pack calls the landing page "Página de apresentação do curso".** Every "hotsite"
  in `lang/pt_br` (37 strings: the course notice chips, the field descriptions, the recommendations, the settings
  tab) now uses the name the custom field category already had, with articles and agreements rewritten to the
  feminine noun. The settings tab is named after that category in English too ("Course landing page", formerly
  "Landing pages"). Values only: no key changed, so no version bump; a site picks it up after a cache purge.

- **Landing page defaults for new courses** (2026100516). A new *Course landing page* tab sets what a course created from now on
  starts with in three of its *Course landing page* fields: the page model, the modality and the study format, each
  offering that field's own options (as the field is configured now) and *None*, the default. The course form of a
  new course shows them preselected and its creator may change them or leave a field empty; every path through
  `create_course()` (the form, an approved course request, `core_course_create_courses`, tool_uploadcourse) gets
  them. Existing courses never change, and changing a setting writes nothing: the three fields keep their own default
  value at `''`, so a course with no data row reads empty whatever the setting says, and the default reaches a course
  once, as a data row written when it is created and only where it holds none (an explicit empty answer is a row and
  wins). Restore and duplication keep what the backup carried.
  - Settings `landingdefaultmodel`, `landingdefaultmodality`, `landingdefaultformat` (tab `theme_dimensions_landing`,
    between Focus mode and Category showcase).
  - `classes/local/landingdefaults.php` (the choices, the clamped value, the one write) and two hook callbacks:
    `local\hook\course\after_form_submission` (core's `after_form_submission`, new courses only) and
    `local\hook\course\after_form_definition_after_data` (the preselection, new courses only). `db/hooks.php`
    registers both. `landingfields::option_lines()` is now the one split of a select's options, which
    `option_label()` reads too.
  - Eight mutation gates (`landingdefault_*`), dry-matched.

- **Tablet and sheets** (phase 4 of the navbar; 2026100515). Between tablet and desktop
  widths, when the site's navigation would send more than two items into its "More" menu, the navbar takes the phone's
  layout: core's menu button, the logo and the icons on the right, with the navigation in the side menu (B5). The
  decision is the browser's, read from what core's own navigation measured, and the navigation is held back until it
  is made, so the first paint never shows items about to go; at tablet width the side menu then gets a scrim, closes
  on a press outside it and stacks above the docked drawers. On phones the notifications popover (D2) and the user menu
  (D3) open as bottom sheets: full width, rounded top, a grab handle, scrolling inside, a scrim, 44px rows, rising only
  without `prefers-reduced-motion`. In the context bar a reader who may edit the page finds core's edit mode switch
  again (D5). Never in the Moodle app; focus mode keeps its own bar. Notifications and messages are not merged into one
  control yet.
  - Setting `navbarcompact` (Identity tab), a checkbox that ships on and reads as on until an explicit `0` is stored.
  - Every decision of B5 is `classes/local/compactnav.php`; the renderer splices `dimensions-navcompact-armed` into the
    layout's body attributes and arms the new `amd/src/compactnav.js`, which writes `dimensions-navcompact-measured`,
    `dimensions-nav-compact` and takes `d-md-none` off core's toggler while compact. New `scss/compactnav.scss` and
    `scss/sheets.scss`. D5 is `readingbar::edit_applies()`, core's `user_allowed_editing()`, with core's
    `edit_switch()` rendered into `theme_dimensions/navbar_readingbar`.
  - Two visual defects of the first build, found in real-Chrome screenshots at 390 and 1024: the scrim of the tablet
    drawer and of both sheets was the design system's navy shadow tint at .45 (the page turned purple), and is now
    the neutral `--dimensions-backdrop` (core's modal black, .5 on a light page and .7 on a dark one, through
    `--bs-black-rgb`; a theme-own token, not part of the family contract). The notifications popover was unreadable
    inside the dark navbar (dark ink on the dark colour mode's `#343a40`, an invisible cog, a white footer): it is now
    one light surface, through the colour mode tokens re-pointed on `.navbar .popover-region-container`, on phones and
    on the desktop dropdown alike, which had the same defect.
  - The tablet drawer opened 58px wide, cut at the left edge, on the standard layouts and on `body.limitedwidth`: from lg
    up core anchors every `.drawer.drawer-left` (zero width, an anchored `right`, `overflow: clip`, a top under the page
    title, rounded corners) for the course index, and the primary drawer is a `drawer-left` too. In compact mode it is
    again the plain overlay drawer core gives it below md (left edge, offscreen when closed, under the navbar, the
    rest of the viewport high), under a selector with the drawer's id that the anchored ones cannot outrank. Gate
    `scss_compactnav_primary_overlay`.
  - Twenty-one mutation gates (`compactnav_*`, `js_compactnav_*`, `scss_compactnav_*`, `scss_sheets_*`,
    `readingbar_edit_*`), dry-matched; `readingbar_layer_appended` follows the renderer's new line. Behat feature
    `theme_dimensions_navcompact.feature` and the step `the site navigation has "N" custom menu items`.

- **The bar that follows the reading** (phase 3 of the navbar; 2026100514). On activity pages and the course page the 3px band is the reading progress: it fills from the left as an
  activity page is scrolled, and shows the reader's completion of the course on the course page (computed on the
  server); a faint track shows behind it, and every other page keeps the full band. Past the page header the navbar's
  content swaps, at the same height, for a context layer: the activity's (or the course's) name, the course and
  "X of Y completed", a read-only "Pending" / "Completed" chip read from core's completion data (core's linear footer
  keeps the button) and a control that opens the course index (B1) where the course format has one. Scrolling up
  brings the global bar back. Optionally the navbar hides on the way down and returns on the first way up, its band
  left at the top, with the drawers, their togglers, anchors, the quiz timer and the grading table moving up with it.
  The swap and the slide animate only without `prefers-reduced-motion`; nothing changes while the focus is in the bar,
  one of its menus is open or a drawer lies over the page. Never in focus mode, on the front page, in the Moodle app,
  or for a visitor while the visitor's actions are shown; auto-hide never on a Behat site or beside Boost Union's menu
  bar. Sticky course tabs (C5) are not part of it.
  - Setting `navbarcontextbar` (Identity tab), a checkbox that ships on and reads as on until an explicit `0` is stored.
  - Setting `navbarautohide` (Identity tab), the page kinds whose navbar hides (activity pages, the course page); none
    by default.
  - Every decision is `classes/local/readingbar.php`; the renderer appends `theme_dimensions/navbar_readingbar` to
    `navbar_plugin_output()` (no copy of Boost Union's navbar), arms `amd/src/readingbar.js` and adds the body classes
    `dimensions-readingbar` and `dimensions-autohide`. New `scss/readingbar.scss`; the band in `scss/post.scss` reads
    `--dimensions-band-progress`. New theme-owned properties `--dimensions-band-progress` and
    `--dimensions-navbar-offset`.
  - Twenty-one mutation gates (`readingbar_*`, `js_readingbar_*`, `scss_readingbar_reduced_motion`, `scss_band_scale`),
    dry-matched; the sweep is still owed. Behat feature `theme_dimensions_readingbar.feature` and the step
    `I show the context bar of the navbar`.

- **The mobile drawer of a logged-in account** (phase 2 of the navbar; 2026100513). On a phone, the side menu of a
  logged-in account (not a visitor, not the guest account, not the Moodle app) opens on who is logged in - core's
  avatar, name and metadata, and a role line ("Site administrator", and the number of courses in progress) - then a
  "Continue where you left off" card (the last activity opened, else the last course entered, with "X of Y
  completed"), the site's navigation under "Browse" and each Boost Union smart menu as a section of its own, with
  icons and the current page marked. A row with children opens a sub-panel that slides in, with a back control; the
  focus follows both ways, Escape goes back one level, hidden panels are inert, and reduced motion swaps without a
  slide. The footer carries Light, Dark and System while core's colour modes let the person choose (core's own
  `theme_boost/colourmode` writes the mode), and the language while core's language menu offers more than one.
  Visitors and the guest account keep Boost Union's menu with the "New here?" card. The course index trigger (B1)
  moves to phase 3's context bar.
  - Setting `navbardrawer` (Identity tab), a checkbox that ships on and reads as on until an explicit `0` is stored.
  - Every decision is `classes/local/drawernav.php`; the renderer writes `dimensionsdrawer` by the context's shape
    (`mobileprimarynav` beside `usermenu.avatardata`) and the override of `theme_boost/primary-drawer-mobile` draws
    `theme_dimensions/drawer_nav` (re-diff note updated). `amd/src/drawer.js` runs the panels; `scss/drawer.scss`.
  - New web service `theme_dimensions_get_drawer_summary` (read-only, `readonlysession`, asked once per page when
    the primary drawer is first shown), delegate `classes/local/drawersummary.php`.
  - Thirteen mutation gates (`drawer_*`, `js_drawer_*`), each reddening its named test on PostgreSQL and MariaDB.

- **The visitor's navbar: "Log in" and "Create account"** (phase 1; 2026100512). A visitor
  who is not logged in, and the guest account, no longer see core's "Log in" link and "You are currently using
  guest access" in the navbar: two actions stand at its right end instead, "Log in" (an outline pill) and
  "Create account" (a filled one), the second only while the site takes sign-ups (core's `signup_is_enabled()`,
  so a plugin that answers core's sign-up page, auth_loginsteps among them, is followed through it). The guest
  account also gets a small "Guest" chip that opens core's sentence and what a guest can and cannot do. Below
  the md breakpoint only "Log in" stays in the bar, and the mobile drawer opens with a "New here?" card offering
  both (the theme now overrides `theme_boost/primary-drawer-mobile`: Boost Union's copy plus that card; re-diff
  it on every Boost Union update). Never in the Moodle app or on the login, maintenance, embedded, popup and
  secure layouts.
  - Setting `navbarguestactions` (Identity tab), a checkbox that ships on and reads as on until an explicit `0`
    is stored, so no upgrade step writes it. Switched off, the navbar is Boost Union's.
  - Every decision is `classes/local/guestnav.php`; the renderer appends the actions to `navbar_plugin_output()`
    and empties the user menu by the context's shape (`usermenu.unauthenticateduser`). Nine mutation gates
    (`guestnav_*`), each reddening a test on PostgreSQL and MariaDB.

- **The brand on dark surfaces** (2026100512). Two file settings on the Identity tab,
  `logodark` and `logocompactdark`, give Boost Union's logo and compact logo a variant for dark surfaces: the
  compact one stands in the navy navbar, in a dark Boost Union navbar colour, in the mobile drawer's heading in dark
  mode and on the theme's login page (its panel and its band are navy in either mode); the full one on Boost
  Union's login page in dark mode. The nearest `data-bs-theme` decides which surface is dark, so a navbar Boost
  Union paints light keeps the light logo in a dark page. A dark file is a variant: without its light partner in
  Boost Union it is not used, and a flavour (or the multi-tenancy extension) with a logo of its own keeps it,
  variant by variant. No template is copied: the light `<img>` keeps its box and its alternative text, its image is
  moved out of the box and the dark file is drawn as that image's own background, so the dark file should keep the
  light file's proportions.
  - Every decision is `classes/local/darklogo.php`: the address (config and file settings only, so the extra SCSS
    callback can ask it on the command line; through `styleurl::safe()`), the logo in force (Boost Union's flavour
    lookup, asked by the renderer only), the body classes `dimensions-darklogo-compact` and
    `dimensions-darklogo-full` the renderer adds in `body_attributes()` (the login layout included), and the two
    file areas `theme_dimensions_pluginfile()` serves from the system context to anybody, as Boost Union serves its
    logos. Seven mutation gates (`darklogo_*`), each reddening a test on PostgreSQL and MariaDB.

- **The filter line previews what a filter would keep** (`2026100511`): the filter-line redesign's stage T4 (contract
  section 6), for both the logged-in and the visitor's live client, through a module of its own (`categorypreview`)
  that the shared line module arms.
  - Pointing a mouse or a pen at a scope cell, the favourites star, an option of the Filters panel (a state, a field's
    value, a quick date) or a suggestion of the search - or reaching one with the keyboard, the arrows included in the
    suggestions - lights the cards on screen it would keep with a 2px ring in the accent and lets the others recede;
    leaving or blurring takes every mark away. A finger never previews (a tap is a press), a filter already in force
    or one that narrows nothing previews nothing, and no request is made: a list the server pages lights only what it
    has drawn.
  - What a filter would keep is decided from facts the server now writes on every card, on the page and in the rows
    answer (so a card the client renders carries them too): the state chip the course counts under, each filter
    field's value and the start day in the site's timezone; the favourite is the card's own star. A card states only
    what its reader may already see on it: a visitor's card carries no chip and no star, only the fields core shows a
    visitor (plus the model and the modality, which the card prints) and a start day only on an event, whose date tile
    prints it. A card that does not state the fact a filter is decided on makes the preview stand down. With the page
    cut in SQL (`categorystatesort` off) the cards state no field. The facts cost no statement (`budget_test`, flat at
    11 reads for four cards and for twenty-four with a field of each visibility).
  - A receding card remaps its own tokens - surface, inks, line, state pills, star, call to action and a film over its
    image - never its opacity, with every fill named with its ink and dark values of its own; the surface eases only
    where motion is welcome.

- **The filter line folds, searches over itself and suggests filters** (`2026100510`): the filter-line redesign's
  stage T3, for both the logged-in and the visitor's live client, through one shared module (`categoryline`).
  - The line measures its own width (never the viewport's) and takes the first step at which it fits: full; Filters
    keeps its icon and badge and loses its word (still its accessible name); the search becomes an icon button
    ("Search courses"); two lines, the scope on the second at full control height, scrolling sideways with a fade on a
    hidden side, and the view toggle moved into the Organise menu as "Show as". It measures with every cell of the scope
    drawn (the server now writes a cell with nothing to offer, hidden) and with no transition, so the step follows the
    width and the language and never the counts. Without JavaScript nothing of this applies and the line wraps as before.
  - From the third step the search grows over the line: the other controls slide out (none under reduced motion), a
    "Close search" button takes the last place, Escape closes it and the focus returns to the control that opened it.
    A term left in force while the field is folded away shows as a chip under the line, built by the server's one chip
    builder (labelled with the term, its address dropping `q` alone) and shown by the stylesheet only then.
  - Suggestions: one listbox under the line (the field is its combobox): every scope cell, the favourites star, every
    option of the Filters panel and its quick dates whose label or group matches the typed text, accent- and
    case-insensitively, word starts first and then by count, each with its group and count; up to five filters with
    the highest counts while the field is empty ("Start with a filter"). Choosing one applies it as pressing that
    control does, the term dropped in the same request, and keeps the search open. The counts are the last answer
    given while the field held no term (the server's, never the browser's).
  - Every floating layer (suggestions, the Filters panel, the Organise menu) is placed under the line's measured
    bottom; on the two-line step the Filters panel is a bottom sheet (full width, at most 80% of the screen, scrolling
    inside itself, the date calendar opening whole in its flow). A half-hidden item of a row that scrolls sideways (the
    scope's line, the chips under the line) scrolls itself into view when pressed or focused. `/` focuses the search
    while the line is on screen and the focus is in no field.

- **The toolbar is one filter line** (`2026100509`): the filter-line redesign's stage T2.
  - The three rows (narrow, organise, count) become ONE server-drawn line in the contract's slot order: the scope
    (the state chip's All, My courses, Open for enrolment and Scheduled as counted cells of a platter, for a logged-in
    reader with the state sort on), the pill search with an eraser ("Clear text", shown with JavaScript while the field
    holds text), the favourites star, Filters (sliders icon, the word "Filters", the badge), Organise (a menu holding the
    sort chips and the no-JavaScript Apply), the view toggle (icons only, List first) and the managers' menu. The line
    wraps where it does not fit; every control still submits without JavaScript, and the name-less default submit is
    still the form's first.
  - Under review and Free access are the panel's "More statuses", the same radio group as the scope, so one
    choice holds across both; `criteria::SCOPE` is the split, mirrored as `SCOPE` in the shared client.
  - The Filters panel is closed on load and is a popover under the line from 576px (inline below). Its badge counts
    what the panel holds: each custom field, the period as one and a "More statuses" state - no longer the search, a
    scope value, the star or the tree.
  - A second row (`dim-line-chips`) shows while anything narrows: the count sentence (kept visually hidden at rest as
    the live region), one chip per criterion the line does not show - each field, the period, a "More statuses" state,
    the tree's category - each a link dropping that one criterion, "Clear all" from two criteria up, "See all as a list"
    and the automatic opening's note. The chips are built by one server builder for the page and both web services
    (`chips`, `clearall` in the rows answer), and the calendar no longer writes a pill of its own.
  - The phone's copy of the view toggle is gone.
  - Fix: the Filters panel no longer scrolls inside itself (no `max-height`, no `overflow-y`), so the date calendar that
    opens inside it is never clipped at any width.
  - Fix: on a phone the hero's "More" menu opened hidden: the tab rail was a scroll container (`overflow-x: auto` below
    768px, which clips the dropdown to the rail's 50px) and the filter line (`z-index: 30`) painted over it; the rail
    is no longer a scroll container and stacks at 31.
  - Fix: a scope cell that was absent and came back was appended at the end of the line; the shared painter now keeps
    the cells in `SCOPE`'s order (and "More statuses" in the answer's), moving only what is out of place.

- **Favourites are a toggle of their own, combined with the state** (`2026100508`): the filter-line redesign's stage T1.
  - The favourites stop being a value of the state chip and become a boolean criterion, `fav=1`, ANDed with the state:
    "my favourites among the courses I am enrolled in" is one request now. It is read only for a real account (never a
    visitor nor the guest), honoured where the state chip is (a logged-in page cut in PHP) and dropped on the request
    elsewhere, counted as one filter on the badge, carried by the pager, the quick dates and a panel's call to action, and
    dropped by "Clear filters".
  - Each criterion is counted with the other applied: every state pill under the favourites in force, the favourites
    under the state chip in force.
  - The toolbar draws a star toggle on the search line, right before the Filter control (`data-region="dim-line-star"`,
    `aria-pressed`, the course card's own star, its accessible name "Favourites" with the number), only for a real
    account where the state group is offered. Without JavaScript it is a submit button posting `fav` with the value a
    press turns it to, and a hidden field carries `fav=1` through every other submission while it is on. The
    favourites pill of the state group, its star and the web service's `isfavourites` key are gone.
  - `get_category_rows` and `remember_category_state` take `fav` (a boolean); `chip` no longer accepts `favourites`.
    The rows answer carries the toggle (`favourites`: `available`, `pressed`, `count`); the visitor's service declares
    no `fav`. The remembered state stores the toggle under its own key (`fav`, true or absent).
  - Older addresses and remembered states keep working: `chip=favourites` on an address, and a remembered entry holding
    the favourites as its state, are read as the toggle with no state.

- **Default category hero colour `#0a4a85`** (`2026100507`) (9.01:1 opaque; 5.08:1 over a white photograph at the default 78% overlay).
  - Replaces `#0f6cbf` (5.36:1 opaque, 3.57:1 over white at 78%): `branding::DEFAULT_BG`, the `bgcolour` column default,
    the templates' examples and the tests' numbers. The folder watermark on the default band is .20 again (was .09). A 60%
    overlay cannot reach 4.5:1 with any colour (3.27:1 here), so it stays a manager's explicit choice that D31 measures.

- **The competency path clears core's floated "Path:" label** (`2026100507`): on the course competencies page the
  "Upon course completion" selector no longer wraps beside the label.

- **The competency framework's pages and dialogues in the theme's design** (`2026100506`).
  - `scss/competency.scss`, concatenated by `lib.php`: every top-level selector scoped to the body classes
    core derives for tool_lp, report_competency and block_lp's scripts (`path-admin-tool-lp`,
    `path-report-competency`, `path-blocks-lp`), to `.block_lp` or to the theme's own `dim-comp-` classes;
    colours from the family tokens `--dimensions-*` and Bootstrap only, radius and spacing from the design
    system; every ground states its ink, and the progress bar's fill is restated for the dark mode (the
    brand ink). Core's proficiency badges, where the template stays core's, get the pill's shape and an
    icon per state.
  - Restyled copies of twenty core templates, each naming the 5.3 file it mirrors: tool_lp's
    `progress_bar`, `competency_summary`, `competency_path`, `evidence_summary`, `user_summary`,
    `linked_courses_summary`, the three navigation templates, `related_competencies`,
    `course_competency_statistics`, `template_statistics`, `competency_grader` (the rating dialogue) and
    `user_competency_summary`; report_competency's `report`, `user_competency_summary` and
    `user_course_navigation`; block_lp's `summary` and its two review pages. Every data-region,
    data-action, data-* attribute, id and form name core's AMD and Behat read is kept. Proficiency is said
    in words with an icon (the breakdown report's green cell is gone); a progress bar is named by its own
    sentence and prints its percentage. No renderer is overridden.
  - One language string, `competency_notproficient`. Tests `competency_scss_test`,
    `competency_templates_test`, `competency_render_test`, the Behat feature
    `theme_dimensions_competency.feature`, and mutation gates `comp_grader_rate_action`,
    `comp_evidence_delete_action` and `comp_report_cell_hook`.
- **Family design tokens `--dimensions-*`; crests answered through block_compass's hook** (`2026100505`).
  - `scss/post.scss` declares the 54 family tokens local_dimensions and block_dimensions will read as
    `var(--dimensions-x, <their own chain>)`: 33 shared (surfaces, line, ink, accent, the brand, the focus
    ring, the five tone families, shadow, scrim) and 21 read by local_dimensions alone (motion and the six
    enrolment states, which are the category showcase's state badges in both modes). Light values on
    `:root`, `body` and `.modal`; dark values on the same roots under `:root[data-bs-theme="dark"]`. One
    new design system token, `--dim-ds-shadow-rgb` (the navy's channels). `docs/tokens.md` is the public
    contract, and `tests/local/family_tokens_test.php` holds the names, the roots, the values and the
    document against each other.
  - block_compass asks for the crests of its cards through its own hook, `\block_compass\hook\course_badges`
    (5.3): `classes/local/hook/block_compass/course_badges.php` answers it from
    `theme_dimensions_course_badges()`, whose viewer filter is unchanged, and reports a fault instead of
    throwing. The block stays optional: the registration in `db/hooks.php` is inert without it, and CI
    installs `block_compass` (`MOODLE_503_STABLE`) only to exercise the callback. Mutation gates
    `compass_hook_viewer_path` and `compass_hook_catch`.
- **Landing pages, English identifiers** (`2026100504`). The theme has no install base yet, so
  `install.xml`, `install.php` and the field definitions change in place, with no upgrade step:
  - "hotsite" is "landing" in code and "landing page" in prose: `classes/local/landing/`,
    `landingfields`, `output\landing_page`, `output\landing_guestentry`, the route
    `/theme_dimensions/landing/{course}` (`route\controller\landing`), the script `landing.php`, the
    shortlink types `landing` and `landingguest`, the AMD module `landingguest`, `scss/landing.scss`
    and its `dimensions-landing__*` classes, the lang keys `landing_*` and `landingfield_*`, the
    recommendation `dimensions_landingaddresses`, the config key `landingcfcategoryid`. The custom
    field category reads "Course landing page".
  - The provisioned course custom fields have English short names prefixed `dimensions_`
    (`dimensions_model`, `dimensions_modality`, `dimensions_workload`, `dimensions_audience`,
    `dimensions_format`, `dimensions_liveclasses`, `dimensions_crest_1` to `_4`,
    `dimensions_shareimage`, `dimensions_publicdescription`, ...), and every Portuguese identifier
    of the code is English ("brasao" is "crest").
  - The category branding column `chamada` is `tagline`.
  - The course notice's custom properties are `--dim-notice-*`; its own page gate is the "course
    home" (`builder::is_course_home()`).
  - The backup elements of the picture rows are named `picture`.
- **Structural de-brand** (`2026100503`):
  - The login page's CPF mask moved to auth_loginsteps (its setting `logincpfmask`, with the same
    `?nocpf=1` bypass). The theme drops `amd/src/logincpf.js`, the setting `logincpfvalidation` and
    its strings, the bypass parameter and its session key, and the module arming in
    `render_login()`; the login page, its panel, the ways in, the failed-login hint and the local
    identity provider marks stay. The local route reads "Sign in with your username".
  - Neutral default palette: the theme assigns no `$primary` (Boost Union's brand colour, a
    flavour's, or Boost's default decide), and an unbranded category's band is Boost's default
    primary `#0f6cbf` with white ink (5.36:1) instead of the institutional navy, in
    `branding::DEFAULT_BG`, the `bgcolour` column default and the templates' examples. The navbar
    style's "navy" option reads "Dark navbar in the brand colour".
  - customfield_picture is optional: no longer in `$plugin->dependencies`. Without it (or with the
    Picture type disabled) the four badges and the share picture are not provisioned and not drawn;
    the recommendation reports the type as recommended (a notice), and the tests that need it skip
    themselves. CI keeps installing it.
  - Design-system tokens namespaced: the 49 unprefixed aliases declared on `:root`
    (`--surface-page`, `--text-body`, `--text-muted`, `--radius-*`, `--space-*`, ...) and focus
    mode's local scale are `--dim-ds-*` now.
  - Neutral copy: "organisation" for "corporation" in both packs, and generic defaults for the
    login panel's title and the identity provider route's audience.
- **Ported to Moodle 5.3** (`2026100502`): requires `2026100500`, supports `[503, 503]`, on Boost
  Union's `upgrade-503` branch until Boost Union tags its 5.3 release; CI runs `MOODLE_503_STABLE`
  with customfield_picture `master` and local_page `main`.
  - Focus mode follows core's linear navigation (topics and weeks, on by default): previous, next
    and the manual completion button are in core's sticky footer, the bar carries the completion
    status core updates from it, and the theme's own previous/next row is drawn only where linear
    navigation is off, so the page never shows two.
  - The focus page's course index keeps its controls, passed in 5.3's `drawercontrols` block.
  - The navbar shows the user's name once: core 5.3's first name beside the avatar is dropped where
    the theme already shows the full name.
  - The course notice reads the switched role through `\core\user::get_user_navigation_info()`;
    `user_get_user_navigation_info()` is deprecated in 5.3. The profile rule's test asks
    `\core\user::can_view_profile()` for the same reason.
  - Tests: Font Awesome's icon names are read from `lib/bundles/fontawesome`, the category page's
    tab rows are asserted per component (`core/primarymoremenu`, `core/secondarymoremenu`), and
    Boost Union's retired `activitynavigation` setting is no longer set; `loginshell_test` declares
    its coverage with `#[CoversClass]`, as PHPUnit 11 deprecates doc-comment metadata.
- **First public import.** `theme_dimensions` is derived from a private Boost Union child theme,
  renamed throughout for its life as a community theme. Its feature set equals that theme's on
  Moodle 5.2: the custom login page, the category showcase, the course landing pages, focus mode,
  the course notice, the profile access rule, guest access, Open Graph tags and the pages strip.
  The private theme's history is not carried over.
- **The version line restarts at `2026100500`**, release `v5.3-r1-dev`, with Moodle 5.3 as the
  next base once Boost Union releases its 5.3 version.
