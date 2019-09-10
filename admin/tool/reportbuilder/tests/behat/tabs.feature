@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Tabs management
  In order to manage reports
  As a manager
  I need to be able to view the tabs: Detail, Table, Schedule and Access (in this order)

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
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |

  Scenario: Tabs in management view existing report
    When the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And I log in as "manager1"
    And I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And the "class" attribute of "a#table-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#schedule-tab" "css_element" should not contain "disabled"
    And the "class" attribute of "a#access-tab" "css_element" should not contain "disabled"
    And I should see "Table" in the "a#table-tab" "css_element"
    And I should see "Schedule" in the "a#schedule-tab" "css_element"
    And I should see "Access" in the "a#access-tab" "css_element"
    And "a#table-tab" "css_element" should appear before "a#schedule-tab" "css_element"
    And "a#schedule-tab" "css_element" should appear before "a#access-tab" "css_element"
    And I follow "Schedule"
    And I follow "Access"
    And I follow "Table"
    And I log out