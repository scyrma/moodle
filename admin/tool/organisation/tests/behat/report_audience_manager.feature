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

  Scenario Outline: Configure report audience for managers
    When I log in as "admin"
    And I navigate to "Report builder" in workplace launcher
    And I switch to tenant "<tenant>"
    And I follow "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Report name              | <tenant>       |
      | Report source            | Users list     |
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Audience" "tool_wp > Tab"
    And I click on "Managers" "link"
    And I set the field "Permissions" to "<permission>"
    And I press "Save changes"
    And I click on "Access" "tool_wp > Tab"
    Then I <user1shouldsee> "User 1" in the "report-table" "table"
    And I <user2shouldsee> "User 2" in the "report-table" "table"
    And I <user3shouldsee> "User 3" in the "report-table" "table"
    And I <user4shouldsee> "User 4" in the "report-table" "table"
    And I <user5shouldsee> "User 5" in the "report-table" "table"
    Examples:
      | tenant        | permission                  | user1shouldsee | user2shouldsee | user3shouldsee | user4shouldsee | user5shouldsee |
      | Tenant1       | Manager                     | should see     | should see     | should not see | should not see | should not see |
      | Tenant2       | Department lead             | should not see | should not see | should see     | should see     | should see     |
      | Shared space  | Manager or department lead  | should see     | should see     | should see     | should see     | should see     |
