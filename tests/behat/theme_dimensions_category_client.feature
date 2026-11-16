@theme @theme_dimensions @theme_dimensions_category
Feature: With JavaScript on, the category showcase narrows, pages and stars without leaving the page
  In order to find a course without waiting for a page load between every choice
  As somebody studying on a Dimensions site
  I need the showcase's controls to work in place, and what I choose to survive a reload

  # These are thin smoke tests of the client module: that the wiring reaches the server and the
  # page moves. Every rule about what is listed is tested in PHPUnit, and the same controls are
  # tested without JavaScript in theme_dimensions_category.feature - which is the page
  # a crawler and a reader without this module still get.

  Background:
    Given the following config values are set as admin:
      | theme                | dimensions |                            |
      | categoryfilterfields | dimensions_modality           | theme_dimensions |
    And the following "categories" exist:
      | name                | category | idnumber |
      | Test category       | 0        | TESTCAT  |
      | Open category       | TESTCAT  | OPENCAT  |
    # Modality 1 is online, 2 is in-person: the option index the select field stores.
    # Two courses sit directly in the listed category and one inside its subcategory. The split
    # matters because the grouped page lists the two direct ones, so the third is what a panel
    # opens and what the others must not count. Without a second direct course every "narrows to
    # one" assertion below would be narrowing to nothing. Every grid assertion is scoped to the
    # courses' grid by its hook: the areas' grid comes first in the page and carries the same class.
    And the following "courses" exist:
      | fullname           | shortname | category | customfield_dimensions_model | customfield_dimensions_modality |
      | Resgate em Altura  | RESG      | TESTCAT  | 1                          | 2                      |
      | Curso Online       | ONLI      | TESTCAT  | 1                          | 1                      |
      | Combate a Incendio | INCEND    | OPENCAT  | 1                          | 1                      |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |

  @javascript
  Scenario: Choosing a filter pill narrows the listing and its count without pressing Filter
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    # Every control that narrows the listing lives inside one native disclosure, which a request
    # with no filter set renders closed - so a real pointer opens it before it can reach a pill,
    # and so must this. The summary is the button.
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    # The pill's radio is visually hidden behind its label, which is what a pointer actually
    # hits - and what Selenium must hit too: a click sent to the input itself is intercepted by
    # the pill track. A keyboard reader never needs the click at all; the native radio group
    # moves with the arrow keys, which is why the input is a real radio and not a button.
    And I click on "//label[contains(@class, 'dim-pill')][contains(normalize-space(.), 'In-person')]" "xpath_element"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Online"
    # The group's own "All" pill has to survive the repaint: it is the only way back out of the
    # facet, and it is not one of the options the server returns - it is a triple beside them.
    # "should be visible", never "should exist": the repaint hides a pill it did not recognise
    # rather than removing it, so an existence check passes over the very defect this guards.
    And "//fieldset[starts-with(@data-name,'cf_')]//label[normalize-space(.)='All']" "xpath_element" should be visible
    # One criterion in force: the clear-all link stays hidden and the field's chip undoes it. The chip
    # has to be in the document and the client has to un-hide the row after a filter it applied.
    And "//a[@data-region='dim-vitrine-clear'][@hidden]" "xpath_element" should exist
    And "//*[@data-region='dim-line-chips']//a[@data-action='dim-chip-remove'][starts-with(@data-param,'cf_')]" "xpath_element" should be visible

  @javascript
  Scenario: Pointing at a scope cell lights the cards it would keep and lets the others recede
    # The filter line's preview: a mouse over a scope cell marks the cards on screen that cell would keep
    # and dims the rest, asking the server nothing; moving off the line takes every mark away. The two
    # direct courses are the grid's, one of them the reader's own, so "My courses" keeps one and dims one.
    Given the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | RESG   | student |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I hover "//fieldset[@data-role='scope']//label[starts-with(normalize-space(.), 'My courses')]" "xpath_element"
    Then "//section[contains(@class, 'dim-vitrine--previewing')]" "xpath_element" should exist
    And "//article[contains(@class, 'dim-card--lit')][.//h3[normalize-space(.)='Resgate em Altura']]" "xpath_element" should exist
    And "//article[contains(@class, 'dim-card--dim')][.//h3[normalize-space(.)='Curso Online']]" "xpath_element" should exist
    And "//article[contains(@class, 'dim-card--dim')][.//h3[normalize-space(.)='Resgate em Altura']]" "xpath_element" should not exist
    # Nothing was applied: the count still counts every course.
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    When I hover "//h1" "xpath_element"
    Then "//article[contains(@class, 'dim-card--dim')]" "xpath_element" should not exist
    And "//section[contains(@class, 'dim-vitrine--previewing')]" "xpath_element" should not exist

  @javascript
  Scenario: Typing in the search box narrows the listing where it stands
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    When I press "Search courses"
    And I set the field "Search courses" to "Online"
    Then I should see "1 course for “Online”: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Curso Online" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Resgate em Altura"

  @javascript
  Scenario: The star round-trips: it relabels itself, survives a reload and reaches the favourites toggle
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "Add to favourites: Resgate em Altura" "button"
    # The button relabels itself from the two spellings the card already carried, with no round trip.
    Then the "aria-pressed" attribute of "Remove from favourites: Resgate em Altura" "button" should contain "true"
    When I reload the page
    Then the "aria-pressed" attribute of "Remove from favourites: Resgate em Altura" "button" should contain "true"
    # The favourites toggle sits on the search line, outside the filter disclosure: its number is in
    # its accessible name, and pressing it narrows the listing in place.
    And the "aria-pressed" attribute of "[data-region='dim-line-star']" "css_element" should contain "false"
    And "//*[@data-region='dim-fav-count'][normalize-space(.)='1']" "xpath_element" should exist
    When I click on "[data-region='dim-line-star']" "css_element"
    Then the "aria-pressed" attribute of "[data-region='dim-line-star']" "css_element" should contain "true"
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Online" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The badge counts what the Filters panel holds, and the star is on the line.
    And "//*[@data-region='dim-filtercount'][@hidden]" "xpath_element" should exist
    When I click on "[data-region='dim-line-star']" "css_element"
    Then the "aria-pressed" attribute of "[data-region='dim-line-star']" "css_element" should contain "false"
    And I should see "Curso Online" in the "[data-region='dim-vitrine-grid']" "css_element"

  @javascript
  Scenario: Load more appends the NEXT page, one page at a time, and keeps the focus
    # A third course, in this scenario alone, so that "one more page" and "all the rest" are
    # different answers. Over two courses at perpage=1 they are the same, and an assertion that
    # only asks whether both names are on the page cannot tell an append of page one from an
    # append of the whole listing over the row already on screen.
    #
    # The count line counts the whole subtree at rest, the same sentence on every page, so the
    # synchronisation is the grid itself: a wait until it holds the rows asked for, each past its
    # fade-in class.
    Given the following "courses" exist:
      | fullname           | shortname | category | customfield_dimensions_model |
      | Primeiros Socorros | PSOC      | TESTCAT  | 1                          |
    And I log in as "student1"
    When I am on the "TESTCAT|perpage=1" "theme_dimensions > Category showcase" page
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    And I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    When I click on "Load more" "button"
    # Two cards, not three and not four: the page that was asked for is the page after this one,
    # cut at the size the address asked for and not at the site default.
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 2]" "xpath_element" exists
    And I should see "2" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    # And the reader who pressed the button still has it. Disabling it while the request is out
    # blurs it to the document body, and a disabled element cannot be focused back.
    And the focused element is "Load more" "button"
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 3]" "xpath_element" exists
    And I should see "3" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The last page takes the button away, so focus lands on the first row that just arrived.
    And "Load more" "button" should not be visible

  @javascript
  Scenario: The view toggle swaps the view at once and is remembered for the next visit
    Given I change viewport size to "1280x900"
    And I log in as "student1"
    And I am on the "TESTCAT|perpage=1" "theme_dimensions > Category showcase" page
    And ".dim-vitrine.dim-vitrine--cards" "css_element" should exist
    # The toggle is one icon-only group whose words are visually hidden.
    And "//*[@data-region='dim-view-row']/button[@value='list']" "xpath_element" should exist
    And "//*[@data-region='dim-view-row']/button[@value='cards']" "xpath_element" should exist
    # The appended page is state that lives in this document and nowhere else, which is what
    # makes the assertions after the toggle able to tell a class swap from a page load: the view
    # buttons are real submits, so without this module the click would reload and lose it.
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 2]" "xpath_element" exists
    When I click on "List" "button" in the "[data-region='dim-view-row']" "css_element"
    Then ".dim-vitrine.dim-vitrine--list" "css_element" should exist
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Curso Online" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The preference is written 500 ms after the click, and the page below is asked for without a
    # view parameter, so what it reads back is the preference and not the address bar.
    And I wait "1" seconds
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then ".dim-vitrine.dim-vitrine--list" "css_element" should exist

  @javascript
  Scenario: The tenth appended page hands paging back to the link the server drew
    # The client appends only so many pages into one document - PAGE_WINDOW in
    # js/esm/src/listing/paint.ts, ten - because each one is DOM, an observer entry and memory a
    # long session never gives back. Past that it stops appending and shows the continue link,
    # which is a real navigation and starts a fresh document with its own page-one baseline.
    # Twelve courses at perpage=1 is the smallest fixture where that boundary is not also the
    # end of the listing: ten appends put eleven cards on screen and leave a twelfth behind, so
    # "the window closed" and "there is nothing more" are different answers.
    #
    # Every click is followed by its own wait on the grid, which holds each step to the row count
    # it asked for: a bare run of ten clicks would assert on whatever count the last one left.
    Given the following "courses" exist:
      | fullname          | shortname | category | customfield_dimensions_model |
      | Brigada Um        | BRIG01    | TESTCAT  | 1                          |
      | Brigada Dois      | BRIG02    | TESTCAT  | 1                          |
      | Brigada Tres      | BRIG03    | TESTCAT  | 1                          |
      | Brigada Quatro    | BRIG04    | TESTCAT  | 1                          |
      | Brigada Cinco     | BRIG05    | TESTCAT  | 1                          |
      | Brigada Seis      | BRIG06    | TESTCAT  | 1                          |
      | Brigada Sete      | BRIG07    | TESTCAT  | 1                          |
      | Brigada Oito      | BRIG08    | TESTCAT  | 1                          |
      | Brigada Nove      | BRIG09    | TESTCAT  | 1                          |
      | Brigada Dez       | BRIG10    | TESTCAT  | 1                          |
    And I log in as "student1"
    When I am on the "TESTCAT|perpage=1" "theme_dimensions > Category showcase" page
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '13 courses in Test category')]" "xpath_element" should exist
    And I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 1]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 2]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 3]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 4]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 5]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 6]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 7]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 8]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 9]" "xpath_element" exists
    When I click on "Load more" "button"
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 10]" "xpath_element" exists
    When I click on "Load more" "button"
    # The tenth append closes the window. Eleven of the twelve are on screen, so this is not the
    # end of the listing - the button goes away and the link the server drew for this very state
    # takes over.
    Then I wait until "//ul[@data-region='dim-vitrine-grid'][count(li[not(contains(@class, 'dim-vitrine__item--enter'))]) = 11]" "xpath_element" exists
    And I should see "11" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And "Load more" "button" should not be visible
    And "Continue on the next page" "link" should be visible
    When I click on "Continue on the next page" "link"
    # A real navigation, to the page the numbered pager would have reached, at the size the
    # address asked for and not at the site default.
    Then the url should match "[?&]page=11(&|$)"
    And I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And "Load more" "button" should not be visible

  @javascript
  Scenario: Going back in the browser puts the listing and the search box back
    # Every state of the listing has an address, and narrowing it pushes a history entry rather
    # than replacing one. The back button therefore returns to a state of this document: the
    # module reads the address back into its state, repaints the controls from it and asks the
    # server for page zero of what the address now says. Nothing is remembered anywhere else,
    # which is why the box has to empty as well - a search term left standing over a whole
    # listing is how a reader concludes the filter is broken.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    When I press "Search courses"
    And I set the field "Search courses" to "Online"
    Then I should see "1 course for “Online”: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And the url should match "[?&]q=Online"
    When I press the "back" button in the browser
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    And I should see "2" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And the field "Search courses" matches value ""
    And the url should match "^(?!.*[?&]q=)"

  @javascript
  Scenario: More information opens the course in a dialogue with its two ways in, and Escape gives the focus back
    # With JavaScript the card's "More information" link opens core/modal instead of the course's page. A self
    # enrolment enabled on the new course makes it open, and the admin may walk into any course: "Enrol" goes to the
    # landing page and "Course page" to the course - the pair that stays two (for a learner both would open the
    # landing page, and the dialogue draws one). The dialogue opens at once with the card's half and is filled by
    # theme_dimensions_get_course_dialogue; the press stays pending until the content is painted, so the skeleton
    # is gone by the next step. The dialogue is found by its title; the link gets the focus back when it closes.
    Given the following config values are set as admin:
      | status | 0 | enrol_self |
    And the following "courses" exist:
      | fullname        | shortname | category | customfield_dimensions_model |
      | Busca Terrestre | BUSCA     | TESTCAT  | 1                          |
    And I log in as "admin"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-region='dim-card'][contains(., 'Busca Terrestre')]//a[@data-region='dim-card-more']" "xpath_element"
    Then "Busca Terrestre" "dialogue" should be visible
    And "Enrol" "link" should exist in the "Busca Terrestre" "dialogue"
    And "Course page" "link" should exist in the "Busca Terrestre" "dialogue"
    And "//*[@data-region='dim-cmodal-content'][not(@aria-busy)]" "xpath_element" should exist
    And "body.dim-category" "css_element" should exist
    When I press the escape key
    Then "Busca Terrestre" "dialogue" should not exist
    And the focused element is "//article[@data-region='dim-card'][contains(., 'Busca Terrestre')]//a[@data-region='dim-card-more']" "xpath_element"

  @javascript
  Scenario: An enrolled learner's card paints its progress row under the state pill once the card is on screen
    # theme_dimensions_get_card_details answers for the enrolled cards in view: a course with completion tracking and
    # one tracked activity reads 0 %, as the bar's value and the number at its right; a course without tracking draws
    # no row, never "0 %".
    Given the following "courses" exist:
      | fullname        | shortname | category | customfield_dimensions_model | enablecompletion |
      | Curso Rastreado | TRACK     | TESTCAT  | 1                            | 1                |
    And the following "activities" exist:
      | activity | name             | course | completion |
      | page     | Pagina rastreada | TRACK  | 1          |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TRACK  | student |
      | student1 | RESG   | student |
    And I log in as "student1"
    # The details are asked only for the cards an IntersectionObserver reports on screen, after the page has loaded and
    # outside core's pending-JS count. The test category's long description pushes the grid below the fold, so the
    # hero is folded on a large window to bring every card on screen, and the wait spins until the answer is painted.
    And I change window size to "large"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And I press "Collapse"
    Then I wait until "//article[@data-region='dim-card'][contains(., 'Curso Rastreado')]//*[@data-region='dim-card-progress'][@role='progressbar'][@aria-valuenow='0'][not(@hidden)]" "xpath_element" exists
    And I should see "0%" in the "//article[@data-region='dim-card'][contains(., 'Curso Rastreado')]//*[@data-region='dim-card-pct']" "xpath_element"
    And "//article[@data-region='dim-card'][contains(., 'Resgate em Altura')]//*[@data-region='dim-card-progress'][@hidden]" "xpath_element" should exist

  @javascript @tool_courserating
  Scenario: A rated course shows its rating on the card and in the dialogue, whose reviews open the plugin's popup
    # tool_courserating is optional for this theme: the theme's CI installs it, and a site without it excludes this
    # scenario with --tags '~@tool_courserating'. A Behat site rates every course by default (the plugin turns itself
    # off under PHPUnit only), and the plugin's own generator writes the ratings, so its summary row is its own. The
    # rules (the predicate, the statement, the reviews' condition) are PHPUnit's; this is the wiring: the card's meta
    # line, the block the service fills, and the plugin's listener opening its popup once the theme's dialogue closed.
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student2 | Sara      | Student  | student2@example.com |
    And the following "tool_courserating > ratings" exist:
      | user     | course | rating | review     |
      | student1 | RESG   | 5      | Excelente. |
      | student2 | RESG   | 4      |            |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then "//article[@data-region='dim-card'][contains(., 'Resgate em Altura')]//p[contains(@class, 'dim-card__meta')]/span[contains(@class, 'dim-card__rating')][contains(normalize-space(.), '4.5 (2)')]" "xpath_element" should exist
    And "//article[@data-region='dim-card'][contains(., 'Curso Online')]//*[contains(@class, 'dim-card__rating')]" "xpath_element" should not exist
    When I click on "//article[@data-region='dim-card'][contains(., 'Resgate em Altura')]//a[@data-region='dim-card-more']" "xpath_element"
    Then "Resgate em Altura" "dialogue" should be visible
    And I should see "2 ratings" in the "Resgate em Altura" "dialogue"
    And "//section[@data-region='dim-cmodal-rating']//progress[@max='2'][@value='1'][@aria-label='5 stars: 1']" "xpath_element" should exist
    When I click on "Read 1 review" "button" in the "Resgate em Altura" "dialogue"
    Then "Course reviews" "dialogue" should be visible
    And "Resgate em Altura" "dialogue" should not exist

  @javascript
  Scenario: A-Z reorders the flat listing where it stands, and the address says so
    # A-Z and Recent are the same shell - a bare grid with no posters - so moving between them
    # is a fetch. The page starts on Recent so that the two orders really are different answers:
    # the course starting tomorrow is the newest and the alphabet puts it last.
    Given the following "courses" exist:
      | fullname     | shortname | category | customfield_dimensions_model | startdate  |
      | Zumbi do Mar | ZUMBI     | TESTCAT  | 1                          | ##tomorrow## |
    And I log in as "student1"
    And I am on the "TESTCAT|sort=recent" "theme_dimensions > Category showcase" page
    # Four, the whole subtree: a flat organisation lists all of it, where the grouped page lists
    # only the direct courses. And no posters: the areas are the grouped organisation's, and "By
    # area" brings them back.
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    And I should see "Zumbi do Mar" in the "//ul[@data-region='dim-vitrine-grid']/li[1]" "xpath_element"
    When I click on "[data-region='dim-organise'] > summary" "css_element"
    And I click on "//fieldset[@data-region='dim-sort']//label[normalize-space(.)='A-Z']" "xpath_element"
    Then I should see "Combate a Incendio" in the "//ul[@data-region='dim-vitrine-grid']/li[1]" "xpath_element"
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    And the url should match "[?&]sort=name(&|$)"
    # The shell did not change, so the areas are still absent - which is what says this was a
    # fetch into the flat page and not a navigation back into the grouped one.
    And "[data-region='dim-areas']" "css_element" should not exist

  @javascript
  Scenario: Choosing "By area" from a flat order brings the posters back
    # The other half of the same rule, and the one that has to be a real navigation: the grouped
    # page draws the posters and the panel's slot, which the flat page has no markup for, so no
    # fetch could produce them.
    Given I log in as "student1"
    And I am on the "TESTCAT|sort=name" "theme_dimensions > Category showcase" page
    And "[data-region='dim-areas']" "css_element" should not exist
    When I click on "[data-region='dim-organise'] > summary" "css_element"
    And I click on "//fieldset[@data-region='dim-sort']//label[normalize-space(.)='By area']" "xpath_element"
    Then "[data-region='dim-areas']" "css_element" should exist
    And I should see "Open category" in the "[data-region='dim-areas']" "css_element"
    And I should see "Courses in Test category" in the "[data-region='dim-direct']" "css_element"
    # The address names the organisation the reader pressed. It is the chip's own value, and the
    # server reads it and the empty string as the same request - what may never be in the address
    # is the flat order the reader has just left.
    And the url should match "[?&]sort=category(&|$)"

  @javascript
  Scenario: The page remembers the organisation chosen in this category, and only in this one
    # A real account's organisation, view and filters are remembered per category: the
    # bare address of the category opens in them, the client then writes that address out in full,
    # and another category is untouched. The rules - what a bare address is, what is validated, the
    # bound - are PHPUnit's (remembered_test); this is the wiring, end to end.
    Given the following "categories" exist:
      | name           | category | idnumber |
      | Other category | 0        | OTHERCAT |
    And the following "courses" exist:
      | fullname    | shortname | category | customfield_dimensions_model |
      | Curso Outro | OUTRO     | OTHERCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "[data-region='dim-areas']" "css_element" should exist
    When I click on "[data-region='dim-organise'] > summary" "css_element"
    And I click on "//fieldset[@data-region='dim-sort']//label[normalize-space(.)='A-Z']" "xpath_element"
    Then "[data-region='dim-areas']" "css_element" should not exist
    And the url should match "[?&]sort=name(&|$)"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then "[data-region='dim-areas']" "css_element" should not exist
    And "//fieldset[@data-region='dim-sort']//input[@value='name'][@checked]" "xpath_element" should exist
    And the url should match "[?&]sort=name(&|$)"
    When I am on the "OTHERCAT" "theme_dimensions > Category showcase" page
    Then "//fieldset[@data-region='dim-sort']//input[@value='category'][@checked]" "xpath_element" should exist
    And the url should match "^(?!.*[?&]sort=)"

  @javascript
  Scenario: A quick date option narrows the listing in place and the address carries the period
    # The link is a real address for a reader without this module; with it, the press is
    # intercepted and the two bounds it carries are used exactly as they are - the client resolves
    # no date of its own, which is why the course seeded yesterday is the control.
    Given the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate     |
      | Curso de Onte | ONTEM     | TESTCAT  | 1                          | ##yesterday## |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "Today" "link"
    Then I should see "3 courses with these filters: 2 in this category and 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should not see "Curso de Onte"
    And the url should match "[?&]from=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    And the url should match "[?&]to=[0-9]{4}-[0-9]{2}-[0-9]{2}"

  @javascript
  Scenario: The calendar describes a range in two presses from the start box and "Done" is what applies it
    # The start box opens the calendar in the start's mode: the first press sets the start and moves
    # the calendar on to the end, so the second press on the same day is the one-day period - the
    # smallest thing that proves both presses were taken. The day is the one the server marked as
    # today, so nothing here depends on the browser's own clock. The course seeded yesterday and the
    # one seeded two days ahead are the controls on each side of it.
    #
    # The presses apply nothing. They describe a period, the popover stays open showing it, and the
    # reader says they are finished; the address still carrying no period is the whole of that rule.
    Given the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate     |
      | Curso de Onte | ONTEM     | TESTCAT  | 1                          | ##yesterday## |
      | Curso Futuro  | FUTURO    | TESTCAT  | 1                          | ##+2 days##   |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '5 courses in Test category')]" "xpath_element" should exist
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-open'][@data-bound='from']" "xpath_element"
    Then "//*[@data-region='dim-daterange-pop'][not(@hidden)]" "xpath_element" should exist
    When I click on "//button[@data-region='dim-daterange-day'][@aria-current='date']" "xpath_element"
    And I click on "//button[@data-region='dim-daterange-day'][@aria-current='date']" "xpath_element"
    Then "//*[@data-region='dim-daterange-pop'][not(@hidden)]" "xpath_element" should exist
    And the url should match "^(?!.*[?&](from|to)=)"
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '5 courses in Test category')]" "xpath_element" should exist
    # And now the reader says they are done with it.
    When I click on "//button[@data-action='dim-daterange-done']" "xpath_element"
    Then I should see "3 courses with these filters: 2 in this category and 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should not see "Curso de Onte"
    And I should not see "Curso Futuro"
    And the url should match "[?&]from=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    And the url should match "[?&]to=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    # The badge on the Filters summary counts the period once, in both of its halves: it is the only
    # number of this toolbar a reader who shuts the panel can still see, and the second half is
    # the sentence a screen reader is read, which is where the digit alone would have been right.
    And "//*[@data-region='dim-filtercount'][not(@hidden)]//*[@data-region='dim-filtercount-number'][normalize-space(text())='1']" "xpath_element" should exist
    And "//*[@data-region='dim-filtercount-label'][normalize-space(text())='Filters applied: 1']" "xpath_element" should exist
    # Clearing the start box alone leaves the end alone, which is a period of its own: the courses
    # starting on that day or earlier. The address keeps the end and drops the start.
    When I click on "//button[@data-action='dim-daterange-clear'][@data-bound='from']" "xpath_element"
    Then I should see "4 courses with these filters: 3 in this category and 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Curso de Onte" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Futuro"
    And the url should match "[?&]to=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    And the url should match "^(?!.*[?&]from=)"
    # The period is stated where a reader who shuts the panel can still see it, and that statement
    # is a control: pressing it drops the period and the whole listing comes back. The panel is a
    # popover over the row under the line from the sm breakpoint up, so the reader shuts it first.
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    Then "//details[@data-region='dim-filters'][not(@open)]" "xpath_element" should exist
    And "//*[@data-region='dim-daterange-pill']//a[starts-with(normalize-space(.), 'Until ')]" "xpath_element" should be visible
    When I click on "//*[@data-region='dim-daterange-pill']//a" "xpath_element"
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '5 courses in Test category')]" "xpath_element" should exist
    And the url should match "^(?!.*[?&](from|to)=)"
    # And the badge goes back with it, which is what makes the assertions above a measurement of
    # this request rather than of a number that happened to be painted once.
    And "//*[@data-region='dim-filtercount'][@hidden]" "xpath_element" should exist

  @javascript
  Scenario: A start alone keeps the courses from that day on, and the address carries the start alone
    Given the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate     |
      | Curso de Onte | ONTEM     | TESTCAT  | 1                          | ##yesterday## |
      | Curso Futuro  | FUTURO    | TESTCAT  | 1                          | ##+2 days##   |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-open'][@data-bound='from']" "xpath_element"
    And I click on "//button[@data-region='dim-daterange-day'][@aria-current='date']" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-done']" "xpath_element"
    Then I should see "4 courses with these filters: 3 in this category and 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Curso Futuro" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso de Onte"
    And the url should match "[?&]from=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    And the url should match "^(?!.*[?&]to=)"
    And "//*[@data-region='dim-daterange-pill']//a[starts-with(normalize-space(.), 'From ')]" "xpath_element" should be visible

  @javascript
  Scenario: The end box opens the same calendar, and an end alone keeps the courses up to that day
    # One popover serves both boxes. Opened from the end box, it says it is asking for the last day,
    # and its one press sets the end.
    Given the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate     |
      | Curso de Onte | ONTEM     | TESTCAT  | 1                          | ##yesterday## |
      | Curso Futuro  | FUTURO    | TESTCAT  | 1                          | ##+2 days##   |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-open'][@data-bound='to']" "xpath_element"
    Then "//*[@data-region='dim-daterange-pop'][not(@hidden)]" "xpath_element" should exist
    And "//button[@data-bound='to'][@aria-expanded='true'][@aria-controls=//*[@data-region='dim-daterange-pop']/@id]" "xpath_element" should exist
    And "//*[@data-region='dim-daterange-prompt'][normalize-space(.)='Choose the last day']" "xpath_element" should exist
    When I click on "//button[@data-region='dim-daterange-day'][@aria-current='date']" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-done']" "xpath_element"
    Then I should see "4 courses with these filters: 3 in this category and 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Curso de Onte" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Futuro"
    And the url should match "[?&]to=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    And the url should match "^(?!.*[?&]from=)"

  @javascript
  Scenario: A start alone typed into the address narrows the page and stays in the address after a fetch
    # A fixed date years ahead, because a whole-argument transformation cannot sit inside the page's
    # address. The page draws the start in its box; the first fetch of the client - a facet pill -
    # carries it, and the address the client writes afterwards keeps it, alone.
    Given the following "courses" exist:
      | fullname       | shortname | category | customfield_dimensions_model | customfield_dimensions_modality | startdate               |
      | Curso Distante | DISTANTE  | TESTCAT  | 1                          | 1                      | ##2031-06-15 12:00:00## |
    And I log in as "student1"
    And I am on the "TESTCAT|from=2031-01-01" "theme_dimensions > Category showcase" page
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Curso Distante" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Online"
    And "//button[@data-bound='from']//*[@data-region='dim-daterange-value'][normalize-space(.)!='']" "xpath_element" should exist
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "//label[contains(@class, 'dim-pill')][contains(normalize-space(.), 'Online')]" "xpath_element"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should not see "Curso Online"
    And the url should match "[?&]from=2031-01-01(&|$)"
    And the url should match "[?&]cf_[0-9]+=1(&|$)"
    And the url should match "^(?!.*[?&]to=)"

  @javascript
  Scenario: Opening an area's panel lists its whole subtree, the grandchild's course with its overline
    # A panel is the whole area, so the course of a subarea is in it, unlike the grouped page's
    # own courses section - the grandchild's course is the control, drawn with its overline so it
    # is told from the area's own - and the subarea is named on the strip above. The panel is
    # opened in place: nothing reloads, and with JavaScript the poster is a button.
    Given the following "categories" exist:
      | name           | category | idnumber |
      | Inner category | OPENCAT  | INNERCAT |
    And the following "courses" exist:
      | fullname    | shortname | category | customfield_dimensions_model |
      | Curso Fundo | FUNDO     | INNERCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    # A poster says it is an area, and carries no number at rest.
    And I should see "Category" in the "//article[@data-name='Open category']//span[contains(@class, 'dim-poster__kind')]" "xpath_element"
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    When I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And I should see "Curso Fundo" in the "[data-region='dim-panel-rail']" "css_element"
    And I should see "Inner category" in the "//ul[@data-region='dim-panel-rail']//article[contains(., 'Curso Fundo')]//span[contains(@class, 'dim-card__over')]" "xpath_element"
    And I should see "Inner category" in the "[data-region='dim-panel-also']" "css_element"
    And I should see "Hide courses" in the "//article[@data-name='Open category']" "xpath_element"
    And the url should match "[?&]open=[0-9]+"
    # The panel's own subtitle counts its two courses; the page's count line is the subtree's still.
    And I should see "2 courses" in the "[data-region='dim-panel-subtitle']" "css_element"
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist

  @javascript
  Scenario: The panel follows the last poster of its row, one area at a time, at every width
    # The panel's row is the stylesheet's, from five integers on its own element; its place in the
    # document is the module's - after the last poster of the open poster's row - so a keyboard
    # and a screen reader meet it where the eye does. Three more areas beside the background's: a
    # full row of three and a second row at 1280, two rows of two at 800. The fourth is what makes
    # the 1280 claim a claim - the closed panel is drawn at the end of the grid, so with the first
    # row's last poster also the grid's last, the panel would be after it without being moved.
    # The count predicate is the assertion of "one at a time": "a poster is open" passes over two
    # open ones.
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
      | Third category  | TESTCAT  | THIRDCAT  |
      | Fourth category | TESTCAT  | FOURTHCAT |
    And the following "courses" exist:
      | fullname       | shortname | category  | customfield_dimensions_model |
      | Curso Segundo  | SEGU      | SECONDCAT | 1                          |
      | Curso Terceiro | TERC      | THIRDCAT  | 1                          |
      | Curso Quarto   | QUAR      | FOURTHCAT | 1                          |
    And I change viewport size to "1280x900"
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//li[@data-region='dim-panel'][@hidden]/preceding-sibling::li[1]//article[@data-name='Fourth category']" "xpath_element" should exist
    When I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And "//li[@data-region='dim-panel']/preceding-sibling::li[1]//article[@data-name='Third category']" "xpath_element" should exist
    And "//li[@data-region='dim-panel']/following-sibling::li[1]//article[@data-name='Fourth category']" "xpath_element" should exist
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    When I click on "//article[@data-name='Second category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso Segundo" in the "[data-region='dim-panel-rail']" "css_element"
    And "//ul[@data-region='dim-areas-grid'][count(.//button[@data-region='dim-poster-control'][@aria-expanded='true']) = 1]" "xpath_element" should exist
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='false']" "xpath_element" should exist
    When I change viewport size to "800x900"
    And I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And "//li[@data-region='dim-panel']/preceding-sibling::li[1]//article[@data-name='Second category']" "xpath_element" should exist
    # Crossing into one track moves it under its own poster, with nothing pressed.
    When I change viewport size to "390x844"
    Then "//li[@data-region='dim-panel']/preceding-sibling::li[1]//article[@data-name='Open category']" "xpath_element" should exist

  @javascript
  Scenario: Escape closes the panel and puts the focus back on its poster
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And the focused element is "//h3[@id='dim-panel-title']" "xpath_element"
    When I press the escape key
    Then the focused element is "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='false']" "xpath_element" should exist
    And the url should match "^(?!.*[?&]open=)"

  @javascript
  Scenario: An open panel survives a reload, and a search reaches its call to action
    # The address carries the panel (replaceState), so a reload draws it on the server and the
    # module takes it over without a request. And the panel is a listing of the page's criteria one
    # level down: a search applied in place is carried into its call to action.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    When I reload the page
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    And "//li[@data-region='dim-panel']//button[@data-action='dim-panel-close']" "xpath_element" should exist
    When I press "Search courses"
    And I set the field "Search courses" to "Incendio"
    Then I should see "1 course for “Incendio”: 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And I wait until "//a[@data-region='dim-panel-cta'][contains(@href, 'q=Incendio')]" "xpath_element" exists
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    # The term is marked in the title of the panel's card, on the heading's text and ignoring
    # nothing but case and accents.
    And I should see "Incendio" in the "//ul[@data-region='dim-panel-rail']//h3[@data-region='dim-card-title']//mark[contains(@class, 'dim-mark')]" "xpath_element"
    # The one area with results was already the reader's open one: nothing opened by itself.
    And I should not see "Only Open category has results"

  @javascript
  Scenario: Folding the hero keeps the filters applied in place and the open panel
    # The fold form is drawn with the criteria of the server's render, so after a filter applied
    # in place it would reload the page with the old ones; both modules keep its fields in step
    # with the address.
    #
    # "In-person" zeroes the child, whose poster then leaves the grid - so the panel is opened
    # first and the filter applied over it: the open area stays, wearing "0 results", and its
    # panel says so in the server's zero sentence.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    When I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "//label[contains(@class, 'dim-pill')][contains(normalize-space(.), 'In-person')]" "xpath_element"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "No results with these filters in Open category." in the "[data-region='dim-panel-body']" "css_element"
    And I should see "0 results" in the "//article[@data-name='Open category']//span[@data-region='dim-poster-pill']" "xpath_element"
    When I press "Collapse"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And the url should match "[?&]cf_[0-9]+=2"
    And the url should match "[?&]open=[0-9]+"
    And "//li[@data-region='dim-panel'][not(@hidden)]" "xpath_element" should exist

  @javascript
  Scenario: On a phone the line folds to two lines and keeps every control of the bar
    # The filter line measures its own width and takes its two-line step on a phone: the scope has a
    # line of its own, and the capsule keeps the same controls at every width, the view toggle
    # among them.
    Given I change viewport size to "390x844"
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then "//div[@data-region='dim-line'][contains(concat(' ', @class, ' '), ' dim-line--s2 ')]" "xpath_element" should exist
    And "[data-region='dim-view-row']" "css_element" should be visible
    When I click on "List" "button" in the "[data-region='dim-view-row']" "css_element"
    Then ".dim-vitrine.dim-vitrine--list" "css_element" should exist

  @javascript
  Scenario: On a phone the search opens over the line and a suggestion applies its filter in place
    # Pressing the search button opens the search over the line, with a button that closes it. Typing suggests
    # the page's own filters; choosing one applies it with the term dropped in the same request and
    # keeps the search open for the next pick. Escape closes it.
    Given I change viewport size to "390x844"
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I press "Search courses"
    Then "//div[@data-region='dim-line'][contains(concat(' ', @class, ' '), ' dim-line--searching ')]" "xpath_element" should exist
    And "Close search" "button" should be visible
    When I type "In-p"
    Then I should see "In-person" in the "[data-region='dim-line-suggest']" "css_element"
    When I click on "//*[@data-region='dim-line-suggest']//*[@role='option'][contains(., 'In-person')]" "xpath_element"
    Then I should see "1 course with these filters: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And the field "Search courses" matches value ""
    And the url should match "[?&]cf_[0-9]+=2"
    And the url should match "^(?!.*[?&]q=)"
    And "//*[@data-region='dim-line-chips']//a[@data-action='dim-chip-remove'][starts-with(@data-param,'cf_')]" "xpath_element" should exist
    And the focused element is "Search courses" "field"
    When I press the escape key
    Then "//div[@data-region='dim-line'][contains(concat(' ', @class, ' '), ' dim-line--searching ')]" "xpath_element" should not exist

  @javascript
  Scenario: The tree narrows the flat listing where it stands, and the grouped organisation draws none
    # The tree is a filter: pressing an entry narrows the listing to that category's subtree
    # without leaving the page. Under "By area" there is no tree at all: the posters already name
    # every child and open their courses, so a tree would be redundant.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    And "[data-region='dim-vitrine-tree']" "css_element" should not exist
    And "[data-region='dim-areas']" "css_element" should exist
    When I am on the "TESTCAT|sort=name" "theme_dimensions > Category showcase" page
    # Three, which is the flat listing of the whole subtree: two direct courses and the child's.
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    And "//nav[@data-region='dim-vitrine-tree']//a[@data-region='dim-tree-link'][@data-categoryid]" "xpath_element" should exist
    When I click on "//nav[@data-region='dim-vitrine-tree']//a[normalize-space(.)='Open category']" "xpath_element"
    Then I should see "1 course in Open category" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Resgate em Altura"
    # It was a fetch and not a navigation: the address changed under the page that was already
    # there. The criterion is one of the filters in every respect, so the way out of all of them
    # is offered; the category is one criterion, so its chip is the way out and the badge, which
    # counts only what the Filters panel holds, stays hidden.
    And the url should match "[?&]cat=[0-9]+"
    And "//*[@data-region='dim-line-chips']//a[@data-action='dim-chip-remove'][@data-param='cat']" "xpath_element" should be visible
    And "//*[@data-region='dim-filtercount'][@hidden]" "xpath_element" should exist
    # Exactly one entry is marked, and it is the one pressed - the count predicate is the
    # assertion, because "the pressed one is marked" passes while the previous one still is too.
    And "//nav[@data-region='dim-vitrine-tree'][count(.//a[@aria-current='true']) = 1]" "xpath_element" should exist
    And "//a[@data-region='dim-tree-link'][@aria-current='true'][normalize-space(.)='Open category']" "xpath_element" should exist
    # And the page's own entry, the first one, is what puts the whole subtree back.
    When I click on "//nav[@data-region='dim-vitrine-tree']//a[normalize-space(.)='Test category']" "xpath_element"
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    And the url should match "^(?!.*[?&]cat=)"
    And "//a[@data-region='dim-tree-link'][@aria-current='true'][normalize-space(.)='Test category']" "xpath_element" should exist

  @javascript
  Scenario: Clearing the filters in place puts the areas back and the focus in the search box
    # A page loaded under a search draws the areas the term misses as hidden items and names them
    # in the summary; every poster that can open is a button, the empty area's included. "Clear
    # filters" is then a fetch, not a navigation: the posters come back, the empty area is a link
    # to its page again reading "No courses yet", and the focus goes to the search box, since the
    # control pressed hides itself. Typing again collapses them once more.
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
      | Empty category  | TESTCAT  | EMPTYCAT  |
    And the following "courses" exist:
      | fullname      | shortname | category  | customfield_dimensions_model |
      | Curso Segundo | SEGU      | SECONDCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT|q=Resgate" "theme_dimensions > Category showcase" page
    Then I should see "1 course for “Resgate”: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And "//article[@data-name='Second category']" "xpath_element" should not be visible
    And I should see "Nothing for “Resgate” in 3 other areas" in the "[data-region='dim-nomatch-summary']" "css_element"
    And "//article[@data-name='Empty category']//button[@data-region='dim-poster-control']" "xpath_element" should exist
    When I click on "Clear text" "button" in the "[data-region='dim-line-search']" "css_element"
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '4 courses in Test category')]" "xpath_element" should exist
    And the focused element is "Search courses" "field"
    And the url should match "^(?!.*[?&]q=)"
    And "//article[@data-name='Second category']" "xpath_element" should be visible
    And "[data-region='dim-areas-nomatch']" "css_element" should not be visible
    And "//article[@data-name='Empty category']//a[@data-region='dim-poster-control']" "xpath_element" should exist
    And I should see "No courses yet" in the "//article[@data-name='Empty category']" "xpath_element"
    When I set the field "Search courses" to "Resgate"
    Then I should see "1 course for “Resgate”: 1 in this category" in the "[data-region='dim-vitrine-count']" "css_element"
    And "//article[@data-name='Second category']" "xpath_element" should not be visible
    And I should see "Nothing for “Resgate” in 3 other areas" in the "[data-region='dim-nomatch-summary']" "css_element"

  @javascript
  Scenario: A search matching one area and nothing here opens that area by itself and keeps the focus
    # The one area holding every result opens under the posters, the focus stays in the search box
    # the reader is typing in, and the count line says why a panel opened.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I press "Search courses"
    And I type "Incendio"
    Then I should see "Only Open category has results; its courses are open below." in the "[data-region='dim-line-chips']" "css_element"
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    And the focused element is "Search courses" "field"
    And the url should match "[?&]open=[0-9]+"
    And I should see "Tap an area to see its courses." in the "[data-region='dim-areas']" "css_element"

  @javascript
  Scenario: A search never swaps the panel the reader opened, and its suggestions open the other area
    # The reader's panel stays open under a search that zeroes its area, even when another single
    # area holds every result - the automatic opening never swaps a panel the reader opened. The
    # zero panel suggests that area, and the suggestion opens it in place.
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
    And the following "courses" exist:
      | fullname      | shortname | category  | customfield_dimensions_model |
      | Curso Segundo | SEGU      | SECONDCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-name='Second category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso Segundo" in the "[data-region='dim-panel-rail']" "css_element"
    When I press "Search courses"
    And I set the field "Search courses" to "Incendio"
    Then I should see "No results for “Incendio” in Second category." in the "[data-region='dim-panel-body']" "css_element"
    And I should see "0 results" in the "//article[@data-name='Second category']//span[@data-region='dim-poster-pill']" "xpath_element"
    And "//article[@data-name='Second category']" "xpath_element" should be visible
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='false']" "xpath_element" should exist
    And I should not see "Only Open category has results"
    And I should see "Results in:" in the "[data-region='dim-panel-suggestions']" "css_element"
    When I click on "//button[@data-action='dim-panel-openarea'][contains(., 'Open category')]" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    And "//article[@data-name='Second category']" "xpath_element" should not be visible
    And I should see "Nothing for “Incendio” in one other area" in the "[data-region='dim-nomatch-summary']" "css_element"

  @javascript
  Scenario: Closing an open area the search zeroed sends it to the summary with the focus
    # The zeroed area leaves the grid only as it closes, and the focus that would have gone back
    # to its poster goes to the summary it has just joined.
    Given the following "categories" exist:
      | name            | category | idnumber  |
      | Second category | TESTCAT  | SECONDCAT |
    And the following "courses" exist:
      | fullname      | shortname | category  | customfield_dimensions_model |
      | Curso Segundo | SEGU      | SECONDCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-name='Second category']//button[@data-region='dim-poster-control']" "xpath_element"
    And I press "Search courses"
    And I set the field "Search courses" to "Resgate"
    Then I should see "No results for “Resgate” in Second category." in the "[data-region='dim-panel-body']" "css_element"
    And I should see "Nothing for “Resgate” in one other area" in the "[data-region='dim-nomatch-summary']" "css_element"
    When I click on "//button[@data-action='dim-panel-close']" "xpath_element"
    Then "//article[@data-name='Second category']" "xpath_element" should not be visible
    And I should see "Nothing for “Resgate” in 2 other areas" in the "[data-region='dim-nomatch-summary']" "css_element"
    And the focused element is "[data-region='dim-nomatch-summary']" "css_element"
    And the url should match "^(?!.*[?&]open=)"

  @javascript
  Scenario: The course index searches as the reader types, and a cleared search puts the posters back
    # The root's search line is live, as a category page's is. Its posters wear their matches and
    # the areas the term misses leave for the summary; no area opens by itself at the root, and a
    # panel opened under the search lists the matches alone. Clearing the search in place brings
    # every poster back, with no pill.
    Given I log in as "student1"
    And I am on course index
    When I set the field "Search courses" to "Incendio"
    Then I should see "1 result" in the "//article[@data-name='Test category']" "xpath_element"
    And "[data-region='dim-areas-nomatch']" "css_element" should be visible
    And "//li[@data-region='dim-panel'][@hidden]" "xpath_element" should exist
    And the url should match "[?&]q=Incendio"
    When I click on "//article[@data-name='Test category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And I should not see "Resgate em Altura" in the "[data-region='dim-panel-rail']" "css_element"
    When I set the field "Search courses" to ""
    # The rail is asked again only after the answer at rest has repainted the posters and the
    # summary, so once the rail's course is back the posters and the summary are painted and the
    # two visibility steps below need no wait of their own.
    Then I should see "Resgate em Altura" in the "[data-region='dim-panel-rail']" "css_element"
    And "[data-region='dim-areas-nomatch']" "css_element" should not be visible
    And "//article[@data-name='Test category']//*[@data-region='dim-poster-pill']" "xpath_element" should not be visible

  @javascript
  Scenario: Arriving on a remembered bare address writes it out in full without a history entry
    # The bare address of a category draws a reader's remembered state, and the client writes that
    # address out in full in place: pushed, Back would stop on the bare address, which draws
    # the same page again, and the reader could never leave it with one press.
    Given the following "categories" exist:
      | name           | category | idnumber |
      | Other category | 0        | OTHERCAT |
    And the following "courses" exist:
      | fullname    | shortname | category | customfield_dimensions_model |
      | Curso Outro | OUTRO     | OTHERCAT | 1                          |
    And I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "[data-region='dim-organise'] > summary" "css_element"
    And I click on "//fieldset[@data-region='dim-sort']//label[normalize-space(.)='A-Z']" "xpath_element"
    Then the url should match "[?&]sort=name(&|$)"
    When I am on the "OTHERCAT" "theme_dimensions > Category showcase" page
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then the url should match "[?&]sort=name(&|$)"
    And "//fieldset[@data-region='dim-sort']//input[@value='name'][@checked]" "xpath_element" should exist
    When I press the "back" button in the browser
    Then I should see "Curso Outro" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"

  @javascript
  Scenario: Going back with a panel open puts the criteria back and keeps the panel
    # Two writers share the address: the store pushes the criteria, the posters' component replaces
    # its open area. The store hears Back first and asks for the criteria the address names, and the
    # component keeps the panel the same address still names.
    Given I log in as "student1"
    And I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    When I click on "//article[@data-name='Open category']//button[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
    And the url should match "[?&]open=[0-9]+"
    When I press "Search courses"
    And I set the field "Search courses" to "Incendio"
    Then I should see "1 course for “Incendio”: 1 in 1 area" in the "[data-region='dim-vitrine-count']" "css_element"
    And the url should match "[?&]q=Incendio"
    When I press the "back" button in the browser
    Then "//p[@data-region='dim-vitrine-count'][contains(normalize-space(.), '3 courses in Test category')]" "xpath_element" should exist
    And the field "Search courses" matches value ""
    And the url should match "^(?!.*[?&]q=)"
    And the url should match "[?&]open=[0-9]+"
    And "//article[@data-name='Open category']//button[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    And I should see "Combate a Incendio" in the "[data-region='dim-panel-rail']" "css_element"
