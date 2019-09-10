@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Program enrolment method works as exepcted
  In order to test the program enrolment method
  As a manager
  I need to create a program, allocate one user and check if enrolment works

  Background:
    Given "1" tenants exist with "3" users and "1" courses in each
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
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    Given the following users allocations to programs exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |

  Scenario: User cannot access course from program if not enroled
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on ".edit_program" "css_element" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    Then I click on "Course11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select courses"
    Then I press "Save changes" in the modal form dialogue
    And I log out
    # User11 cannot access directly the course but can access it from the dashboard.
    When I log in as "user11"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You can not enrol yourself in this course"
    Then I follow "Dashboard"
    Then I should see "Course11"
    Then I click on ".enrol_to_course" "css_element"
    And I should see "Course11"
    And I click on "Expand all" "button"
    And I should see "Announcements"
    And I should see "Topic 1"
    And I should see "Topic 2"
    And I log out
    And I log in as "tenantadmin1"
    And I am on "Course11" course homepage
    And I click on "Participants" "link"
    Then I should see "Participants" in the "#page-content" "css_element"
    And I click on "#region-main-settings-menu [role=button]" "css_element"
    And I choose "Enrolment methods" in the open action menu
    And I should see "Enrolment methods"
    And I should see "Program enrolment (Program1)"
    And "Edit enrolment" "icon" should not exist in the "Program enrolment (Program1)" "table_row"
    And "Delete" "icon" should not exist in the "Program enrolment (Program1)" "table_row"
    And I click on "Participants" "link"
    And I should see "User 11"
    And "Edit enrolment" "icon" should not exist in the "User 11" "table_row"
    And "Delete" "icon" should not exist in the "User 11" "table_row"
    And I log out
    Then I log in as "user12"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You can not enrol yourself in this course"
    And I log out
    And I log in as "tenantadmin1"
    And I am on "Course11" course homepage with editing mode on
    When I backup "Course11" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course11 restored in a new course |
    Then I should see "Course11 restored in a new course"
    And I click on "Participants" "link"
    And I should see "User 11"
    And I navigate to "Enrolment methods" in current page administration
    And I should see "Enrolment methods"
    And I should not see "Program enrolment (Program1)"
    And I should see "1" in the "Manual enrolments" "table_row"
    And I log out