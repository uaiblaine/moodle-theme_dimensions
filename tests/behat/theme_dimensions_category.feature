@theme @theme_dimensions @theme_dimensions_category
Feature: The category showcase lists what a person may discover, and its filters work without JavaScript
  In order to find a course to enrol in
  As somebody studying on a Dimensions site
  I need the category page to list the courses I may see, to narrow them and to page through them

  Background:
    Given the following config values are set as admin:
      | theme                | dimensions |                            |
      | categoryfilterfields | dimensions_modality           | theme_dimensions |
    And the following "categories" exist:
      | name                | category | idnumber |
      | Test category       | 0        | TESTCAT  |
      | Restricted category | TESTCAT  | RESTCAT  |
      | Open category       | TESTCAT  | OPENCAT  |
      | Public category     | 0        | PUBCAT   |
    # Modality 1 is online, 2 is in-person: the option index the select field stores.
    And the following "courses" exist:
      | fullname           | shortname | category | customfield_dimensions_model | customfield_dimensions_modality |
      | Resgate em Altura  | RESG      | TESTCAT  | 1                          | 2                      |
      | Combate a Incendio | INCEND    | OPENCAT  | 1                          | 1                      |
      | Restricted One     | REST1     | TESTCAT  | 1                          | 1                      |
      | Restricted Inner   | RESTIN    | RESTCAT  | 1                          | 1                      |
      | Curso Público      | PUBC      | PUBCAT   | 1                          | 1                      |
      | Curso Interno      | INTC      | PUBCAT   | 1                          | 1                      |
    And the course "REST1" is "unlisted" for discoverability
    And the category "RESTCAT" is "unlisted" for discoverability
    And the course "PUBC" is "public" for discoverability
    And the category "PUBCAT" is "public" for discoverability
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |

  Scenario: A learner sees the discoverable courses and subcategories, counted, and nothing withheld
    Given I log in as "student1"
    When I am on course index
    # At the root a poster opens its panel in place; the panel's title is the way to the
    # category's own page (its call to action is the flat order, which is not the page read here).
    And I follow "Test category"
    And I click on "Test category" "link" in the "[data-region='dim-panel-title']" "css_element"
    Then "body.dim-category" "css_element" should exist
    And I should see "Test category" in the "h1.dim-hero__title" "css_element"
    # The courses section holds the courses sitting directly here; the course of the open
    # subcategory is behind that subcategory's own poster instead of being drawn twice. The
    # hero's number is the control: it counts the whole subtree, which is what says the course
    # was partitioned away rather than withheld.
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Combate a Incendio"
    And I should not see "Restricted One"
    And I should not see "Restricted Inner"
    # At rest the count line counts the whole subtree, the hero's own number; the partition is
    # what the two sections below it are for.
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '2 courses in Test category')]" "xpath_element" should exist
    And I should see "2 courses and events" in the ".dim-hero__stats" "css_element"
    # The areas name a subcategory the learner may browse; an unlisted one is not among them.
    # (The tree is drawn for the flat orders alone: on this grouped page the posters name the
    # children.)
    And I should see "Open category" in the "[data-region='dim-areas']" "css_element"
    And I should not see "Restricted category"
    # A listed course without a usable method is shown, muted, never explained.
    And I should see "No enrolment available" in the "[data-region='dim-vitrine-grid']" "css_element"

  Scenario: A learner whose enrolment was suspended reads it on the course card
    Given the following "course enrolments" exist:
      | user     | course | role    | status    |
      | student1 | RESG   | student | suspended |
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page logged in as "student1"
    Then I should see "Enrolment suspended" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The same card read "No enrolment available" with no row (the first scenario); no method is open, so no route.
    And I should not see "No enrolment available" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "You can also enrol now"

  Scenario: Without JavaScript, More information leads to the course's own page
    # "More information" is a link to the card's page: for a learner, the landing page of a course that chose a
    # model. With JavaScript the course dialogue opens instead (theme_dimensions_category_client.feature).
    Given I am on the "TESTCAT" "theme_dimensions > Category showcase" page logged in as "student1"
    When I click on "//article[@data-region='dim-card'][contains(., 'Resgate em Altura')]//a[@data-region='dim-card-more']" "xpath_element"
    Then the url should match "landing"
    And I should see "Resgate em Altura"
    And "body.dim-category" "css_element" should not exist

  Scenario: An administrator keeps seeing the unlisted course and the unlisted subcategory
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then I should see "Restricted One" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The unlisted course of the unlisted subcategory is not withheld from an administrator - it
    # is partitioned behind that subcategory's own poster, which is drawn right here. The hero
    # still counts all four.
    And I should not see "Restricted Inner"
    And I should see "Restricted category" in the "[data-region='dim-areas']" "css_element"
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    And I should see "4 courses and events" in the ".dim-hero__stats" "css_element"

  Scenario: The tree narrows the listing in place, and the grouped organisation draws none
    # The tree's links are filters of this same page rather than ways out of it, and the grouped
    # organisation draws no tree, because its posters already name every child. Both halves are
    # here, and all of it works without the client module - which is the page a crawler and a
    # reader with JavaScript off receive.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then ".dim-vitrine__tree" "css_element" should not exist
    And I should see "Open category" in the "[data-region='dim-areas']" "css_element"
    When I am on the "TESTCAT|sort=name" "theme_dimensions > Category showcase" page
    Then I should see "Test category" in the ".dim-vitrine__tree" "css_element"
    And I should see "Open category" in the ".dim-vitrine__tree" "css_element"
    And I should not see "Restricted category"
    # The flat listing holds the whole subtree, and nothing is chosen yet: the page's own entry is
    # the marked one, which is also the entry that clears the filter.
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-vitrine-grid']" "css_element"
    And "//nav[@data-region='dim-vitrine-tree']//a[@aria-current='true'][contains(., 'Test category')]" "xpath_element" should exist
    When I click on "Open category" "link" in the ".dim-vitrine__tree" "css_element"
    # Still on this page - the hero has not changed - and the listing is the chosen subtree alone.
    Then I should see "Test category" in the "h1.dim-hero__title" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Resgate em Altura"
    # A flat order counts its courses in the tree's chosen category.
    And I should see "1 course in Open category" in the "[data-region='dim-vitrine-count']" "css_element"
    And "//nav[@data-region='dim-vitrine-tree']//a[@aria-current='true'][contains(., 'Open category')]" "xpath_element" should exist
    # It is a filter like the others, so the way out of it is offered and the first entry is it; one criterion alone
    # is undone by its chip, not by a clear-all link.
    And "//a[@data-action='dim-chip-remove'][@data-param='cat']" "xpath_element" should exist
    When I click on "Test category" "link" in the ".dim-vitrine__tree" "css_element"
    Then I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-vitrine-grid']" "css_element"

  Scenario: A poster opens its area's panel between the rows, listing the area's whole subtree
    # "By area" is the default organisation: a poster per child, with no number on it at rest,
    # then this category's own courses. Without JavaScript a poster is a link to this page
    # carrying its area, where the server draws the panel right after the poster - the area's
    # whole subtree, so the grandchild's course is in it, with its overline - and the open poster
    # and Close both return to the page without the panel.
    Given the following "categories" exist:
      | name           | category | idnumber |
      | Inner category | OPENCAT  | INNERCAT |
    And the following "courses" exist:
      | fullname    | shortname | category | customfield_dimensions_model |
      | Curso Fundo | FUNDO     | INNERCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then I should see "Areas in Test category" in the "[data-region='dim-areas']" "css_element"
    And I should see "View courses" in the "//article[@data-name='Open category']" "xpath_element"
    And I should not see "Restricted category"
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    And I should see "Tap an area to see its courses." in the "[data-region='dim-areas']" "css_element"
    And I should see "Courses in Test category" in the "[data-region='dim-direct']" "css_element"
    When I click on "//article[@data-name='Open category']//a[@data-region='dim-poster-control']" "xpath_element"
    Then the url should match "[?&]open=[0-9]+"
    And "//li[@data-region='dim-panel'][not(@hidden)]" "xpath_element" should exist
    # Right after its poster, in the same grid.
    And "//li[@data-region='dim-panel']/preceding-sibling::li[1]//article[@data-name='Open category']" "xpath_element" should exist
    And I should see "Courses in Open category" in the "[data-region='dim-panel-title']" "css_element"
    And I should see "Hide courses" in the "//article[@data-name='Open category']" "xpath_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And I should see "Curso Fundo" in the "[data-region='dim-panel-rail']" "css_element"
    And I should see "Inner category" in the "//ul[@data-region='dim-panel-rail']//article[contains(., 'Curso Fundo')]//span[contains(@class, 'dim-card__over')]" "xpath_element"
    # The strip names the subarea, and the hint has gone.
    And I should see "Inner category" in the "[data-region='dim-panel-also']" "css_element"
    And I should not see "Tap an area to see its courses."
    # The call to action is the area's own flat page.
    And the "href" attribute of "[data-region='dim-panel-cta']" "css_element" should contain "sort=recent"
    # Close returns to the page without the panel, landing on the poster.
    When I click on "//a[@data-action='dim-panel-close']" "xpath_element"
    Then the url should match "^(?!.*[?&]open=)"
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    # And so does the open poster itself.
    When I click on "//article[@data-name='Open category']//a[@data-region='dim-poster-control']" "xpath_element"
    And I click on "//article[@data-name='Open category']//a[@data-region='dim-poster-control']" "xpath_element"
    Then the url should match "^(?!.*[?&]open=)"
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    When I click on "//article[@data-name='Open category']//a[@data-region='dim-poster-control']" "xpath_element"
    And I click on "Go to Open category" "link"
    Then I should see "Open category" in the "h1.dim-hero__title" "css_element"
    And I should see "Curso Fundo" in the "[data-region='dim-vitrine-grid']" "css_element"

  Scenario: In the list view the server writes the panel right after its poster, before the next one
    # What this reads is the document order the server writes, which the list view keeps. That the
    # panel is also painted under its poster there - the one-track grid reading --dim-row-1 at every
    # width - is a stylesheet fact BrowserKit cannot see; category_scss_test pins that rule.
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
      | Third category  | TESTCAT  | THIRDCAT  |
    And the following "courses" exist:
      | fullname       | shortname | category  | customfield_dimensions_model |
      | Curso Segundo  | SEGU      | SECONDCAT | 1                          |
      | Curso Terceiro | TERC      | THIRDCAT  | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT|view=list" "theme_dimensions > Category showcase" page
    Then ".dim-vitrine.dim-vitrine--list" "css_element" should exist
    When I click on "//article[@data-name='Open category']//a[@data-region='dim-poster-control']" "xpath_element"
    Then "//li[@data-region='dim-panel'][not(@hidden)]" "xpath_element" should exist
    And "//li[@data-region='dim-panel']/preceding-sibling::li[1]//article[@data-name='Open category']" "xpath_element" should exist
    And "//li[@data-region='dim-panel']/following-sibling::li[1]//article[@data-name='Second category']" "xpath_element" should exist
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"

  Scenario: An open area past the twenty-fourth poster opens the reveal around it
    # The posters are cut at twenty-four, the rest behind a native disclosure. An address naming a
    # poster of the revealed part draws the disclosure open, or the panel would be drawn where
    # nobody can see it.
    Given the following "categories" exist:
      | name       | category | idnumber |
      | Area Big   | 0        | BIGCAT   |
      | Area 01    | BIGCAT   | AREA01   |
      | Area 02    | BIGCAT   | AREA02   |
      | Area 03    | BIGCAT   | AREA03   |
      | Area 04    | BIGCAT   | AREA04   |
      | Area 05    | BIGCAT   | AREA05   |
      | Area 06    | BIGCAT   | AREA06   |
      | Area 07    | BIGCAT   | AREA07   |
      | Area 08    | BIGCAT   | AREA08   |
      | Area 09    | BIGCAT   | AREA09   |
      | Area 10    | BIGCAT   | AREA10   |
      | Area 11    | BIGCAT   | AREA11   |
      | Area 12    | BIGCAT   | AREA12   |
      | Area 13    | BIGCAT   | AREA13   |
      | Area 14    | BIGCAT   | AREA14   |
      | Area 15    | BIGCAT   | AREA15   |
      | Area 16    | BIGCAT   | AREA16   |
      | Area 17    | BIGCAT   | AREA17   |
      | Area 18    | BIGCAT   | AREA18   |
      | Area 19    | BIGCAT   | AREA19   |
      | Area 20    | BIGCAT   | AREA20   |
      | Area 21    | BIGCAT   | AREA21   |
      | Area 22    | BIGCAT   | AREA22   |
      | Area 23    | BIGCAT   | AREA23   |
      | Area 24    | BIGCAT   | AREA24   |
      | Area 25    | BIGCAT   | AREA25   |
    And the following "courses" exist:
      | fullname    | shortname | category | customfield_dimensions_model |
      | Curso Vinte | VINTE     | AREA25   | 1                          |
    And I log in as "student1"
    And I am on the "BIGCAT" "theme_dimensions > Category showcase" page
    Then "//details[@data-region='dim-areas-more'][not(@open)]//article[@data-name='Area 25']" "xpath_element" should exist
    And I should see "Show 1 more area" in the "[data-region='dim-areas']" "css_element"
    When I click on "//article[@data-name='Area 25']//a[@data-region='dim-poster-control']" "xpath_element"
    Then "//details[@data-region='dim-areas-more'][@open]//li[@data-region='dim-panel'][not(@hidden)]" "xpath_element" should exist
    And I should see "Curso Vinte" in the "[data-region='dim-panel-rail']" "css_element"
    # Under a criterion the reveal opens by itself when an area behind it holds a result, and says
    # so; the twenty-four areas above it with none leave their grid.
    When I am on the "BIGCAT" "theme_dimensions > Category showcase" page
    And I set the field "Search courses" to "Vinte"
    And I press "Filter"
    Then "//details[@data-region='dim-areas-more'][@open]//article[@data-name='Area 25']" "xpath_element" should exist
    And I should see "1 more area with results" in the "[data-region='dim-areas-moresummary']" "css_element"
    And I should see "1 result" in the "//article[@data-name='Area 25']//span[@data-region='dim-poster-pill']" "xpath_element"
    And "//li[@hidden]/article[@data-name='Area 01']" "xpath_element" should exist
    And I should see "Nothing for “Vinte” in 24 other areas" in the "[data-region='dim-nomatch-summary']" "css_element"

  Scenario: The organisation chips reorder the whole listing without JavaScript
    # One chip at a time, and each one is a different first card over the same three courses: the
    # alphabet puts Combate first and the start date puts the course of tomorrow first. Asserting
    # only one of them would pass against a page that never reordered anything.
    Given the following "courses" exist:
      | fullname   | shortname | category | customfield_dimensions_model | startdate    |
      | Zulu Curso | ZULU      | TESTCAT  | 1                          | ##tomorrow## |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I set the field "A-Z" to "1"
    And I press "Apply"
    Then "//ul[@data-region='dim-vitrine-grid']/li[1][contains(., 'Combate a Incendio')]" "xpath_element" should exist
    And "[data-region='dim-areas']" "css_element" should not exist
    When I set the field "Recent" to "1"
    And I press "Apply"
    Then "//ul[@data-region='dim-vitrine-grid']/li[1][contains(., 'Zulu Curso')]" "xpath_element" should exist

  Scenario: A quick date option narrows the listing to the courses starting then
    # The quick options are ordinary links carrying the same two parameters the date fields carry,
    # computed on the server in the site's timezone - which is what keeps them working here, with
    # no JavaScript at all. The two courses of the background start today, so they are the control:
    # a filter that narrowed nothing would leave all three on the page.
    Given the following "courses" exist:
      | fullname        | shortname | category | customfield_dimensions_model | startdate    |
      | Curso de Amanha | AMANHA    | TESTCAT  | 1                          | ##tomorrow## |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    When I click on "Tomorrow" "link"
    # Under a criterion the line says what the page draws and where.
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Curso de Amanha" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Resgate em Altura"
    And "//a[@data-region='dim-vitrine-clear'][@hidden]" "xpath_element" should exist
    And "//*[@data-region='dim-line-chips']//a[@data-action='dim-chip-remove'][@data-param='period']" "xpath_element" should exist

  Scenario: Every control that narrows the listing is inside the filter disclosure
    # The line is the search box, the scope, the star and one disclosure holding the facet groups
    # and the date range. It is a native details, so it opens and closes with no script; it is
    # closed on load always, and the chips row under the line says what narrows.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then "//details[@data-region='dim-filters']//fieldset[@data-region='dim-vitrine-group']" "xpath_element" should exist
    And "//details[@data-region='dim-filters']//fieldset[@data-region='dim-date']//input[@type='date']" "xpath_element" should exist
    And "//details[@data-region='dim-filters'][not(@open)]" "xpath_element" should exist
    # The toolbar's order: the one line, then the notice, then the chips row.
    And "//form[@data-region='dim-vitrine-toolbar']/div[@data-region='dim-line']/following-sibling::p[@data-region='dim-toolbar-notice']/following-sibling::div[@data-region='dim-line-chips']" "xpath_element" should exist
    When I set the field "In-person" to "1"
    And I press "Filter"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And "//details[@data-region='dim-filters'][not(@open)]" "xpath_element" should exist

  Scenario: The course index draws every top-level category a learner may discover as a card
    # A course can never sit in the top-level pseudo-category, so at the root "there is no
    # course here" is always true and the page is a directory. The withheld top-level
    # category is the control: it proves the directory is gated rather than a listing of
    # everything, and without it the absence of a toolbar would be the only thing tested.
    Given the following "categories" exist:
      | name           | category | idnumber |
      | Withheld topic | 0        | HIDECAT  |
    And the following "courses" exist:
      | fullname     | shortname | category | customfield_dimensions_model |
      | Curso Oculto | HIDC      | HIDECAT  | 1                          |
    And the category "HIDECAT" is "unlisted" for discoverability
    And I log in as "student1"
    When I am on course index
    Then ".dim-vitrine--directory" "css_element" should exist
    And "//article[@data-region='dim-area'][@data-name='Test category']" "xpath_element" should exist
    And I should see "Test category" in the "[data-region='dim-areas']" "css_element"
    And I should see "Public category" in the "[data-region='dim-areas']" "css_element"
    # And no number on any poster: a poster names an area and says nothing about how much is inside
    # it at rest. Scoped to the areas, because the hero above them still prints the root's own
    # total and its label.
    And I should not see "courses and events" in the "[data-region='dim-areas']" "css_element"
    And I should see "View courses" in the "//article[@data-name='Test category']" "xpath_element"
    And I should not see "Withheld topic"
    And I should not see "Curso Oculto"
    # Nothing of the listing but its search line: at the root there is no course to filter or to
    # page, and nothing is starred until a panel shows courses.
    And ".dim-toolbar--searchonly" "css_element" should exist
    And "[data-region='dim-filters']" "css_element" should not exist
    And ".dim-card__star" "css_element" should not exist
    # The root's posters open panels too, drawn by the server for ?open= without any script - the
    # poster is a link to this same page carrying its area.
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    When I click on "//article[@data-name='Test category']//a[@data-region='dim-poster-control']" "xpath_element"
    Then "//li[@data-region='dim-panel'][not(@hidden)]" "xpath_element" should exist
    And the url should match "[?&]open=[0-9]+"
    And I should see "Resgate em Altura" in the "[data-region='dim-panel-rail']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And I should not see "Restricted One"
    # The star is on the panel's courses at the root as well.
    And "[data-region='dim-panel-rail'] [data-action='dim-star']" "css_element" should exist

  Scenario: A search at the course index counts its matches on the posters and narrows their panels
    # The root reads its search and nothing else. Without JavaScript its search line is a form.
    # The poster the term misses leaves the grid for the summary - still in the document, hidden -
    # and a panel opened under the search lists the matches alone.
    Given I log in as "student1"
    And I am on course index
    When I set the field "Search courses" to "Resgate"
    And I click on "Search" "button" in the "[data-region='dim-vitrine-toolbar']" "css_element"
    Then the url should match "[?&]q=Resgate"
    And I should see "1 result" in the "//article[@data-name='Test category']" "xpath_element"
    And "//li[@hidden]/article[@data-name='Public category']" "xpath_element" should exist
    And "//details[@data-region='dim-areas-nomatch'][not(@hidden)]" "xpath_element" should exist
    And "[data-region='dim-filters']" "css_element" should not exist
    When I click on "//article[@data-name='Test category']//a[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Resgate em Altura" in the "[data-region='dim-panel-rail']" "css_element"
    And I should not see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"

  Scenario: A filter group narrows the listing and both of its numbers
    # The online course here sits directly in this category on purpose: the one in the
    # subcategory is behind its poster whatever the filter says, so it cannot be the control for
    # a filter that removes it.
    Given the following "courses" exist:
      | fullname     | shortname | category | customfield_dimensions_model | customfield_dimensions_modality |
      | Curso Online | ONLI      | TESTCAT  | 1                          | 1                      |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And I should see "In-person" in the ".dim-toolbar" "css_element"
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    When I set the field "In-person" to "1"
    And I press "Filter"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Online"
    And "//*[@data-region='dim-line-chips']//a[@data-action='dim-chip-remove'][starts-with(@data-param,'cf_')]" "xpath_element" should exist

  Scenario: The search box narrows the listing, and the page shows where its results are
    # Two halves. The first is the search itself, over two courses of this category so that one of
    # them is a real control. In the second a term only a subcategory's course matches leaves
    # nothing sitting directly here, so "Courses in" goes - section and skip link - and the area
    # that holds the result says how many on its poster.
    # A search alone leaves the filter disclosure closed: the term is visible in its own box.
    Given the following "courses" exist:
      | fullname            | shortname | category | customfield_dimensions_model |
      | Salvamento Aquatico | SALV      | TESTCAT  | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I set the field "Search courses" to "Resgate"
    And I press "Filter"
    Then I should see "1 course for “Resgate”: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Salvamento Aquatico"
    And "//details[@data-region='dim-filters'][not(@open)]" "xpath_element" should exist
    When I set the field "Search courses" to "Incendio"
    And I press "Filter"
    Then I should see "1 course for “Incendio”: 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And "//section[@data-region='dim-direct'][@hidden]" "xpath_element" should exist
    And "//a[@data-region='dim-direct-skip'][@hidden]" "xpath_element" should exist
    And I should see "1 result" in the "//article[@data-name='Open category']//span[@data-region='dim-poster-pill']" "xpath_element"
    And "//article[@data-name='Open category']//a[@data-region='dim-poster-control'][@aria-describedby]" "xpath_element" should exist

  Scenario: A search that zeroes an area names it in the summary, and the count line offers the flat list
    # The area the term misses leaves its grid - its item hidden, never removed - and joins one
    # closed disclosure after the posters, as a link to its own page without the term (carrying it
    # would open an empty page). The count line says what matched and where, and "See all as a
    # list" is the flat Recent order carrying the term.
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
    And the following "courses" exist:
      | fullname      | shortname | category  | customfield_dimensions_model |
      | Curso Segundo | SEGU      | SECONDCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//details[@data-region='dim-areas-nomatch'][@hidden]" "xpath_element" should exist
    When I set the field "Search courses" to "Incendio"
    And I press "Filter"
    Then I should see "1 result" in the "//article[@data-name='Open category']//span[@data-region='dim-poster-pill']" "xpath_element"
    And "//li[not(@hidden)]/article[@data-name='Open category']" "xpath_element" should exist
    And "//li[@hidden]/article[@data-name='Second category']" "xpath_element" should exist
    And "//details[@data-region='dim-areas-nomatch'][not(@hidden)][not(@open)]" "xpath_element" should exist
    And I should see "Nothing for “Incendio” in one other area" in the "[data-region='dim-nomatch-summary']" "css_element"
    And the "href" attribute of "//ul[@data-region='dim-nomatch-list']//a[normalize-space(.)='Second category']" "xpath_element" should contain "categoryid="
    And the "href" attribute of "//ul[@data-region='dim-nomatch-list']//a[normalize-space(.)='Second category']" "xpath_element" should not contain "q="
    And I should see "1 course for “Incendio”: 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And "//*[@data-region='dim-line-chips']//span[@data-region='dim-vitrine-seeall']/a" "xpath_element" should exist
    And the "href" attribute of "//span[@data-region='dim-vitrine-seeall']/a" "xpath_element" should contain "sort=recent"
    When I click on "See all as a list" "link" in the "[data-region='dim-line-chips']" "css_element"
    Then I should see "1 course for “Incendio” in Test category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-vitrine-grid']" "css_element"
    And "[data-region='dim-areas']" "css_element" should not exist

  Scenario: An open area a search zeroes stays open, suggests where the results are and closes into the summary
    # The open area stays: the reader is looking at it, so a criterion that zeroes it leaves it in
    # its grid, wearing "0 results", and its panel says so and offers the areas that do hold
    # results. Without JavaScript every one of those is a link:
    # a suggestion reopens the page with its area, and Close lands the page without the panel, the
    # zeroed area now inside the summary (the fragment points there).
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
    And the following "courses" exist:
      | fullname      | shortname | category  | customfield_dimensions_model |
      | Curso Segundo | SEGU      | SECONDCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-name='Second category']//a[@data-region='dim-poster-control']" "xpath_element"
    And I set the field "Search courses" to "Incendio"
    And I press "Filter"
    Then the url should match "[?&]open=[0-9]+"
    And "//li[not(@hidden)]/article[@data-name='Second category']" "xpath_element" should exist
    And I should see "0 results" in the "//article[@data-name='Second category']//span[@data-region='dim-poster-pill']" "xpath_element"
    And I should see "No results for “Incendio” in Second category." in the "[data-region='dim-panel-body']" "css_element"
    And I should see "Results in:" in the "[data-region='dim-panel-suggestions']" "css_element"
    And the "href" attribute of "//a[@data-action='dim-panel-close']" "xpath_element" should contain "#dim-nomatch"
    When I click on "//a[@data-action='dim-panel-openarea'][contains(., 'Open category')]" "xpath_element"
    Then I should see "Courses in Open category" in the "[data-region='dim-panel-title']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And "//li[@hidden]/article[@data-name='Second category']" "xpath_element" should exist
    And "//ul[@data-region='dim-nomatch-list']/li[not(@hidden)]/a[normalize-space(.)='Second category']" "xpath_element" should exist
    When I click on "//a[@data-action='dim-panel-close']" "xpath_element"
    Then the url should match "^(?!.*[?&]open=)"
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    And I should see "Nothing for “Incendio” in one other area" in the "[data-region='dim-nomatch-summary']" "css_element"

  Scenario: The listing pages through real links
    # On the grouped page the pager walks the direct courses, so the two courses it pages through
    # are two of this category's own; core lists the newest of a category first. The count line
    # counts the whole subtree at rest, so what is asserted is the rows paged.
    Given the following "courses" exist:
      | fullname            | shortname | category | customfield_dimensions_model |
      | Salvamento Aquatico | SALV      | TESTCAT  | 1                          |
    And I log in as "student1"
    When I am on the "TESTCAT|perpage=1" "theme_dimensions > Category showcase" page
    Then I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Salvamento Aquatico" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Resgate em Altura"
    When I click on "2" "link" in the ".dim-vitrine__pager" "css_element"
    Then I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Salvamento Aquatico"

  Scenario: With the showcase switched off the category page is Boost Union's again
    Given the following config values are set as admin:
      | categorypageenabled | 0 | theme_dimensions |
    And I log in as "student1"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then "body.dim-category" "css_element" should not exist
    And ".dim-vitrine" "css_element" should not exist
    And I should see "Resgate em Altura"
    And I should not see "Restricted One"

  Scenario: A manager's category tabs sit inside the hero and the band below it is gone
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then I should see "Test category" in the "h1.dim-hero__title" "css_element"
    # The tabs are core's own secondary navigation, drawn as the hero's last row.
    And I should see "Category" in the ".dim-hero__tabs" "css_element"
    And I should see "Settings" in the ".dim-hero__tabs" "css_element"
    And ".secondary-navigation" "css_element" should not exist
    And I should see "Customise category" in the ".dim-hero" "css_element"

  Scenario: A learner's hero carries the single category tab and no pencil
    Given I log in as "student1"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then I should see "Category" in the ".dim-hero__tabs" "css_element"
    And I should not see "Settings" in the ".dim-hero__tabs" "css_element"
    And ".secondary-navigation" "css_element" should not exist
    And I should not see "Customise category"

  Scenario: A manager gives the category a tagline, a colour pair and its two descriptions
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I set the field "Tagline" to "Formação continuada da organização"
    # The pair is the category's own only once the box is ticked; unticked, it inherits.
    And I set the field "Own colours" to "1"
    And I set the field "Background colour" to "#12484f"
    And I set the field "Text colour" to "#ffffff"
    And I set the field "Public description" to "Cursos e eventos abertos da organização"
    # The category's own description is core's field, edited on this same screen.
    And I set the field "Description" to "Área da organização na plataforma"
    And I press "Save changes"
    Then I should see "Category appearance saved."
    And I should see "Formação continuada da organização" in the ".dim-hero" "css_element"
    And I should see "Área da organização na plataforma" in the ".dim-hero" "css_element"
    # The colour is written as custom properties in the header's one inline style.
    And the "style" attribute of "header.dim-hero" "css_element" should contain "#12484f"
    # And the editor opens again on what was saved, both fields included.
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    Then the field "Public description" matches value "Cursos e eventos abertos da organização"

  @javascript @_file_upload
  Scenario: A manager uploads a badge for the category and the hero shows it
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Badge 1" filemanager
    And I press "Save changes"
    Then I should see "Category appearance saved."
    # The alternative text is the file name without its extension, read as words: that is the
    # one thing the person uploading it controls, and it is what the help text promises.
    And "//ul[contains(@class, 'dim-hero__badges')]//img[@alt='gd logo']" "xpath_element" should exist

  @javascript @_file_upload
  Scenario: A manager uploads a cover and a film, and the category's card draws the preview
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Card cover" filemanager
    And I upload "theme/dimensions/tests/fixtures/cover.mp4" file to "Cover video" filemanager
    And I press "Save changes"
    Then I should see "Category appearance saved."
    # A category's poster is drawn by its parent's listing, and this category's parent is the root.
    When I am on course index
    # The still is the cover and not the hero image: the first link of the fallback chain.
    Then "//article[@data-region='dim-area']//img[contains(@src, '/categorycover/')]" "xpath_element" should exist
    # The film is in the same poster, nothing of it fetched, and no browser bar over it. Its three
    # controls name the area they play, because a page of posters holds many films.
    And "//video[@data-region='dim-cover-video'][@preload='none'][not(@controls)]/source[@type='video/mp4']" "xpath_element" should exist
    And "Play the film of Test category" "button" should exist
    And "Mute the film of Test category" "button" should exist
    And "Film of Test category in full screen" "button" should exist
    # The cluster is shown by the cover module's own mark on the body, never by the vitrine's.
    And "body.dim-cover-js" "css_element" should exist
    # And the film really runs: the control reports itself pressed, which the module writes when
    # the film starts and takes back if the browser refuses to play it at all. The fixture is
    # tests/fixtures/cover.mp4, an 11-second MP4, on purpose: the WebM beside it decodes and
    # play() resolves, but in a Chromium browser its currentTime was seen never to advance.
    When I click on "Play the film of Test category" "button"
    Then the "aria-pressed" attribute of "Play the film of Test category" "button" should contain "true"

  @javascript @_file_upload
  Scenario: A finger pauses the film in place, and the sound control leaves it paused
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Card cover" filemanager
    And I upload "theme/dimensions/tests/fixtures/cover.mp4" file to "Cover video" filemanager
    And I press "Save changes"
    Then I should see "Category appearance saved."
    When I am on course index
    # A finger has no hover: the browser reports the pointer leaving the card as the finger lifts,
    # before the click it belongs to. A module that read that lift as a departure would pause,
    # rewind and forget the film, so every tap below would meet a card at rest and start it again.
    # The tap step dispatches the real sequence - pointerover, pointerdown, pointerup, pointerout,
    # click - because Selenium drives a mouse and no click step can produce it. The fixture is
    # cover.mp4, for the reason given in the scenario above.
    And I tap "Play the film of Test category" "button" as a touch pointer
    Then the "aria-pressed" attribute of "Play the film of Test category" "button" should contain "true"
    # The second tap holds the frame rather than restarting the film.
    When I tap "Play the film of Test category" "button" as a touch pointer
    Then the "aria-pressed" attribute of "Play the film of Test category" "button" should contain "false"
    # And the sound control on a paused film changes the sound and nothing else: it must not
    # restart the film from its first frame, taking away the frame the reader stopped to see.
    When I tap "Mute the film of Test category" "button" as a touch pointer
    Then the "aria-pressed" attribute of "Play the film of Test category" "button" should contain "false"
    # The control case: on a card at rest the sound control still starts the film, and on one that
    # is running it leaves it running.
    When I tap "Play the film of Test category" "button" as a touch pointer
    And I tap "Mute the film of Test category" "button" as a touch pointer
    Then the "aria-pressed" attribute of "Play the film of Test category" "button" should contain "true"

  @javascript @_file_upload
  Scenario: A manager removes the card cover and the card falls back to the background image
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Background image" filemanager
    And I upload "lib/tests/fixtures/1.jpg" file to "Card cover" filemanager
    And I press "Save changes"
    Then I should see "Category appearance saved."
    When I am on course index
    Then "//article[@data-region='dim-area']//img[contains(@src, '/categorycover/')]" "xpath_element" should exist
    # The single-file fields are file managers because a file picker can only replace its file,
    # never remove it. The file manager deletes through core's own contextual menu, and the save
    # empties the stored area.
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    # Six file managers load their lists by AJAX after the page does, and core's delete step clicks
    # the file's node as soon as it exists - while a manager is still loading the node is in the
    # document and hidden, and the click intermittently dies with "element not interactable".
    And I wait until "//div[@id='fitem_id_categorycover']//div[contains(@class, 'filemanager') and contains(@class, 'fm-loaded')]" "xpath_element" exists
    # Core's "I delete ... from ... filemanager" presses the confirmation's Yes the moment it exists,
    # while core/modal is still showing it (aria-hidden, fading in), and Chrome usually answers
    # "element not interactable". These are that step's three presses, with a wait for the modal's
    # own pending show promise before the last one.
    And I click on "1.jpg" "link" in the "#fitem_id_categorycover" "css_element"
    And I click on "Delete" "button" in the ".moodle-dialogue-focused" "css_element"
    And I wait until the page is ready
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    And I press "Save changes"
    Then I should see "Category appearance saved."
    When I am on course index
    Then "//article[@data-region='dim-area']//img[contains(@src, '/categorycover/')]" "xpath_element" should not exist
    # The still falls back to the background image, which is the next link of the chain and the
    # control: a card with no picture at all would pass the line above on its own.
    And "//article[@data-region='dim-area']//img[contains(@src, '/categoryhero/')]" "xpath_element" should exist

  Scenario: A pair under the WCAG AA floor is refused with its ratio, and nothing is saved
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I set the field "Own colours" to "1"
    And I set the field "Background colour" to "#777777"
    And I set the field "Text colour" to "#888888"
    And I press "Save changes"
    Then I should see "the minimum is 4.50:1"
    And I should not see "Category appearance saved."
    And the field "Background colour" matches value "#777777"

  @javascript
  Scenario: The contrast panel grades the pair as it is typed, and a corrected pair passes
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I set the field "Own colours" to "1"
    And I set the field "Background colour" to "#777777"
    And I set the field "Text colour" to "#888888"
    # The panel is the module's: without JavaScript nothing above this line draws it.
    Then I should see "Fail" in the ".dim-contrast" "css_element"
    When I set the field "Background colour" to "#12484f"
    And I set the field "Text colour" to "#ffffff"
    # That pair on white reads 10.17:1, which is over AAA and so reads "Excellent" rather than
    # "Pass"; the ratio itself is the fixture's, computed in the browser.
    Then I should see "Excellent" in the ".dim-contrast" "css_element"
    And I should see "10.2:1" in the ".dim-contrast" "css_element"

  @javascript
  Scenario: A colour without its hash is flagged at the field, before anything is saved
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    And I set the field "Own colours" to "1"
    And I set the field "Background colour" to "12484f"
    # No save, no reload: the message is there while the value is.
    Then I should see "Enter a colour as #rrggbb."
    And I should not see "Category appearance saved."
    When I set the field "Background colour" to "#12484f"
    Then I should not see "Enter a colour as #rrggbb."

  @javascript
  Scenario: The colours are a section of their own, with the panel and the pair preview inside it
    Given I log in as "admin"
    When I am on the "TESTCAT" "theme_dimensions > Category appearance" page
    # The panel is the module's and it is mounted inside the colours section, which is the box
    # the stylesheet lays out in two columns; the preview is the row above it. Nothing is moved:
    # the fields are where the form drew them and the section is where they were declared.
    Then "Accessibility contrast" "text" should exist in the "Colours" "fieldset"
    And "Preview of the pair on the solid band" "text" should exist in the "Colours" "fieldset"
    And "Background colour" "field" should exist in the "Colours" "fieldset"
    And "Tagline" "field" should exist in the "Appearance" "fieldset"
    # And the band follows the pair as it is typed. The browser writes a style attribute back in
    # its own spelling, so the assertion is the colour Chrome serialises, not the hex typed in.
    When I set the field "Own colours" to "1"
    And I set the field "Background colour" to "#12484f"
    Then the "style" attribute of ".dim-pair-preview" "css_element" should contain "rgb(18, 72, 79)"
    When I set the field "Text colour" to "#ffe600"
    Then the "style" attribute of ".dim-pair-preview" "css_element" should contain "rgb(255, 230, 0)"
    # A value that is not a colour leaves the last valid pair on the band rather than blanking it.
    # The three-digit spelling is deliberate: Behat empties a field one character at a time, so
    # what the band keeps is the last spelling that parsed while the field was being erased - and
    # the way down from "#124" is "#12", "#1", "#", none of which is a colour.
    When I set the field "Background colour" to "#124"
    Then the "style" attribute of ".dim-pair-preview" "css_element" should contain "rgb(17, 34, 68)"
    When I set the field "Background colour" to "nope"
    Then the "style" attribute of ".dim-pair-preview" "css_element" should contain "rgb(17, 34, 68)"
    And I should see "Enter a colour as #rrggbb."

  Scenario: A visitor reaches the public page of a public category
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the course "PUBC" has a course image
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then I should see "Curso Público" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Public access" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Learn more" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The listed course of the same category is what a visitor may not be told about.
    And I should not see "Curso Interno"
    And "Username" "field" should not exist
    And ".dim-card__star" "css_element" should not exist
    # The cover goes through the theme's own file route: core's own answers a visitor with the
    # login page while forcelogin is on, which is what this scenario has switched on. This scenario
    # runs without a browser and loads no image, so the address is what is pinned here.
    # Proved on a course that has a landing page model (the Background gives PUBC one); the model-less
    # course, which the cover area exists for, is pinned at resolve() level by publicfiles_test -
    # the address a card mints does not depend on the model.
    And the "src" attribute of "img.dim-card__image" "css_element" should contain "/theme_dimensions/cover/"

  Scenario: A visitor's merely listed area is a text poster whose panel lists its public courses alone
    # A visitor may be told that a listed area exists, but it has no page a visitor may open - so
    # its poster carries none of its files (the public route would refuse them) and its
    # panel is the public page's own listing narrowed to the area, with "See all as a list" where
    # the call to action to the area's page would be. The non-public course is the control.
    Given the following "categories" exist:
      | name         | category | idnumber  |
      | Listed child | PUBCAT   | LISTCHILD |
    And the following "courses" exist:
      | fullname      | shortname | category  | customfield_dimensions_model |
      | Curso Aberto  | ABERTO    | LISTCHILD | 1                          |
      | Curso Fechado | FECHADO   | LISTCHILD | 1                          |
    And the course "ABERTO" is "public" for discoverability
    And the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then "//article[@data-name='Listed child'][contains(@class, 'dim-poster--text')]" "xpath_element" should exist
    And "//article[@data-name='Listed child']//img" "xpath_element" should not exist
    When I click on "//article[@data-name='Listed child']//a[@data-region='dim-poster-control']" "xpath_element"
    Then "Username" "field" should not exist
    And I should see "Curso Aberto" in the "[data-region='dim-panel-rail']" "css_element"
    And I should not see "Curso Fechado"
    And I should see "See all as a list" in the "[data-region='dim-panel-foot']" "css_element"
    And "[data-region='dim-panel-title'] a" "css_element" should not exist

  Scenario: A visitor's search keeps the public page's areas and names the ones it misses
    # A visitor's posters under a criterion are the areas the page shows at rest - the children
    # holding a public course - and the term only counts in them. So an area the term misses is
    # still named, in the summary, as a link to its page when it is public and as plain text when
    # it is merely listed; and a child with no public course is on neither page.
    Given the following "categories" exist:
      | name        | category | idnumber |
      | Public area | PUBCAT   | PUBAREA  |
      | Quiet area  | PUBCAT   | QUIETCAT |
      | Listed area | PUBCAT   | LISTAREA |
      | Empty area  | PUBCAT   | EMPTYAR  |
    And the following "courses" exist:
      | fullname         | shortname | category | customfield_dimensions_model |
      | Curso Aberto Um  | ABERTO1   | PUBAREA  | 1                          |
      | Curso Silencioso | SILENC    | QUIETCAT | 1                          |
      | Curso Listado    | LISTADO   | LISTAREA | 1                          |
      | Curso Fechado    | FECHADO   | EMPTYAR  | 1                          |
    And the category "PUBAREA" is "public" for discoverability
    And the category "QUIETCAT" is "public" for discoverability
    And the category "EMPTYAR" is "public" for discoverability
    And the course "ABERTO1" is "public" for discoverability
    And the course "SILENC" is "public" for discoverability
    And the course "LISTADO" is "public" for discoverability
    And the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then "//article[@data-name='Quiet area']" "xpath_element" should exist
    And "//article[@data-name='Empty area']" "xpath_element" should not exist
    When I set the field "Search courses" to "Aberto"
    And I press "Filter"
    Then "Username" "field" should not exist
    And I should see "1 result" in the "//article[@data-name='Public area']//span[@data-region='dim-poster-pill']" "xpath_element"
    And I should see "Nothing for “Aberto” in 2 other areas" in the "[data-region='dim-nomatch-summary']" "css_element"
    And "//ul[@data-region='dim-nomatch-list']/li/a[normalize-space(.)='Quiet area']" "xpath_element" should exist
    And "//ul[@data-region='dim-nomatch-list']/li[normalize-space(.)='Listed area'][not(a)]" "xpath_element" should exist
    And "//article[@data-name='Empty area']" "xpath_element" should not exist
    And I should see "1 course for “Aberto”: 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"

  Scenario: The public description a manager writes is what a shared link shows, and the tagline is not
    Given I log in as "admin"
    And I am on the "PUBCAT" "theme_dimensions > Category appearance" page
    And I set the field "Public description" to "Cursos e eventos abertos ao público"
    And I set the field "Tagline" to "Uma tagline para quem já está na página"
    And I press "Save changes"
    And I log out
    And the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then "//meta[@property='og:description'][@content='Cursos e eventos abertos ao público']" "xpath_element" should exist
    # The tagline is written for a reader who is already on the page and does not feed the
    # preview: the public description is the only source of og:description.
    And "//meta[@property='og:description'][contains(@content, 'Uma tagline')]" "xpath_element" should not exist
    And I should see "Uma tagline para quem já está na página" in the ".dim-hero" "css_element"

  Scenario: A visitor asking for the public page of a listed category gets the login form
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "TESTCAT" "theme_dimensions > Public category page" page
    Then "Username" "field" should exist
    And ".dim-vitrine__grid" "css_element" should not exist
    And I should not see "Resgate em Altura"

  Scenario: A visitor asking for the public page of an unlisted category gets the same login form
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "RESTCAT" "theme_dimensions > Public category page" page
    Then "Username" "field" should exist
    And ".dim-vitrine__grid" "css_element" should not exist
    And I should not see "Restricted Inner"

  Scenario: A visitor asking for a category that does not exist gets the same login form again
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I visit "/theme/dimensions/category.php?id=999999"
    Then "Username" "field" should exist
    And ".dim-vitrine__grid" "css_element" should not exist

  Scenario: A visitor who types the showcase address of a public category lands on its public page
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "PUBCAT" "theme_dimensions > Category showcase" page
    Then I should see "Curso Público" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Interno"
    And "Username" "field" should not exist

  Scenario: A visitor who types the showcase address of a listed category still meets the login form
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then "Username" "field" should exist
    And ".dim-vitrine__grid" "css_element" should not exist

  Scenario: A logged-in learner on the public address lands on the showcase instead
    Given I log in as "student1"
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then I should see "Curso Público" in the "[data-region='dim-vitrine-grid']" "css_element"
    # One surface per viewer class: the showcase shows the listed course and offers the star.
    And I should see "Curso Interno" in the "[data-region='dim-vitrine-grid']" "css_element"
    And ".dim-card__star" "css_element" should exist

  Scenario: A visitor asking for the root of the course index meets the login form while it is closed
    # The root is public only on an explicitly stored 1 (categoryrootpublic), so this is what
    # every site gets until an administrator says otherwise.
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "theme_dimensions > Public category root" page
    Then "Username" "field" should exist
    And ".dim-vitrine--directory" "css_element" should not exist
    And I should not see "Public category"

  Scenario: With the root opened a visitor gets a directory of the public top-level categories
    # The listed top-level category is the control: the population admits it - a visitor may be
    # told it exists - but it has no page a visitor may open, so it is not a card.
    Given the following config values are set as admin:
      | forcelogin         | 1 |                            |
      | categoryrootpublic | 1 | theme_dimensions |
    When I am on the "theme_dimensions > Public category root" page
    Then "Username" "field" should not exist
    And I should see "Public category" in the ".dim-vitrine--directory" "css_element"
    And I should not see "Test category"
    # A directory names categories and no course at all, and it has nothing to filter or page -
    # only its search line, which a visitor submits.
    And I should not see "Curso Público"
    And ".dim-toolbar--searchonly" "css_element" should exist
    And "[data-region='dim-filters']" "css_element" should not exist
    And ".dim-card__star" "css_element" should not exist

  Scenario: With the root opened a visitor who types the course index is forwarded to it
    # The after_config forward has no unit test by construction - it reads the script and ends
    # the request with a redirect. The scenario above reaches the public address directly; this
    # one reaches core's.
    Given the following config values are set as admin:
      | forcelogin         | 1 |                            |
      | categoryrootpublic | 1 | theme_dimensions |
    When I visit "/course/index.php"
    Then "Username" "field" should not exist
    And I should see "Public category" in the ".dim-vitrine--directory" "css_element"
    And I should not see "Test category"
