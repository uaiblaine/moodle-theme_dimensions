@theme @theme_dimensions @theme_dimensions_compass @block_compass @javascript @accessibility
Feature: The Compass block's open-for-enrolment line shows the theme's crests and tells courses from events
  In order to see at a glance which unit offers what
  As a learner on a Dimensions site with the Compass block
  I need the line to show each category's crest and to count courses and events apart

  # block_compass is optional for this theme: the theme answers its category_badges and course_kinds
  # hooks only where the block is installed. This feature needs the block; the theme's CI installs it,
  # and a site without it excludes the feature with --tags '~@block_compass'. The listeners' rules (a
  # hidden category, the first crest, the field's values) are held by PHPUnit
  # (tests/local/hook/block_compass/); this is the one place the block's client draws what they answer,
  # in a real browser and under axe.

  Background:
    # The courses are created after enrol_self's status is set, so the self enrolment instance course
    # creation adds starts enabled and open (the plugin's default status is disabled).
    Given the following config values are set as admin:
      | theme        | dimensions |               |
      | news_enabled | 1          | block_compass |
      | status       | 0          | enrol_self    |
    And the following "categories" exist:
      | name              | category | idnumber |
      | Escola de Governo | 0        | ESCGOV   |
      | Sem Brasao        | 0        | PLAINCAT |
      | Meus cursos       | 0        | MINE     |
    And the category "ESCGOV" has a crest
    And the following "courses" exist:
      | fullname     | shortname | category | startdate    | customfield_dimensions_model |
      | Meu curso    | MEU       | MINE     | ##tomorrow## | 1                            |
      | Curso A      | CURSOA    | ESCGOV   | ##tomorrow## | 1                            |
      | Curso B      | CURSOB    | ESCGOV   | ##tomorrow## | 1                            |
      | Evento C     | EVENTOC   | ESCGOV   | ##tomorrow## | 2                            |
      | Curso D      | CURSOD    | PLAINCAT | ##tomorrow## | 1                            |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    # An enrolment makes the line the full one with its pills (with none it is tier 1's tiles), and it
    # keeps MEU off the line: the viewer already holds an enrolment there.
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | MEU    | student |
    # On the default Dashboard, which a user's own is copied from on the first visit: the theme's
    # Dashboard offers no "Add a block" control a click can reach.
    And the following "blocks" exist:
      | blockname | contextlevel | reference | pagetypepattern | defaultregion |
      | compass   | System       | 1         | my-index        | content       |

  Scenario: A crested category's pill shows its crest, its kinds and a tooltip with the whole name; a plain one shows none
    Given I log in as "student1"
    And I visit "/my/"
    # Four courses open to student1 in two categories: three courses and one event in all.
    Then I should see "3 courses and 1 event start by" in the "//section[contains(@class, 'compass-news')]" "xpath_element"
    And "//a[@aria-label='Escola de Governo: 2 courses and 1 event open for enrolment']//span[contains(@class, 'compass-news-crestbox')]/img" "xpath_element" should exist
    # The group of one names its course, and a category with no crest draws no crest box: the control.
    And I should see "Sem Brasao · Curso D" in the "//section[contains(@class, 'compass-news')]" "xpath_element"
    And "//a[contains(@class, 'compass-news-group')][contains(normalize-space(.), 'Sem Brasao')]//span[contains(@class, 'compass-news-crestbox')]" "xpath_element" should not exist
    And I should not see "Meu curso" in the "//section[contains(@class, 'compass-news')]" "xpath_element"
    # The pill's tooltip opens on a resting pointer, with the crest, the whole name and the counts by kind.
    When I hover "//a[@aria-label='Escola de Governo: 2 courses and 1 event open for enrolment']" "xpath_element"
    Then I should see "Escola de Governo" in the ".compass-news-tip" "css_element"
    And I should see "2 courses · 1 event" in the ".compass-news-tip" "css_element"
    And ".compass-news-tip .compass-news-tipcrest img" "css_element" should exist
    And the "Compass" "block" should meet accessibility standards with "best-practice" extra tests
