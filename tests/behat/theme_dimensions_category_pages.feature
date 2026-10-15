@theme @theme_dimensions @theme_dimensions_category @local_page
Feature: A category's pages sit under the showcase's hero when local_page is installed
  In order to read what an area publishes beside its courses
  As somebody browsing a Dimensions site
  I need the category page to name the area's pages I may read, and nothing else

  # local_page is optional for this theme: the strip is drawn when the plugin is installed and not
  # at all without it. This feature creates pages through the plugin's own generator, so it needs
  # the plugin; the theme's CI installs it, and a site without it excludes the feature with
  # --tags '~@local_page'. What the page looks like without the plugin is held by PHPUnit
  # (category_pages_test), with a stand-in in place of the plugin.

  Background:
    Given the following config values are set as admin:
      | theme              | dimensions |                            |
    And the following "categories" exist:
      | name            | category | idnumber |
      | Test category   | 0        | TESTCAT  |
      | Bare category   | 0        | BARECAT  |
      | Public category | 0        | PUBCAT   |
    And the following "courses" exist:
      | fullname          | shortname | category | customfield_dimensions_model |
      | Resgate em Altura | RESG      | TESTCAT  | 1                          |
      | Curso Sem Paginas | BARE      | BARECAT  | 1                          |
      | Curso Público     | PUBC      | PUBCAT   | 1                          |
    And the course "PUBC" is "public" for discoverability
    And the category "PUBCAT" is "public" for discoverability
    And the following "local_page > pages" exist:
      | pagename           | menuname | category | status | onlyloggedin |
      | Edital 2026        | edital   | TESTCAT  | live   | 0            |
      | Rascunho do edital | rascunho | TESTCAT  | draft  | 0            |
      | Agenda pública     | agenda   | PUBCAT   | live   | 0            |
      | Normas internas    | normas   | PUBCAT   | live   | 1            |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |

  Scenario: A learner meets the area's live page above the toolbar, never its draft, and the link opens it
    Given I log in as "student1"
    When I am on the "TESTCAT" "theme_dimensions > Category showcase" page
    Then I should see "Edital 2026" in the "[data-region='dim-pages']" "css_element"
    And I should not see "Rascunho do edital"
    And I should see "Resgate em Altura" in the "[data-region='dim-vitrine-grid']" "css_element"
    When I click on "Edital 2026" "link" in the "[data-region='dim-pages']" "css_element"
    Then I should see "Edital 2026" in the ".page-header-headings" "css_element"

  Scenario: A category with no page to name draws no strip
    Given I log in as "student1"
    When I am on the "BARECAT" "theme_dimensions > Category showcase" page
    # The course is the control that the page itself was drawn.
    Then I should see "Curso Sem Paginas" in the "[data-region='dim-vitrine-grid']" "css_element"
    And "[data-region='dim-pages']" "css_element" should not exist

  Scenario: The course index draws no strip
    Given I log in as "student1"
    When I am on course index
    Then I should see "Test category" in the "[data-region='dim-areas']" "css_element"
    And "[data-region='dim-pages']" "css_element" should not exist

  Scenario: A visitor of a public category meets the pages open to the internet, and no other
    Given the following config values are set as admin:
      | forcelogin | 1 |
    When I am on the "PUBCAT" "theme_dimensions > Public category page" page
    Then I should see "Agenda pública" in the "[data-region='dim-pages']" "css_element"
    # A page for logged-in readers is the plugin's rule, and it is what a visitor may not be told about.
    And I should not see "Normas internas"
    And I should see "Curso Público" in the "[data-region='dim-vitrine-grid']" "css_element"
