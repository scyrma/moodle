@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Check capabilities
  In order to check capabilities
  As a manager
  I need have data to perform checks

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
      | Certification2 | 0        | Tenant2 | Program2  |
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
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user4  |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1 | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1  | Framework_t1   |      0        |       0           |       1           |        1              |
      | Tenant1    | Position_t1_f2  | Position_t1_f1 |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | manager1 | Deparment_t1_d1 | Position_t1_f1 |
      | manager2 | Deparment_t1_d1 | Position_t1_f1 |
      | manager3 | Deparment_t1_d1 | Position_t1_f2 |
      | user1 | Deparment_t1_d1 | Position_t1_f2 |
      | user2 | Deparment_t1_d1 | Position_t1_f2 |
      | user3 | Deparment_t1_d1 | Position_t1_f2 |
      | user6 | Deparment_t1_d1 | Position_t1_f1 |
    And the following "roles" exist:
      | shortname                | name                       | archetype |
      | certificationusermanager | certification user manager |           |
      | certificationeditmanager | certification edit manager |           |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager4 | tool_certification_manager | System       |           |
      | manager2 | certificationusermanager   | System       |           |
      | manager3 | certificationeditmanager   | System       |           |
    And the following "permission overrides" exist:
      | capability                      | permission | role                     | contextlevel | reference |
      | tool/certification:allocateuser | Allow      | certificationusermanager | System       |           |
      | moodle/site:configview          | Allow      | certificationusermanager | System       |           |
      | tool/certification:edit         | Allow      | certificationeditmanager | System       |           |
      | moodle/site:configview          | Allow      | certificationeditmanager | System       |           |

  Scenario: Manager has both capabilities and can edit and allocate users
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    And "Edit details" "link" should exist in the "Certification1" "table_row"
    And "Allocate users" "link" should exist in the "Certification1" "table_row"
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    And the "class" attribute of "a#certification_calendar_tab-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#certification_users_tab-tab" "css_element" should not contain "disabled"
    Then I click on "Edit details" "button"
    And I should see "Certification full name"
    And I should see "Program1"
    Then I press "Save" in the modal form dialogue
    Then I click on "Certification" "link" in the ".wptabs" "css_element"
    And I should see "Start date"
    And I should see "End date"
    Then I click on "Users" "link"
    And I should see "Users"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User a" item in the autocomplete list
    And I click on "User b" item in the autocomplete list
    Then I press "Save" in the modal form dialogue
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should see "User d"
    And I should not see "User e"
    Then I should not see "Suspended"
    Then I click on "Edit" "link" in the "User a" "table_row"
    Then I should see "Allocation for 'User a'"
    And I should see "Status"
    And I should see "Start date"
    And I should see "Due date"
    And I select "Suspended" from the "status" singleselect
    Then I press "Save" in the modal form dialogue
    Then I should see "Suspended"
    And I log out
    Then I log in as "manager4"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should not see "Certification1"
    And I should see "Certification2"
    And I log out

  Scenario: Manager has allocate user capabilities but can not edit certification
    When I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I should see "Active certifications"
    And I should see "Certification1"
    And I should not see "Certification2"
    And "Edit content" "link" should not exist in the "Certification1" "table_row"
    And "Edit details" "link" should not exist in the "Certification1" "table_row"
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And I should see "Users"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User a" item in the autocomplete list
    And I click on "User b" item in the autocomplete list
    Then I press "Save" in the modal form dialogue
    Then I should see "Users"
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should see "User d"
    Then I should not see "Suspended"
    Then I click on "Delete" "link" in the "User a" "table_row"
    And I click on "Yes" "button" in the "Delete user allocation" "dialogue"
    And I should not see "User a"
    And I should see "User b"
    And I log out

  Scenario: Manager has edit capabilities and can not allocate users
    When I log in as "manager3"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    And "Edit details" "link" should exist in the "Certification1" "table_row"
    And "Allocate users" "link" should not exist in the "Certification1" "table_row"
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    And the "class" attribute of "a#certification_calendar_tab-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#certification_users_tab-tab" "css_element" should not contain "disabled"
    Then I click on "Edit details" "button"
    And I should see "Certification full name"
    And I should see "Program1"
    Then I press "Save" in the modal form dialogue
    Then I click on "Certification" "link" in the ".wptabs" "css_element"
    And I should see "Start date"
    And I should see "End date"
    Then I click on "Users" "link"
    And I should see "Users"
    And "Allocate users" "link" should not exist in the "#certification_users_tab" "css_element"
    And I log out

  Scenario: Organisation user has allocate user capabilities but can not edit certification and has no manager capabilities
    When I log in as "user6"
    And I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I should see "Active certifications"
    And I should see "Certification1"
    And I should not see "Certification2"
    And "Edit content" "link" should not exist in the "Certification1" "table_row"
    And "Edit details" "link" should not exist in the "Certification1" "table_row"
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And I should see "Users"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User a" item in the autocomplete list
    And I click on "User b" item in the autocomplete list
    Then I press "Save" in the modal form dialogue
    Then I should see "Users"
    And I should see "User a"
    And I should see "User b"
    And I should not see "User c"
    And I should not see "User d"
    Then I should not see "Suspended"
    Then I click on "Delete" "link" in the "User a" "table_row"
    And I click on "Yes" "button" in the "Delete user allocation" "dialogue"
    And I should not see "User a"
    And I should see "User b"
    And I log out
    Then I log in as "user6"
    And I should not see "Program1"
    And I log out
    Then I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User f" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    Then I should see "Users"
    And I should see "User f"
    And I log out
    Then I log in as "user6"
    And I should see "Programs"
    And I click on "Programs" "link" in the "#region-main" "css_element"
    And I should see "Program1"
    And I log out