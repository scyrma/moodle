@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import course categories
  As a tenant administrator
  I want to export and import course categories

  Background:
    Given "1" tenants exist with "0" users and "1" courses in each
    And the following "categories" exist:
      | name   | category | idnumber | visible |
      | cat1   | 0        | cat1     | 1       |
      | cat11  | cat1     | cat11    | 1       |
      | cat2   | 0        | cat2     | 1       |
    And the following "courses" exist:
      | fullname  | shortname | category | visible |
      | Course 1  | c1        | cat1     | 1       |
      | Course 2  | c2        | cat2     | 1       |
      | Course 3  | c3        | cat11    | 1       |

  Scenario: Export all course categories
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Course categories" "radio"
    And I press "Next"
    And I press "Next"
    And I should see "Select at least one category"
    And I open the autocomplete suggestions list
    And "cat1 / cat11" "autocomplete_suggestions" should exist
    And "cat2" "autocomplete_suggestions" should exist
    And I click on "cat1" item in the autocomplete list
    And I press "Next"
    And I should see "About the file"
    And I should see "Instances (2)"
    And I should see "cat1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "cat1 / cat11" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I press "Proceed"
    And I click on "Close" "link_or_button"
    And I run all adhoc tasks
    And I navigate to "Migration" in workplace launcher
    Then the following should exist in the "report-table" table:
      | Exporter           | Status    |
      | Course categories  | Success   |
    And I click on "View export" "link" in the "Course categories" "table_row"
    Then I should see "Success"
    And I should see "Instances (2)"
    And I should see "cat1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "cat1 / cat11" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I navigate to "Export and import" in workplace launcher
    And I click on "New import from this file" "link"
    And I should see "Course categories (2)"
    And I should see "Courses (2)"
    And I click on "Available for all tenants" "radio"
    And I press "Next"
    And I set the field "select_category" to "cat2"
    And I press "Next"
    And I should see "An instance with the same 'idnumber' already exists"
    And I should see "Courses with the same shortname already exist"
    And I press "Next"
    And I should see "Instances (2)"
    And I should see "cat1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "cat1 / cat11" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button"

  Scenario Outline: Validate the include course content checkbox availability
    When the following config values are set as admin:
      | coursecontentbackup | <configsetting> | tool_wp |
    And I log in as "<user>"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Course categories" "radio"
    And I press "Next"
    And I set the field "Course structure" to "1"
    Then the "Include course content" "checkbox" should be <checkboxstate>
    And I press "Cancel"

    Examples:
      | user         | configsetting | checkboxstate |
      | admin        | 1             | enabled       |
      | admin        | 0             | enabled       |
      | tenantadmin1 | 1             | enabled       |
      | tenantadmin1 | 0             | disabled      |
