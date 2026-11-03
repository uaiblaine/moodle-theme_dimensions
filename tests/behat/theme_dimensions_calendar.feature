@theme @theme_dimensions @theme_dimensions_calendar
Feature: The theme's calendar page shows a month with spanning bars, event types, a category tree and a saved state
  In order to see what my month holds at a glance
  As somebody studying on a Dimensions site
  I need each multi-day event drawn once, the keys and categories to narrow it, and my choices kept

  # Thin smoke tests of the page (docs/calendar/PROPOSAL.md, stages C2 and C3): that the route serves it, that the
  # client draws and narrows it, that a day opens its drawer (its sheet on a phone) and an event its popover, and that
  # the server's agenda stands without JavaScript. What the range answers, to whom,
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
      | name              | eventtype | course | timestart              | timeduration | description             |
      | Mentoring session | course    | PROJ   | ##9 March 2027 14:30## | 3600         | Bring your project plan |
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
  Scenario: An event type unchecked in the dropdown hides its key, and "Clear filters" brings it back
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    And I should see "All" in the "[data-action='dim-cal-types']" "css_element"
    And "[data-action='dim-cal-clear']" "css_element" should not exist
    When I click on "[data-action='dim-cal-types']" "css_element"
    And "[data-region='dim-cal-types'] .dim-cal-key--site .dim-cal__chipcount" "css_element" should exist
    And I click on "[data-region='dim-cal-types'] input[data-key='site']" "css_element"
    Then "[data-region='dim-cal-types'] .dim-cal-key--site .dim-cal__chipcount" "css_element" should not exist
    And I should see "7 of 8" in the "[data-action='dim-cal-types']" "css_element"
    And I should not see "Platform maintenance" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Read chapter four" in the "[data-region='dim-cal-grid']" "css_element"
    And I click on "[data-action='dim-cal-clear']" "css_element"
    And I should see "Platform maintenance" in the "[data-region='dim-cal-grid']" "css_element"
    And "[data-action='dim-cal-clear']" "css_element" should not exist

  # The boxes' state is read through data-on, which the component draws: Mink turns CSS into XPath, so :checked would
  # read the attribute, never a React checkbox's live state.
  @javascript
  Scenario: "More filters" starts with every model checked, and unchecking one narrows the month until "Clear filters"
    Given the following "courses" exist:
      | fullname     | shortname | category | customfield_dimensions_model |
      | Model course | MODELC    | AREAA    | 1                            |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | MODELC | student |
    And the following "events" exist:
      | name        | eventtype | course | timestart               | timeduration |
      | Model class | course    | MODELC | ##11 March 2027 10:00## | 3600         |
    And I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    And "[data-action='dim-cal-clear']" "css_element" should not exist
    When I click on "[data-region='dim-cal-more'] button" "css_element"
    Then "[data-region='dim-cal-more'] input[name='model'][value='1'][data-on='1']" "css_element" should exist
    And "[data-region='dim-cal-more'] input[name='model'][value='2'][data-on='1']" "css_element" should exist
    And I click on "[data-region='dim-cal-more'] input[name='model'][value='2']" "css_element"
    And "[data-region='dim-cal-more'] input[name='model'][value='2'][data-on='0']" "css_element" should exist
    And I should see "1 filters on" in the "[data-region='dim-cal-more'] button" "css_element"
    And I should see "Model class" in the "[data-region='dim-cal-grid']" "css_element"
    And I should not see "Mentoring session" in the "[data-region='dim-cal-grid']" "css_element"
    And I should see "Platform maintenance" in the "[data-region='dim-cal-grid']" "css_element"
    And I click on "[data-action='dim-cal-clear']" "css_element"
    And I should see "Mentoring session" in the "[data-region='dim-cal-grid']" "css_element"
    And "[data-region='dim-cal-more'] input[name='model'][value='2'][data-on='1']" "css_element" should exist
    And "[data-action='dim-cal-clear']" "css_element" should not exist

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
    And I click on "[data-action='dim-cal-types']" "css_element"
    And I click on "[data-region='dim-cal-types'] input[data-key='site']" "css_element"
    And "[data-region='dim-cal-types'] .dim-cal-key--site .dim-cal__chipcount" "css_element" should not exist
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then I should see "7 of 8" in the "[data-action='dim-cal-types']" "css_element"
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

  @javascript
  Scenario: A day opens its drawer, which a reload keeps open and its button closes
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    When I click on "[data-role='day'][data-day='2027-03-09']" "css_element"
    Then I should see "Other area meeting" in the "[data-region='dim-cal-day']" "css_element"
    And I should see "Mentoring session" in the "[data-region='dim-cal-day']" "css_element"
    And the url should match "day=2027-03-09"
    And I reload the page
    And I should see "Mentoring session" in the "[data-region='dim-cal-day']" "css_element"
    And I click on "[data-action='dim-cal-day-close']" "css_element"
    And "[data-region='dim-cal-day']" "css_element" should not exist

  @javascript
  Scenario: An event opens its popover with its description, and Escape closes it
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    When I click on "//div[@data-region='dim-cal-grid']//a[@data-cal-pop][contains(normalize-space(.), 'Mentoring session')]" "xpath_element"
    Then I should see "Mentoring session" in the "[data-region='dim-cal-popover']" "css_element"
    And I should see "Bring your project plan" in the "[data-region='dim-cal-popover']" "css_element"
    And I press the escape key
    And "[data-region='dim-cal-popover']" "css_element" should not exist

  @javascript
  Scenario: Two events that overlap say so in the popover and in the drawer
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    When I click on "//div[@data-region='dim-cal-grid']//a[@data-cal-pop][contains(normalize-space(.), 'Mentoring session')]" "xpath_element"
    Then I should see "Overlaps with Other area meeting" in the "[data-region='dim-cal-overlap']" "css_element"
    And I press the escape key
    And I click on "[data-role='day'][data-day='2027-03-09']" "css_element"
    And I should see "Overlaps" in the "//section[@data-region='dim-cal-day']//a[contains(normalize-space(.), 'Mentoring session')]" "xpath_element"

  @javascript
  Scenario: On a phone the month is dots, a day opens its agenda sheet and an event its popover as a sheet
    Given I log in as "student1"
    And I change viewport size to "mobile"
    And I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then "[role='gridcell'][data-day='2027-03-09'] .dim-cal__daydot" "css_element" should exist
    And ".dim-cal__bar" "css_element" should not exist
    And I click on "[data-role='day'][data-day='2027-03-09']" "css_element"
    And ".dim-cal__drawer--sheet[data-region='dim-cal-day']" "css_element" should exist
    And I should see "Mentoring session" in the "[data-region='dim-cal-day']" "css_element"
    And I click on "//section[@data-region='dim-cal-day']//a[@data-cal-pop][contains(normalize-space(.), 'Mentoring session')]" "xpath_element"
    And ".dim-cal__pop--sheet[data-region='dim-cal-popover']" "css_element" should exist

  # The week view (docs/calendar/PROPOSAL.md section 21): its layout and keyboard are Jest's, these are the page.
  @javascript
  Scenario: The week view shows two overlapping events side by side and keeps the view through a reload
    Given I log in as "student1"
    And I visit "/theme/dimensions/calendar.php?month=2027-03&day=2027-03-09"
    When I click on "[data-region='dim-cal-app'] [data-action='dim-cal-view-week']" "css_element"
    Then "[data-region='dim-cal-weekgrid']" "css_element" should exist
    And the url should match "view=week"
    And "//div[@data-region='dim-cal-weekcol'][@data-day='2027-03-09']//a[@data-column='0'][@data-columns='2'][contains(normalize-space(.), 'Other area meeting')]" "xpath_element" should exist
    And "//div[@data-region='dim-cal-weekcol'][@data-day='2027-03-09']//a[@data-column='1'][@data-columns='2'][contains(normalize-space(.), 'Mentoring session')]" "xpath_element" should exist
    And I should see "Spring seminar" in the "[data-region='dim-cal-weekstrip']" "css_element"
    And I reload the page
    And "[data-region='dim-cal-weekgrid']" "css_element" should exist
    And the url should match "view=week"
    And "[data-region='dim-cal-app'] [data-action='dim-cal-view-week'][aria-pressed='true']" "css_element" should exist
    And I should see "Mentoring session" in the "[data-region='dim-cal-weekgrid']" "css_element"

  @javascript
  Scenario: On a phone the week is an agenda of seven days, and a day's header opens its sheet
    Given I log in as "student1"
    And I change viewport size to "mobile"
    When I visit "/theme/dimensions/calendar.php?view=week&week=2027-03-09"
    Then "[data-region='dim-cal-weekagenda']" "css_element" should exist
    And "[data-region='dim-cal-weekgrid']" "css_element" should not exist
    And I should see "Mentoring session" in the "[data-region='dim-cal-weekagenda'] section[data-day='2027-03-09']" "css_element"
    And I click on "[data-region='dim-cal-weekagenda'] [data-role='day'][data-day='2027-03-09']" "css_element"
    And ".dim-cal__drawer--sheet[data-region='dim-cal-day']" "css_element" should exist
    And I should see "Other area meeting" in the "[data-region='dim-cal-day']" "css_element"

  # The Day view is a phone's (docs/calendar/PROPOSAL.md section 31): its layout and its creation paths are Jest's.
  @javascript
  Scenario: On a phone the Day view shows one day and its arrows move it a day at a time
    Given I log in as "student1"
    And I change viewport size to "mobile"
    When I visit "/theme/dimensions/calendar.php?view=day&day=2027-03-09"
    Then "[data-region='dim-cal-dayview'][data-day='2027-03-09']" "css_element" should exist
    And "[data-region='dim-cal-grid']" "css_element" should not exist
    And "[data-region='dim-cal-app'] [data-action='dim-cal-view-day'][aria-pressed='true']" "css_element" should exist
    And I should see "Mentoring session" in the "[data-region='dim-cal-dayview']" "css_element"
    And I should see "Spring seminar" in the "[data-region='dim-cal-dayview'] [data-region='dim-cal-allday']" "css_element"
    And I click on "[data-region='dim-cal-app'] [data-action='dim-cal-next']" "css_element"
    And "[data-region='dim-cal-dayview'][data-day='2027-03-10']" "css_element" should exist
    And the url should match "view=day&day=2027-03-10"
    And I should not see "Mentoring session" in the "[data-region='dim-cal-dayview']" "css_element"

  @javascript
  Scenario: On a desktop a Day view's address shows the month with that day's drawer
    Given I log in as "student1"
    When I visit "/theme/dimensions/calendar.php?view=day&day=2027-03-09"
    Then ".dim-cal__drawer--drawer[data-region='dim-cal-day'][data-day='2027-03-09']" "css_element" should exist
    And "[data-region='dim-cal-dayview']" "css_element" should not exist
    And "[data-region='dim-cal-app'] [data-action='dim-cal-view-day']" "css_element" should not exist
    And the url should match "month=2027-03"
    And I should see "Mentoring session" in the "[data-region='dim-cal-day']" "css_element"

  Scenario: Without JavaScript the month is an agenda, with no link to core's calendar view
    Given I log in as "student1"
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then I should see "Spring seminar" in the "[data-region='dim-cal-agenda']" "css_element"
    And I should see "Platform maintenance" in the "[data-region='dim-cal-agenda']" "css_element"
    And I should see "Other area meeting" in the "[data-region='dim-cal-agenda']" "css_element"
    And "//a[contains(@href, 'calendar/view.php')]" "xpath_element" should not exist in the "[data-region='dim-cal']" "css_element"

  # Core's calendar leads to the page, whatever its view (docs/calendar/PROPOSAL.md sections 15 and 27): the decision
  # is PHPUnit's (tests/local/calendar/coreview_test.php), these are the redirect itself.
  Scenario: Core's month view lands on the theme's page, on the month its address named
    Given I log in as "student1"
    When I visit "/calendar/view.php?view=month&cal_d=1&cal_m=3&cal_y=2027"
    Then the url should match "month=2027-03"
    And I should see "Spring seminar" in the "[data-region='dim-cal-agenda']" "css_element"

  @javascript
  Scenario: Core's day view lands on the theme's page with that day's drawer open
    Given I log in as "student1"
    When I visit "/calendar/view.php?view=day&cal_d=9&cal_m=3&cal_y=2027"
    Then the url should match "day=2027-03-09"
    And I should see "Mentoring session" in the "[data-region='dim-cal-day']" "css_element"

  Scenario: Core's upcoming view lands on the theme's page too
    Given I log in as "student1"
    When I visit "/calendar/view.php?view=upcoming&cal_d=1&cal_m=3&cal_y=2027"
    Then the url should match "month=2027-03"
    And I should see "Spring seminar" in the "[data-region='dim-cal-agenda']" "css_element"

  Scenario: With the setting off core's month view answers as it always did
    Given the following config values are set as admin:
      | calendarcoreview | 0 | theme_dimensions |
    And I log in as "student1"
    When I visit "/calendar/view.php?view=month&cal_d=1&cal_m=3&cal_y=2027"
    Then the url should match "calendar/view\.php"

  # Core's calendar blocks drawn with the theme's calendar (docs/calendar/PROPOSAL.md section 17): the decision and the
  # data are PHPUnit's (tests/local/calendar/blocks_test.php, tests/output/core_calendar_renderer_test.php), the arrows
  # and the keyboard Jest's (js/esm/tests/calendar/block.test.ts). The blocks draw the month of now and the next days,
  # so these scenarios read dates relative to today rather than March 2027.
  @javascript
  Scenario: The month block draws the theme's month: a day leads to the page's drawer, an arrow moves the month in place
    Given the following "blocks" exist:
      | blockname      | contextlevel | reference | pagetypepattern | defaultregion |
      | calendar_month | System       | 1         | my-index        | content       |
    And I log in as "student1"
    When I visit "/my/"
    # The default Dashboard already carries a calendar block in the closed block drawer: read the one this scenario adds.
    Then "#block-region-content [data-region='dim-calblock-month'].dim-calblock-js" "css_element" should exist
    And I click on "#block-region-content [data-region='dim-calblock-month'] a[aria-current='date']" "css_element"
    And the url should match "day=\d{4}-\d{2}-\d{2}"
    And "[data-region='dim-cal-day']" "css_element" should exist
    And I visit "/my/"
    And I click on "(//*[@id='block-region-content']//div[@data-region='dim-calblock-month']//a[@data-action='dim-calblock-nav'])[2]" "xpath_element"
    And I should see "##first day of next month##%B %Y##" in the "#block-region-content [data-region='dim-calblock-status']" "css_element"
    And the url should match "/my/"

  Scenario: The upcoming block lists the next events from the theme's calendar and ends with the full calendar
    Given the following "blocks" exist:
      | blockname         | contextlevel | reference | pagetypepattern | defaultregion |
      | calendar_upcoming | System       | 1         | my-index        | content       |
    And the following "events" exist:
      | name            | eventtype | timestart          | timeduration |
      | Upcoming review | site      | ##tomorrow 10:00## | 3600         |
    And I log in as "student1"
    When I visit "/my/"
    Then I should see "Upcoming review" in the "[data-region='dim-calblock-upcoming']" "css_element"
    And I should not see "Platform maintenance" in the "[data-region='dim-calblock-upcoming']" "css_element"
    And I click on "Full calendar" "link" in the "[data-region='dim-calblock-upcoming']" "css_element"
    And the url should match "theme_dimensions/calendar|theme/dimensions/calendar\.php"

  # Stage C5 (docs/calendar/PROPOSAL.md section 23): creating is core's form, reached from the page's "New event", which
  # the page draws only when core would. Who may edit what is PHPUnit's (tests/local/calendar/editing_test.php).
  @javascript
  Scenario: An administrator creates an event from "New event" with core's form and sees it in the month
    Given I log in as "admin"
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    And I click on "[data-region='dim-cal-app'] [data-action='dim-cal-new']" "css_element"
    And I set the field "Event title" to "Planning meeting"
    And I press "Save"
    Then I should see "Planning meeting" in the "[data-region='dim-cal-app'] [data-region='dim-cal-grid']" "css_element"
    And I reload the page
    And I should see "Planning meeting" in the "[data-region='dim-cal-app'] [data-region='dim-cal-grid']" "css_element"

  @javascript
  Scenario: "New event" is drawn only for an account core lets add an event
    Given I log in as "student1"
    When I visit "/theme/dimensions/calendar.php?month=2027-03"
    Then "[data-region='dim-cal-app'] [data-action='dim-cal-new']" "css_element" should exist
    And the following "permission overrides" exist:
      | capability                       | permission | role | contextlevel | reference |
      | moodle/calendar:manageownentries | Prohibit   | user | System       |           |
    And I reload the page
    And I should see "March 2027" in the "[data-region='dim-cal-caption']" "css_element"
    And "[data-region='dim-cal-app'] [data-action='dim-cal-new']" "css_element" should not exist
