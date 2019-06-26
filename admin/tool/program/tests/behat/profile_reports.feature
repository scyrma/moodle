@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Check user report capabilities on profile
  In order to check reports on profile
  As a manager
  I need have permission to see the user reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | a        | user1@example.com    |
      | user2    | User      | b        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
    And the following users allocations to programs exist:
      | user     | program  |
      | user1    | Program1 |
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

  Scenario: In programs manager has permission only over its users
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I should not see "User b"
    And I click on "//button[contains(.,'User a')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Details"
    And I should see "Job assignments"
    And I should see "Active programs: 1"
    Then I click on "Active programs: 1" "link"
    And I should see "Program name"
    And I should see "Associated certifications"
    And I should see "Expiry date"
    And I should see "Due date"
    And I should see "Program status"
    And I should see "Program1"
    And I should see "Open"
    And I log out
    When I log in as "manager2"
    Then I follow "Dashboard"
    And I should not see "Teams"
    And I should not see "User a"
    And I should not see "User b"
    And I should not see "Active programs: 1"
    And I log out
