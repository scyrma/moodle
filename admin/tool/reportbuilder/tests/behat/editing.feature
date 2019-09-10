@tool @tool_reportbuilder @moodleworkplace
Feature: Creating, editing and deleting reports
  In order to manage reports
  As a manager
  I need to be able to add, edit and delete reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                        | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager  | System       |           |

  @javascript
  Scenario: Preview a report
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report1 |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I click on "Switch to preview view" "button"
    And I should see "Switch to edit view"
    And I click on "Switch to edit view" "button"
    And I should see "Switch to preview view"
    And I log out

  @javascript
  Scenario: Edit details of a report from the report page
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "Report1"
    And I press "Edit details"
    And I set the field "Report name" to "Report2"
    And I press "Save" in the modal form dialogue
    And I should see "Report2"
    And I should not see "Report1"
    And the "class" attribute of "a#table-tab" "css_element" should not contain "disabled"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Report2"
    And I should not see "Report1"
    And I log out

  @javascript
  Scenario: Delete a report
    Given the following custom reports exist:
      | name      | tenant  | source |
      | My report | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Delete report" "link" in the "My report" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then "My report" "table_row" should not exist