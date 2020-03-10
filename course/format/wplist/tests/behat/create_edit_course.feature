@format @format_wplist @moodleworkplace @javascript
Feature: Course in wplist format can be created and edited
  In order to create courses
  As a teacher
  I need to create and edit courses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | manager  | Manager   | 1        | manager1@example.com |
    And the following "role assigns" exist:
      | user  | role    | contextlevel | reference |
      | manager | manager | System       |           |

  Scenario: Create a course in wplist format and edit it
    When I log in as "manager"
    And I am on site homepage
    When I press "Add a new course"
    And I expand all fieldsets
    And I set the field "Format" to "Workplace list format"
    And I wait to be redirected
    And I expand all fieldsets
    And I set the following fields to these values:
      | Course short name  | myfirstcourse   |
      | Course full name   | My first course |
      | Number of sections | 5               |
    And the field "Accordion effect" matches value "No"
    And I press "Save and display"
    And I press "Proceed to course content"
    And I navigate to "Edit settings" in current page administration
    And I expand all fieldsets
    And the field "Format" matches value "Workplace list format"
    And I should not see "Number of sections"
    And I press "Save"
    And I log out
