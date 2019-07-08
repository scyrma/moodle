@tool @tool_program @moodleworkplace @javascript
Feature: Create custom fields on programs
  In order to create custom fields in programs
  As a manager
  I need to be able to go into site administration and create them

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
    And the following "roles" exist:
      | shortname     | name            | archetype |
      | programmanager | program manager |           |
      | programeditmanager | program edit manager |           |
    And the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | manager1 | programmanager  | System       |           |
      | manager2 | programeditmanager  | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "program manager" role:
      | capability              | permission |
      | tool/program:edit | Allow      |
      | tool/program:allocateuser | Allow      |
      | tool/program:configurecustomfields | Allow      |
      | moodle/site:configview  | Allow      |
    And I set the following system permissions of "program edit manager" role:
      | capability              | permission |
      | tool/program:edit | Allow      |
      | tool/program:allocateuser | Allow      |
      | moodle/site:configview  | Allow      |
      | tool/reportbuilder:edit | Allow      |
    And I log out

  Scenario: Create and edit custom fields
    When I log in as "admin"
    Then I navigate to "Courses > Programs custom fields" in site administration
    And I should see "Programs custom fields"
    And I should see "Add a new category"
    Then I press "Add a new category"
    And I should see "Add a new custom field"
    And I should see "Other fields"
    And I click on "Add a new custom field" "link"
    And I click on "Short text" "link"
    And I set the following fields to these values:
      | Name       | Testing a custom text field |
      | Short name | testwp  |
    And I press "Save changes"
    Then I log out
    Then I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And I should see "Edit program"
    And I should see "Testing a custom text field"
    And I set the field "Testing a custom text field" to "Workplace"
    Then I press "Save" in the modal form dialogue
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And the field "Testing a custom text field" matches value "Workplace"
    Then I press "Save" in the modal form dialogue
    And I log out
    Then I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And I should see "Edit program"
    And I should see "Testing a custom text field"
    And I set the field "Testing a custom text field" to "Workplace 123"
    Then I press "Save" in the modal form dialogue
    Then I click on ".edit_details" "css_element" in the "Program1" "table_row"
    And the field "Testing a custom text field" matches value "Workplace 123"
    Then I press "Save" in the modal form dialogue
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report customfields |
      | Report source | Programs  |
    And I press "Save" in the modal form dialogue
    Then I should see "Report customfields"
    And I should see "ID number" in the "#entity_tool_program" "css_element"
    And I should see "Program name" in the "#entity_tool_program" "css_element"
    And I should see "Description" in the "#entity_tool_program" "css_element"
    And I should see "Visible" in the "#entity_tool_program" "css_element"
    And I should see "Allocation start date" in the "#entity_tool_program" "css_element"
    And I should see "Allocation end date" in the "#entity_tool_program" "css_element"
    And I should see "Archived" in the "#entity_tool_program" "css_element"
    And I should see "Archived on" in the "#entity_tool_program" "css_element"
    And I should see "Allow direct allocation" in the "#entity_tool_program" "css_element"
    And I should see "Start date" in the "#entity_tool_program" "css_element"
    And I should see "Due date" in the "#entity_tool_program" "css_element"
    And I should see "End date" in the "#entity_tool_program" "css_element"
    And I should see "Testing a custom text field" in the "#entity_tool_program" "css_element"
    Then I click on "[data-field='tool_program:fullname']" "css_element"
    Then I should see "Program1" in the "[data-region='report-table']" "css_element"
    Then I click on "Testing a custom text field" "link"
    Then I should see "Workplace 123" in the "[data-region='report-table']" "css_element"
    And I log out