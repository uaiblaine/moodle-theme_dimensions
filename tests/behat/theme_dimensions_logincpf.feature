@theme @theme_dimensions @theme_dimensions_logincpf
Feature: Logging in with a CPF
  In order to sign in with the document number which is my username
  As a user of a Dimensions site
  I need the username field of the login form to accept nothing but a CPF

  Background:
    Given the following "users" exist:
      | username    | firstname | lastname | email             | password      |
      | 52998224725 | Joana     | Silva    | joana@example.com | Dimensions#2026 |
    And the following config values are set as admin:
      | theme | dimensions |

  @javascript
  Scenario: The username field formats a CPF while it is typed
    When I visit "/login/index.php"
    And I set the field "Username" to "52998224725"
    Then the field "Username" matches value "529.982.247-25"

  @javascript
  Scenario: A masked CPF is unmasked again before the form is sent
    When I visit "/login/index.php"
    And I set the field "Username" to "529.982.247-25"
    And I set the field "Password" to "Dimensions#2026"
    And I click on "#loginbtn" "css_element"
    Then "#username" "css_element" should not exist
    And I should see "Joana"

  @javascript
  Scenario: A CPF whose check digits do not add up is refused before the form is sent
    When I visit "/login/index.php"
    And I set the field "Username" to "52998224724"
    And I set the field "Password" to "Dimensions#2026"
    And I click on "#loginbtn" "css_element"
    Then I should see "This is not a valid CPF. Check the digits and try again."
    And the field "Username" matches value "529.982.247-24"

  @javascript
  Scenario: The username field refuses anything which is not a digit
    When I visit "/login/index.php"
    And I set the field "Username" to "admin"
    Then the field "Username" matches value ""
    And I should see "This field takes a CPF, so only digits are accepted here."

  @javascript
  Scenario: The username field stops at eleven digits
    When I visit "/login/index.php"
    And I set the field "Username" to "529982247259"
    Then the field "Username" matches value "529.982.247-25"
    And I should see "A CPF has 11 digits. The field does not take any more than that."

  @javascript
  Scenario: The bypass parameter gives a plain login form back to an account which is not a CPF
    When I visit "/login/index.php?nocpf=1"
    And I set the field "Username" to "admin"
    Then the field "Username" matches value "admin"
    And I set the field "Password" to "admin"
    And I click on "#loginbtn" "css_element"
    And "#username" "css_element" should not exist

  @javascript
  Scenario: Switching the setting off leaves the username field alone
    Given the following config values are set as admin:
      | logincpfvalidation | 0 | theme_dimensions |
    When I visit "/login/index.php"
    And I set the field "Username" to "admin"
    Then the field "Username" matches value "admin"
