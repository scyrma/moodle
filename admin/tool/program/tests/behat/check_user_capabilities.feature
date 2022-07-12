@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Check different user capabilities
  In order to check different capabilities
  As a manager
  I need have data to perform checks

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | a        | user1@example.com    |
      | user2    | User      | b        | user2@example.com    |
      | user3    | User      | c        | user3@example.com    |
      | user4    | User      | d        | user4@example.com    |
      | user5    | User      | e        | user5@example.com    |
      | user6    | User      | f        | user6@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | manager3 | Manager   | 3        | manager3@example.com |
      | manager4 | Manager   | 4        | manager4@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
      | user3    | Tenant1 |
      | user4    | Tenant1 |
      | user5    | Tenant2 |
      | user6    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
      | manager3 | Tenant1 |
      | manager4 | Tenant2 |
    And the following departments exist in organisation structure:
      | tenant  | name            | parent       |
      | Tenant1 | Framework_t1    |              |
      | Tenant1 | Deparment_t1_d1 | Framework_t1 |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant  | name           | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1 | Framework_t1   |                | 0             | 0                 | 0                 | 0                     |
      | Tenant1 | Position_t1_f1 | Framework_t1   | 0             | 0                 | 1                 | 1                     |
      | Tenant1 | Position_t1_f2 | Position_t1_f1 | 0             | 0                 | 0                 | 0                     |
    And the following job assignments exist in organisation structure:
      | user     | department      | position       |
      | manager1 | Deparment_t1_d1 | Position_t1_f1 |
      | manager2 | Deparment_t1_d1 | Position_t1_f1 |
      | manager3 | Deparment_t1_d1 | Position_t1_f2 |
      | user1    | Deparment_t1_d1 | Position_t1_f2 |
      | user2    | Deparment_t1_d1 | Position_t1_f2 |
      | user3    | Deparment_t1_d1 | Position_t1_f2 |
      | user6    | Deparment_t1_d1 | Position_t1_f1 |
    And the following "roles" exist:
      | shortname          | name                 | archetype |
      | programusermanager | program user manager |           |
      | programeditmanager | program edit manager |           |
    And the following "role assigns" exist:
      | user     | role               | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
      | manager4 | tool_program_manager | System       |           |
      | manager2 | programusermanager | System       |           |
      | manager3 | programeditmanager | System       |           |
      | user1    | user               | System       |           |
      | user2    | user               | System       |           |
      | user3    | user               | System       |           |
      | user4    | user               | System       |           |
      | user5    | user               | System       |           |
      | user6    | user               | System       |           |
    And the following "permission overrides" exist:
      | capability                | permission | role               | contextlevel | reference |
      | tool/program:allocateuser | Allow      | programusermanager | System       |           |
      | moodle/site:configview    | Allow      | programusermanager | System       |           |
      | tool/program:edit         | Allow      | programeditmanager | System       |           |
      | moodle/site:configview    | Allow      | programeditmanager | System       |           |

  Scenario: Manager has both capabilities and can edit and allocate users
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I should not see "Program2"
    And "Users" "link" should exist in the "Program1" "table_row"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And the "class" attribute of "Schedule" "link" should not contain "disabled"
    And the "class" attribute of "Users" "link" should not contain "disabled"
    And I navigate to "Details" in current page administration
    And I should see "Program name"
    Then I press "Save changes"
    And I navigate to "Schedule" in current page administration
    And I should see "Start date"
    And I should see "End date"
    And I navigate to "Users" in current page administration
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User c" "autocomplete_suggestions" should exist
    And "User d" "autocomplete_suggestions" should exist
    And I click on "User a" item in the autocomplete list
    And I click on "User b" item in the autocomplete list
    Then I press "Save changes"
    Then I should see "Users"
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should not see "User d"
    Then I should not see "Suspended"
    Then I press "Edit" action in the "User a" report row
    Then I should see "Allocation for 'User a'"
    And I should see "Status"
    And I should see "Start date"
    And I should see "Due date"
    And I select "Suspended" from the "Status" singleselect
    Then I press "Save changes"
    Then I should see "Suspended"
    And I log out
    Then I log in as "manager4"
    Then I navigate to "Programs" in site administration
    And I should see "Programs"
    And I should not see "Program1"
    And I should see "Program2"
    And I log out

  Scenario: Manager has allocate user capabilities but can not edit program
    When I log in as "manager2"
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should not see "Archived"
    And I should see "Active programs"
    And I should not see "Program2"
    Then I press "Users" action in the "Program1" report row
    And the "class" attribute of "Schedule" "link" should not contain "disabled"
    And the "class" attribute of "Users" "link" should not contain "disabled"
    And I should see "Users"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User c" "autocomplete_suggestions" should exist
    And "User d" "autocomplete_suggestions" should exist
    And I click on "User a" item in the autocomplete list
    And I click on "User b" item in the autocomplete list
    Then I press "Save changes"
    Then I should see "Users"
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should not see "User d"
    Then I should not see "Suspended"
    Then I press "Delete" action in the "User a" report row
    And I click on "Yes" "button" in the "Delete user allocation" "dialogue"
    And I should not see "User a"
    And I should see "User b"
    And I log out

  Scenario: Manager has edit capabilities and can not allocate users
    When I log in as "manager3"
    And I navigate to "Programs" in workplace launcher
    And I should not see "Program2"
    And "Users" "link" should exist in the "Program1" "table_row"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And the "class" attribute of "Schedule" "link" should not contain "disabled"
    And the "class" attribute of "Users" "link" should not contain "disabled"
    Then I navigate to "Details" in current page administration
    And I should see "Program name"
    Then I press "Save changes"
    And I navigate to "Schedule" in current page administration
    And I should see "Start date"
    And I should see "End date"
    And I navigate to "Users" in current page administration
    Then I should not see "Allocate users"
    And "Allocate users" "link" should not exist in the "#program_users_tab" "css_element"
    And I log out

  Scenario: Organisation user has allocate user capabilities but can not edit program and has no manager capabilities
    When I log in as "user6"
    And I navigate to "Programs" in workplace launcher
    And I should see "Active programs"
    And I should not see "Program2"
    Then I press "Users" action in the "Program1" report row
    And the "class" attribute of "Schedule" "link" should not contain "disabled"
    And the "class" attribute of "Users" "link" should not contain "disabled"
    And I should see "Users"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User c" "autocomplete_suggestions" should exist
    And "User d" "autocomplete_suggestions" should not exist
    And "User e" "autocomplete_suggestions" should not exist
    And I click on "User a" item in the autocomplete list
    And I click on "User b" item in the autocomplete list
    Then I press "Save"
    Then I should see "Users"
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should not see "User d"
    Then I should not see "Suspended"
    Then I press "Delete" action in the "User a" report row
    And I click on "Yes" "button" in the "Delete user allocation" "dialogue"
    And I should not see "User a"
    And I should see "User b"
    And I log out
    Then I am on the "My courses" page logged in as "user6"
    And I should not see "Program1"
    And I log out
    Then I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    Then I press "Users" action in the "Program1" report row
    Then I click on "Allocate users" "link"
    And I set the field "Select users" to "User f"
    Then I press "Save changes"
    Then I should see "Users"
    And I should see "User f"
    And I log out
    Then I am on the "My courses" page logged in as "user6"
    And I should see "Program1"
    And I log out
