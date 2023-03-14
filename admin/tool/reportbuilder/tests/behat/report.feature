@tool @tool_reportbuilder @moodleworkplace
Feature: Manage a report
  In order to manage a report
  As an manager
  I need to be able to add, remove, change header and move columns

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
    And the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report_nodefault |
      | Report2 | Tenant1 | tool_reportbuilder\test\mock_report           |

  @javascript
  Scenario: Edit details of a report from the report page
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I press "Edit details"
    And I set the field "Report name" to "My cool report"
    And I click on "Save" "button" in the "Edit report 'Report1'" "dialogue"
    Then I should see "My cool report"
    And I should not see "Report1"
    And I navigate to "Report builder" in workplace launcher
    And the following should exist in the "report-table" table:
      | Report name   | Plugin         |
      | My cool report| Report builder |
    And I should not see "Report1" in the "report-table" "table"
    And I log out

  @javascript
  Scenario: Edit report when live display of data is disabled by site administrator
    Given the following config values are set as admin:
      | tool_reportbuilder_liveediting | 0 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Add field 'First name' to the report" "button"
    And I click on "Add field 'Surname' to the report" "button"
    Then I should see "Data pre-visualization is disabled by the site administrator"
    And I click on "Switch to preview view" "button"
    And the following should exist in the "report-table" table:
      | First name | Surname |
      | Manager    | 1       |

  @javascript
  Scenario: Add a new column to the report
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    # Check there are no columns in report.
    Then I should see "Add a column to the report"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should see "Sortable columns not yet added" in the "region-report-sorting" "region"
    And I click on "Show/hide filters sidebar" "button"
    # Add field.
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname" in the "report-table" "table"
    And I should not see "Add a column to the report"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Surname" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Delete a column
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I click on "Delete column 'First name'" "link"
    Then I should not see "First name" in the "region-report-sorting" "region"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should not see "First name" in the "region-report-sorting" "region"
    And I click on "Show/hide filters sidebar" "button"
    # Delete the remaining column.
    And I click on "Delete column 'ID number'" "link"
    And I should not see "ID number" in the "region-report-sorting" "region"
    And I should see "Add a column to the report"
    And I click on "Show/hide filters sidebar" "button"
    And I should not see "ID number" in the "region-report-sorting" "region"
    And I should see "Sortable columns not yet added" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Rename a column
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I set the field "Edit header for the column 'First name'" in the "report-table" "table" to "Testable"
    Then I should see "Testable" in the "report-table" "table"
    And I should not see "First name" in the "region-report-sorting" "region"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should not see "First name" in the "region-report-sorting" "region"
    And "Enable sorting on column 'Testable'" "button" should exist in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Add a the same column to the report
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I click on "Add field 'First name' to the report" "button"
    Then I should see "First name 1" in the "report-table" "table"
    And I click on "Add field 'First name' to the report" "button"
    And I should see "First name 2" in the "report-table" "table"
    And I click on "Switch to preview view" "button"
    And I should see "First name" in the "report-table" "table"
    And I should see "First name 1" in the "report-table" "table"
    And I should see "First name 2" in the "report-table" "table"
    And I click on "Switch to edit view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should see "First name" in the "region-report-sorting" "region"
    And I should see "First name 1" in the "region-report-sorting" "region"
    And I should see "First name 2" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Search for a report column to add
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I search for "Surname" in aside
    Then I should see "Surname" in the "[data-region='aside-search-area']" "css_element"
    And I should not see "First name" in the "[data-region='aside-search-area']" "css_element"
    And I click on "Add field 'Surname' to the report" "button"
    And I should see "Surname" in the "report-table" "table"
    And I log out

  @javascript
  Scenario: Change the order of a column
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I click on "Move column First name" "button"
    And I follow "After \" ID number \""
    # Switch to the preview view to ensure the changed has been done correctly.
    And I click on "Switch to preview view" "button"
    Then "First name" "text" should appear after "ID number" "text"
    # Switch to the edit view, add more column, and move it the first.
    And I click on "Switch to edit view" "button"
    And I click on "Move column First name" "button"
    And I follow "To the top of the list"
    # Switch to the edit view, add more column, and move it the first.
    And I click on "Switch to preview view" "button"
    And "First name" "text" should appear before "ID number" "text"
    And I log out

  @javascript
  Scenario: Ensure the formatting is applied correctly to columns names
    Given the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    #<span lang="en" class="multilang">Test&"1</span><span lang="es" class="multilang">Prueba&"1</span>
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I should see "First name" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I set the field "Edit header for the column 'First name'" in the "report-table" "table" to "<span lang=\"en\" class=\"multilang\">Test&\"1</span><span lang=\"es\" class=\"multilang\">Prueba&\"1</span>"
    And I should see "Test&\"1" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I should not see "Prueba"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should see "Test&\"1" in the ".tool_reportbuilder_report_sidebar_settings_sorting" "css_element"
    And I should not see "Prueba"
    # Now refresh the page and check the same - this will check the initial loading and moving.
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should see "Test&\"1" in the ".tool_reportbuilder_report_sidebar_settings_sorting" "css_element"
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

  @javascript
  Scenario: Change the table sorting by heading
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Report builder" in site administration
    # Test system report, clicking the heading twice will change sorting to descending.
    And I click on "Report name" "link" in the "report-table" "table"
    And I should see "Reset table"
    And I click on "Report name" "link" in the "report-table" "table"
    Then "Report2" "table_row" should appear before "Report1" "table_row"
    # Test custom report.
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I click on "Add field 'Surname' to the report" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I click on "Enable sorting on column 'First name'" "button"
    And I click on "Expand filters" "button"
    And I set the field "Select a filter" to "Surname"
    And I click on "Switch to preview view" "button"
    And I click on "First name" "link" in the "report-table" "table"
    And I should see "Reset table"
    And "Admin" "table_row" should appear before "Manager" "table_row"
    And I click on "Surname" "link" in the "report-table" "table"
    And "Manager" "table_row" should appear before "Admin" "table_row"
    # Clicking again on the 'First name' column will change sort to descending.
    And I click on "First name" "link" in the "report-table" "table"
    And "Manager" "table_row" should appear before "Admin" "table_row"
    # Edit view is not affected by heading sorting.
    And I click on "Switch to edit view" "button"
    And I should not see "Reset table"
    And "Admin" "table_row" should appear before "Manager" "table_row"
    # Preview should preserve the heading sorting.
    And I click on "Switch to preview view" "button"
    And "Manager" "table_row" should appear before "Admin" "table_row"
    # Add filter
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Surname field limiter" to "is empty"
    And I should see "1" in the ".js-filters-active" "css_element"
    # Check "Reset table" resets both sorting and filtering.
    And I press "Reset table"
    And "Admin" "table_row" should appear before "Manager" "table_row"
    And ".js-filters-active" "css_element" should not be visible
    And I should not see "Reset table"

  @javascript
  Scenario: Hide columns sidebar
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Report builder" in site administration
    And I click on "Report name" "link" in the "report-table" "table"
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide sidebar" "button"
    Then "[data-region='sidebar-columns']" "css_element" should not be visible
    And I click on "Show/hide sidebar" "button"
    And "[data-region='sidebar-columns']" "css_element" should be visible
