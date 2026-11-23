@theme @theme_dimensions @theme_dimensions_profilelayer @javascript
Feature: The profile layer of a logged-in account
  In order to reach my own things and my preferences from one place
  As a logged-in person
  I need the user button to open a layer that says who I am, walks into its sub-views and gives the focus back

  # The logic (the groups, the identity and its mask, the notice, the hooks of the module) is PHPUnit's: these
  # scenarios only run the module. Generated names are random, so every person here has a first and a last name.
  # local_rank, where a site has it, owns user.alternatename and composes it again when a user is created (the full
  # name for somebody without a rank), so it is switched off before the users are made: the alternate names below
  # are then the ones stored. The row is inert on a site without the plugin.
  Background:
    Given the following config values are set as admin:
      | theme   | dimensions |            |
      | enabled | 0          | local_rank |
    And the following "theme_boost_union > flavours" exist:
      | title   |
      | Harbour |
      | Campus  |
    And the Boost Union flavours "Harbour,Campus" are offered as looks
    And the following "users" exist:
      | username    | firstname | lastname | alternatename |
      | student1    | Sam       | Student  | Cadet Student |
      | 12345678909 | Ana       | Souza    | Cap. Souza    |

  # Look is the sub-view walked here because it needs nothing from the network; the Language sub-view has the
  # scenario after this one, which runs only where remote langimport tests are enabled.
  Scenario: A student opens the layer from the user button, walks into a sub-view and leaves it with Escape twice
    Given I log in as "student1"
    When I click on "#user-menu-toggle" "css_element"
    Then "[data-region='dimensions-profile']" "css_element" should be visible
    And the focused element is "[data-region='dimensions-profile'] [data-view='main'] [data-action='dimensions-layer-close']" "css_element"
    And I should see "Sam Student" in the "[data-region='dimensions-profile']" "css_element"
    And I should see "My area" in the "[data-region='dimensions-profile']" "css_element"
    And I should see "Account" in the "[data-region='dimensions-profile']" "css_element"
    And I should see "Display" in the "[data-region='dimensions-profile']" "css_element"
    And I click on "Look" "button" in the "[data-region='dimensions-profile']" "css_element"
    And I should see "Harbour" in the "[data-region='dimensions-profile'] [data-view='look']" "css_element"
    And I should see "Campus" in the "[data-region='dimensions-profile'] [data-view='look']" "css_element"
    And the focused element is "[data-region='dimensions-profile'] [data-view='look'] [data-action='dimensions-layer-back']" "css_element"
    And I press the escape key
    And the focused element is "Look" "button" in the "[data-region='dimensions-profile']" "css_element"
    And "[data-region='dimensions-profile'] [data-view='look']" "css_element" should not be visible
    And I press the escape key
    And "[data-region='dimensions-profile']" "css_element" should not be visible
    And the focused element is "#user-menu-toggle" "css_element"

  # A second language needs a language pack, which Behat downloads only on a site that defines
  # TOOL_LANGIMPORT_REMOTE_TESTS in config.php: without it this scenario is skipped, as core's own language menu
  # feature is.
  Scenario: A student opens the Language sub-view, sees the languages and leaves with Escape twice
    Given remote langimport tests are enabled
    And the following "language pack" exists:
      | language | en_ar |
    And I log in as "student1"
    When I click on "#user-menu-toggle" "css_element"
    And I click on "Language" "button" in the "[data-region='dimensions-profile']" "css_element"
    Then I should see "English (pirate)" in the "[data-region='dimensions-profile'] [data-view='language']" "css_element"
    And "[data-region='dimensions-profile'] [data-view='language'] [role='menuitemradio'][aria-checked='true']" "css_element" should exist
    And the focused element is "[data-region='dimensions-profile'] [data-view='language'] [data-action='dimensions-layer-back']" "css_element"
    And I press the escape key
    And the focused element is "Language" "button" in the "[data-region='dimensions-profile']" "css_element"
    And I press the escape key
    And "[data-region='dimensions-profile']" "css_element" should not be visible
    And the focused element is "#user-menu-toggle" "css_element"

  Scenario: The layer shows the identity fields the administrator chose, masked on the server
    Given the following config values are set as admin:
      | identitytitle    | alternatename           | theme_dimensions |
      | identitylines    | username                | theme_dimensions |
      | identitypatterns | username=***.###.###-** | theme_dimensions |
    And I log in as "12345678909"
    When I click on "#user-menu-toggle" "css_element"
    Then I should see "Cap. Souza" in the "[data-region='dimensions-profile']" "css_element"
    And I should see "•••.456.789-••" in the "[data-region='dimensions-profile']" "css_element"
    And I should not see "12345678909" in the "[data-region='dimensions-profile']" "css_element"

  Scenario: An administrator logged in as a student sees the notice and returns through its link
    Given I am on the "student1" "user > profile" page logged in as "admin"
    And I follow "Log in as"
    And I press "Continue"
    When I click on "#user-menu-toggle" "css_element"
    Then I should see "You are logged in as this user" in the "[data-region='dimensions-profile']" "css_element"
    And I should see "Sam Student" in the "[data-region='dimensions-profile']" "css_element"
    And I click on "Return to Admin User" "link" in the "[data-region='dimensions-profile']" "css_element"
    And "[data-region='dimensions-profile']" "css_element" should not exist
    And I should not see "You are logged in as this user"

  Scenario: With core's colour modes on, the layer's Dark cell switches the page to the dark mode
    Given the following config values are set as admin:
      | enablecolourmodes | 1 | theme_boost |
    And I log in as "student1"
    And "html[data-bs-theme='dark']" "css_element" should not exist
    When I click on "#user-menu-toggle" "css_element"
    And I click on "[data-region='dimensions-profile'] [data-action='set-colourmode'][data-colourmode='dark']" "css_element"
    Then "html[data-bs-theme='dark']" "css_element" should exist
    And "[data-region='dimensions-profile'] [data-colourmode='dark'][aria-checked='true']" "css_element" should exist
    And "#usernavigation .colourmodemenu" "css_element" should not be visible
