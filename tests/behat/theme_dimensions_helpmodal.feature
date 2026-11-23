@theme @theme_dimensions @theme_dimensions_helpmodal @javascript
Feature: A long help text opens in a dialogue
  In order to read a help text that does not fit beside its field
  As anybody filling in a form
  I need a long help to open as a dialogue I can close, and a short one to stay a popover

  # The decisions (the guards, the order of the swap, the reading rhythm) are PHPUnit's: these scenarios only run the
  # module. The fixture draws core's help icon with one text no window can show inside the popover's cap and one that
  # always fits.
  Background:
    Given the following config values are set as admin:
      | theme | dimensions |
    And I log in as "admin"
    And I am on fixture page "/theme/dimensions/tests/behat/fixtures/helpmodal.php"

  Scenario: A long help opens as a dialogue and gives the focus back to its icon
    When I click on "Help with Long setting" "icon"
    Then "Help with Long setting" "dialogue" should be visible
    And I should see "Item 40 of the long help" in the "Help with Long setting" "dialogue"
    And I press the escape key
    And "Help with Long setting" "dialogue" should not exist
    And the focused element is "#dimensions-help-long .help-icon" "css_element"
    # The focus given back shows no popover over the page the dialogue was closed on.
    And ".help-popover" "css_element" should not exist

  Scenario: A short help stays core's popover
    When I click on "Help with Short setting" "icon"
    Then I should see "A short help text." in the ".help-popover" "css_element"
    And "Help with Short setting" "dialogue" should not exist
