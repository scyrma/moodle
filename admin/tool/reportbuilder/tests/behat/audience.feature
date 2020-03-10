@tool @tool_reportbuilder @moodleworkplace @javascript
Feature: Configure access to reports based on intended audience
  As a manager
  I want to restrict which users have access to a report

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname |
      | manager1  | Manager   | 1        |
      | manager2  | Manager   | 2        |
      | manager3  | Manager   | 3        |
      | user1     | User      | 1        |
      | user2     | User      | 2        |
    And the following tenants exist:
      | name    |
      | Tenant1 |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
      | manager3 | Tenant1 |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following custom reports exist:
      | name        | tenant  | source                              |
      | Mock Report | Tenant1 | tool_reportbuilder\test\mock_report |
    And the following departments exist in organisation structure:
      | tenant  | name        | parent      |
      | Tenant1 | Framework   |             |
      | Tenant1 | Department1 | Framework   |
      | Tenant1 | Department2 | Department1 |
    And the following positions exist in organisation structure:
      | tenant  | name      | parent    | globalmanager | departmentmanager |
      | Tenant1 | Framework |           | 0             | 0                 |
      | Tenant1 | Manager   | Framework | 1             | 1                 |
      | Tenant1 | Deputy    | Manager   | 0             | 1                 |
      | Tenant1 | Minion    | Framework | 0             | 0                 |
    And the following job assignments exist in organisation structure:
      | user     | department  | position |
      | manager1 | Department1 | Manager  |
      | manager2 | Department2 | Manager  |
      | manager3 | Department2 | Deputy   |
      | user1    | Department1 | Minion   |
      | user2    | Department2 | Minion   |
    # Login as manager, edit the report and navigate to the Audience tab.
    And I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"

  Scenario: Configure report audience to no users
    When I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then I should see "Nothing to display"

  Scenario: Configure report audience for all users
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | Position   | All |
      | Department | All |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 2  | Manager (Department2)   |
      | Manager 3  | Deputy (Department2)    |
      | User 1     | Minion (Department1)    |
      | User 2     | Minion (Department2)    |

  Scenario: Configure report audience for a position
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | Position | Manager |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 2  | Manager (Department2)   |
    And the following should not exist in the "report-table" table:
      | Manager 3  | Deputy (Department2)    |
      | User 1     | Minion (Department1)    |
      | User 2     | Minion (Department2)    |

  Scenario: Configure report audience for a position including sub-positions
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | Position             | Manager |
      | Include subpositions | 1       |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 2  | Manager (Department2)   |
      | Manager 3  | Deputy (Department2)    |
    And the following should not exist in the "report-table" table:
      | User 1     | Minion (Department1)    |
      | User 2     | Minion (Department2)    |

  Scenario: Configure report audience for a department
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | Department | Department1 |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | User 1     | Minion (Department1)    |
    And the following should not exist in the "report-table" table:
      | Manager 2  | Manager (Department2)   |
      | Manager 3  | Deputy (Department2)    |
      | User 2     | Minion (Department2)    |

  Scenario: Configure report audience for a department including sub-departments
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | Department             | Department1 |
      | Include subdepartments | 1           |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 2  | Manager (Department2)   |
      | Manager 3  | Deputy (Department2)    |
      | User 1     | Minion (Department1)    |
      | User 2     | Minion (Department2)    |

  Scenario: Configure report audience for a position and department
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | Position   | Minion      |
      | Department | Department1 |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | User 1     | Minion (Department1)    |
    And the following should not exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 2  | Manager (Department2)   |
      | Manager 3  | Deputy (Department2)    |
      | User 2     | Minion (Department2)    |

  Scenario: Configure report audience with multiple jobs
    When I click on "Add job" "button"
    And I set the following fields to these values:
      | position[0][id]   | Manager     |
      | department[0][id] | Department2 |
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | position[1][id]               | Minion      |
      | department[1][id]             | Department1 |
      | department[1][subdepartments] | 1           |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 2  | Manager (Department2)   |
      | User 1     | Minion (Department1)    |
      | User 2     | Minion (Department2)    |
    And the following should not exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 3  | Deputy (Department2)    |
    # Switch back to the Audience tab and remove the last job.
    When I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "deletebutton[1]" "button"
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 2  | Manager (Department2)   |
    And the following should not exist in the "report-table" table:
      | Full name  | Position and department |
      | Manager 1  | Manager (Department1)   |
      | Manager 3  | Deputy (Department2)    |
      | User 1     | Minion (Department1)    |
      | User 2     | Minion (Department2)    |