@tool @tool_reportbuilder @moodleworkplace
Feature: Download reports in different formats
  In order to download the reports
  As an manager
  I need to be able to add user list report and download it

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
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |

  @javascript
  Scenario Outline: Download a report in the view page in different formats
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Preview" "link" in the "Report1" "table_row"
    Then I set the field "Download table data as" to "<format>"
    And I press "Download"
    And I log out
    Examples:
      | format                             |
      | Comma separated values (.csv)      |
      | Microsoft Excel (.xlsx)            |
      | OpenDocument (.ods)                |
      | Portable Document Format (.pdf)    |
