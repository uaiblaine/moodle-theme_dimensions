@theme @theme_dimensions @theme_dimensions_calendar
Feature: The theme's calendar page shows a month with spanning bars, key chips, a category tree and a saved state
  In order to see what my month holds at a glance
  As somebody studying on a Dimensions site
  I need each multi-day event drawn once, the keys and categories to narrow it, and my choices kept

  # Thin smoke tests of the page (docs/calendar/PROPOSAL.md, stage C2): that the route serves it, that the client
  # draws and narrows it, and that the server's agenda stands without JavaScript. What the range answers, to whom,
  # is PHPUnit's (tests/local/calendar), and the layout and the keyboard contract are Jest's (js/esm/tests/calendar).
  # March 2027 is fixed so the scenarios do not move with the day they run on; the page is asked for it by address.

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "categories" exist:
      | name   | category | idnumber |
      | Area A | 0        | AREAA    |
      | Area B | 0        | AREAB    |
    And the following "courses" exist:
      | fullname        | shortname | category |
      | Project course  | PROJ      | AREAA    |
      | Other course    | OTHER     | AREAB    |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | PROJ   | student |
      | student1 | OTHER  | student |
    And the following "events" exist:
      | name                 | eventtype | course | timestart              | timeduration |
      | Spring seminar       | course    | PROJ   | ##2 March 2027 00:00## | 864000       |
      | Other area meeting   | course    | OTHER  | ##9 March 2027 14:00## | 3600         |
    And the following "events" exist:
      | name                 | eventtype | timestart              | timeduration |
      | Platform maintenance | site      | ##4 March 2027 10:00## | 3600         |
    And the following "events" exist:
      | name                 | eventtype | user     | timestart              | timeduration |
      | Read chapter four    | user      | student1 | ##5 March 2027 20:00## | 3600         |
    And the following "events" exist:
      | name                 | eventtype | category | timestart              | timeduration |
      | Area A open day      | category  | AREAA    | ##8 March 2027 09:00## | 3600         |

  @javascript
  Scenario: A ten-day event is drawn as a bar, never as a copy in each day
    Given I log in as "student1"
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then "//a[contains(@class, 'dim-cal__bar')][starts-with(@aria-label, 'Spring seminar')]" "xpath_element" should exist
    And "//div[contains(@class, 'dim-cal__content')]//a[contains(normalize-space(.), 'Spring seminar')]" "xpath_element" should not exist
    And I should see "Platform maintenance" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Read chapter four" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Area A open day" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "March 2027" in the "[data-region='dim-cal-caption']" "css_element"
    And "[data-region='dim-cal-server']" "css_element" should not be visible

  @javascript
  Scenario: A key chip switched off hides its key
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    And "[data-region='dim-cal-chips'] [data-key='site'][aria-pressed='true']" "css_element" should exist
    When I click on "[data-region='dim-cal-chips'] [data-key='site']" "css_element"
    Then "[data-region='dim-cal-chips'] [data-key='site'][aria-pressed='false']" "css_element" should exist
    And I should not see "Platform maintenance" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Read chapter four" in the "[data-region='dim-cal-grid']" "css_element"

  @javascript
  Scenario: The category tree narrows the month to a category
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    When I click on "[data-region='dim-cal-category'] button" "css_element"
    And I click on "//li[@role='treeitem'][contains(normalize-space(.), 'Area B')]" "xpath_element"
    Then I should see "Other area meeting" in the "[data-region='dim-cal-grid']" "css_element"
    And I should not see "Spring seminar" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Area B" in the "[data-region='dim-cal-category'] button" "css_element"

  @javascript
  Scenario: The keys and filters a reader leaves are there on the next visit
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    And I click on "[data-region='dim-cal-chips'] [data-key='site']" "css_element"
    And "[data-region='dim-cal-chips'] [data-key='site'][aria-pressed='false']" "css_element" should exist
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then "[data-region='dim-cal-chips'] [data-key='site'][aria-pressed='false']" "css_element" should exist
    And I should not see "Platform maintenance" in the "[data-region='dim-cal-grid']" "css_element"

  @javascript
  Scenario: The month moves with the keyboard
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    And I focus the calendar day "2027-03-03"
    When I press the right key
    Then the focused element is "[data-role='day'][data-day='2027-03-04']" "css_element"
    And I press the page_down key
    And the focused element is "[data-role='day'][data-day='2027-04-04']" "css_element"
    And I should see "April 2027" in the "[data-region='dim-cal-caption']" "css_element"

  Scenario: Without JavaScript the month is an agenda, with core's calendar a link away
    Given I log in as "student1"
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then I should see "Spring seminar" in the "[data-region='dim-cal-agenda']" "css_element"
    And I should see "Platform maintenance" in the "[data-region='dim-cal-agenda']" "css_element"
    And I should see "Other area meeting" in the "[data-region='dim-cal-agenda']" "css_element"
    And "Classic calendar" "link" should exist in the "[data-region='dim-cal-agenda']" "css_element"
