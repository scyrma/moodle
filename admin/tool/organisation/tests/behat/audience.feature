@tool @tool_organisation @moodleworkplace @javascript
Feature: Configure reports with the audience condition
  As a manager
  I want to restrict which users are listed in reports

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | manager  | Mr        | Manager  | manager@example.com |
      | user1    | User      | 1        | user1@example.com   |
      | user2    | User      | 2        | user2@example.com   |
      | user3    | User      | 3        | user3@example.com   |
    And the following tenants exist:
      | name    |
      | Tenant1 |
    And the following users allocations to tenants exist:
      | user    | tenant  |
      | manager | Tenant1 |
      | user1   | Tenant1 |
      | user2   | Tenant1 |
      | user3   | Tenant1 |
    And the following "role assigns" exist:
      | user    | role                       | contextlevel | reference |
      | manager | tool_reportbuilder_manager | System       |           |
    And the following "tool_reportbuilder > reports" exist:
      | name        | tenant  | source                                                              |
      | User Report | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    And the following positions exist in organisation structure:
      | tenant  | name      | parent    | globalmanager | departmentmanager |
      | Tenant1 | Framework |           | 0             | 0                 |
      | Tenant1 | Manager   | Framework | 1             | 1                 |
      | Tenant1 | Minion    | Framework | 0             | 0                 |
    # Login as manager, edit the report and add the audience condition.
    And I log in as "manager"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "User Report" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Relation to the report viewer"

  Scenario: Configure audience condition to show all users
    When I set the field "Select audience" to "All users"
    And I click on "Switch to preview view" "button"
    Then the following should exist in the "report-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
      | User 3     | user3@example.com   |

  Scenario: Configure audience conditions to show only the current user
    When I set the field "Select audience" to "Themselves"
    And I click on "Switch to preview view" "button"
    Then the following should exist in the "report-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
    And the following should not exist in the "report-table" table:
      | Full name  | Email address       |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
      | User 3     | user3@example.com   |

  Scenario: Configure custom audience conditions without selecting anything
    When I set the field "Select audience" to "Customise..."
    And I click on "Switch to preview view" "button"
    Then I should see "Nothing to display"

  Scenario: Configure custom audience conditions to show users who report to me
    Given the following job assignments exist in organisation structure:
      | user    | department  | position |
      | manager | Department1 | Manager  |
      | user1   | Department1 | Minion   |
    When I set the following fields to these values:
      | Select audience              | Customise... |
      | Reports to the report viewer | 1            |
    And I click on "Switch to preview view" "button"
    Then the following should exist in the "report-table" table:
      | Full name  | Email address       |
      | User 1     | user1@example.com   |
    And the following should not exist in the "report-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 2     | user2@example.com   |
      | User 3     | user3@example.com   |

  Scenario: Configure custom audience conditions to show users in my own department
    Given the following job assignments exist in organisation structure:
      | user    | department  | position |
      | manager | Department1 | Manager  |
      | user1   | Department1 | Minion   |
      | user2   | Department2 | Minion   |
    When I set the following fields to these values:
      | Select audience                             | Customise... |
      | In the same department as the report viewer | 1            |
    And I click on "Switch to preview view" "button"
    Then the following should exist in the "report-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 1     | user1@example.com   |
    And the following should not exist in the "report-table" table:
      | Full name  | Email address       |
      | User 2     | user2@example.com   |
      | User 3     | user3@example.com   |

  Scenario: Configure custom audience conditions to show users in my own department or sub-departments
    Given the following job assignments exist in organisation structure:
      | user    | department  | position |
      | manager | Department1 | Manager  |
      | user1   | Department1 | Minion   |
      | user2   | Department2 | Minion   |
    When I set the following fields to these values:
      | Select audience                             | Customise... |
      | In the same department as the report viewer | 1            |
      | Include subdepartments                      | 1            |
    And I click on "Switch to preview view" "button"
    Then the following should exist in the "report-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
    And the following should not exist in the "report-table" table:
      | Full name  | Email address       |
      | User 3     | user3@example.com   |
