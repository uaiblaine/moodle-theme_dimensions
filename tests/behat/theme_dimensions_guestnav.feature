@theme @theme_dimensions @theme_dimensions_guestnav
Feature: The visitor's navbar
  In order to know what to do next on a site I have not joined yet
  As a visitor or a guest
  I need the navbar to offer logging in and, where the site takes sign-ups, creating an account

  Background:
    Given the following config values are set as admin:
      | theme      | dimensions |
      | forcelogin | 0          |

  Scenario: A visitor of a site that takes sign-ups is offered both actions instead of core's link
    Given the following config values are set as admin:
      | registerauth | email |
    When I am on course index
    Then "Log in" "link" should exist in the "[data-region='dimensions-guestnav']" "css_element"
    And I should see "Create account" in the "[data-region='dimensions-guestnav']" "css_element"
    And ".usermenu .login" "css_element" should not exist
    And ".dimensions-guestnav-chip" "css_element" should not exist
    And I click on "Create account" "link" in the "[data-region='dimensions-guestnav']" "css_element"
    And I should see "New account"

  Scenario: A site without sign-ups offers logging in alone
    Given the following config values are set as admin:
      | registerauth | |
    When I am on course index
    Then "Log in" "link" should exist in the "[data-region='dimensions-guestnav']" "css_element"
    And "Create account" "link" should not exist

  @javascript
  Scenario: The guest account reads core's guest sentence behind the Guest button
    Given the following config values are set as admin:
      | registerauth | email |
    And I log in as "guest"
    When I am on course index
    Then I should not see "You are currently using guest access" in the "nav.navbar" "css_element"
    And I click on ".dimensions-guestnav-chip" "css_element"
    And I should see "You are currently using guest access" in the ".dimensions-guestnav-menu" "css_element"
    And I should see "Create account" in the ".dimensions-guestnav-menu" "css_element"

  @javascript
  Scenario: On a phone the navbar keeps logging in and the side menu offers both actions
    Given the following config values are set as admin:
      | registerauth | email |
    And I change window size to "mobile"
    When I am on course index
    Then "Log in" "link" in the "[data-region='dimensions-guestnav']" "css_element" should be visible
    And "[data-region='dimensions-guestnav-signup']" "css_element" should not be visible
    And I click on "[data-target='theme_boost-drawers-primary']" "css_element"
    And I should see "New here?" in the "#theme_boost-drawers-primary" "css_element"
    And I should see "Create account" in the "#theme_boost-drawers-primary" "css_element"

  Scenario: Switched off, the navbar is Boost Union's again
    Given the following config values are set as admin:
      | navbarguestactions | 0 | theme_dimensions |
    When I am on course index
    Then ".usermenu .login" "css_element" should exist
    And "[data-region='dimensions-guestnav']" "css_element" should not exist
