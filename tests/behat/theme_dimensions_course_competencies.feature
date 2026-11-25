@theme @theme_dimensions @theme_dimensions_competency @javascript
Feature: The course competencies page shows the class to the teacher and the learner's own state, and keeps core's actions
  In order to see where a course's competencies stand
  As a teacher and as a learner
  I need the theme's extras on core's course competencies page, kept after core redraws it

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | first    |
      | student2 | Student   | second   |
      | teacher1 | Teacher   | first    |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name           | intro          | course | idnumber | completion |
      | page     | Salad workshop | Salad workshop | C1     | PAGE1    | 1          |
    And the following "scales" exist:
      | name       | scale            |
      | Test Scale | Bad, Good, Great |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber | scale      |
      | Cookery   | cookery  | Test Scale |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | description          | competencyframework |
      | Salads    | salads   | Salads are important | cookery             |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | salads     |
    And the following "core_competency > user_competency_courses" exist:
      | course | competency | user     | grade |
      | C1     | salads     | student1 | Good  |

  Scenario: The teacher sees the class distribution and can still change a course outcome
    Given I am on the "C1" "tool_lp > course competencies" page logged in as "teacher1"
    Then I should see "1 of 2 proficient" in the "[data-region='dim-comp-extra']" "css_element"
    And I should see "1 not rated" in the "[data-region='dim-comp-extra']" "css_element"
    # Core's own select, bound to core's tool_lp/competencies, still saves and redraws the page.
    When I set the field "Upon course completion" to "Attach evidence"
    Then the field "Upon course completion" matches value "Attach evidence"
    # The redraw is core's, from core's context alone: the theme's module puts the distribution back.
    And I should see "1 of 2 proficient" in the "[data-region='dim-comp-extra']" "css_element"

  Scenario: The learner sees their rating and the state of a linked activity
    Given I am on the "Salad workshop" "page activity editing" page logged in as "admin"
    And I click on "Expand all" "link" in the "region-main" "region"
    And I set the field "Course competencies" to "Salads"
    And I press "Save and return to course"
    And I log out
    And I am on the "Salad workshop" "page activity" page logged in as "student1"
    And I press "Mark as done"
    And I wait until the page is ready
    When I am on the "C1" "tool_lp > course competencies" page
    Then "Good" "tool_lp > competency grade" should exist in the "Salads" "tool_lp > competency"
    And I should see "Completed on" in the "[data-region='dim-comp-cm']" "css_element"
    And I should not see "proficient" in the "[data-region='dim-comp-extra']" "css_element"
