@tool @tool_reportbuilder @moodleworkplace
Feature: Download reports in different formats
  In order to download the reports
  As an manager
  I need to be able to add user list report.

  Background:
    Given "1" tenants exist with "5" users and "0" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: Download a report in the view page in different formats
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "Report1"
    And I click on "Switch to preview view" "button"
    And I should see "Download table data as"
    And I click on "#downloadtype_download" "css_element"
    And I click on "Microsoft Excel (.xlsx)" "text" in the "#downloadtype_download" "css_element"
    And I click on "#downloadtype_download" "css_element"
    And I click on "HTML table" "text" in the "#downloadtype_download" "css_element"
    And I click on "#downloadtype_download" "css_element"
    And I click on "Javascript Object Notation (.json)" "text" in the "#downloadtype_download" "css_element"
    And I click on "#downloadtype_download" "css_element"
    And I click on "OpenDocument (.ods)" "text" in the "#downloadtype_download" "css_element"
    And I click on "#downloadtype_download" "css_element"
    And I click on "Portable Document Format (.pdf)" "text" in the "#downloadtype_download" "css_element"
    And I log out