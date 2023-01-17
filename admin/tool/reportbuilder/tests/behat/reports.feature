@tool @tool_reportbuilder @moodleworkplace
Feature: View reports table
  In order to see the reports in the reports table
  As an manager
  I need to be able to view existing reports, delete, create and edit.

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
      | manager2 | Tenant1 |
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
      | manager2 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: There are no existing reports
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    Then I should see "Nothing to display"

  @javascript
  Scenario: Create a new custom report
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "New report"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I should see "You must select a report source."
    And I should see "You must supply a value here"
    And I set the field "Report name" in the "New report" "dialogue" to "Report1"
    And I set the field "Report source" in the "New report" "dialogue" to "Course completion from datastore"
    And I click on "Save" "button" in the "New report" "dialogue"
    # Check the tabs are enabled after the report has been created.
    And the "class" attribute of "Table" "link" should not contain "disabled"
    And the "class" attribute of "Schedule" "link" should not contain "disabled"
    And the "class" attribute of "Access" "link" should not contain "disabled"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And the following should exist in the "report-table" table:
      | Report name | Plugin         | Created             | Last modified       | Modified by |
      | Report1     | Report builder | ##today##%d/%m/%y## | ##today##%d/%m/%y## | Manager 1   |

  @javascript
  Scenario: Ensure site report limit is observed when creating new reports
    Given the following config values are set as admin:
      | tool_reportbuilder_limitsenabled | 1 |
      | tool_reportbuilder_sitelimit     | 2 |
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant2 | tool_reportbuilder\test\mock_report |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "New report"
    Then I should see "The maximum number of custom reports for this site has been reached" in the "Report limit reached" "dialogue"
    And I click on "OK" "button" in the "Report limit reached" "dialogue"

  @javascript
  Scenario: Ensure tenant report limit is observed when creating new reports
    Given the following config values are set as admin:
      | tool_reportbuilder_limitsenabled | 1 |
      | tool_reportbuilder_tenantlimit   | 2 |
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant1 | tool_reportbuilder\test\mock_report |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "New report"
    Then I should see "You can only create 2 custom report(s) on this site" in the "Report limit reached" "dialogue"
    And I click on "OK" "button" in the "Report limit reached" "dialogue"

  @javascript
  Scenario: Ensure creation of new reports can be disabled via config
    Given the following config values are set as admin:
      | tool_reportbuilder_limitsenabled | 1 |
      | tool_reportbuilder_sitelimit     | 0 |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "New report"
    Then I should see "The maximum number of custom reports for this site has been reached" in the "Report limit reached" "dialogue"
    And I click on "OK" "button" in the "Report limit reached" "dialogue"

  @javascript
  Scenario: Edit name of a custom report
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I set the field "Edit report name" in the "Report1" "table_row" to "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"
    Then I should see "Test&\"2"
    And I should not see "Prueba"
    And I should not see "Report1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I should see "Test&\"2"
    And I should not see "Prueba"
    And I should not see "Report1"
    And I click on "Edit report name" "link" in the "Test&\"2" "table_row"
    And the field "New value for 'Test&\"2'" matches value "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"

  @javascript
  Scenario Outline: Filter custom reports
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list        |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Report source field limiter" in the "//*[@data-region='active-filters']" "xpath_element" to "is equal to"
    And I set the field "Report source value" in the "//*[@data-region='active-filters']" "xpath_element" to "<sourcefilter>"
    Then the following should exist in the "report-table" table:
      | Report name   | Plugin         |
      | <reportshown> | Report builder |
    And I should not see "<reporthidden>" in the "report-table" "table"
    Examples:
      | sourcefilter                     | reportshown | reporthidden |
      | Course completion from datastore | Report1     | Report2      |
      | Users list                       | Report2     | Report1      |

  @javascript
  Scenario: Delete a report
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Delete report" "link" in the "Report1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "Nothing to display"

  @javascript
  Scenario Outline: Users reports should not show suspended or not confirmed users to user without permissions
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolment_completion |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_enrolments        |
      | Report3 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list        |
    And the following "tool_tenant > users" exist:
      | tenant    | username    | firstname   | lastname     | email                 | suspended     | confirmed   |
      | Tenant1   | user1       | User        | 1            | student1@example.com  | 0             | 1           |
      | Tenant1   | user2       | User        | 2            | student3@example.com  | 0             | 1           |
      | Tenant1   | user3       | User        | 3            | student5@example.com  | <suspended>   | <confirmed> |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user2    | C1     | student        |
      | user3    | C1     | student        |
    And the following "roles" exist:
      | shortname      | name             | archetype |
      | browseusers    | Browse users     |           |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | user1    | browseusers                | System       |           |
      | user1    | tool_reportbuilder_manager | System       |           |
      | user2    | tool_reportbuilder_manager | System       |           |
      | user3    | tool_reportbuilder_manager | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role             | contextlevel | reference |
      | tool/tenant:browseusers | Allow      | browseusers      | System       |           |
    When I log in as "user1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "<report>" "table_row"
    Then I should see "User 2"
    And I should <user1view> "User 3"
    And I navigate to "Access" in current page administration
    And I should see "User 2"
    And I should <user1view> "User 3"
    And I log out
    And I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "<report>" "table_row"
    And I should see "User 2"
    And I should <manager1view> "User 3"
    And I navigate to "Access" in current page administration
    And I should see "User 2"
    And I should <manager1view> "User 3"
    And I log out
    Examples:
      | report           | suspended | confirmed | manager1view     | user1view     |
      | Report1          | 1         | 0         | not see          | see           |
      | Report2          | 0         | 1         | see              | see           |
      | Report3          | 0         | 0         | not see          | see           |
      | Report1          | 1         | 1         | not see          | see           |
