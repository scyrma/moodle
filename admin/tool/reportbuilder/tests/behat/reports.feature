@tool @tool_reportbuilder @moodleworkplace
Feature: View reports table
  In order to see the reports in the reports table
  As an manager
  I need to be able to view existing reports, delete, create and edit.

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "users" exist:
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
      | manager2 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: There are no existing reports
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Nothing to display"
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report1 |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Report name" in the "table.report-table thead" "css_element"
    And I should see "Plugin" in the "table.report-table thead" "css_element"
    And I should see "Created" in the "table.report-table thead" "css_element"
    And I should see "Last modified" in the "table.report-table thead" "css_element"
    And I should see "Modified by" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And "Report name" "text" should appear before "table.report-table thead th.c1" "css_element"
    And "Plugin" "text" should appear before "table.report-table thead th.c2" "css_element"
    And "Created" "text" should appear before "table.report-table thead th.c3" "css_element"
    And "Last modified" "text" should appear before "table.report-table thead th.c4" "css_element"
    And "Modified by" "text" should appear before "table.report-table thead th.c5" "css_element"
    And I should see "Manager 1" in the "Report1" "table_row"
    And I log out

  @javascript
  Scenario: Create a new custom report
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report1 |
    And I press "Save" in the modal form dialogue
    And I should see "You must select a report source."
    And I set the following fields to these values:
      | Report name | |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I should see "You must supply a value here"
    And I set the following fields to these values:
      | Report name | Report1 |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I should not see "You must select a report source."
    And I should not see "You must supply a value here"
    # Check the tabs are enabled after the report has been created.
    And the "class" attribute of "a#table-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#schedule-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#access-tab" "css_element" should not contain "disabled"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Report1"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @javascript
  Scenario: Edit name of a custom report
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit report name" "link" in the "Report1" "table_row"
    And I set the field "New value for 'Report1'" to "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"
    And I press key "13" in the field "New value for 'Report1'"
    And I should see "Test&\"2"
    And I should not see "Prueba"
    And I should not see "Report1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Test&\"2"
    And I should not see "Prueba"
    And I should not see "Report1"
    And I click on "Edit report name" "link" in the "Test&\"2" "table_row"
    And the field "New value for 'Test&\"2'" matches value "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @javascript
  Scenario: Delete a report
    # Delete the report
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "[data-action='delete']" "css_element" in the "Report1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Report1"
    And I should see "Nothing to display"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @javascript
  Scenario: Delete a report with pagination
    # Delete the report
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report-a | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-b | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-c | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-d | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-e | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-f | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-g | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-h | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-i | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-j | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report-k | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report1-l | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I click on "[data-action='delete']" "css_element" in the "Report-k" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I click on "2" "link" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    And I should not see "Report-k"
    And I log out