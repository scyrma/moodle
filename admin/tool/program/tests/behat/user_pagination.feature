@tool @tool_program @moodleworkplace @javascript
Feature: Manage programs users list pagination
  In order to see the users pagination list
  As a manager
  I need to see all existing allocations

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
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
      | user7    | User      | g        | user7@example.com    |
      | user8    | User      | h        | user8@example.com    |
      | user9    | User      | i        | user9@example.com    |
      | user10   | User      | j       | user10@example.com    |
      | user11   | User      | k       | user11@example.com    |
      | user12   | User      | l       | user12@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
      | user3    | Tenant1 |
      | user4    | Tenant1 |
      | user5    | Tenant1 |
      | user6    | Tenant1 |
      | user7    | Tenant1 |
      | user8    | Tenant1 |
      | user9    | Tenant1 |
      | user10   | Tenant1 |
      | user11   | Tenant1 |
      | user12   | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1  | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0          |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2    | Position_t1_f1   |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | manager1 | Deparment_t1_d1 | Position_t1_f1 |
      | user1 | Deparment_t1_d1 | Position_t1_f2 |
      | user2 | Deparment_t1_d1 | Position_t1_f2 |
      | user3 | Deparment_t1_d1 | Position_t1_f2 |
      | user4 | Deparment_t1_d1 | Position_t1_f2 |
      | user5 | Deparment_t1_d1 | Position_t1_f2 |
      | user6 | Deparment_t1_d1 | Position_t1_f2 |
      | user7 | Deparment_t1_d1 | Position_t1_f2 |
      | user8 | Deparment_t1_d1 | Position_t1_f2 |
      | user9 | Deparment_t1_d1 | Position_t1_f2 |
      | user10 | Deparment_t1_d1 | Position_t1_f2 |
      | user11 | Deparment_t1_d1 | Position_t1_f2 |
      | user12 | Deparment_t1_d1 | Position_t1_f2 |
    And the following "role assigns" exist:
      | user     | role                  | contextlevel | reference |
      | manager1 | tool_program_manager  | System       |           |
      | manager2 | tool_program_manager  | System       |           |

  Scenario: Pagination works correctly for users allocated to programs
    When I log in as "manager1"
    Then I navigate to "Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on ".edit_program" "css_element" in the "Program1" "table_row"
    Then I click on "Users" "link"
    And I should see "Users"
    And I should see "Nothing to display"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I should see "User a" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User b" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User c" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User d" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User e" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User f" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User g" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User h" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User i" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User j" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User k" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User l" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User a" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User b" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User c" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User d" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User e" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User f" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User g" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User h" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User i" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User j" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User k" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User l" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I should see "Full name" in the "table.report-table thead" "css_element"
    And I should see "Due date" in the "table.report-table thead" "css_element"
    And I should see "Allocation source" in the "table.report-table thead" "css_element"
    And I should see "Certification status" in the "table.report-table thead" "css_element"
    And I should see "Program status" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And I should see "User a" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User b" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User c" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User d" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User e" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User f" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User g" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User h" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User i" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User j" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User k" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User l" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And ".tool_reportbuilder_report .pagination" "css_element" should exist
    And I should see "Next" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "1" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "User a" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User b" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User c" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User d" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User e" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User f" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User g" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User h" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User i" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should not see "User j" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User k" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    And I should see "User l" in the "[data-region='edit-program-user-list'] table.report-table" "css_element"
    Then I log out
