@theme @theme_dimensions @theme_dimensions_trail @mod_subcourse
Feature: A trail's landing page draws its courses in order
  In order to see what a trail of courses holds before joining it
  As a visitor or a member of the site
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
