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
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "roles" exist:
      | shortname     | name            | archetype |
      | reportviewer  | Reports viewer  |           |
    And the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
      | manager2 | tool_reportbuilder_manager | System       |           |
      | user1    | reportviewer   | System       |           |
      | user2    | reportviewer   | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Reports viewer" role:
      | capability              | permission |
      | tool/reportbuilder:read | Allow      |
    And I log out

  Scenario: Create a new custom report for different tenants
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report1 |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I am on homepage
    And I log out
    When I log in as "manager2"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report2 |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Report2"
    And I should not see "Report1"
    And I log out
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I should see "Report1"
    And I should not see "Report2"
    And I log out

  Scenario: Editing basic report details and deleting reports
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report3 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit details" "link" in the "Report1" "table_row"
    And I set the following fields to these values:
      | Report name | Newreport |
    And I press "Save" in the modal form dialogue
    And I should see "Newreport"
    And I should not see "Report1"
    And I click on "Delete report" "link" in the "Report2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "Newreport"
    And I should not see "Report2"
    And I should see "Report3"