@tool @tool_reportbuilder @moodleworkplace
Feature: Manage a report
  In order to manage a report
  As an manager
  I need to be able to add, remove, change header and move columns

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
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
  Scenario: Add a new column to the report
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report_nodefault |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    # Check there are no columns in report.
    And I should see "Add a column to the report"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should see "Sortable columns not yet added" in the "region-report-sorting" "region"
    And I click on "Show/hide filters sidebar" "button"
    # Add field.
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname" in the "report-table" "table"
    And I should not see "Add a column to the report"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should see "Surname" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Delete a column
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report_nodefault |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Add field 'Surname' to the report" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should see "Surname" in the "region-report-sorting" "region"
    And I click on "Delete column 'Surname'" "link"
    And I should see "Add a column to the report"
    And I should not see "Surname" in the "region-report-sorting" "region"
    And I should see "Sortable columns not yet added" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Rename a column
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should see "Surname" in the "region-report-sorting" "region"
    And "Enable sorting on column 'Surname'" "button" should exist in the "region-report-sorting" "region"
    And I click on "Edit header for the column 'Surname'" "link" in the "report-table" "table"
    And I set the field "New value for 'Surname'" to "Testable"
    And I press key "13" in the field "New value for 'Surname'"
    And I should see "Testable" in the "report-table" "table"
    And I should not see "Surname" in the "region-report-sorting" "region"
    And "Enable sorting on column 'Surname'" "button" should not exist in the "region-report-sorting" "region"
    And I should see "Testable" in the "region-report-sorting" "region"
    And "Enable sorting on column 'Testable'" "button" should exist in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Add a the same column to the report
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname" in the "report-table" "table"
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname 1" in the "report-table" "table"
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname 2" in the "report-table" "table"
    And I click on "Switch to preview view" "button"
    And I should see "Surname" in the "report-table" "table"
    And I should see "Surname 1" in the "report-table" "table"
    And I should see "Surname 2" in the "report-table" "table"
    And I click on "Switch to edit view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should see "Surname" in the "region-report-sorting" "region"
    And I should see "Surname 1" in the "region-report-sorting" "region"
    And I should see "Surname 2" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Change the order of a column
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Move column First name" "button"
    And I follow "After \" ID number \""
    # Switch to the preview view to ensure the changed has been done correctly.
    And I click on "Switch to preview view" "button"
    And "First name" "text" should appear after "ID number" "text"
    # Switch to the edit view, add more column, and move it the first.
    Then I click on "Switch to edit view" "button"
    And I click on "Move column First name" "button"
    And I follow "To the top of the list"
    # Switch to the edit view, add more column, and move it the first.
    And I click on "Switch to preview view" "button"
    And "First name" "text" should appear before "ID number" "text"
    And I log out

  @javascript
  Scenario: Ensure the formatting is applied correctly to columns names
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    #<span lang="en" class="multilang">Test&"1</span><span lang="es" class="multilang">Prueba&"1</span>
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I should see "First name" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I click on "Edit header for the column 'First name'" "link" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And the field "New value for 'First name'" matches value ""
    And the field "New value for 'First name'" does not match value "First name"
    And I set the field "New value for 'First name'" to "<span lang=\"en\" class=\"multilang\">Test&\"1</span><span lang=\"es\" class=\"multilang\">Prueba&\"1</span>"
    And I press key "13" in the field "New value for 'First name'"
    And I should see "Test&\"1" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I should not see "Prueba"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should see "Test&\"1" in the ".tool_reportbuilder_report_sidebar_sorting" "css_element"
    And I should not see "Prueba"
    # TODO SP-422 finish checking sorting tab - all titles/labels are currently missing there
    # Now refresh the page and check the same - this will check the initial loading and moving.
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Sorting"
    And I should not see "Prueba"
    And I click on "Move column Test&\"1" "button"
    And I click on "After \" ID number \"" "link" in the "Move column Test&\"1" "dialogue"
    And I click on "Move column ID number" "button"
    And I click on "After \" Test&\"1 \"" "link" in the "Move column ID number" "dialogue"
    And I should not see "Prueba"
    # Preview mode
    And I click on "Switch to preview view" "button"
    And I should see "Test&\"1"
    And I should not see "Prueba"
    And I log out
