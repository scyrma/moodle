@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import course categories
  As a tenant administrator
  I want to export and import course categories

  Background:
    Given "2" tenants exist with "0" users and "1" courses in each
    And the following "categories" exist:
      | name      | category | idnumber | visible |
      | Category3 | CAT1     | CAT3     | 0       |
    And the following "courses" exist:
      | fullname  | shortname | category | visible |
      | Course 3  | C3        | CAT3     | 1       |

  Scenario: Export and import course categories as tenant admin
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Course categories" "radio"
    And I press "Next"
    And "Category1" "autocomplete_selection" should exist
    And I open the autocomplete suggestions list
    And "Category1 / Category3" "autocomplete_suggestions" should exist
    And "Category2" "autocomplete_suggestions" should not exist
    And I press the escape key
    And I press "Next"
    And I should see "About the file"
    And I should see "Instances (2)"
    And I should see "Category1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "Category1 / Category3" in the "[data-region=\"exportimport-instances\"]" "css_element"
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
    And I should see "Category1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "Category1 / Category3" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I navigate to "Export and import" in workplace launcher
    And I click on "New import from this file" "link"
    And I should see "Course categories (2)"
    And I should see "Courses (2)"
    And I press "Next"
    And I set the field "select_category" to "Category1"
    And I press "Next"
    And I should see "An instance with the same 'idnumber' already exists"
    And I should see "Courses with the same shortname already exist"
    And I press "Next"
    And I should see "Instances (2)"
    And I should see "Category1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "Category1 / Category3" in the "[data-region=\"exportimport-instances\"]" "css_element"
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

  Scenario Outline: Export course categories as admin
    When I log in as "admin"
    # Switching to this tenant means "Category2" won't be included when exporting only this tenant.
    And I switch to tenant "Tenant1"
    And I navigate to "Migration" in workplace launcher
    And I click on "Exports" "link" in the "[role=tablist]" "css_element"
    And I press "Export"
    And I click on "Course categories" "radio"
    And I set the field "Create export from" in the "//div[contains(@data-groupname,'coursecategories')]" "xpath_element" to "<exportfrom>"
    And I press "Next"
    And I open the autocomplete suggestions list
    And "Category2" "autocomplete_suggestions" <shouldornotexist> exist
    And I click on "Category1 / Category3" item in the autocomplete list
    And I take focus off "Course categories" "field"
    And I press "Next"
    And I press "Cancel"
    Examples:
      | exportfrom     | shouldornotexist |
      | Site           | should           |
      | Current tenant | should not       |
