@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Reportbuilder permissions check
  As a user
  I want to be able to access or not no reports

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | user1     | User      | 1         | user1@address.invalid |
      | user2     | User      | 1         | user1@address.invalid |

  Scenario: Try to access to reportbuilder without permissions
    When I log in as "user1"
    Then I should not see "Report builder" in the "nav.navbar" "css_element"
    And I log out

  Scenario: Try to access to reportbuilder with job with permissions
    When the following tenants exist:
      | name    |
      | Tenant1 |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Department_t1_d1  | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1   |      1        |       7           |       1           |        7              |
    And the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | user1 | Department_t1_d1 | Position_t1_f1 |
    And the following custom reports exist:
      | name    | source                              |
      | Report1 | tool_reportbuilder\test\mock_report |
    And the following custom report audiences exist:
      | report  | department       | position       |
      | Report1 | Department_t1_d1 | Position_t1_f1 |
    And I log in as "user1"
    And I click on "#workplace-menulink" "css_element"
    Then I should see "Custom reports" in the ".workplace-menu" "css_element"
    And I log out