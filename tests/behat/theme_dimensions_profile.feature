@theme @theme_dimensions @theme_dimensions_profile
Feature: A profile is only reachable by someone with a course in common
  In order to keep the people on a public-security platform from being enumerated
  As a platform administrator
  I need a profile to open only for a fellow participant, and for a teacher inside their own course

  Background:
    Given the following config values are set as admin:
      | theme                | dimensions |                            |
      | logincpfvalidation   | 0                    | theme_dimensions |
      | forceloginforprofiles| 1                    |                            |
    And the following "courses" exist:
      | fullname     | shortname |
      | Their Course | THEIRS    |
      | Other Course | OTHERS    |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tess      | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
      | student2 | Robin     | Learner  | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | THEIRS | editingteacher |
      | student1 | THEIRS | student        |
      | student2 | OTHERS | student        |

  @javascript
  Scenario: A fellow participant sees the redesigned profile
    When I am on the "student1" "user > profile" page logged in as "teacher1"
    # The card headings are what the theme's own renderer emits; core's renderer emits the
    # same strings, so the control below is what distinguishes the two.
    Then I should see "User details"
    And ".dimensions-profile-card" "css_element" should exist
    And I should not see "The details of this user are not available to you"

  Scenario: An authenticated user with no course in common gets nothing
    When I am on the "student1" "user > profile" page logged in as "student2"
    Then I should see "The details of this user are not available to you"
    And I should not see "Sam Student"

  Scenario: A teacher of another course is not a directory entry either
    # Without the theme's rule this is the case core opens: has_coursecontact_role() grants
    # every authenticated user the profile of anybody holding an editingteacher role anywhere.
    When I am on the "teacher1" "user > profile" page logged in as "student2"
    Then I should see "The details of this user are not available to you"
    And I should not see "Tess Teacher"

  Scenario: The same teacher is reachable from inside the course they teach
    Given I am on the "THEIRS" "Course" page logged in as "student1"
    When I follow "Participants"
    And I follow "Tess Teacher"
    Then I should see "User details"
    And I should not see "The details of this user are not available to you"
