@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Reportbuilder access check
  As a user
  I want to be able to access or not no reports

  Background:
    Given "4" tenants exist with "4" users and "2" courses in each
    And I log in as "admin"
    And the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | manager1     | Manager      | 1         | manager1@address.invalid |
      | manager2     | Manager      | 2         | manager2@address.invalid |
      | user1        | User         | 1         | user1@address.invalid |
      | user2        | User         | 2         | user2@address.invalid |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
    And the following custom reports exist:
      | name    | tenant  | source                                                              |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
      | Report2 | Tenant2 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
      | Report3 | Tenant1 | tool_program\tool_reportbuilder\datasources\report_programs |
      | Report4 | Tenant2 | tool_program\tool_reportbuilder\datasources\report_programs |
    And the following "roles" exist:
      | shortname     | name            | archetype |
      | reportviewer  | Reports viewer  |           |
    And the following "role assigns" exist:
      | user     | role                        | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager  | System       |           |
      | manager2 | tool_reportbuilder_manager  | System       |           |
      | user1    | reportviewer                | System       |           |
      | user2    | reportviewer                | System       |           |
    And I set the following system permissions of "Reports viewer" role:
      | capability              | permission |
      | tool/reportbuilder:read | Allow      |
    And the following "users" exist:
      | username        | firstname         | lastname  | email                       |
      | orgmanager3     | Org. Manager      | 3         | orgmanager3@address.invalid |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | orgmanager3 | Tenant1 |
    And the following departments exist in organisation structure:
      | tenant     | name             | parent         |
      | Tenant1    | Framework_t1     |                |
      | Tenant1    | Deparment_t1_d1  | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name              | parent           | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1      |                  |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1     |      1        |       0           |       2          |        2              |
      | Tenant1    | Position_t1_f2    | Position_t1_f1   |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user        | department      | position       |
      | orgmanager3 | Deparment_t1_d1 | Position_t1_f1 |
      | user11     | Deparment_t1_d1 | Position_t1_f2 |
      | user12     | Deparment_t1_d1 | Position_t1_f2 |
    And I log out

  Scenario: A tenantmanager of one tenant can not view reports from other tenant and can edit it.
    And I log in as "tenantadmin1"
    And I navigate to "Report builder" in workplace launcher
    And I should see "Report1" in the "table.report-table" "css_element"
    And I should see "Report3" in the "table.report-table" "css_element"
    And I should not see "Report2" in the "table.report-table" "css_element"
    And I should not see "Report4" in the "table.report-table" "css_element"
    And "Delete report" "link" should exist in the "Report1" "table_row"
    And "Delete report" "link" should exist in the "Report3" "table_row"
    And I follow "Report1"
    And I should see "Manager 1"
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should see "User 11"
    And I should see "User 12"
    And I should see "User 13"
    And I should not see "User 2"
    And I should see "Switch to preview view"
    And I navigate to "Report builder" in workplace launcher
    And I follow "Report3"
    And I should see "Switch to preview view"
    And I log out
    And I log in as "tenantadmin2"
    And I navigate to "Report builder" in workplace launcher
    And I should see "Report2" in the "table.report-table" "css_element"
    And I should see "Report4" in the "table.report-table" "css_element"
    And "Delete report" "link" should exist in the "Report2" "table_row"
    And "Delete report" "link" should exist in the "Report4" "table_row"
    And I should not see "Report1" in the "table.report-table" "css_element"
    And I should not see "Report3" in the "table.report-table" "css_element"
    And I follow "Report2"
    And I should see "Manager 2"
    And I should see "Tenantadmin 2"
    And I should see "User 2"
    And I should see "User 21"
    And I should see "User 22"
    And I should see "User 23"
    And I should not see "User 1"
    And I should see "Switch to preview view"
    And I navigate to "Report builder" in workplace launcher
    And I follow "Report4"
    And I should see "Switch to preview view"
    And I log out

  Scenario: A userviewer from one tenant can not see reports from other tenant
    And I log in as "user1"
    And I navigate to "Custom reports" in workplace launcher
    And I should see "Report1" in the "table.report-table" "css_element"
    And I should see "Report3" in the "table.report-table" "css_element"
    And I should not see "Report2" in the "table.report-table" "css_element"
    And I should not see "Report4" in the "table.report-table" "css_element"
    And "Delete report" "link" should not exist in the "Report1" "table_row"
    And "Delete report" "link" should not exist in the "Report3" "table_row"
    And I follow "Report1"
    And I should not see "Switch to preview view"
    And I should see "User 1"
    And I should see "User 11"
    And I should see "User 12"
    And I should see "User 13"
    And I should not see "User 2"
    And I navigate to "Custom reports" in workplace launcher
    And the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
      | Program3 | Tenant2 |
      | Program4 | Tenant2 |
    And I follow "Report3"
    And I should see "Program1"
    And I should see "Program2"
    And I should not see "Program3"
    And I should not see "Program4"
    And I should not see "Switch to preview view"

  Scenario: An user can not view reports.
    When I log in as "user11"
    Then "#workplace-menulink" "css_element" should not exist