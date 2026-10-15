@theme @theme_dimensions @theme_dimensions_category @javascript
Feature: A visitor's public category page narrows in place once the site opens its live listing
  In order to find an open course without waiting for a page load between every choice
  As a visitor who is not logged in
  I need the public page's search, dates and area panels to work in place, and the page to stay its plain form while the site keeps that off

  # Thin smoke tests of the visitor's client module (amd/src/categorypublic.js): that it is armed
  # only by the switch, that its requests reach the visitor's web service through
  # service-nologin.php while the site forces login, and that the page moves. What a visitor may be
  # told is PHPUnit's (rows_anonymous_test, get_public_category_rows_test), and the same page
  # without the module is theme_dimensions_category.feature's. A panel opened in place and
  # the one the server draws for the same address are one markup, so whether a page was loaded is
  # read off a mark left on the browser's window, which only a page load takes away.

  Background:
    Given the following config values are set as admin:
      | theme              | dimensions |                            |
    And the following "categories" exist:
      | name            | category | idnumber |
      | Public category | 0        | PUBCAT   |
    And the following "courses" exist:
      | fullname        | shortname | category | customfield_dimensions_model | startdate     |
      | Curso Online    | ONLINE    | PUBCAT   | 1                          | ##yesterday## |
      | Curso Futuro    | FUTURO    | PUBCAT   | 1                          | ##+2 days##   |
      | Curso Interno   | INTERNO   | PUBCAT   | 1                          | ##+2 days##   |
    And the course "ONLINE" is "public" for discoverability
    And the course "FUTURO" is "public" for discoverability
    And the category "PUBCAT" is "public" for discoverability
    And the following config values are set as admin:
      | forcelogin | 1 |

  Scenario: Typing in the search box narrows a visitor's list with no Filter pressed, and the address follows
    Given the following config values are set as admin:
      | categorypublicclient | 1 | theme_dimensions |
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then "body.dim-public-js" "css_element" should exist
    And "body.dim-js" "css_element" should not exist
    And I should see "2" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    # The listed course of the same category, sharing the word, is what a visitor may not be told about.
    And I should not see "Curso Interno"
    When I set the field "Search courses" to "Online"
    Then I should see "1 course for “Online”" in the "[data-region='dim-vitrine-count']" "css_element"
    And I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should see "Curso Online" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Futuro"
    And the url should match "[?&]q=Online"
    And "Username" "field" should not exist
    # The submit only a reader without the module needs is hidden by the visitor's own mark.
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    Then "//button[contains(@class, 'dim-toolbar__submit')]" "xpath_element" should not be visible

  Scenario: A start alone narrows a visitor's list in place through the calendar
    Given the following config values are set as admin:
      | categorypublicclient | 1 | theme_dimensions |
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    And I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-open'][@data-bound='from']" "xpath_element"
    And I click on "//button[@data-region='dim-daterange-day'][@aria-current='date']" "xpath_element"
    And I click on "//button[@data-action='dim-daterange-done']" "xpath_element"
    Then I should see "Curso Futuro" in the "[data-region='dim-vitrine-grid']" "css_element"
    And I should not see "Curso Online"
    And I should see "1" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    And the url should match "[?&]from=[0-9]{4}-[0-9]{2}-[0-9]{2}"
    And the url should match "^(?!.*[?&]to=)"
    And "Username" "field" should not exist

  Scenario: With the live listing off a visitor's page is the plain form it always was
    # Without the module a poster is the link it was drawn as: the panel is the server's, for the address.
    Given the following "categories" exist:
      | name         | category | idnumber |
      | Area publica | PUBCAT   | PUBAREA  |
    And the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate   |
      | Curso da Area | AREACO    | PUBAREA  | 1                          | ##+2 days## |
    And the category "PUBAREA" is "public" for discoverability
    And the course "AREACO" is "public" for discoverability
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then "body.dim-public-js" "css_element" should not exist
    And "body.dim-js" "css_element" should not exist
    And "body.dim-panel-js" "css_element" should not exist
    And I should see "2" node occurrences of type "article" in the "[data-region='dim-vitrine-grid']" "css_element"
    When I click on "//details[@data-region='dim-filters']/summary" "xpath_element"
    Then "//button[contains(@class, 'dim-toolbar__submit')]" "xpath_element" should be visible
    And "//article[@data-name='Area publica']//a[@data-region='dim-poster-control'][@aria-expanded]" "xpath_element" should not exist
    When I mark the page in the browser
    And I click on "//article[@data-name='Area publica']//a[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso da Area" in the "[data-region='dim-panel-rail']" "css_element"
    And the page in the browser is not the one I marked
    And the url should match "[?&]open=[0-9]+"

  Scenario: A visitor's poster opens its panel in place, a second one closes the first, and Escape closes it
    # The public area is asked as the panel of its own page and the merely listed one, which has no
    # page a visitor may open, as a slice of this page's listing: the two shapes the service answers.
    Given the following config values are set as admin:
      | categorypublicclient | 1 | theme_dimensions |
    And the following "categories" exist:
      | name         | category | idnumber |
      | Area publica | PUBCAT   | PUBAREA  |
      | Area listada | PUBCAT   | LISTAREA |
    And the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate   |
      | Curso da Area | AREACO    | PUBAREA  | 1                          | ##+2 days## |
      | Curso Listado | LISTCO    | LISTAREA | 1                          | ##+2 days## |
    And the category "PUBAREA" is "public" for discoverability
    And the course "AREACO" is "public" for discoverability
    And the course "LISTCO" is "public" for discoverability
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then "body.dim-panel-js" "css_element" should exist
    And "//article[@data-name='Area publica']//a[@data-region='dim-poster-control'][@role='button'][@aria-expanded='false']" "xpath_element" should exist
    When I mark the page in the browser
    And I click on "//article[@data-name='Area publica']//a[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso da Area" in the "[data-region='dim-panel-rail']" "css_element"
    And the page in the browser is the one I marked
    And the url should match "[?&]open=[0-9]+"
    And "//article[@data-name='Area publica']//a[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    When I click on "//article[@data-name='Area listada']//a[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso Listado" in the "[data-region='dim-panel-rail']" "css_element"
    And I should not see "Curso da Area" in the "[data-region='dim-panel-rail']" "css_element"
    And "//article[@data-name='Area publica']//a[@data-region='dim-poster-control'][@aria-expanded='false']" "xpath_element" should exist
    And "//article[@data-name='Area listada']//a[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    And the focused element is "//h3[@id='dim-panel-title']" "xpath_element"
    And the page in the browser is the one I marked
    When I press the escape key
    Then the focused element is "//article[@data-name='Area listada']//a[@data-region='dim-poster-control']" "xpath_element"
    And "//article[@data-name='Area listada']//a[@data-region='dim-poster-control'][@aria-expanded='false']" "xpath_element" should exist
    And the url should match "^(?!.*[?&]open=)"
    And the page in the browser is the one I marked
    And "Username" "field" should not exist

  Scenario: With a panel open, a search narrows the panel in place and the panel stays open
    Given the following config values are set as admin:
      | categorypublicclient | 1 | theme_dimensions |
    And the following "categories" exist:
      | name         | category | idnumber |
      | Area publica | PUBCAT   | PUBAREA  |
    And the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate   |
      | Curso da Area | AREACO    | PUBAREA  | 1                          | ##+2 days## |
      | Outro da Area | OUTRO     | PUBAREA  | 1                          | ##+3 days## |
    And the category "PUBAREA" is "public" for discoverability
    And the course "AREACO" is "public" for discoverability
    And the course "OUTRO" is "public" for discoverability
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    And I mark the page in the browser
    And I click on "//article[@data-name='Area publica']//a[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso da Area" in the "[data-region='dim-panel-rail']" "css_element"
    And I should see "Outro da Area" in the "[data-region='dim-panel-rail']" "css_element"
    When I set the field "Search courses" to "Outro"
    Then I wait until "//a[@data-region='dim-panel-cta'][contains(@href, 'q=Outro')]" "xpath_element" exists
    And I should see "Outro da Area" in the "[data-region='dim-panel-rail']" "css_element"
    And I should not see "Curso da Area" in the "[data-region='dim-panel-rail']" "css_element"
    And the url should match "[?&]q=Outro"
    And the url should match "[?&]open=[0-9]+"
    And the page in the browser is the one I marked
    And "Username" "field" should not exist

  Scenario: A poster whose slice the service no longer draws loads its link
    # The page drew the merely listed area as a slice of its listing; it turns public before the
    # press, so the service drops the slice and answers no panel. The module then loads the poster's
    # link, and the server draws the panel of what the area now is.
    Given the following config values are set as admin:
      | categorypublicclient | 1 | theme_dimensions |
    And the following "categories" exist:
      | name         | category | idnumber |
      | Area listada | PUBCAT   | LISTAREA |
    And the following "courses" exist:
      | fullname      | shortname | category | customfield_dimensions_model | startdate   |
      | Curso Listado | LISTCO    | LISTAREA | 1                          | ##+2 days## |
    And the course "LISTCO" is "public" for discoverability
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    And I mark the page in the browser
    And the category "LISTAREA" is "public" for discoverability
    And I click on "//article[@data-name='Area listada']//a[@data-region='dim-poster-control']" "xpath_element"
    Then I should see "Curso Listado" in the "[data-region='dim-panel-rail']" "css_element"
    And the page in the browser is not the one I marked
    And the url should match "[?&]open=[0-9]+"
    And "//article[@data-name='Area listada']//a[@data-region='dim-poster-control'][@aria-expanded='true']" "xpath_element" should exist
    And "Username" "field" should not exist
