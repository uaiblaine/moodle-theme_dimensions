@theme @theme_dimensions @theme_dimensions_competency @javascript
Feature: A learner's competency in a course wears the theme and keeps core's rating
  In order to read and rate one learner's competency in my course at a glance
  As a teacher
  I need the band of figures and the evidence table in the theme's markup, and core's rating dialogue to work in them

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | first    |
      | teacher1 | Teacher   | first    |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
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

  Scenario: A teacher opens a learner's competency in a course and rates it through core's dialogue
    Given I am on the "student1|salads|C1" "theme_dimensions > user competency in course" page logged in as "teacher1"
    Then ".dim-comp-ucc [data-region='dim-comp-ucc-band']" "css_element" should exist
    And I should see "Salads are important"
    And "Good" "tool_lp > competency page rating" should exist
    And I should see "No evidence" in the "[data-region='evidence-listing']" "css_element"
    When I click on "Rate" "button" in the ".dim-comp-ucc-primary" "css_element"
    Then "Rate" "dialogue" should be visible
    And ".dim-comp-grader" "css_element" should exist in the "Rate" "dialogue"
    And I set the field "Rating" to "Great"
    And I click on "Rate" "button" in the ".competency-grader" "css_element"
    # Core's inline grader stored the rating and tool_lp/user_competency_info re-rendered the theme's copy from core's
    # context alone: the band is back, its count drawn by the template's JavaScript, the rating's evidence a table row.
    Then "Great" "tool_lp > competency page rating" should exist
    And "Great" "tool_lp > competency grade" should exist in the "[data-region='dim-comp-ucc-rows']" "css_element"
    And I should see "1" in the "[data-dim-figure='evidence']" "css_element"
    And ".dim-comp-ucc [data-region='dim-comp-ucc-band']" "css_element" should exist
