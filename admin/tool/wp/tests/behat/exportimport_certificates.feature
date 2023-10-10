@tool @tool_certificate @tool_wp @moodleworkplace @javascript
Feature: Export and import certificates
  As an administrator
  I want to export and import certificates

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each
    And the following certificate templates exist:
      | name          | category  |
      | Certificate 1 | Category1 |
      | Certificate 2 |           |
      | Certificate 3 | Category2 |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |

  Scenario: Export and import certificates as tenant admin
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Certificates" "radio"
    And I press "Next"
    And I click on "Select categories and subcategories manually" "radio"
    And I press "Next"
    And I should see "Select at least one category"
    And I click on "Select certificate templates manually" "radio"
    And I press "Next"
    And I should see "Select at least one template"
    And I click on "Select all certificate templates" "radio"
    And I press "Next"
    And I should see "Certificate 1"
    And I should see "Certificate 2"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    Then the following should exist in the "reportbuilder-table" table:
      | Exporter       | Status    |
      | Certificates   | Scheduled |
    And I run all adhoc tasks
    And I navigate to "Export and import" in workplace launcher
    And I press "New import from this file" action in the "Tenantadmin 1" report row
    And I should see "Certificates (2)"
    And I press "Next"
    And I should see "Instances"
    And I click on "Select certificate templates manually" "radio"
    And I press "Next"
    And I should see "Select at least one template"
    And I open the autocomplete suggestions list
    And I click on "Certificate 1" item in the autocomplete list
    And I press "Next"
    And I should see "Will be imported"
    And I should see "Instances (2)"
    And I should see "Certificate 1"
    And I should see "Certificate issue: User 11"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"

  Scenario Outline: Export all certificates as admin
    When I log in as "admin"
    # Switching to this tenant means "Certificate 3" won't be included when exporting only this tenant.
    And I switch to tenant "Tenant1"
    And I perform a new export with these options:
      | Step 1 | Certificates                     | 1            |
      | Step 1 | Create export from               | <exportfrom> |
      | Step 2 | Select all certificate templates | 1            |
    And I press "View export" action in the "Certificates" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (<instancecount>)"
    And I should see "Certificate 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I should see "Certificate 2" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I <shouldornotsee> see "Certificate 3" in the "[data-region=\"exportimport-instances\"]" "css_element"
    Examples:
      | exportfrom     | instancecount | shouldornotsee |
      | Site           | 3             | should         |
      | Current tenant | 2             | should not     |
