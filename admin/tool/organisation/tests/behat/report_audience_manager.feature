@tool @tool_organisation @moodleworkplace @javascript
Feature: Configure report access with the manager audience type
  As a tenant admin
  I want to restrict which users can access reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And shared space is enabled
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email               | tenantadmin |
      | Tenant1 | tadmin1  | Tenant    | Admin    | tadmin1@example.com | 1           |
      | Tenant1 | user1    | User      | 1        | user1@example.com   | 0           |
      | Tenant1 | user2    | User      | 2        | user2@example.com   | 0           |
      | Tenant2 | user3    | User      | 3        | user3@example.com   | 0           |
      | Tenant2 | user4    | User      | 4        | user4@example.com   | 0           |
      | Tenant2 | user5    | User      | 5        | user5@example.com   | 0           |
    And the following departments exist in organisation structure:
      | tenant          | name             | parent         |
      | Default tenant  | Frameworkadmin   |                |
      | Default tenant  | Departmentadmin  | Frameworkadmin |
      | Tenant1         | Framework        |                |
      | Tenant1         | Department1      | Framework      |
      | Tenant1         | Department2      | Department1    |
      | Tenant2         | Framework1       |                |
      | Tenant2         | Department3      | Framework1     |
      | Tenant2         | Department4      | Department3    |
      | -               | Frameworkshared  |                |
      | -               | Departmentshared | Framework1     |
    And the following positions exist in organisation structure:
      | tenant          | name            | parent            | globalmanager | departmentmanager |
      | Default tenant  | Frameworkadmin  |                   | 0             | 0                 |
      | Default tenant  | Positionadmin   | Frameworkadmin    | 1             | 1                 |
      | Tenant1         | Framework       |                   | 0             | 0                 |
      | Tenant1         | Position1       | Framework         | 1             | 0                 |
      | Tenant2         | Framework1      |                   | 0             | 0                 |
      | Tenant2         | Position2       | Framework1        | 0             | 1                 |
      | -               | Frameworkshared |                   | 0             | 0                 |
      | -               | Positionshared  | Frameworkshared   | 1             | 1                 |
    And the following job assignments exist in organisation structure:
      | user  | department        | position       |
      | admin | Departmentadmin   | Positionadmin  |
      | user1 | Department1       | Position1      |
      | user2 | Department2       | Position1      |
      | user3 | Department3       | Position2      |
      | user4 | Department4       | Position2      |
      | user5 | Departmentshared  | Positionshared |

  Scenario: Configure report audience for managers
    Given the following "tool_tenant > reports" exist:
      | tenant  | name        | source                                   |
      | Tenant1 | Mock Report | core_user\reportbuilder\datasource\users |
    When I log in as "tadmin1"
    And I navigate to "Reports > Report builder > Custom reports" in site administration
    And I follow "Mock Report"
    And I click on the "Audience" dynamic tab
    And I click on "Add audience 'Managers'" "link"
    And I set the field "Permissions" to "Manager"
    And I press "Save changes"
    And I click on the "Access" dynamic tab
    Then I should see "User 1" in the "reportbuilder-table" "table"
    And I should see "User 1" in the "reportbuilder-table" "table"
    And I should not see "User 3" in the "reportbuilder-table" "table"
    And I should not see "User 4" in the "reportbuilder-table" "table"
    And I should not see "User 5" in the "reportbuilder-table" "table"

  Scenario Outline: Configure report audience for managers from different tenants
    When I log in as "admin"
    And I switch to tenant "<tenant>"
    And I navigate to "Reports > Report builder > Custom reports" in site administration
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "<tenant>"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on the "Audience" dynamic tab
    And I click on "Add audience 'Managers'" "link"
    And I set the field "Permissions" to "<permission>"
    And I press "Save changes"
    And I click on the "Access" dynamic tab
    Then I <user1shouldsee> "User 1" in the "reportbuilder-table" "table"
    And I <user2shouldsee> "User 2" in the "reportbuilder-table" "table"
    And I <user3shouldsee> "User 3" in the "reportbuilder-table" "table"
    And I <user4shouldsee> "User 4" in the "reportbuilder-table" "table"
    And I <user5shouldsee> "User 5" in the "reportbuilder-table" "table"
    Examples:
      | tenant        | permission                  | user1shouldsee | user2shouldsee | user3shouldsee | user4shouldsee | user5shouldsee |
      | Tenant1       | Manager                     | should see     | should see     | should not see | should not see | should not see |
      | Tenant2       | Department lead             | should not see | should not see | should see     | should see     | should see     |
      | Shared space  | Manager or department lead  | should see     | should see     | should see     | should see     | should see     |
