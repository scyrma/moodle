@tool @tool_reportbuilder @moodleworkplace @javascript
Feature: Configure access to tool_reportbuilder reports based on intended audience
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
      | admin    | Tenant1 |
    And the following "roles" exist:
      | shortname                  | name                   | archetype |
      | tool_reportbuilder_manager | Report builder manager |           |
    And the following "permission overrides" exist:
      | capability              | permission | role                       | contextlevel | reference |
      | tool/reportbuilder:edit | Allow      | tool_reportbuilder_manager | System       |           |
      | moodle/site:configview  | Allow      | tool_reportbuilder_manager | System       |           |
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

  Scenario: Configure report audience with manually added users audience type
    Given I log in as "manager6"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Audience" in current page administration
    And I should see "Add an audience to this report"
    Then I click on "Manually added users" "link"
    And I set the field "Add users manually" to "User 1"
    And I press "Save changes"
    And I should see "User 1"
    And I should not see "Add an audience to this report"
    Then I navigate to "Access" in current page administration
    And I should see "User 1"
    Then I log out
    Then I log in as "user1"
    And I follow "Reports" in the user menu
    And I should see "Some reports were not converted to the latest version"
    And I click on "View reports" "link" in the ".tool_reportbuilder-warning" "css_element"
    Then I should see "Mock Report"

  Scenario: Configure report audience with is member of cohort audience type
    Given the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
    And the following "cohort members" exist:
      | user  | cohort |
      | user2 | CHS1   |
    Given I log in as "manager6"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Audience" in current page administration
    Then I click on "Member of cohort" "link"
    And I set the field "Select cohorts" to "System cohort 1"
    And I press "Save changes"
    And I should see "System cohort 1"
    Then I navigate to "Access" in current page administration
    And I should see "User 2"
    Then I log out
    Then I log in as "user2"
    And I follow "Reports" in the user menu
    And I click on "View reports" "link" in the ".tool_reportbuilder-warning" "css_element"
    Then I should see "Mock Report"

  Scenario: Configure report audience with has system role audience type
    Given the following "roles" exist:
      | shortname | name      | archetype |
      | testrole  | Test role |            |
    And the following "role assigns" exist:
      | user    | role     | contextlevel | reference |
      | user2   | testrole | System       |          |
    Given I log in as "admin"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Audience" in current page administration
    Then I click on "Assigned system role" "link"
    And I set the field "Select a role" to "Test role"
    And I press "Save changes"
    And I should see "Test role"
    Then I navigate to "Access" in current page administration
    And I should see "User 2"
    Then I log out
    Then I log in as "user2"
    And I follow "Reports" in the user menu
    And I click on "View reports" "link" in the ".tool_reportbuilder-warning" "css_element"
    Then I should see "Mock Report"

  Scenario: Configure report audience to no users. Only users with RB capabilities will show up on the report
    Given shared space is enabled
    # Manager6 can switch tenants and edit reports.
    When I log in as "manager6"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Access" in current page administration
    And "Can view all reports" "text" should exist in the "Manager 1" "table_row"
    And "Can view all reports" "text" should exist in the "Manager 4" "table_row"
    # Manager 5 has RB capabilities but does not belong to Tenant 1.
    And I should not see "Manager 5"
    And I am on homepage
    And I switch to tenant "Shared space"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users list"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I navigate to "Access" in current page administration
    And I should see "Manager 1"
    And I should see "Manager 4"
    And I should see "Manager 6"
    And I should see "Manager 5"
    And I log out
    # Manager1 can edit reports but can not switch tenants.
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Mock Report" "table_row"
    And I navigate to "Access" in current page administration
    And I should see "Manager 1"
    And I should see "Manager 4"
    And I should see "Manager 6"
    And I should not see "Manager 5"

  Scenario: User without access to any reports should not see launcher icon
    When I log in as "user1"
    And I follow "Reports" in the user menu
    Then ".tool_reportbuilder-warning" "css_element" should not exist
