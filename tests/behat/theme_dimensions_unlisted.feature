@theme @theme_dimensions @theme_dimensions_unlisted
Feature: Unlisted courses and categories are not named to people who cannot reach them
  In order to keep a sensitive course or a whole sensitive category from being discovered
  As a platform administrator
  I need them to disappear from every listing, and a guessed URL to reveal nothing either

  Background:
    Given the following config values are set as admin:
      | theme              | dimensions |                              |
    And the following "categories" exist:
      | name                | category | idnumber |
      | Test category       | 0        | TESTCAT  |
      | Restricted category | TESTCAT  | RESTCAT  |
      | Open category       | TESTCAT  | OPENCAT  |
    And the following "courses" exist:
      | fullname         | shortname | category |
      | Restricted One   | REST1     | TESTCAT  |
      | Open One         | OPEN1     | TESTCAT  |
      | Restricted Inner | RESTIN    | RESTCAT  |
    And the course "REST1" is "unlisted" for discoverability
    And the category "RESTCAT" is "unlisted" for discoverability
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
    # The cohort is defined at the restricted category's own context, which is the only
    # placement the plugin's cohort term accepts: an ancestor's cohort is a relationship with
    # the ancestor, and a system one is where a site keeps its whole population.
    And the following "cohorts" exist:
      | name              | idnumber | contextlevel | reference |
      | Restricted cohort | RESTCOH  | Category     | RESTCAT   |
    And the following "cohort members" exist:
      | user     | cohort  |
      | student2 | RESTCOH |

  Scenario: A user who cannot enrol does not see the unlisted course in the category listing
    Given I log in as "student1"
    When I am on course index
    And I follow "Test category"
    # "Open One" is the control: it proves the listing rendered and that the filter is
    # selective, rather than the page having failed to show anything at all.
    Then I should see "Open One"
    And I should not see "Restricted One"

  Scenario: An administrator keeps seeing the unlisted course
    Given I log in as "admin"
    When I am on course index
    And I follow "Test category"
    Then I should see "Open One"
    And I should see "Restricted One"

  Scenario: Guessing the enrolment URL of an unlisted course reveals nothing about it
    When I am on the "REST1" "theme_dimensions > Standard enrolment page" page logged in as "student1"
    Then I should see "This content is not available"
    And I should not see "Restricted One"

  Scenario: Guessing a course that does not exist answers as an unlisted course does
    # An answer of its own for a missing id or shortname would tell an unlisted course from no course.
    Given I log in as "student1"
    When I visit "/enrol/index.php?id=987654"
    Then I should see "This content is not available"
    And I visit "/course/info.php?id=987654"
    And I should see "This content is not available"
    And I visit "/course/info.php?name=NOSUCHCOURSE"
    And I should see "This content is not available"

  Scenario: The enrolment page of a course that is not unlisted is untouched
    When I am on the "OPEN1" "theme_dimensions > Standard enrolment page" page logged in as "student1"
    Then I should not see "This content is not available"
    And I should see "Open One"

  Scenario: Guessing the landing page URL of an unlisted course reveals nothing about it either
    # The course carries a landing page model, so without the guard the landing page itself renders and
    # names the course; a course without a model would be forwarded to the enrolment page and
    # ghosted there, which would let this scenario pass against a landing page that leaks.
    Given the following "courses" exist:
      | fullname           | shortname | category | customfield_dimensions_model |
      | Restricted Landing page | RESTH     | TESTCAT  | 1                          |
    And the course "RESTH" is "unlisted" for discoverability
    When I am on the "RESTH" "theme_dimensions > Enrolment landing page" page logged in as "student1"
    Then I should see "This content is not available"
    And I should not see "Restricted Landing page"

  Scenario: The landing page of an unlisted course still serves somebody entitled to it
    Given the following "courses" exist:
      | fullname           | shortname | category | customfield_dimensions_model |
      | Restricted Landing page | RESTH     | TESTCAT  | 1                          |
    And the course "RESTH" is "unlisted" for discoverability
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | RESTH  | student |
    When I am on the "RESTH" "theme_dimensions > Enrolment landing page" page logged in as "student1"
    Then I should see "Restricted Landing page"
    And I should not see "This content is not available"

  Scenario: A user outside the cohort does not see the unlisted category on the course index
    Given I log in as "student1"
    When I am on course index
    And I follow "Test category"
    # "Open category" is the control: it proves the subcategories were drawn at all and that
    # the filter is selective, rather than the page having failed to draw them.
    Then I should see "Open category"
    And I should not see "Restricted category"
    # Following the area opens its panel, which lists the area's whole subtree, so the course
    # inside the withheld category would be named here if the population stopped withholding
    # it. The member scenario below is its control. The AJAX path that expands a category of
    # core's tree (course/category.ajax.php) is pinned in
    # tests/output/core/course_renderer_test.php.
    And I should not see "Restricted Inner"

  Scenario: A member of the category's cohort sees it
    Given I log in as "student2"
    When I am on course index
    And I follow "Test category"
    Then I should see "Restricted category"
    # The course inside the category is the control for the outsider's scenario above: the
    # same panel names it for somebody the plugin recognises.
    And I should see "Restricted Inner"

  Scenario: Guessing the URL of an unlisted category reveals nothing about it
    When I am on the "RESTCAT" "Category" page logged in as "student1"
    Then I should see "This content is not available"
    And I should not see "Restricted category"
    # The listed sibling is the control, in the same scenario and for the same user: it proves
    # the page type resolves and the category page renders for this person, so the refusal
    # above is the guard answering rather than a step that never reached a page.
    And I am on the "OPENCAT" "Category" page
    And I should see "Open category"
    And I should not see "This content is not available"

  Scenario: The category selector is gone from the category page
    # It named every category on the site through make_categories_list(), with no capability
    # required, so it is removed for everyone by a template override. The action bar itself is
    # the control: it still renders, which is what tells a missing select from a missing bar.
    # The action bar belongs to core's page, which the category showcase replaces, so the
    # showcase is switched off here: this is what a site that switched it off still gets.
    Given the following config values are set as admin:
      | categorypageenabled | 0 | theme_dimensions |
    When I am on the "TESTCAT" "Category" page logged in as "admin"
    Then "#action_bar" "css_element" should exist
    And "#action_bar select" "css_element" should not exist
