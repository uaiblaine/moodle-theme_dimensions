@theme @theme_dimensions @theme_dimensions_loginpage
Feature: The custom login page
  In order to find the way in that is mine
  As a visitor of a Dimensions site
  I need the login page to name both ways in, and the site to be able to hand the page back to Boost Union

  Background:
    Given the following config values are set as admin:
      | theme | dimensions |

  Scenario: The login page names both ways in, beside the institutional panel
    When I visit "/login/index.php"
    Then I should see "Sign in to the learning platform"
    And I should see "Sign in with your username"
    And "#login" "css_element" should exist
    And "#username" "css_element" should exist
    And "#loginbtn" "css_element" should exist
    And ".dimensions-login-panel" "css_element" should exist
    And "#theme_boost_union-loginform" "css_element" should not exist

  Scenario: The page is side by side whatever Boost Union's arrangement says
    Given the following config values are set as admin:
      | loginarrangement | legacy | theme_boost_union |
    When I visit "/login/index.php"
    Then "body.theme_dimensions-login" "css_element" should exist
    And ".login-layout-left .dimensions-login-panel" "css_element" should exist
    And "body.theme_boost_union-loginarrangement-legacy" "css_element" should not exist

  Scenario: Switching the page off hands the login page back to Boost Union
    Given the following config values are set as admin:
      | loginpage | 0 | theme_dimensions |
    And the following config values are set as admin:
      | loginarrangement | legacy | theme_boost_union |
    When I visit "/login/index.php"
    Then "#theme_boost_union-loginform" "css_element" should exist
    And "body.theme_boost_union-loginarrangement-legacy" "css_element" should exist
    And ".dimensions-login-panel" "css_element" should not exist
    And "body.theme_dimensions-login" "css_element" should not exist

  Scenario: The panel's copy comes from the theme settings, and the monitoring notice has a switch
    Given the following config values are set as admin:
      | loginpaneltitle          | Our own headline | theme_dimensions |
      | loginpanelshowmonitoring | 0                | theme_dimensions |
    When I visit "/login/index.php"
    Then I should see "Our own headline"
    And I should not see "Continuing education for those who protect society"
    And I should not see "Use of the platform is monitored"
    And I should see "Learning platform"

  Scenario: Creating an account is offered only while the site allows self registration
    Given the following config values are set as admin:
      | registerauth | email |
    When I visit "/login/index.php"
    Then I should see "Create my account"
    And ".dimensions-login-signup a.btn-outline-primary" "css_element" should exist
    And the following config values are set as admin:
      | registerauth | |
    And I visit "/login/index.php"
    And I should not see "Create my account"
    And ".dimensions-login-signup" "css_element" should not exist

  Scenario: The second wrong password in a row suggests resetting it
    When I visit "/login/index.php"
    And I set the field "Username" to "52998224725"
    And I set the field "Password" to "not-the-password"
    And I press "Log in"
    Then "#loginerrormessage" "css_element" should exist
    And I should not see "Reset my password"
    When I set the field "Username" to "52998224725"
    And I set the field "Password" to "not-the-password"
    And I press "Log in"
    Then "#loginerrormessage" "css_element" should exist
    And I should see "Reset my password"
    And ".dimensions-login-resethint a.alert-link" "css_element" should exist
