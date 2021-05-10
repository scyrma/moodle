@tool @tool_reportbuilder @moodleworkplace
Feature: Manage report builder schedules
  As an manager with permissions
  I want to be able to create, update, run and delete schedules.

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | user1    | User      | 1        | user1@example.com    |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | user1    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |

  @javascript
  Scenario: There are no existing report schedules
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    Then I should see "Nothing to display"
    And I log out

  @javascript
  Scenario: Create a report schedule
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    And I follow "New schedule"
    Then "Manager 1" "autocomplete_selection" should exist in the "View report data as" "form_row"
    And I set the following fields in the "New schedule" "dialogue" to these values:
      | Schedule name | New schedule |
      | Report        | Report1      |
      | Recurrence    | 1            |
      | Subject       | New schedule |
    And I set the field "Message" to "Example"
    And I click on "Save" "button" in the "New schedule" "dialogue"
    And I should see "You must select a format" in the "Format" "form_row"
    And I set the field "Format" to "csv"
    And I click on "Save" "button" in the "New schedule" "dialogue"
    And I should see "You must select some recipients for this schedule" in the "Add users manually" "form_row"
    And I set the field "Add users manually" to "User 1"
    And I set the following fields in the "New schedule" "dialogue" to these values:
      | scheduled[day] | 1 |
      | scheduled[month] | January |
      | scheduled[year] | 2030 |
      | scheduled[hour] | 01 |
      | scheduled[minute] | 00 |
    And I click on "Save" "button" in the "New schedule" "dialogue"
    And the following should exist in the "report-table" table:
      | Schedule name | Report name | Scheduled      | Last sent on | Format                        |
      | New schedule  | Report1     | 1/01/30, 01:00 |              | Comma separated values (.csv) |

  @javascript
  Scenario: Edit a report schedule
    Given the following departments exist in organisation structure:
      | tenant   | name       | parent     |
      | Tenant1  | Framework1 |            |
      | Tenant1  | Dept1      | Framework1 |
    And the following "tool_reportbuilder > schedules" exist:
      | name               | report  | department |
      | Example schedule 1 | Report1 | Dept1      |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    And I click on "Edit schedule" "link" in the "Example schedule 1" "table_row"
    And I set the field "Schedule name" in the "Edit schedule" "dialogue" to "My new schedule name"
    And I click on "Save" "button" in the "Edit schedule" "dialogue"
    Then I should see "My new schedule name" in the "Report1" "table_row"
    And I log out

  @javascript
  Scenario: Delete a report schedule
    Given the following "tool_reportbuilder > schedules" exist:
      | name    | report |
      | Example schedule 1 | Report1 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    And I click on "Delete schedule" "link" in the "Example schedule 1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I should see "Nothing to display"
    And I log out

  @javascript
  Scenario: List schedules for a specific report
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                              |
      | Report2 | Tenant1 | tool_reportbuilder\test\mock_report |
    And the following "tool_reportbuilder > schedules" exist:
      | name      | report  |
      | Schedule1 | Report1 |
      | Schedule2 | Report2 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    Then the following should exist in the "report-table" table:
      | Schedule name | Last sent on |
      | Schedule1     | Never        |
    And the following should not exist in the "report-table" table:
      | Schedule name | Last sent on |
      | Schedule2     | Never        |

  @javascript
  Scenario: Create schedule for a specific report
    Given the following departments exist in organisation structure:
      | tenant   | name       | parent     |
      | Tenant1  | Framework1 |            |
      | Tenant1  | Dept1      | Framework1 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    And I follow "New schedule"
    And I set the following fields in the "New schedule" "dialogue" to these values:
      | Schedule name | My schedule     |
      | Format        | HTML table      |
      | Recurrence    | Does not repeat |
      | Department    | Dept1           |
      | Subject       | Hola            |
    And I set the field "Message" to "Here it is"
    And I click on "Save" "button" in the "New schedule" "dialogue"
    Then the following should exist in the "report-table" table:
      | Schedule name | Last sent on |
      | My schedule   | Never        |

  @javascript
  Scenario: Filter schedules table by schedule name filter
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report3 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report4 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report5 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the following "tool_reportbuilder > schedules" exist:
      | name    | report |
      | Example schedule 1 | Report1 |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I change window size to "large"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    And I click on "Show/hide filters sidebar" "button" in the ".tool_reportbuilder_tab_schedules" "css_element"
    And I set the field "Schedule name value" to "Example schedule 1"
    And I press the enter key
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name field limiter | doesn't contain |
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
    And I set the following fields to these values:
      | Schedule name field limiter | is equal to |
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name value | Example schedule 1 |
      | Schedule name field limiter | starts with |
    And I should see "Example schedule 1" in the "table.report-table" "css_element"
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name value | schedule 1 |
      | Schedule name field limiter | ends with |
    And I should see "Example schedule 1" in the "table.report-table" "css_element"
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name field limiter | is empty |
    And I should see "Nothing to display"
    And I log out

  @javascript
  Scenario: Filter schedules table by report name
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report3 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the following "tool_reportbuilder > schedules" exist:
      | name    | report |
      | Example schedule 1 | Report1 |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    Then I log in as "manager1"
    Then I navigate to "Report builder" in workplace launcher
    And I change window size to "large"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    And I click on "Show/hide filters sidebar" "button" in the ".tool_reportbuilder_tab_schedules" "css_element"
    And I set the following fields to these values:
      | Report name field limiter | is equal to |
    And I set the following fields to these values:
      | Report name value | Report1 |
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    Then the following should exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
    And I set the following fields to these values:
      | Report name field limiter | isn't equal to |
    And I set the following fields to these values:
      | Report name value | Report1 |
    Then the following should exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
    And I set the following fields to these values:
      | Report name field limiter | is any value |
    Then the following should exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    And I log out
