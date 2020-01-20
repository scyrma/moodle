@tool @tool_reportbuilder @moodleworkplace
Feature: Creating, editing and deleting reports
  In order to manage reports
  As a manager
  I need to be able to add, edit and delete reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                        | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager  | System       |           |
    And the following custom reports exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |

  @javascript
  Scenario: Preview a report
    When I log in as "manager1"
    And I navigate to "Report builder" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Switch to preview view" "button"
    Then I should see "Switch to edit view"
    And I click on "Switch to edit view" "button"
    And I should see "Switch to preview view"
    And I log out

  @javascript
  Scenario: Edit details of a report from the report page
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I press "Edit details"
    And I set the field "Report name" to "Report2"
    And I press "Save" in the modal form dialogue
    And I should see "Report2"
    And I should not see "Report1"
    And I navigate to "Report builder" in workplace launcher
    And the following should exist in the "report-table" table:
      | Report name | Plugin         |
      | Report2     | Report builder |
    And I should not see "Report1" in the "report-table" "table"
    And I log out