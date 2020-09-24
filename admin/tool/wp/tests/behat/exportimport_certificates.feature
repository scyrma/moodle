@tool @tool_certificate @tool_wp @moodleworkplace @javascript
Feature: Export and import certificates
  As an administrator
  I want to export and import certificates

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each
    And the following certificate templates exist:
      | name          | category  |
      | Certificate 0 |           |
      | Certificate 1 | Category1 |
      | Certificate 2 |           |
    Given the following certificate issues exist:
      | template      | user   |
      | Certificate 0 | user11 |

  Scenario: Export and import certificates
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
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
    And I should see "Certificate 0"
    And I should see "Certificate 1"
    And I should see "Certificate 2"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button"
    Then the following should exist in the "report-table" table:
      | Exporter       | Status    |
      | Certificates   | Scheduled |
    And I run all adhoc tasks
    And I navigate to "Export and import" in workplace launcher
    And I click on "New import from this file" "link"
    And I should see "Certificates (3)"
    And I press "Next"
    And I should see "Instances"
    And I click on "Select certificate templates manually" "radio"
    And I press "Next"
    And I should see "Select at least one template"
    And I open the autocomplete suggestions list
    And I click on "Certificate 0" item in the autocomplete list
    And I press "Next"
    And I should see "Will be imported"
    And I should see "Instances (2)"
    And I should see "Certificate 0"
    And I should see "Certificate issue: User 11"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button"
