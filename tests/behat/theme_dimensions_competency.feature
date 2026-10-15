@theme @theme_dimensions @theme_dimensions_competency @javascript
Feature: The competency pages and dialogues wear the theme and keep core's actions
  In order to read and rate competencies in one coherent design
  As a learner and as a teacher
  I need the learning plan, the breakdown report and their dialogues in the theme's markup, and core's rating and review to work in them

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
      | shortname | idnumber | description            | competencyframework |
      | Salads    | salads   | Salads are important   | cookery             |
      | Desserts  | desserts | Desserts are important | cookery             |
    And the following "core_competency > plans" exist:
      | name         | description          | competencies     | user     | status |
      | Kitchen plan | Cookery is important | salads, desserts | student1 | active |
    And the following "core_competency > course_competencies" exist:
      | course | competency |
      | C1     | salads     |
      | C1     | desserts   |
    And the following "core_competency > user_competency" exist:
      | competency | user     | grade |
      | salads     | student1 | Good  |
    And the following "core_competency > user_competency_courses" exist:
      | course | competency | user     | grade |
      | C1     | salads     | student1 | Good  |

  Scenario: A learner opens a user competency of their learning plan and requests its review
    Given I log in as "student1"
    And I visit "/admin/tool/lp/plans.php"
    When I click on "Kitchen plan" "link" in the "region-main" "region"
    Then I should see "Cookery is important"
    # The plan's progress is core's plan page around the theme's copy of tool_lp/progress_bar.
    And ".dim-comp-progress progress" "css_element" should exist in the "region-main" "region"
    And "Good" "tool_lp > learning plan rating" should exist in the "Salads" "tool_lp > learning plan"
    When I click on "Salads" "link" in the "Salads" "tool_lp > learning plan"
    Then "User competency summary" "dialogue" should be visible
    And ".dim-comp-summary" "css_element" should exist in the "User competency summary" "dialogue"
    And I should see "Salads are important" in the "User competency summary" "dialogue"
    And "Yes" "tool_lp > competency page proficiency" should exist
    And "Good" "tool_lp > competency page rating" should exist
    # Core's review workflow, bound to core's data-action hooks, still runs and re-renders the summary.
    When I click on "Request review" "button" in the "User competency summary" "dialogue"
    Then I should see "Waiting for review" in the "User competency summary" "dialogue"
    And ".dim-comp-summary" "css_element" should exist in the "User competency summary" "dialogue"

  Scenario: A teacher rates a competency from the breakdown report in the theme's dialogues
    Given I am on the "C1" "report_competency > breakdown" page logged in as "teacher1"
    Then ".dim-comp-report" "css_element" should exist
    And "Good" "report_competency > breakdown rating" should exist in the "Salads" "report_competency > breakdown"
    And "Not rated" "report_competency > breakdown rating" should exist in the "Desserts" "report_competency > breakdown"
    # Proficiency is said in words in the cell, where core only painted it green.
    And I should see "Proficient" in the "Salads" "report_competency > breakdown"
    When I click on "Not rated" "link" in the "Desserts" "report_competency > breakdown"
    Then "User competency summary" "dialogue" should be visible
    And ".dim-comp-summary" "css_element" should exist in the "User competency summary" "dialogue"
    When I click on "Rate" "button" in the "User competency summary" "dialogue"
    Then "Rate" "dialogue" should be visible
    And ".dim-comp-grader" "css_element" should exist in the "Rate" "dialogue"
    And I set the field "Rating" to "Great"
    And I click on "Rate" "button" in the ".competency-grader" "css_element"
    # Core's grade_dialogue fired on data-action="rate": the rating is stored, its evidence listed in the theme's card.
    Then "Great" "tool_lp > competency page rating" should exist
    And ".dim-comp-evidence" "css_element" should exist in the "User competency summary" "dialogue"
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    # The grading popup re-rendered the theme's report through core/templates.
    And "Great" "report_competency > breakdown rating" should exist in the "Desserts" "report_competency > breakdown"
    And ".dim-comp-report" "css_element" should exist
