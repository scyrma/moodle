@block @block_myteams @moodleworkplace @javascript
Feature: Shared space in block_myteams
  As a global admin
  I can use My teams block with shared positions and departments

  Background:
    Given "2" tenants exist with "5" users and "0" courses in each
    And shared space is enabled
    Given the following departments exist in organisation structure:
      | tenant     | name             | parent           |
      | -          | Shared Framework |                  |
      | -          | DepartmentA      | Shared Framework |
      | -          | DepartmentAA     | DepartmentA      |
      | Tenant1    | Framework        |                  |
      | Tenant1    | DepartmentB      | Framework        |
    And the following positions exist in organisation structure:
      | tenant   | name              | parent           | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | -        | Shared Framework  |                  | 0             | 0                 | 0                 | 0                     |
      | -        | PositionA         | Shared Framework | 1             | 1                 | 1                 | 0                     |
      | -        | PositionAA        | PositionA        | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | Framework         |                  | 0             | 0                 | 0                 | 0                     |
      | Tenant1  | PositionB         | Framework        | 1             | 1                 | 1                 | 0                     |
      | Tenant1  | PositionBB        | PositionB        | 0             | 0                 | 0                 | 0                     |
    And the following job assignments exist in organisation structure:
      | user          | department   | position   |
      | tenantadmin1  | DepartmentA  | PositionA  |
      | tenantadmin1  | DepartmentB  | PositionB  |
      | user11        | DepartmentA  | PositionA  |
      | user12        | DepartmentA  | PositionAA |
      | user13        | DepartmentB  | PositionBB |
      | user14        | DepartmentAA | PositionA  |
      | user21        | DepartmentA  | PositionAA |

  Scenario: Shared departments and shared positions show up on My Teams block
    When I log in as "tenantadmin1"
    And I select "My teams" from primary navigation
    # Check users in shared Department/Position are displayed.
    Then I should see "User 11" in the "reportbuilder-table" "table"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 14" in the "reportbuilder-table" "table"
    # Check user13 in not shared Department/Position is displayed.
    And I should see "User 13" in the "reportbuilder-table" "table"
    # Check user in a different tenant is not displayed.
    And I should not see "User 21" in the "reportbuilder-table" "table"
