@theme @theme_dimensions @theme_dimensions_navcompact @javascript
Feature: The navbar goes compact on a tablet when its navigation does not fit
  In order to reach every item of the site's navigation on a tablet
  As a learner
  I need the navbar to offer the menu button and the side menu when the navigation would overflow

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Ana       | Souza    |
    And the site navigation has "8" custom menu items

  # The decision is the browser's (amd/src/compactnav.js reads what core's React navigation measured), so the
  # scenarios assert the classes it writes and what a reader can see and press.
  Scenario: At tablet width a navigation that does not fit gives the menu button, and it opens the side menu
    Given I change window size to "small"
    When I log in as "student1"
    Then "body.dimensions-navcompact-armed.dimensions-nav-compact" "css_element" should exist
    And "Side panel" "button" should be visible
    And ".primary-navigation" "css_element" should not be visible
    And "#theme_boost-drawers-primary.show" "css_element" should not exist
    And I click on "Side panel" "button"
    And "#theme_boost-drawers-primary.show" "css_element" should exist
    And I should see "Catalogue item 8" in the "#theme_boost-drawers-primary" "css_element"

  Scenario: Above the tablet range the navbar keeps its navigation and its "More" menu
    Given I change window size to "medium"
    When I log in as "student1"
    Then "body.dimensions-navcompact-armed" "css_element" should exist
    And "body.dimensions-nav-compact" "css_element" should not exist
    And ".primary-navigation" "css_element" should be visible
    And "Side panel" "button" should not be visible

  # The two layouts of the bar differ, and where the compact one gives the navigation more room, the navigation
  # re-splits to fit there; reading that split as "the full bar fits" would flip the mode on every frame after a
  # resize (see the hold in amd/src/compactnav.js). The step below makes that difference certain at any width.
  Scenario: Resizing through narrow, wide and medium keeps the menu button and the mode in step
    Given I change window size to "small"
    And I log in as "student1"
    And the compact navbar gives its navigation more room than the full navbar
    When I change window size to "mobile"
    Then "body.dimensions-nav-compact" "css_element" should not exist
    And the navbar keeps its mode for a second, and its menu button agrees
    And I change window size to "medium"
    And "body.dimensions-nav-compact" "css_element" should not exist
    And the navbar keeps its mode for a second, and its menu button agrees
    And I change window size to "small"
    And "body.dimensions-nav-compact" "css_element" should exist
    And "Side panel" "button" should be visible
    And the navbar keeps its mode for a second, and its menu button agrees
