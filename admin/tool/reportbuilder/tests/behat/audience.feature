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
      | manager4  | Manager   | 4        |
      | manager5  | Manager   | 5        |
      | manager6  | Manager   | 6        |
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
      | manager4 | Tenant1 |
      | manager6 | Tenant1 |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
      | manager4 | tool_reportbuilder_manager | System       |           |
      | manager5 | tool_reportbuilder_manager | System       |           |
      | manager6 | manager                    | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role    | contextlevel | reference |
      | tool/tenant:manage      | Allow      | manager | System       |           |
      | tool/reportbuilder:edit | Allow      | manager | System       |           |
    And the following "tool_reportbuilder > reports" exist:
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

  Scenario: Configure report audience to no users. Only users with RB capabilities will show up on the report
    Given shared space is enabled
    # Manager6 can switch tenants and edit reports.
    When I log in as "manager6"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    And "Can view all reports" "text" should exist in the "Manager 1" "table_row"
    And "Can view all reports" "text" should exist in the "Manager 4" "table_row"
    # Manager 5 has RB capabilities but does not belong to Tenant 1.
    And I should not see "Manager 5"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users list"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    And I should see "Manager 1"
    And I should see "Manager 4"
    And I should see "Manager 6"
    And I should see "Manager 5"
    And I log out
    # Manager1 can edit reports but can not switch tenants.
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    And I should see "Manager 1"
    And I should see "Manager 4"
    And I should see "Manager 6"
    And I should not see "Manager 5"

  Scenario: Configure report audience for all users
    When I log in as "manager6"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | Position   | All |
      | Department | All |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 1  | Manager  | Department1 |
      | Manager 2  | Manager  | Department2 |
      | Manager 3  | Deputy   | Department2 |
      | User 1     | Minion   | Department1 |
      | User 2     | Minion   | Department2 |
    And "Can view all reports" "text" should not exist in the "Manager 2" "table_row"
    And "Can view all reports" "text" should not exist in the "User 2" "table_row"
    And I should not see "Manager 5"

  Scenario: Configure report audience for a position
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | Position | Manager |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 1  | Manager  | Department1 |
      | Manager 2  | Manager  | Department2 |
    And the following should not exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 3  | Deputy   | Department2 |
      | User 1     | Minion   | Department1 |
      | User 2     | Minion   | Department2 |

  Scenario: Configure report audience for a position including sub-positions
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | Position             | Manager |
      | Include subpositions | 1       |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 1  | Manager  | Department1 |
      | Manager 2  | Manager  | Department2 |
      | Manager 3  | Deputy   | Department2 |
    And the following should not exist in the "report-table" table:
      | Full name  | Position | Department  |
      | User 1     | Minion   | Department1 |
      | User 2     | Minion   | Department2 |

  Scenario: Configure report audience for a department
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | Department | Department1 |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department |
      | Manager 1  | Manager   | Department1 |
      | User 1     | Minion  | Department1 |
    And the following should not exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 2  | Manager  | Department2 |
      | Manager 3  | Deputy   | Department2 |
      | User 2     | Minion   | Department2 |

  Scenario: Configure report audience for a department including sub-departments
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | Department             | Department1 |
      | Include subdepartments | 1           |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department |
      | Manager 1  | Manager | Department1 |
      | Manager 2  | Manager | Department2 |
      | Manager 3  | Deputy  | Department2 |
      | User 1     | Minion  | Department1 |
      | User 2     | Minion  | Department2 |

  Scenario: Configure report audience for a position and department
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
    And I set the following fields to these values:
      | Position   | Minion      |
      | Department | Department1 |
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department  |
      | User 1     | Minion   | Department1 |
    And the following should not exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 1  | Manager  | Department1 |
      | Manager 2  | Manager  | Department2 |
      | Manager 3  | Deputy   | Department2 |
      | User 2     | Minion   | Department2 |

  Scenario: Configure report audience with multiple jobs
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "Add job" "button"
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
      | Full name  | Position | Department  |
      | Manager 2  | Manager  | Department2 |
      | User 1     | Minion   | Department1 |
      | User 2     | Minion   | Department2 |
    And the following should not exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 1  | Manager  | Department1 |
      | Manager 3  | Deputy   | Department2 |
    # Switch back to the Audience tab and remove the last job.
    When I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "deletebutton[1]" "button"
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 2  | Manager  | Department2 |
    And the following should not exist in the "report-table" table:
      | Full name  | Position | Department  |
      | Manager 1  | Manager  | Department1 |
      | Manager 3  | Deputy   | Department2 |
      | User 1     | Minion   | Department1 |
      | User 2     | Minion   | Department2 |

  Scenario: User without access to any reports should not see launcher icon
    When I log in as "user1"
    Then "Custom reports" "text" should not exist in the "nav.navbar" "css_element"

  Scenario Outline: View reports with access via matching audience record
    Given the following "tool_reportbuilder > audiences" exist:
      | report      | position   | department   | subpositions   | subdepartments   |
      | Mock Report | <position> | <department> | <subpositions> | <subdepartments> |
    When I log in as "<user>"
    And I navigate to "Custom reports" in workplace launcher
    And I click on "Preview" "link" in the "Mock Report" "table_row"
    Then I should see "Mock Report"
    Examples:
      | user  | position | department  | subpositions | subdepartments |
      | user1 | Minion   |             | 0            | 0              |
      | user1 |          | Department1 | 0            | 0              |
      | user1 | Minion   | Department1 | 0            | 0              |
      | user1 |          |             | 0            | 0              |
      | user2 |          | Department1 | 0            | 1              |
      | user2 |          |             | 0            | 0              |
