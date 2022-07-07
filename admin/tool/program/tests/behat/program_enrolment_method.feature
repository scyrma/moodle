@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Program enrolment method works as exepcted
  In order to test the program enrolment method
  As a manager
  I need to create a program, allocate one user and check if enrolment works

  Background:
    Given "1" tenants exist with "4" users and "2" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_program_manager     | System       |           |
      | manager1 | tool_dynamicrule_manager | System       |           |
    Given the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |

  Scenario: User cannot access course from program if not enroled
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    And I set the field "Select courses" to "Course 12,Course 11"
    Then I press "Save changes"
    And I log out
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program1" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And I click on "Course12" "link"
    And I click on "Start course" "button"
    And I should see "Course12"
    And I should see "Announcements"
    And I should see "Topic 1"
    And I log out
    Then I log in as "user12"
    And I am on "Course12" course homepage
    And I should see "Course12"
    And I should see "Announcements"
    And I should see "Topic 1"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You can not enrol, this course is locked."
    And I log out
    And I log in as "tenantadmin1"
    And I am on "Course12" course homepage
    And I click on "Participants" "link"
    Then I should see "Participants" in the "#page-content" "css_element"
    And I am on the "Course12" "enrolment methods" page
    And I should see "Enrolment methods"
    And I should see "Program enrolment (Program1)"
    And "Edit enrolment" "icon" should not exist in the "Program enrolment (Program1)" "table_row"
    And "Delete" "icon" should not exist in the "Program enrolment (Program1)" "table_row"
    And I click on "Participants" "link"
    And I should see "User 11"
    And I should see "User 12"
    And "Edit enrolment" "icon" should not exist in the "User 11" "table_row"
    And "Delete" "icon" should not exist in the "User 11" "table_row"
    And I log out
    Then I log in as "user13"
    And I am on "Course12" course homepage
    And I should see "Course12"
    And I should see "You cannot enrol yourself in this course"
    And I log out
    And I log in as "tenantadmin1"
    And I am on "Course12" course homepage with editing mode on
    When I backup "Course12" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course12 restored in a new course |
    Then I should see "Course12 restored in a new course"
    And I click on "Participants" "link"
    And I should see "User 11"
    And I should see "User 12"
    And I am on the "Course12 restored in a new course copy 1" "enrolment methods" page
    And I should see "Enrolment methods"
    And I should not see "Program enrolment (Program1)"
    And I should see "2" in the "Manual enrolments" "table_row"
    And I log out
