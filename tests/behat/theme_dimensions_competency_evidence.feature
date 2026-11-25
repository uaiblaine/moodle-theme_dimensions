@theme @theme_dimensions @theme_dimensions_competency_evidence @javascript
Feature: The evidence page lists prior learning and the platform's records in one dated list
  In order to see everything that counts towards my competencies in one place
  As a learner
  I need my evidence of prior learning and what the platform recorded in one list, and core's actions to work in it

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | first    |
    And the following "courses" exist:
      | fullname | shortname |
      | Bakery   | C1        |
    And the following "scales" exist:
      | name       | scale            |
      | Test Scale | Bad, Good, Great |
    And the following "core_competency > frameworks" exist:
      | shortname | idnumber | scale      |
      | Cookery   | cookery  | Test Scale |
    And the following "core_competency > competencies" exist:
      | shortname | idnumber | competencyframework |
      | Salads    | salads   | cookery             |
    And the following "core_competency > user_evidence" exist:
      | name                    | description         | user     |
      | Food safety certificate | Eight hours of work | student1 |
    And the platform recorded "student1" completed "C1" for "salads"

  Scenario: A learner keeps prior learning only, opens an evidence beside the list and deletes it with core's action
    When I am on the "student1" "theme_dimensions > evidence" page logged in as "student1"
    # Both kinds in one list: core's prior-learning evidence and, fetched by the theme's module, the platform's record.
    Then I should see "Food safety certificate" in the "[data-region='dim-comp-ev-list']" "css_element"
    And I should see "The course 'Bakery' was completed." in the "[data-region='dim-comp-ev-list']" "css_element"
    When I click on "//*[@data-region='dim-comp-scope']/button[@data-value='prior']" "xpath_element"
    Then "Food safety certificate" "link" should be visible
    And I should not see "The course 'Bakery' was completed."
    # The detail opens beside the list, from the item's own template element.
    When I click on "Food safety certificate" "link"
    Then "[data-region='dim-comp-ev-detail']" "css_element" should be visible
    And I should see "Eight hours of work" in the "[data-region='dim-comp-ev-detail']" "css_element"
    # Core's own menu and confirmation, from the item's kebab; core re-renders the region and the module re-arms.
    When I click on "Actions for Food safety certificate" "link"
    And I click on "Delete this evidence" "link" in the "//li[@data-region='user-evidence-node'][contains(., 'Food safety certificate')]" "xpath_element"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should not see "Food safety certificate"
    And "[data-region='dim-comp-ev-detail']" "css_element" should not be visible
    And the "aria-checked" attribute of "//*[@data-region='dim-comp-scope']/button[@data-value='prior']" "xpath_element" should contain "true"
