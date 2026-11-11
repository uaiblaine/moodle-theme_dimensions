@theme @theme_dimensions @theme_dimensions_trail @mod_subcourse
Feature: A trail's landing page draws its courses in order
  In order to see what a trail of courses holds before joining it
  As a visitor, a member of the site or a learner of the trail
  I need the trail's page to list its courses, and a visitor to be told of a course they cannot open without being told what it is

  # Thin smoke tests of the trail part of the landing page. Who sees which step, in full or as a ghost, is
  # PHPUnit's (display_test, landing_page_trail_test); this checks that the page draws what it decided.

  Background:
    Given the following config values are set as admin:
      | forcelogin | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname            | shortname | customfield_dimensions_model |
      | Trail of Management | TRAIL     | 3                            |
    And the following "courses" exist:
      | fullname          | shortname |
      | Open Fundamentals | OPEN      |
      | Listed Budget     | LISTED    |
    And the course "TRAIL" is "public" for discoverability
    And the course "OPEN" is "public" for discoverability
    And the course "OPEN" is a step of the trail "TRAIL"
    And the course "LISTED" is a step of the trail "TRAIL"

  Scenario: A visitor sees the public course and a ghost of the listed one
    When I am on the "TRAIL" "theme_dimensions > Enrolment landing page" page
    Then I should see "Courses of the trail"
    And I should see "2 courses in sequence"
    And I should see "Open Fundamentals" in the "[data-region='dimensions-landing-trail']" "css_element"
    And I should see "This course is not open to the public." in the "[data-region='dimensions-landing-trail']" "css_element"
    And I should not see "Listed Budget"

  Scenario: A member sees both courses and no ghost
    When I am on the "TRAIL" "theme_dimensions > Enrolment landing page" page logged in as "student1"
    Then I should see "Open Fundamentals" in the "[data-region='dimensions-landing-trail']" "css_element"
    And I should see "Listed Budget" in the "[data-region='dimensions-landing-trail']" "css_element"
    And I should not see "This course is not open to the public."

  Scenario: A learner of the trail sees how far they have gone and continues at the next course
    Given the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | TRAIL  | student |
    When I am on the "TRAIL" "theme_dimensions > Enrolment landing page" page logged in as "student1"
    Then I should see "0 of 2 courses completed"
    And I should see "You are enrolled in this trail"
    And "[data-region='dimensions-landing-trail'] [data-state='current']" "css_element" should exist
    And "[data-region='dimensions-landing-trail'] [data-state='upcoming']" "css_element" should exist
    And I click on "Continue: Open Fundamentals" "link" in the "#dimensions-landing-card" "css_element"
    And I should see "Open Fundamentals"
    And I should not see "Courses of the trail"

  @javascript
  Scenario: A member opens the trail in the calendar's Trilhas view
    When I am on the "TRAIL" "theme_dimensions > Enrolment landing page" page logged in as "student1"
    And I click on "View in the calendar" "link" in the "#dimensions-landing-card" "css_element"
    Then "[data-action='dim-cal-view-trail'][aria-pressed='true']" "css_element" should exist
    And I should see "Trail of Management" in the "[data-region='dim-cal-trails']" "css_element"
    And I should see "Open Fundamentals" in the "[data-region='dim-cal-trails']" "css_element"
    And "[data-region='dim-cal-trailfilters']" "css_element" should be visible
    And "[data-action='dim-cal-types']" "css_element" should not be visible
    # The months held are named in a band above the days (Jest pins the day cells, with no column after them).
    And "[data-region='dim-cal-trailmonths'] [data-action='dim-cal-trailmonth']" "css_element" should exist
    # A course's dates are the quieter line under its name (a start alone here: the generator sets no end).
    And I should see "From" in the "[data-region='dim-cal-trailrow'] [data-region='dim-cal-traildates']" "css_element"
    # By weeks the second header row is the weeks; the zoom is written to the address.
    And I click on "Weeks" "button" in the "[data-region='dim-cal-trailzoom']" "css_element"
    And "[data-action='dim-cal-trailzoom'][data-zoom='week'][aria-pressed='true']" "css_element" should exist
    And "[data-region='dim-cal-trailweek']" "css_element" should exist
    And ".dim-cal__trailday" "css_element" should not exist
    # A course's row in the trail column scrolls to its bar and opens its popover once the scroll has ended.
    And I click on "[data-region='dim-cal-trailrow'][data-cmid]" "css_element"
    And I should see "Course 1 of 2 · Trail of Management" in the "[data-region='dim-cal-trailpopover']" "css_element"
    # The popover is the course card's dialog: the course's cover with the close disc on it, the calendar's buttons.
    And "[data-region='dim-cal-trailpopover'] [data-region='dim-cal-trailcover'] img" "css_element" should exist
    And "Open course" "link" should exist in the "[data-region='dim-cal-trailpopover']" "css_element"
    And "View trail" "link" should exist in the "[data-region='dim-cal-trailpopover']" "css_element"
    And I click on "Close" "button" in the "[data-region='dim-cal-trailcover']" "css_element"
    And "[data-region='dim-cal-trailpopover']" "css_element" should not exist
