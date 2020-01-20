@tool @tool_reportbuilder @moodleworkplace
Feature: Can add user list report
  In order to see the reports in the reports table
  As an manager
  I need to be able to add user list report.

  Background:
    Given "1" tenants exist with "5" users and "0" courses in each
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: Create a new custom report
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the visible field "Report name" to "Report1"
    And I set the visible field "Report source" to "Users list"
    And I press "Save" in the modal form dialogue
    # Check the tabs are enabled after the report has been created.
    And the "class" attribute of "a#table-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#schedule-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#access-tab" "css_element" should not contain "disabled"
    And I navigate to "Report builder" in workplace launcher
    Then the following should exist in the "report-table" table:
      | Report name | Plugin         |
      | Report1     | Report builder |

  @javascript
  Scenario: View users list as an organisation manager
    Given user "user11" has a global manager position over users "user12,user13" with permissions "2"
    And the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
      | Report2 | Tenant1 | tool_program\tool_reportbuilder\datasources\report_programs |
    And the following custom report audiences exist:
      | report  |
      | Report1 |
      | Report2 |
    # Manager with global capability can view both reports and all users in the users report.
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I should see "Report1"
    And I should see "Report2"
    And I follow "Report1"
    And I should see "Tenantadmin 1"
    And I should see "User 11"
    And I should see "User 12"
    And I should see "User 14"