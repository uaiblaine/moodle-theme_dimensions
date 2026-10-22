@theme @theme_dimensions @theme_dimensions_readingbar @javascript
Feature: The navbar follows the reading
  In order to know where I am while I read an activity
  As a learner
  I need the navbar to carry the activity, the course and my progress, and a way into the course index

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Algebra  | ALG       | topics | 1                |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Ana       | Souza    |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | ALG    | student |
    And the following "activities" exist:
      | activity | name        | course | completion |
      | page     | Reading one | ALG    | 1          |

  # The Behat site never swaps the bar with the scroll (amd/src/readingbar.js reads the server's flag), so the
  # scenario shows the layer the way the module would, through the theme's step, and asserts what it carries.
  Scenario: The context layer carries the activity and its status, and its control opens the course index
    Given I change window size to "mobile"
    And I am on the "Reading one" "page activity" page logged in as "student1"
    And "nav.navbar.fixed-top [data-region='dimensions-ctxbar']" "css_element" should exist
    And "#theme_boost-drawers-courseindex.show" "css_element" should not exist
    When I show the context bar of the navbar
    Then I should see "Reading one" in the "[data-region='dimensions-ctxbar-title']" "css_element"
    And I should see "Algebra" in the "[data-region='dimensions-ctxbar-sub']" "css_element"
    And I should see "0 of 1 completed" in the "[data-region='dimensions-ctxbar-sub']" "css_element"
    And I should see "Pending" in the "[data-region='dimensions-ctxbar-status']" "css_element"
    And I click on "[data-region='dimensions-ctxbar-index']" "css_element"
    And "#theme_boost-drawers-courseindex.show" "css_element" should exist
    And I should see "Reading one" in the "#theme_boost-drawers-courseindex" "css_element"

  # Core 5.3's linear navigation moves the manual completion button into the sticky footer; the context layer
  # follows core's manualCompletionToggled event there, its status and the course's progress alike.
  Scenario: Marking the activity done in core's footer updates the context layer
    Given the following config values are set as admin:
      | enablelinearnav | 1 | format_topics |
    And I am on the "Reading one" "page activity" page logged in as "student1"
    And I show the context bar of the navbar
    And I should see "0 of 1 completed" in the "[data-region='dimensions-ctxbar-sub']" "css_element"
    And I should see "Pending" in the "[data-region='dimensions-ctxbar-status']" "css_element"
    When I toggle the manual completion state of "Reading one"
    And the manual completion button of "Reading one" is displayed as "Done"
    Then I should see "Completed" in the "[data-region='dimensions-ctxbar-status']" "css_element"
    And I should see "1 of 1 completed" in the "[data-region='dimensions-ctxbar-sub']" "css_element"
    And I toggle the manual completion state of "Reading one"
    And the manual completion button of "Reading one" is displayed as "Mark as done"
    And I should see "Pending" in the "[data-region='dimensions-ctxbar-status']" "css_element"
    And I should see "0 of 1 completed" in the "[data-region='dimensions-ctxbar-sub']" "css_element"
