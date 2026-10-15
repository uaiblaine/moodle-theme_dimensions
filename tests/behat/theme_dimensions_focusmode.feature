@theme @theme_dimensions @theme_dimensions_focusmode
Feature: Focus mode on activities
  In order to read one activity without the rest of the platform around it
  As somebody studying on a Dimensions site
  I need the activity page to open with a reading bar in place of the navbar

  Background:
    Given the following config values are set as admin:
      | theme              | dimensions |                            |
      | logincpfvalidation | 0                    | theme_dimensions |
      | activitynavigation | no                   | theme_boost_union          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "courses" exist:
      | fullname     | shortname | format | enablecompletion |
      | Focus Course | FC1       | topics | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | FC1    | student        |
      | teacher1 | FC1    | editingteacher |
    And the following "activities" exist:
      | activity | course | name         | idnumber |
      | page     | FC1    | Reading task | fp1      |
      | page     | FC1    | Second task  | fp2      |

  Scenario: A learner opens an activity of a course which switched focus mode on
    Given the course "FC1" has focus mode "on"
    When I am on the "Reading task" "page activity" page logged in as "student1"
    Then ".dimensions-focusbar" "css_element" should exist
    And "body.dimensions-focusmode" "css_element" should exist
    And I should see "Reading task" in the ".dimensions-focusbar-title" "css_element"
    And I should see "Exit focus mode" in the ".dimensions-focusbar" "css_element"
    And "#usernavigation" "css_element" should not exist

  Scenario: A course which did not switch focus mode on keeps the ordinary page
    Given the course "FC1" has focus mode "off"
    When I am on the "Reading task" "page activity" page logged in as "student1"
    Then ".dimensions-focusbar" "css_element" should not exist
    And "body.dimensions-focusmode" "css_element" should not exist
    And "#usernavigation" "css_element" should exist
    And ".dimensions-focus-entry" "css_element" should exist

  Scenario: The course page itself is never focused
    Given the course "FC1" has focus mode "on"
    When I am on the "FC1" "Course" page logged in as "student1"
    Then ".dimensions-focusbar" "css_element" should not exist
    And "#usernavigation" "css_element" should exist
    And ".dimensions-focus-entry" "css_element" should not exist

  Scenario: A teacher who turns editing on gets the whole theme back
    Given the course "FC1" has focus mode "on"
    When I am on the "Reading task" "page activity" page logged in as "teacher1"
    And I turn editing mode on
    Then ".dimensions-focusbar" "css_element" should not exist
    And "body.dimensions-focusmode" "css_element" should not exist
    And "#usernavigation" "css_element" should exist

  Scenario: The support parameter gives one page load of the ordinary theme
    Given the course "FC1" has focus mode "on"
    And I am on the "Reading task" "page activity" page logged in as "student1"
    And ".dimensions-focusbar" "css_element" should exist
    When I am on the "Reading task" "theme_dimensions > Activity with focus mode off" page
    Then ".dimensions-focusbar" "css_element" should not exist
    And "#usernavigation" "css_element" should exist

  @javascript
  Scenario: The width control widens the reading column and the choice survives a reload
    Given the course "FC1" has focus mode "on"
    # Stated rather than inherited: the width control is hidden below the lg breakpoint, so a
    # scenario which did not name a window size would start failing on a default change.
    And I change window size to "medium"
    When I am on the "Reading task" "page activity" page logged in as "student1"
    Then "body.dimensions-focusmode-expanded" "css_element" should not exist
    And the "aria-pressed" attribute of ".dimensions-focusbar-width" "css_element" should contain "false"
    And the "title" attribute of ".dimensions-focusbar-width" "css_element" should contain "Wide reading column"
    And I click on ".dimensions-focusbar-width" "css_element"
    And "body.dimensions-focusmode-expanded" "css_element" should exist
    And the "aria-pressed" attribute of ".dimensions-focusbar-width" "css_element" should contain "true"
    # Only aria-pressed carries the state; the name stays the same.
    And the "title" attribute of ".dimensions-focusbar-width" "css_element" should contain "Wide reading column"
    # The class is toggled in the browser; the preference is written by a web service call
    # behind it, and the reload below is what this scenario is really about.
    And I wait "1" seconds
    And I reload the page
    Then "body.dimensions-focusmode-expanded" "css_element" should exist
    And the "aria-pressed" attribute of ".dimensions-focusbar-width" "css_element" should contain "true"

  Scenario: A learner opens focus mode from the navbar and leaves it again
    Given the course "FC1" has focus mode "off"
    When I am on the "Reading task" "page activity" page logged in as "student1"
    And I click on ".dimensions-focus-entry-button" "css_element"
    Then ".dimensions-focusbar" "css_element" should exist
    And I should see "Reading task" in the ".dimensions-focusbar-title" "css_element"
    # Core 5.3's linear navigation is the route forward on a topics course, in focus mode as anywhere.
    And I click on "Next" "link" in the "sticky-footer" "region"
    And ".dimensions-focusbar" "css_element" should exist
    And I should see "Second task" in the ".dimensions-focusbar-title" "css_element"
    And I click on ".dimensions-focusbar-close" "css_element"
    And ".dimensions-focusbar" "css_element" should not exist
    And I am on the "Second task" "page activity" page
    Then ".dimensions-focusbar" "css_element" should not exist
    And ".dimensions-focus-entry" "css_element" should exist
