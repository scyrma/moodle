@tool @tool_reportbuilder @moodleworkplace
Feature: Download reports in different formats
  In order to download the reports
  As an manager
  I need to be able to add user list report and download it

  Background:
    Given "1" tenants exist with "5" users and "0" courses in each
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |

  @javascript
  Scenario Outline: Download a report in the view page in different formats
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Switch to preview view" "button"
    Then I set the field "Download table data as" to "<format>"
    And I press "Download"
    And I log out
    Examples:
      | format                             |
      | Comma separated values (.csv)      |
      | Microsoft Excel (.xlsx)            |
      | OpenDocument (.ods)                |
      | Portable Document Format (.pdf)    |