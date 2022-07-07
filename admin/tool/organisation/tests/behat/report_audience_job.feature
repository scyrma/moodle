@tool @tool_organisation @moodleworkplace @javascript
Feature: Configure report access with the job audience type
  As a tenant admin
  I want to restrict which users can access reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email               | tenantadmin |
      | Tenant1 | tadmin1  | Tenant    | Admin    | tadmin1@example.com | 1           |
      | Tenant1 | user1    | User      | 1        | user1@example.com   | 0           |
      | Tenant1 | user2    | User      | 2        | user2@example.com   | 0           |
      | Tenant1 | user3    | User      | 3        | user3@example.com   | 0           |
      | Tenant1 | user4    | User      | 4        | user4@example.com   | 0           |
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    And the following positions exist in organisation structure:
      | tenant  | name      | parent    |
      | Tenant1 | Framework |           |
      | Tenant1 | Position1 | Framework |
      | Tenant1 | Position2 | Position1 |
    And the following job assignments exist in organisation structure:
      | user  | department  | position  |
      | user1 | Department1 | Position1 |
      | user2 | Department1 | Position2 |
      | user3 | Department2 | Position1 |
      | user4 | Department2 | Position2 |
    And the following "tool_reportbuilder > reports" exist:
      | tenant  | name        | source                                                              |
      | Tenant1 | Mock Report | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |

  Scenario Outline: Configure report audience for a department
    When I log in as "tadmin1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Audience" in current page administration
    And I click on "Job assignments" "link"
    And I set the following fields to these values:
      | Department             | <department>     |
      | Include subdepartments | <subdepartments> |
    And I press "Save changes"
    And I navigate to "Access" in current page administration
    Then I <user1shouldsee> "User 1" in the "report-table" "table"
    And I <user2shouldsee> "User 2" in the "report-table" "table"
    And I <user3shouldsee> "User 3" in the "report-table" "table"
    And I <user4shouldsee> "User 4" in the "report-table" "table"
    Examples:
      | department | subdepartments | user1shouldsee | user2shouldsee | user3shouldsee | user4shouldsee |
      | Department1| 0              | should see     | should see     | should not see | should not see |
      | Department1| 1              | should see     | should see     | should see     | should see     |

  Scenario Outline: Configure report audience for a position
    When I log in as "tadmin1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Audience" in current page administration
    And I click on "Job assignments" "link"
    And I set the following fields to these values:
      | Position             | <position>     |
      | Include subpositions | <subpositions> |
    And I press "Save changes"
    And I navigate to "Access" in current page administration
    Then I <user1shouldsee> "User 1" in the "report-table" "table"
    And I <user2shouldsee> "User 2" in the "report-table" "table"
    And I <user3shouldsee> "User 3" in the "report-table" "table"
    And I <user4shouldsee> "User 4" in the "report-table" "table"
    Examples:
      | position  | subpositions | user1shouldsee | user2shouldsee | user3shouldsee | user4shouldsee |
      | Position1 | 0            | should see     | should not see | should see     | should not see |
      | Position1 | 1            | should see     | should see     | should see     | should see     |

  Scenario: Configure report audience for a department and position
    When I log in as "tadmin1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Audience" in current page administration
    And I click on "Job assignments" "link"
    And I set the following fields to these values:
      | Department | Department1 |
      | Position   | Position1   |
    And I press "Save changes"
    And I navigate to "Access" in current page administration
    Then I should see "User 1" in the "report-table" "table"
    And I should not see "User 2" in the "report-table" "table"
    And I should not see "User 3" in the "report-table" "table"
    And I should not see "User 4" in the "report-table" "table"
