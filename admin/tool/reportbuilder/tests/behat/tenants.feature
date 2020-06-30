@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Check tenant visibility
  In order to manage reports
  As a manager
  I need to be able to add, edit and delete reports from my tenant

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
      | manager2 | tool_reportbuilder_manager | System       |           |
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant2 | tool_reportbuilder\test\mock_report |

  # TODO: this whole feature file appears to duplicate "access.feature" - move those tests here.
  Scenario Outline: View reports for my own tenant only
    When I log in as "<user>"
    And I navigate to "Report builder" in workplace launcher
    Then the following should exist in the "report-table" table:
      | Report name    | Plugin         |
      | <reporttenant> | Report builder |
    And I should not see "<reportnontenant>" in the "report-table" "table"

    Examples:
      | user     | reporttenant | reportnontenant |
      | manager1 | Report1      | Report2         |
      | manager2 | Report2      | Report1         |
