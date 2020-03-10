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
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
      | manager2 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: There are no existing reports
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    Then I should see "Nothing to display"

  @javascript
  Scenario: Create a new custom report
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I press "Save" in the modal form dialogue
    And I should see "You must select a report source."
    And I should see "You must supply a value here"
    And I set the visible field "Report name" to "Report1"
    And I set the visible field "Report source" to "Course completion from datastore"
    And I press "Save" in the modal form dialogue
    # Check the tabs are enabled after the report has been created.
    And the "class" attribute of "a#table-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#schedule-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#access-tab" "css_element" should not contain "disabled"
    And I navigate to "Report builder" in workplace launcher
    And the following should exist in the "report-table" table:
      | Report name | Plugin         | Created          | Last modified    | Modified by |
      | Report1     | Report builder | ##today##j/m/y## | ##today##j/m/y## | Manager 1   |

  @javascript
  Scenario: Ensure site report limit is observed when creating new reports
    Given the following config values are set as admin:
      | tool_reportbuilder_limitsenabled | 1 |
      | tool_reportbuilder_sitelimit     | 2 |
    And the following custom reports exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant2 | tool_reportbuilder\test\mock_report |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    Then I should see "The maximum number of custom reports for this site has been reached" in the "Report limit reached" "dialogue"
    And I click on "Ok" "button" in the "Report limit reached" "dialogue"

  @javascript
  Scenario: Ensure tenant report limit is observed when creating new reports
    Given the following config values are set as admin:
      | tool_reportbuilder_limitsenabled | 1 |
      | tool_reportbuilder_tenantlimit   | 2 |
    And the following custom reports exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
      | Report2 | Tenant1 | tool_reportbuilder\test\mock_report |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    Then I should see "You can only create 2 custom report(s) on this site" in the "Report limit reached" "dialogue"
    And I click on "Ok" "button" in the "Report limit reached" "dialogue"

  @javascript
  Scenario: Ensure creation of new reports can be disabled via config
    Given the following config values are set as admin:
      | tool_reportbuilder_limitsenabled | 1 |
      | tool_reportbuilder_sitelimit     | 0 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    Then I should see "The maximum number of custom reports for this site has been reached" in the "Report limit reached" "dialogue"
    And I click on "Ok" "button" in the "Report limit reached" "dialogue"

  @javascript
  Scenario: Edit name of a custom report
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit report name" "link" in the "Report1" "table_row"
    And I set the field "New value for 'Report1'" to "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"
    And I press key "13" in the field "New value for 'Report1'"
    Then I should see "Test&\"2"
    And I should not see "Prueba"
    And I should not see "Report1"
    And I navigate to "Report builder" in workplace launcher
    And I should see "Test&\"2"
    And I should not see "Prueba"
    And I should not see "Report1"
    And I click on "Edit report name" "link" in the "Test&\"2" "table_row"
    And the field "New value for 'Test&\"2'" matches value "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"

  @javascript
  Scenario Outline: Filter custom reports
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list        |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Show/hide filters sidebar" "button"
    And I set the visible field "Report source field limiter" to "is equal to"
    And I set the visible field "Report source value" to "<sourcefilter>"
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
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Delete report" "link" in the "Report1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "Nothing to display"