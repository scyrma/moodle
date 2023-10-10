@tool @tool_reportbuilder @moodleworkplace
Feature: Manage card view settings in the report editor
  In order to manage a report card view settings
  As an user with permissions
  I need to be able to edit and save the form

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "users" exist:
      | username | firstname | lastname | email                | city       |
      | manager1 | Manager   | 1        | manager1@example.com | Tarragona  |
      | user1    | User      | 1        | user1@example.com    | Altafulla  |
      | user2    | User      | 2        | user2@example.com    | Barcelona  |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: Edit card view settings form
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand card view" "button"
    # Mock report has 2 active columns.
    Then I set the field "visibility" to "2"
    And I press "Save changes"
    # Let's check that after switching to preview mode card view form gets rendered again.
    And I click on "Switch to preview view" "button"
    And I should not see "Card view"
    And I click on "Switch to edit view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand card view" "button"
    Then the "visibility" select box should contain "2"
    And I click on "Delete column 'ID number'" "link"
    Then the "visibility" select box should contain "1"

  @javascript
  Scenario: Cards show correct number of columns
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    # Edit Card view form.
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand card view" "button"
    # Set 'Number of columns always visible' to 1.
    Then I set the field "visibility" to "1"
    # Set 'Show the first column title' to NO.
    Then I set the field "showtitle" to "0"
    And I press "Save changes"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    # Check content in cards.
    Then I change window size to "530x678"
    And I click on "Table" "link" in the "[role=tablist]" "css_element"
    And I press "Switch to preview view"
    # Manager card content should not be visible when card is closed.
    And I should not see "manager1@example.com" in the "report-table" "table"
    And I should not see "Tarragona" in the "report-table" "table"
    And "[data-cardtitle=\"Full name\"]" "css_element" should not exist in the "report-table" "table"
    And I should see "User 1" in the "report-table" "table"
    And I should not see "user1@example.com" in the "report-table" "table"
    And I should not see "Tarragona" in the "report-table" "table"
    And I should see "User 2" in the "report-table" "table"
    And I should not see "user2@example.com" in the "report-table" "table"
    And I should not see "Altafulla" in the "report-table" "table"
    Then I click on "Expand" "button" in the "report-table" "table"
    # Manager card content should be visible now that card is open.
    And I should see "manager1@example.com" in the "report-table" "table"
    And I should see "Tarragona" in the "report-table" "table"
    Then I change window size to "large"
    And I press "Switch to edit view"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand card view" "button"
    # Set 'Number of columns always visible' to 2.
    Then I set the field "visibility" to "2"
    And I press "Save changes"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    Then I change window size to "530x678"
    And I click on "Table" "link" in the "[role=tablist]" "css_element"
    And I press "Switch to preview view"
    And I should see "User 1" in the "report-table" "table"
    And I should see "user1@example.com" in the "report-table" "table"
    And I should not see "Tarragona" in the "report-table" "table"
    And I should see "User 2" in the "report-table" "table"
    And I should see "user2@example.com" in the "report-table" "table"
    And I should not see "Altafulla" in the "report-table" "table"
    Then I change window size to "large"
    And I press "Switch to edit view"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand card view" "button"
    # Set 'Number of columns always visible' to 3.
    Then I set the field "visibility" to "3"
    # Set 'Show the first column title' to YES.
    Then I set the field "showtitle" to "1"
    And I press "Save changes"
    And I click on "Schedules" "link" in the "[role=tablist]" "css_element"
    Then I change window size to "530x678"
    And I click on "Table" "link" in the "[role=tablist]" "css_element"
    And I press "Switch to preview view"
    And "[data-cardtitle=\"Full name\"]" "css_element" should exist in the "report-table" "table"
    And "[data-cardtitle=\"Email address\"]" "css_element" should exist in the "report-table" "table"
    And "[data-cardtitle=\"City/town\"]" "css_element" should exist in the "report-table" "table"
    And I should see "User 1" in the "report-table" "table"
    And I should see "user1@example.com" in the "report-table" "table"
    And I should see "Tarragona" in the "report-table" "table"
    And I should see "User 2" in the "report-table" "table"
    And I should see "user2@example.com" in the "report-table" "table"
    And I should see "Altafulla" in the "report-table" "table"
