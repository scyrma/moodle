@tool @tool_program @moodleworkplace @javascript
Feature: Manage users allocations in programs
  In order to see the users allocation in programs
  As a manager
  I need to see all existing allocations

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
      | Program3 | 0        | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | user3    | User      | 3        | user3@example.com    |
      | user4    | User      | 4        | user4@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | user3    | Tenant1 |
      | user4    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    Given user "manager1" has a department lead position over users "user1,user3" with permissions "3"

  Scenario: There are no existing allocations
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I press "Users" action in the "Program1" report row
    Then I should see "Users"
    Then I should see "Nothing to display"
    Then I log out

  Scenario: We allocate two users and delete one allocation
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    And I should not see "Program2"
    Then I press "Users" action in the "Program1" report row
    Then I click on "Allocate users" "link"
    And I set the field "Select users" to "User 1,User 3"
    Then I press "Save changes"
    And I should see "User 1"
    And I should see "User 3"
    Then I press "Delete" action in the "User 1" report row
    And I click on "Yes" "button" in the "Delete user allocation" "dialogue"
    Then I should see "Users"
    And I should not see "User 1"
    And I should see "User 3"
    Then I log out
    # Check notifications are triggered.
    Then I log in as "user3"
    And I am on site homepage
    When I click on ".popover-region-notifications" "css_element"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    Then I should see "Welcome to 'Program1'"
    And I should see "Welcome to the program 'Program1', which you can find on your 'My courses' page."
    And I log out
    Then I log in as "user1"
    And I am on site homepage
    When I click on ".popover-region-notifications" "css_element"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    Then I should see "'Program1' closed"
    And I should see "The program 'Program1' has now closed and can no longer be accessed."
    And I log out

  Scenario: Cannot delete allocation if comes from a dynamic rule
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | manager1 | tool_program_manager       | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Program not completed
    And I follow "Program not completed"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    And I navigate to "Actions" in current page administration
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program3"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Go back to programs
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    # Allocate user
    Then I click on "Allocate users" "link"
    And I set the field "Select users" to "User 1,User 3"
    Then I press "Save changes"
    And I log out
    And I trigger cron
    Then I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program3" report row
    And I should see "User 1"
    And "Edit" "link" should exist in the "User 1" "table_row"
    And "Delete" "link" should not exist in the "User 1" "table_row"
    And I log out

  Scenario: Cannot delete allocation if allocation window is closed
    Given the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And "Edit" "link" should exist in the "User 1" "table_row"
    And "Delete" "link" should exist in the "User 1" "table_row"
    And "Edit" "link" should exist in the "User 3" "table_row"
    And "Delete" "link" should exist in the "User 3" "table_row"
    Then I click on "Schedule" "link"
    And I set the following fields to these values:
      | allocationenddatetype            | Select date  |
      | allocationenddateabsolute[day]   | 3            |
      | allocationenddateabsolute[month] | March        |
      | allocationenddateabsolute[year]  | 2019         |
    Then I press "Save changes"
    And I navigate to "Users" in current page administration
    And "Edit" "link" should exist in the "User 1" "table_row"
    And "Delete" "link" should not exist in the "User 1" "table_row"
    And "Edit" "link" should exist in the "User 3" "table_row"
    And "Delete" "link" should not exist in the "User 3" "table_row"
    And I log out

  Scenario: A modal should appear when trying to allocate a user and allocation window is closed or allow direct allocation is disabled
    Given the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    Then I click on "Allocate users" "link"
    Then I click on "Cancel" "button" in the "Allocate users" "dialogue"
    Then I click on "Schedule" "link"
    And I set the following fields to these values:
      | allocationstartdatetype                 | Select date |
      | allocationstartdateabsolute[day]        | 3           |
      | allocationstartdateabsolute[month]      | March       |
      | allocationstartdateabsolute[year]       | 2050        |
    Then I press "Save changes"
    And I click on "Users" "link"
    And I click on "Allocate users" "link"
    Then I should see "The allocation window for this program starts on"
    And I click on "OK" "button" in the "Users allocation is not available" "dialogue"
    And I navigate to "Details" in current page administration
    And I set the field "Allow direct allocation" to "No"
    Then I press "Save changes"
    And I navigate to "Users" in current page administration
    And I click on "Allocate users" "link"
    Then I should see "Direct allocation to this program is disabled"
    And I click on "OK" "button" in the "Users allocation is not available" "dialogue"
    And I log out

  Scenario: De-allocate users in bulk
    Given the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
      | Program1 | user4  |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I set the field "User 1" to "1"
    And I set the field "User 3" to "1"
    And I set the field "With selected users..." to "De-allocate users"
    And I should see "This action will completely delete allocation and associated data"
    And I press "De-allocate users"
    Then I should see "User 4"
    And I should not see "User 1"
    And I should not see "User 3"
    # Test Cancel button works.
    Then I set the field "User 4" to "1"
    And I set the field "With selected users..." to "De-allocate users"
    And I should see "This action will completely delete allocation and associated data"
    And I click on "Cancel" "button" in the "De-allocate users" "dialogue"
    Then I should see "User 4"

  Scenario: Update status and dates for users in bulk
    Given the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
      | Program1 | user4  |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I set the field "User 1" to "1"
    And I set the field "User 3" to "1"
    And I set the field "With selected users..." to "Edit status and dates"
    And I should see "Edit status and dates for multiple users"
    And I select "Suspended" from the "Status" singleselect
    Then I press "Save changes"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Surname | Program status  |
      | User 1    | Suspended       |
      | User 3    | Suspended       |
      | User 4    | Open            |

  Scenario: Reset users program in bulk
    Given the following users allocations to tenants exist:
      | user  | tenant  |
      | admin | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
      | Program1 | user4  |
    When I log in as "admin"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I set the field "User 1" to "1"
    And I set the field "User 3" to "1"
    And I set the field "With selected users..." to "Reset users program"
    And I should see "For all the selected users, this action will:" in the "Reset program" "dialogue"
    And I click on "Reset" "button" in the "Reset program" "dialogue"
    Then I should see "The program was reset for 2 user(s)"
    And I should see "User 4"
    And I should see "User 1"
    And I should see "User 3"

  Scenario: Allocate users to programs using bulk actions from tenant users list
    And the following "tool_program > program_users" exist:
      | program  | user     |
      | Program1 | manager1 |
    When I log in as "admin"
    Then I switch to tenant "Tenant2"
    And I navigate to "All tenants" in workplace launcher
    # Allocate tenant2 users into a Tenant2 program from within same tenant.
    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
    And I set the field "Select user 'User 2'" to "1"
    And I set the field "Select user 'Manager 2'" to "1"
    And I set the field "With selected users..." to "Allocate to program"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program2" report row
    Then I should see "User 2"
    And I should see "Manager 2"
    Then I navigate to "All tenants" in workplace launcher
    # Allocate tenant1 users into a Tenant1 program from a different tenant.
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I set the field "Select user 'User 1'" to "1"
    And I set the field "Select user 'User 3'" to "1"
    And I set the field "Select user 'Manager 1'" to "1"
    And I set the field "With selected users..." to "Allocate to program"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    # Check WP Notifications.
    Then I should see "2 user allocations were succesfully created"
    # Manager 1 is already alocated to the program.
    And I should see "1 user(s) skipped because this option is not available to them"
    Then I switch to tenant "Tenant1"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program1" report row
    Then I should see "User 1"
    And I should see "User 3"
    And I log out

  Scenario: Allocate users to programs using bulk actions from All users list
    Given shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname      | idnumber | archived | tenant  | generatecourses |
      | ProgramShared | prog0    | 0        | -       | 0               |
    When I log in as "admin"
    Then I switch to tenant "Tenant2"
    And I navigate to "All users" in workplace launcher
    # Allocate tenant2 users into a Tenant2 program from within same tenant.
    And I set the field "Select user 'User 2'" to "1"
    And I set the field "Select user 'Manager 2'" to "1"
    And I set the field "With selected users..." to "Allocate to program"
    And I set the field "Program" to "Program2"
    And I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program2" report row
    Then I should see "User 2"
    And I should see "Manager 2"
    Then I navigate to "All users" in workplace launcher
    # Allocate tenant1 users into a Tenant1 program from a different tenant.
    And I set the field "Select user 'User 1'" to "1"
    And I set the field "Select user 'User 3'" to "1"
    And I set the field "With selected users..." to "Allocate to program"
    And I set the field "Program" to "Program1"
    And I press "Save changes"
    Then I switch to tenant "Tenant1"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program1" report row
    Then I should see "User 1"
    And I should see "User 3"
    # Allocate all users to a shared program.
    And I am on homepage
    Then I switch to tenant "Tenant2"
    Then I navigate to "All users" in workplace launcher
    And I click on "Select all" "checkbox"
    And I set the field "With selected users..." to "Allocate to program"
    And I set the field "Program" to "ProgramShared"
    And I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "ProgramShared" report row
    Then I should see "User 2"
    Then I should see "Manager 2"
    Then I should not see "User 1"
    Then I should not see "User 3"
    Then I switch to tenant "Tenant1"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "ProgramShared" report row
    Then I should not see "User 2"
    Then I should not see "Manager 2"
    Then I should see "User 1"
    Then I should see "User 3"
    And I log out

  Scenario: Allocate users to programs using bulk actions from All users list from within Shared space
    Given shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname      | idnumber | archived | tenant  | generatecourses |
      | ProgramShared | prog0    | 0        | -       | 0               |
    When I log in as "admin"
    Then I switch to tenant "Shared space"
    # Allocate all users to the shared program.
    Then I navigate to "All users" in workplace launcher
    And I click on "Select all" "checkbox"
    And I set the field "With selected users..." to "Allocate to program"
    And I set the field "Program" to "ProgramShared"
    And I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "ProgramShared" report row
    And the following should exist in the "reportbuilder-table" table:
      | First name / Surname  | Tenant name |
      | User 1                | Tenant1     |
      | User 2                | Tenant2     |
      | User 3                | Tenant1     |
      | Manager 2             | Tenant2     |
    And I log out

  Scenario: Recalculate program completion for a single user
    Given the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
      | Program1 | user4  |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    Then I press "Re-calculate" action in the "User 3" report row
    And I should see "Re-calculate program completion"
    And I should see "Normally if program settings are changed after some students have already completed the program"
    Then I click on "Re-calculate program completion" "button" in the "Re-calculate program completion" "dialogue"
    And I should see "The program completion was recalculated for 1 user(s)"

  Scenario: Recalculate program completion for users in bulk
    Given the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
      | Program1 | user4  |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    Then I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program1" report row
    And I set the field "User 1" to "1"
    And I set the field "User 3" to "1"
    And I set the field "With selected users..." to "Re-calculate program completion"
    And I should see "Re-calculate program completion"
    And I should see "Normally if program settings are changed after some students have already completed the program"
    Then I click on "Re-calculate program completion" "button" in the "Re-calculate program completion" "dialogue"
    And I should see "The program completion was recalculated for 2 user(s)"
