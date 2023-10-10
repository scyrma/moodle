@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import courses
  As a tenant administrator
  I want to export and import courses

  Background:
    Given "2" tenants exist with "1" users and "2" courses in each

  Scenario: Export all courses
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Courses" "radio"
    And I press "Next"
    And I click on "Select courses manually" "radio"
    And I press "Next"
    And I should see "Select at least one course"
    And I click on "Select categories and subcategories manually" "radio"
    And I click on "Category1" "autocomplete_selection"
    And I press "Next"
    And I should see "Select at least one category"
    And I set the field "Course categories" to "Category1"
    And I press "Next"
    And I should see "Instances (2)"
    And I should see "Course12"
    And I should see "Course11"
    And I press "Export"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I run all adhoc tasks
    And I navigate to "Migration" in workplace launcher
    Then the following should exist in the "reportbuilder-table" table:
      | Exporter      | Status    |
      | Courses       | Success   |
    And I press "View export" action in the "Courses" report row
    Then I should see "Success"
    And I should see "Instances (2)"
    And I should see "Course12"
    And I should see "Course11"

  Scenario Outline: Export courses as admin
    When I log in as "admin"
    # Switching to this tenant means "Category2" won't be included when exporting only this tenant.
    And I switch to tenant "Tenant1"
    And I navigate to "Migration" in workplace launcher
    And I click on "Exports" "link" in the "[role=tablist]" "css_element"
    And I press "Export"
    And I click on "Courses" "radio"
    And I set the field "Create export from" in the "//div[contains(@data-groupname,'courses')]" "xpath_element" to "<exportfrom>"
    And I press "Next"
    And I click on "Select categories and subcategories manually" "radio"
    And I open the autocomplete suggestions list
    And "Category2" "autocomplete_suggestions" <shouldornotexist> exist
    And I press the escape key
    And I press "Cancel"
    Examples:
      | exportfrom     | shouldornotexist |
      | Site           | should           |
      | Current tenant | should not       |

  Scenario: Validate "Include course content" checkbox availability
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Courses" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be enabled
    And I press "Cancel"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Courses" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be disabled
    And I press "Cancel"
    And I log out
    When the following config values are set as admin:
      | coursecontentbackup | 1 | tool_wp |
    And I log in as "tenantadmin1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Courses" "radio"
    And I press "Next"
    Then the "Include course content" "checkbox" should be enabled
    And I press "Cancel"

  @_file_upload
  Scenario: Import a course into current tenant
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/wp/tests/fixtures/courses-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Courses (2)"
    And I press "Next"
    And I should see "Instances"
    And I click on "Select courses manually..." "radio"
    And I press "Next"
    And I should see "Select at least one course"
    And I set the field "Courses" to "Course A"
    And I set the field "Select course category" to "Category1"
    And I press "Next"
    And I should see "Will be imported"
    And I should see "Instances (1)"
    And I should see "Course A"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I run all adhoc tasks
    And I navigate to "Courses" in workplace launcher
    And I should see "Course A"

  @_file_upload
  Scenario: Import a course as admin to any category
    And the following "categories" exist:
      | name  | category | idnumber |
      | catA  | 0        | cata     |
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/wp/tests/fixtures/courses-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Courses (2)"
    And I click on "Available for all tenants" "radio"
    And I press "Next"
    And I should see "Instances"
    And I select "catA" from the "select_category" singleselect
    And I should see "Instances"
    And I click on "Select courses manually..." "radio"
    And I press "Next"
    And I should see "Select at least one course"
    And I set the field "Courses" to "Course A"
    And I should see "catA"
    And I press "Next"
    And I should see "Will be imported"
    And I should see "Instances (1)"
    And I should see "Course A"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I run all adhoc tasks
    And I navigate to "Migration" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "View import" action in the "Courses" report row
    And I should see "Success"
    And I should see "Course A"
    And I navigate to "Courses" in workplace launcher
    And I click on "catA" "link"
    And I should see "Course A"
