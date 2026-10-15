@theme @theme_dimensions @theme_dimensions_servicename
Feature: The user's name in the navigation bar
  In order to see at a glance whose session this is
  As a logged-in user
  I need my name beside the avatar on a wide screen, and inside the user menu on a phone

  # The name is whatever Moodle's full name format composes; with local_rank and the format set to
  # the alternate name it is the service name. These scenarios keep the default format, so they
  # read the same with and without local_rank on the site.
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | ribeiro  | Ana       | Ribeiro  | ribeiro@example.com |
    And the following config values are set as admin:
      | theme | dimensions |
    # Boost Union ships this off; the scenarios which depend on it say so rather than relying on
    # a default in another plugin. Note the vocabulary: Boost Union's binary selects store
    # 'yes'/'no', while this theme's store 1/0.
    And the following config values are set as admin:
      | showfullnameinusermenu | no | theme_boost_union |

  @javascript
  Scenario: The navigation bar shows the name beside the avatar
    When I log in as "ribeiro"
    Then I should see "Ana Ribeiro" in the "#user-menu-toggle" "css_element"

  @javascript
  Scenario: On a phone the name moves into the user menu
    When I log in as "ribeiro"
    And I change window size to "mobile"
    And I reload the page
    Then ".dimensions-usermenu-name" "css_element" should exist
    And ".dimensions-usermenu-name" "css_element" should not be visible
    And I click on "#user-menu-toggle" "css_element"
    And I should see "Ana Ribeiro" in the "#user-action-menu" "css_element"

  @javascript
  Scenario: On a desktop the name stays in the navbar and is not repeated in the menu
    When I log in as "ribeiro"
    And I change window size to "medium"
    And I reload the page
    Then I should see "Ana Ribeiro" in the "#user-menu-toggle" "css_element"
    And I click on "#user-menu-toggle" "css_element"
    And I should not see "Ana Ribeiro" in the "#user-action-menu" "css_element"

  @javascript
  Scenario: With Boost Union's own setting on, the menu keeps the name at every width
    Given the following config values are set as admin:
      | showfullnameinusermenu | yes | theme_boost_union |
    And the theme cache is purged and the theme is reloaded
    When I log in as "ribeiro"
    And I change window size to "medium"
    And I reload the page
    Then I should see "Ana Ribeiro" in the "#user-menu-toggle" "css_element"
    And I click on "#user-menu-toggle" "css_element"
    And I should see "Ana Ribeiro" in the "#user-action-menu" "css_element"
