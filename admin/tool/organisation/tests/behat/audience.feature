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
    And the following "roles" exist:
      | shortname       | name                   | archetype |
      | reports_manager | Report builder manager |           |
    And the following "permission overrides" exist:
      | capability                   | permission | role            | contextlevel | reference |
      | moodle/reportbuilder:editall | Allow      | reports_manager | System       |           |
    And the following "role assigns" exist:
      | user    | role            | contextlevel | reference |
      | manager | reports_manager | System       |           |
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    And the following positions exist in organisation structure:
      | tenant  | name      | parent    | globalmanager | departmentmanager |
      | Tenant1 | Framework |           | 0             | 0                 |
      | Tenant1 | Manager   | Framework | 1             | 0                 |
      | Tenant1 | Assistant | Manager   | 0             | 0                 |
      | Tenant1 | Minion    | Assistant | 0             | 0                 |
    # Login as manager, edit the report and add the audience condition.
    And I log in as "manager"
    And I navigate to "Reports > Report builder > Custom reports" in site administration
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "User Report"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Show/hide 'Conditions'" "button"
    And I set the field "Select a condition" to "Relation to the report viewer"

  Scenario: Configure audience condition to show all users
    When I set the field "Select audience" to "All users"
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
      | User 3     | user3@example.com   |

  Scenario: Configure audience conditions to show only the current user
    When I set the field "Select audience" to "Themselves"
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
      | User 3     | user3@example.com   |

  Scenario: Configure custom audience conditions without selecting anything
    When I set the field "Select audience" to "Customise..."
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then I should see "Nothing to display"

  Scenario: Configure custom audience conditions to show users who report to me
    Given the following job assignments exist in organisation structure:
      | user    | department  | position  |
      | manager | Department1 | Manager   |
      | user1   | Department1 | Assistant |
      | user2   | Department1 | Minion    |
    When I set the following fields to these values:
      | Select audience              | Customise... |
      | Reports to the report viewer | 1            |
      | Direct reports only          | 0            |
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 3     | user3@example.com   |

  Scenario: Configure custom audience conditions to show users who report directly to me
    Given the following job assignments exist in organisation structure:
      | user    | department  | position  |
      | manager | Department1 | Manager   |
      | user1   | Department1 | Assistant |
      | user2   | Department1 | Minion    |
    When I set the following fields to these values:
      | Select audience              | Customise... |
      | Reports to the report viewer | 1            |
      | Direct reports only          | 1            |
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | User 1     | user1@example.com   |
    And the following should not exist in the "reportbuilder-table" table:
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
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 1     | user1@example.com   |
    And the following should not exist in the "reportbuilder-table" table:
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
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    And I click on "Switch to preview mode" "button"
    Then the following should exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | Mr Manager | manager@example.com |
      | User 1     | user1@example.com   |
      | User 2     | user2@example.com   |
    And the following should not exist in the "reportbuilder-table" table:
      | Full name  | Email address       |
      | User 3     | user3@example.com   |
