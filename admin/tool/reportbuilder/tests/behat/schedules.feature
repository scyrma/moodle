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
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following custom reports exist:
      | name    | tenant  | source |
      | Example report | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |

  @javascript
  Scenario: There are no existing schedules
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "Schedules"
    And I should see "Nothing to display"
    And I log out

  @javascript
  Scenario: Create a new schedule
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "Schedules"
    And I follow "New schedule"
    Then "Manager 1" "autocomplete_selection" should exist in the "View report data as" "form_row"
    And I press "Save" in the modal form dialogue
    And I should see "You must supply a value here" in the "//*[@data-region='modal']//*[contains(@class, 'fitem') and contains(.,'Schedule name')]" "xpath_element"
    And I set the following visible fields to these values:
      | Schedule name | New schedule |
      | Recurrence | 1 |
      | Subject | New schedule |
    And I set the field "Message" to "Example"
    And I press "Save" in the modal form dialogue
    And I should see "You must supply a value here" in the "//*[@data-region='modal']//*[contains(@class, 'fitem') and contains(.,'Report')]" "xpath_element"
    And I set the visible field "Report" to "Example report"
    And I press "Save" in the modal form dialogue
    And I should see "You must select a format" in the "Format" "form_row"
    And I set the following visible fields to these values:
      | Format | csv |
      | scheduled[day] | 1 |
      | scheduled[month] | January |
      | scheduled[year] | 2030 |
      | scheduled[hour] | 01 |
      | scheduled[minute] | 00 |
    And I press "Save" in the modal form dialogue
    And the following should exist in the "report-table" table:
      | Schedule name | Report name    | Scheduled      | Last sent on | Format                        |
      | New schedule  | Example report | 1/01/30, 01:00 |              | Comma separated values (.csv) |

  @javascript
  Scenario: Edit schedule
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the following custom schedules exist:
      | name    | report |
      | Example schedule 1 | Report1 |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "Schedules"
    And I click on "Edit schedule" "link" in the "Example schedule 1" "table_row"
    And I set the following visible fields to these values:
      | Schedule name       | Name updated |
      | View report data as | Manager 1    |
    And I press "Save" in the modal form dialogue
    Then "Name updated" "text" should exist in the "Report1" "table_row"
    And I log out

  @javascript
  Scenario: Delete schedule
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    Given the following custom schedules exist:
      | name    | report |
      | Example schedule 1 | Report1 |
    Then I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "Schedules"
    Then I click on "[data-action='delete']" "css_element" in the "Example schedule 1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Example schedule 1"
    And I log out

  @javascript
  Scenario: Filter schedules table by schedule name filter
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report3 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report4 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report5 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    Given the following custom schedules exist:
      | name    | report |
      | Example schedule 1 | Report1 |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    Then I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I change window size to "large"
    And I follow "Schedules"
    And I click on "Show/hide filters sidebar" "button" in the ".tool_reportbuilder_tab_schedules" "css_element"
    And I set the following fields to these values:
      | Schedule name value | Example schedule 1 |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name field limiter | doesn't contain |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
    And I set the following fields to these values:
      | Schedule name field limiter | is equal to |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name value | Example schedule 1 |
    And I wait "1" seconds
    And I set the following fields to these values:
      | Schedule name field limiter | starts with |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    And I should see "Example schedule 1" in the "table.report-table" "css_element"
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name value | schedule 1 |
    And I wait "1" seconds
    And I set the following fields to these values:
      | Schedule name field limiter | ends with |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    And I should see "Example schedule 1" in the "table.report-table" "css_element"
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
      | Example schedule 4 | Report4 |
      | Example schedule 5 | Report5 |
    And I set the following fields to these values:
      | Schedule name field limiter | is empty |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    And I should see "Nothing to display"
    And I log out

  @javascript
  Scenario: Filter schedules table by report name
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report2 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
      | Report3 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    Given the following custom schedules exist:
      | name    | report |
      | Example schedule 1 | Report1 |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    Then I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I change window size to "large"
    And I follow "Schedules"
    And I click on "Show/hide filters sidebar" "button" in the ".tool_reportbuilder_tab_schedules" "css_element"
    And I set the following fields to these values:
      | Report name field limiter | is equal to |
    And I set the following fields to these values:
      | Report name value | Report1 |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
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
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    Then the following should exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    Then the following should not exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
    And I set the following fields to these values:
      | Report name field limiter | is any value |
    # the search component have a delay to avoid concurrent calls to the reload table service
    And I wait "1" seconds
    Then the following should exist in the "report-table" table:
      | Schedule name      | Report name  |
      | Example schedule 1 | Report1 |
      | Example schedule 2 | Report2 |
      | Example schedule 3 | Report3 |
    And I log out