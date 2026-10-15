@theme @theme_dimensions @theme_dimensions_coursenotice
Feature: The course notice on the course page
  In order to share a course's hotsite without opening its settings
  As a teacher who edits the course
  I need the course page to show the course's public addresses, and nobody else to see them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tea       | Cher     | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname      | shortname | customfield_dimensions_model | customfield_dimensions_modality |
      | Notice Course | NC1       | 1                          | 1                      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | NC1    | editingteacher |
      | student1 | NC1    | student        |

  Scenario: The teacher sees the promotion addresses on the course page and the student does not
    When I am on the "NC1" "Course" page logged in as "teacher1"
    Then I should see "Promotion" in the "[data-region='dim-coursenotice']" "css_element"
    And I should see "Enrolment page" in the "[data-region='dim-coursenotice']" "css_element"
    And I should see the hotsite address of course "NC1"
    # The control: the same page for a student carries no notice and no address.
    And I log out
    And I am on the "NC1" "Course" page logged in as "student1"
    And "[data-region='dim-coursenotice']" "css_element" should not exist
    And I should not see the hotsite address of course "NC1"
