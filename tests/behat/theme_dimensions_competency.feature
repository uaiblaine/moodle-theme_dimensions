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
    # The plan cards open local_dimensions' overview, so core's plan page is opened by its address, with noredirect=1,
    # which keeps the owner on it.
    Given I am on the "Kitchen plan" "theme_dimensions > learning plan" page logged in as "student1"
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

  Scenario: The owner of a plan is taken from core's plan page to the plan overview, and a manager is not
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Manager   | first    |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    When I am on the "Kitchen plan" "theme_dimensions > learning plan address" page logged in as "student1"
    Then "#local-dimensions-viewplan-accordion" "css_element" should exist
    And "body#page-admin-tool-lp-plan" "css_element" should not exist
    When I am on the "Kitchen plan" "theme_dimensions > learning plan address" page logged in as "manager1"
    Then "body#page-admin-tool-lp-plan" "css_element" should exist
    And "#local-dimensions-viewplan-accordion" "css_element" should not exist

  Scenario: A teacher rates a competency from the breakdown report in the theme's dialogues
    Given I am on the "C1|student1" "theme_dimensions > competency breakdown" page logged in as "teacher1"
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
    # Core's grade_dialogue fired on data-action="rate": the rating is stored, its evidence a row of the theme's table
    # (the dialogue is core's grading popup rendering tool_lp/user_competency_summary_in_course).
    Then "Great" "tool_lp > competency page rating" should exist
    And "[data-region='dim-comp-ucc-rows'] [data-region='evidence']" "css_element" should exist in the "User competency summary" "dialogue"
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    # The grading popup re-rendered the theme's report through core/templates.
    And "Great" "report_competency > breakdown rating" should exist in the "Desserts" "report_competency > breakdown"
    And ".dim-comp-report" "css_element" should exist

  Scenario: A teacher hides a competency in the class report's view and rates a learner from a cell
    Given the following "users" exist:
      | username | firstname | lastname |
      | student2 | Student   | second   |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student2 | C1     | student |
    When I am on the "C1" "report_competency > breakdown" page logged in as "teacher1"
    # No learner named: the class report's React view, one row per learner, one column per competency.
    Then I should see "Student first" in the "[data-region='dim-comp-rview-rows']" "css_element"
    And I should see "Student second" in the "[data-region='dim-comp-rview-rows']" "css_element"
    And I should see "2 learners" in the "[data-region='dim-comp-rview-total']" "css_element"
    And I should see "Salads" in the "[data-region='dim-comp-rview-rows'] thead" "css_element"
    And I should see "Desserts" in the "[data-region='dim-comp-rview-rows'] thead" "css_element"
    And I should see "Good" in the "Student first" "table_row"
    # Columns writes the reader's choice, then the view reads the rows again without the hidden competency.
    When I press "Columns"
    And I click on "Desserts" "button" in the "[data-region='dim-comp-rview-columns']" "css_element"
    Then I should not see "Desserts" in the "[data-region='dim-comp-rview-rows']" "css_element"
    And I should see "Salads" in the "[data-region='dim-comp-rview-rows'] thead" "css_element"
    And I press "Columns"
    # Core's grading dialogue, opened from the cell: the second learner's one "Not rated" is now Salads.
    When I click on "Not rated" "link" in the "Student second" "table_row"
    Then "User competency summary" "dialogue" should be visible
    When I click on "Rate" "button" in the "User competency summary" "dialogue"
    # Core fills the grader's ratings after the dialogue opens: wait for it, as the breakdown scenario does.
    Then "Rate" "dialogue" should be visible
    And ".dim-comp-grader" "css_element" should exist in the "Rate" "dialogue"
    # The report's own filters are selects too: name the dialogue's field.
    And I set the field "Rating" in the "Rate" "dialogue" to "Great"
    And I click on "Rate" "button" in the ".competency-grader" "css_element"
    Then "Great" "tool_lp > competency page rating" should exist
    And I click on "Close" "button" in the "User competency summary" "dialogue"
    # The dialogue's close told the view, which read its rows again: the cell names the new rating.
    And I should see "Great" in the "Student second" "table_row"
    # Core's table is one link away and keeps part one's page: the same learners, the same choice of columns.
    When I click on "Table view" "link" in the "[data-region='dim-comp-classreport']" "css_element"
    Then I should see "Student second" in the "[data-region='core_reportbuilder/report']" "css_element"
    And I should see "Great" in the "Student second" "table_row"
    And I should not see "Desserts" in the "[data-region='core_reportbuilder/report']" "css_element"

  Scenario: A manager completes a learner's plan from its card and the card follows the plan
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager1 | Manager   | first    |
    And the following "role assigns" exist:
      | user     | role    | contextlevel | reference |
      | manager1 | manager | System       |           |
    And the following "core_competency > plans" exist:
      | name        | user     | status   |
      | Garden plan | student1 | complete |
      | Herb plan   | student1 | draft    |
    When I am on the "student1" "theme_dimensions > learning plans" page logged in as "manager1"
    # The first paint: block_dimensions' card, under the scope the page opens on, the first with plans.
    Then ".plan-card" "css_element" should exist in the "//li[@data-region='plan-node'][contains(., 'Kitchen plan')]" "xpath_element"
    And the "aria-checked" attribute of "//*[@data-region='dim-comp-scope']/button[@data-value='active']" "xpath_element" should contain "true"
    And "Kitchen plan" "link" should be visible
    And "Garden plan" "link" should not be visible
    # Core's own menu and confirmation, from the card's kebab.
    When I click on "Actions for Kitchen plan" "link"
    And I click on "Complete this learning plan" "link" in the "//li[@data-region='plan-node'][contains(., 'Kitchen plan')]" "xpath_element"
    And I click on "Complete this learning plan" "button" in the "Confirm" "dialogue"
    # Core re-rendered the region from its own context; the module put the cards back and refilled them, so the
    # completed plan's card carries the block's completion chip, and the scope moved to the one left with plans.
    Then the "aria-checked" attribute of "//*[@data-region='dim-comp-scope']/button[@data-value='complete']" "xpath_element" should contain "true"
    And I should see "Completed on" in the "//li[@data-region='plan-node'][contains(., 'Kitchen plan')]" "xpath_element"
    And ".plan-card" "css_element" should exist in the "//li[@data-region='plan-node'][contains(., 'Kitchen plan')]" "xpath_element"
    And "Garden plan" "link" should be visible
    And "Herb plan" "link" should not be visible

  Scenario: A learner stars a plan, keeps only favourites, filters by a tag and sorts by due date on their plans page
    Given the following config values are set as admin:
      | enable_favourites       | 1 | block_dimensions |
      | enable_plan_tag1_filter | 1 | block_dimensions |
    And the following "core_competency > templates" exist:
      | shortname |
      | Safety    |
    And the learning plan template "Safety" shows plan cards tagged "Kitchen"
    And "student1" holds an active learning plan "Fire safety" based on the template "Safety"
    And the following "core_competency > plans" exist:
      | name      | user     | status | duedate     |
      | Herb plan | student1 | active | ##+1 week## |
    When I am on the "student1" "theme_dimensions > learning plans" page logged in as "student1"
    # The star is block_dimensions' own, on the card; the bar's star keeps the favourites.
    And I click on ".dims-fav-btn" "css_element" in the "//li[@data-region='plan-node'][contains(., 'Fire safety')]" "xpath_element"
    And I click on "Favourites only" "button"
    Then the "aria-pressed" attribute of "[data-action='dim-comp-fav']" "css_element" should contain "true"
    And "Fire safety" "link" should be visible
    And "Kitchen plan" "link" should not be visible
    And "Herb plan" "link" should not be visible
    When I click on "Remove filter Favourites only" "button"
    Then "Herb plan" "link" should be visible
    # Filters: the template's tag values, applied on press; the chip removes it again.
    When I click on "Filters" "button"
    And I click on "Kitchen" "button" in the "[data-region='dim-comp-fpanel']" "css_element"
    And I click on "Show results" "button"
    Then "Fire safety" "link" should be visible
    And "Herb plan" "link" should not be visible
    When I click on "Remove filter" "button" in the "[data-region='dim-comp-chips']" "css_element"
    Then "Herb plan" "link" should be visible
    # Organise sorts in place: core's order is by name; by due date the dated plan comes first.
    And "Fire safety" "link" should appear before "Herb plan" "link"
    When I click on "Organise" "button"
    And I click on "Due date" "button" in the "[data-region='dim-comp-organise-menu']" "css_element"
    Then "Herb plan" "link" should appear before "Fire safety" "link"
    And the "aria-checked" attribute of "//*[@data-region='dim-comp-organise-menu']//button[@data-value='due']" "xpath_element" should contain "true"
