@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import a site
  As a site administration
  I want to export and import a site

  Scenario: Export site
    Given "2" tenants exist with "1" users and "1" courses in each
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I click on "Exports" "link" in the "[role=tablist]" "css_element"
    And I press "Export"
    And I click on "Site" "radio"
    And I press "Next"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Acceptance test site" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View export" action in the "Site" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Acceptance test site" in the "[data-region=\"exportimport-instances\"]" "css_element"

  Scenario: Import an export into the same site
    When I log in as "admin"
    And I perform a new export with these options:
      | Step 1 | Site | 1 |
    And I press "View export" action in the "Site" report row
    And I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Acceptance test site" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "New import from this file"
    And I press "Next"
    And I press "Next"
    Then I should see "Cannot import into the same site the export originated from"
    And I press "Next"
    And I should see "Instances (0)"
    And the "Import" "button" should be disabled
    And I press "Cancel"

  @_file_upload
  Scenario: Import site
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I click on "Import" "link" in the "[role=tablist]" "css_element"
    And I press "Import"
    And I upload "admin/tool/wp/tests/fixtures/site-export.zip" file to "Upload a file" filemanager
    And I press "Next"
    And I should see "Site (1)"
    And I press "Next"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Workplace Docker Demo" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I press "Import"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View import" action in the "Site" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (1)"
    And I should see "Workplace Docker Demo" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I click on "[data-action=\"show-log\"]" "css_element"
    And I should see "Imported site"
