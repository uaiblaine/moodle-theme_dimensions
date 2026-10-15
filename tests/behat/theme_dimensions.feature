@theme @theme_dimensions
Feature: Extending Boost Union with the Dimensions grandchild theme
  In order to run the Dimensions customisations
  As an admin
  I need the theme to install itself into the Boost Union settings category

  @javascript
  Scenario: The theme settings page is reachable from the Boost Union tertiary navigation
    When I log in as "admin"
    And I navigate to "Appearance > Boost Union > Accessibility" in site administration
    And I should see "Accessibility" in the ".admin_settingspage_tabs_with_tertiary .dropdown-toggle" "css_element"
    And I set the field "List of Boost Union settings pages" to "Dimensions"
    Then "body#page-admin-setting-theme_dimensions" "css_element" should exist
    And ".admin_settingspage_tabs_with_tertiary" "css_element" should exist
    And ".admin_settingspage_tabs_with_tertiary" "css_element" should be visible
    And I should see "Dimensions" in the ".admin_settingspage_tabs_with_tertiary .dropdown-toggle" "css_element"

  Scenario: The theme ships a login page tab
    Given I log in as "admin"
    When I navigate to "Appearance > Boost Union > Dimensions" in site administration
    Then I should see "Login page"
