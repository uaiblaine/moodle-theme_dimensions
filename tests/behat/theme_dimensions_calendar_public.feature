@theme @theme_dimensions @theme_dimensions_calendar
Feature: A visitor sees the public categories' events on the theme's calendar once the site opens it
  In order to know what a public area holds this month without an account
  As a visitor who is not logged in
  I need the calendar page to show the public categories' events and the public event courses, and the login page while the site keeps it closed

  # Thin smoke tests of the public calendar (docs/calendar/PROPOSAL.md, stage CV): that the switch opens the same page
  # to a visitor under forced login, that the visitor's client draws and asks its own service, and that the switch
  # off is the login page. What a visitor may be told is PHPUnit's (tests/local/calendar/public_*_test.php,
  # tests/external/get_public_calendar_range_test.php); the client's contract is Jest's. March 2027 is fixed so the
  # scenarios do not move with the day they run on.

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "categories" exist:
      | name          | category | idnumber |
      | Public area   | 0        | PUBAREA  |
      | Internal area | 0        | INTAREA  |
    And the category "PUBAREA" is "public" for discoverability
    And the following "courses" exist:
      | fullname          | shortname | category | customfield_dimensions_model | startdate              | enddate                |
      | Open congress     | OPENCONG  | INTAREA  | 2                            | ##3 March 2027 00:00## | ##5 March 2027 23:00## |
      | Internal congress | INTCONG   | INTAREA  | 2                            | ##3 March 2027 00:00## | ##5 March 2027 23:00## |
    And the course "OPENCONG" is "public" for discoverability
    And the following "events" exist:
      | name             | eventtype | category | timestart               | timeduration | description |
      | Public open day  | category  | PUBAREA  | ##10 March 2027 09:00## | 3600         | Bring a pen |
      | Internal meeting | category  | INTAREA  | ##11 March 2027 09:00## | 3600         | Staff only  |
    And the following config values are set as admin:
      | forcelogin | 1 |

  @javascript
  Scenario: A visitor sees the public events of the month, and an event's description as text
    Given the following config values are set as admin:
      | calendarpublic | 1 | theme_dimensions |
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then "Username" "field" should not exist
    And I should see "Public open day" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Open congress" in the "[data-region='dim-cal-grid']" "css_element"
    And I should not see "Internal meeting"
    And I should not see "Internal congress"
    And "[data-region='dim-cal-server']" "css_element" should not be visible
    And "[data-region='dim-cal-types'] input[data-key='site']" "css_element" should not exist
    And "[data-region='dim-cal-types'] input[data-key='category']" "css_element" should exist
    And "//a[contains(@href, 'calendar/view.php')]" "xpath_element" should not exist
    When I click on "//div[@data-region='dim-cal-grid']//a[@data-cal-pop][contains(normalize-space(.), 'Public open day')]" "xpath_element"
    Then I should see "Bring a pen" in the "[data-region='dim-cal-description']" "css_element"
    And I press the escape key
    And "[data-region='dim-cal-popover']" "css_element" should not exist
    When I click on "//div[@data-region='dim-cal-grid']//a[@data-cal-pop][contains(normalize-space(.), 'Open congress')]" "xpath_element"
    Then "[data-region='dim-cal-popover'] a[data-action='dim-cal-join'][href*='landing']" "css_element" should exist

  @javascript
  Scenario: A key switched off asks the visitor's service again, and the month stays the public one
    Given the following config values are set as admin:
      | calendarpublic | 1 | theme_dimensions |
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    When I click on "[data-action='dim-cal-types']" "css_element"
    And I click on "[data-region='dim-cal-types'] input[data-key='category']" "css_element"
    Then I should not see "Public open day" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Open congress" in the "[data-region='dim-cal-grid']" "css_element"
    And the url should match "keys%5B%5D=open|keys\[\]=open"

  Scenario: With the public calendar off, a visitor is sent to the login page
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then "Username" "field" should exist
    And I should not see "Public open day"

  Scenario: A visitor's public category page leads to the public calendar while the site opens it
    Given the following config values are set as admin:
      | calendarpublic | 1 | theme_dimensions |
    When I am on the "PUBAREA" "theme_dimensions > Public category page" page
    And I click on "[data-region='dim-hero-calendar']" "css_element"
    Then "Username" "field" should not exist
    And "[data-region='dim-cal']" "css_element" should exist
    And the following config values are set as admin:
      | calendarpublic | 0 | theme_dimensions |
    And I am on the "PUBAREA" "theme_dimensions > Public category page" page
    And "[data-region='dim-hero-calendar']" "css_element" should not exist
