@theme @theme_dimensions @theme_dimensions_flavourchoice
Feature: A person chooses their own look from the profile layer
  In order to work in the colours I am comfortable with
  As a logged-in person
  I need to pick one of the looks the administrator offers, and have every page drawn in it

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And the following "theme_boost_union > flavours" exist:
      | title   |
      | Harbour |
      | Campus  |
    And the Boost Union flavours "Harbour" are offered as looks
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |

  # The user button opens the profile layer (amd/src/profilelayer.js); its Look sub-view goes through the same write as
  # Boost Union's user menu did, which stays on the page as the fallback and still carries the Look submenu.
  @javascript
  Scenario: The Look sub-view of the profile layer records the choice in place and the page is redrawn in it
    Given I log in as "student1"
    And the page is drawn in the Boost Union flavour "none"
    When I click on "#user-menu-toggle" "css_element"
    And I click on "Look" "button" in the "[data-region='dimensions-profile']" "css_element"
    And I click on "Harbour" "link" in the "[data-region='dimensions-profile'] [data-view='look']" "css_element"
    Then the page is drawn in the Boost Union flavour "Harbour"
    And I am on site homepage
    And the page is drawn in the Boost Union flavour "Harbour"
    And "Campus" "link" should not exist in the "[data-region='dimensions-profile'] [data-view='look']" "css_element"
    And "Campus" "link" should not exist in the "#carousel-item-dimensions-look" "css_element"

  Scenario: Without JavaScript the Look item opens a form, and only its submission changes the look
    Given I log in as "student1"
    When I click on "Harbour" "link" in the "#carousel-item-dimensions-look" "css_element"
    Then the page is drawn in the Boost Union flavour "none"
    And I press "Save changes"
    And I should see "Your look was saved."
    And the page is drawn in the Boost Union flavour "Harbour"

  Scenario: The preferences page links to the choice page, which comes back to it
    Given I log in as "student1"
    And I follow "Preferences" in the user menu
    # The user menu has a Look link of its own, so the page's is the one in the main region.
    When I click on "Look" "link" in the "#region-main" "css_element"
    And I set the field "Harbour" to "1"
    And I press "Save changes"
    Then I should see "Your look was saved."
    # Back on the preferences page, whose main region carries the Look link again.
    And "Look" "link" should exist in the "#region-main" "css_element"
    And the page is drawn in the Boost Union flavour "Harbour"
