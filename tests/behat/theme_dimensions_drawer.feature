@theme @theme_dimensions @theme_dimensions_drawer @javascript
Feature: The mobile drawer of a logged-in account
  In order to find my way and pick up where I stopped on a phone
  As a logged-in learner
  I need the side menu to offer what I was doing, let me walk into a menu and back, and close on Escape

  Background:
    Given the following config values are set as admin:
      | theme           | dimensions |
      | forcelogin      | 0          |
      | registerauth    | email      |
      | enablemycourses | 1          |
    # Core's colour modes on: the drawer once carried a switch for them, which is the profile layer's now.
    And the following config values are set as admin:
      | enablecolourmodes | 1 | theme_boost |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion | startdate     | enddate        |
      | Algebra  | ALG       | 1                | ##1 day ago## | ##+1 month##   |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Ana       | Souza    |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | ALG    | student |
    And the following "activities" exist:
      | activity | name        | course | completion |
      | page     | Reading one | ALG    | 1          |
    And the following "theme_boost_union > smart menu" exists:
      | title    | Learning        |
      | location | Main navigation |
    And the following "theme_boost_union > smart menu item" exists:
      | menu     | Learning        |
      | title    | Course list     |
      | itemtype | Dynamic courses |
      | itemmode | Submenu         |
    # A second sub-panel, so the drawer holds three: a panel left beside the viewport keeps it wider than itself.
    And the following "theme_boost_union > smart menu item" exists:
      | menu     | Learning        |
      | title    | More courses    |
      | itemtype | Dynamic courses |
      | itemmode | Submenu         |

  Scenario: A student continues where they left off, walks into a sub-panel and back, and closes the drawer with Escape
    Given I change window size to "mobile"
    And I am on the "Reading one" "page activity" page logged in as "student1"
    When I click on "[data-target='theme_boost-drawers-primary']" "css_element"
    # The drawer holds nothing about the person: the name and the colour mode are the profile layer's.
    Then I should not see "Ana Souza" in the "[data-region='dimensions-drawer']" "css_element"
    And "[data-region='dimensions-drawer'] [data-action='set-colourmode']" "css_element" should not exist
    And "[data-region='dimensions-profile'] [data-action='set-colourmode']" "css_element" should exist
    And I should see "Continue where you left off" in the "[data-region='dimensions-drawer-resume']" "css_element"
    And I should see "Reading one" in the "[data-region='dimensions-drawer-resume']" "css_element"
    And I should see "0 of 1 completed" in the "[data-region='dimensions-drawer-resume']" "css_element"
    And I should see "Learning" in the "#dimensions-drawer-panel-root" "css_element"
    # The sub-panel slides in and the focus goes to its way back.
    And I click on "Course list" "link" in the "#dimensions-drawer-panel-root" "css_element"
    And I should see "Algebra" in the "[data-region='dimensions-drawer-panel'][data-state='current']" "css_element"
    And the current panel of the drawer is in view
    And the focused element is "[data-region='dimensions-drawer-panel'][data-state='current'] [data-action='dimensions-drawer-back']" "css_element"
    And "#dimensions-drawer-panel-root[inert]" "css_element" should exist
    # The way back returns the focus to the row that opened the panel.
    And I click on "[data-region='dimensions-drawer-panel'][data-state='current'] [data-action='dimensions-drawer-back']" "css_element"
    And the focused element is "Course list" "link" in the "#dimensions-drawer-panel-root" "css_element"
    And "#dimensions-drawer-panel-root[inert]" "css_element" should not exist
    # Escape inside a sub-panel goes back one level too.
    And I click on "Course list" "link" in the "#dimensions-drawer-panel-root" "css_element"
    And I press the escape key
    And the focused element is "Course list" "link" in the "#dimensions-drawer-panel-root" "css_element"
    And "#theme_boost-drawers-primary.show" "css_element" should exist
    # Escape at the root closes the drawer, and the focus goes back to the menu button.
    And I press the escape key
    And "#theme_boost-drawers-primary.show" "css_element" should not exist
    And the focused element is "[data-action='opendrawer'][data-target='theme_boost-drawers-primary']" "css_element"

  Scenario: A visitor keeps the "New here?" card and gets none of the account's drawer
    Given I change window size to "mobile"
    When I am on course index
    And I click on "[data-target='theme_boost-drawers-primary']" "css_element"
    Then I should see "New here?" in the "#theme_boost-drawers-primary" "css_element"
    And I should see "Create account" in the "#theme_boost-drawers-primary" "css_element"
    And "[data-region='dimensions-drawer']" "css_element" should not exist
