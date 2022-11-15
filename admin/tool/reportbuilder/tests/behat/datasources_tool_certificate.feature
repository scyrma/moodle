@tool @tool_reportbuilder @moodleworkplace @javascript
Feature: Check datasources for certificate
  In order to check datasources
  As a manager
  I need be able to create reports with them

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following certificate templates exist:
      | name          | category   |
      | Certificate 1 | Category1 |
      | Certificate 2 | Category2 |
    And the following certificate issues exist:
      | template      | user   |
      | Certificate 1 | user11 |
      | Certificate 1 | user12 |
      | Certificate 2 | user21 |

  Scenario: Create a report about certificates templates
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                                                                              |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_templates |
      | Report2 | Tenant2 | tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_templates |
    When I log in as "tenantadmin1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    Then I should see "Certificate"
    And I should see "Certificate template" in the "#entity_tool_certificate_template" "css_element"
    And I should see "Number of pages" in the "#entity_tool_certificate_template" "css_element"
    And I should see "Time created" in the "#entity_tool_certificate_template" "css_element"
    And I should see "Certificate: Certificate template"
    And I should see "Certificate: Time created"
    And I should see "Certificate 1"
    And I should not see "Certificate 2"
    And I log out
    When I log in as "tenantadmin2"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report2" "table_row"
    And I should see "Certificate 2"
    And I should not see "Certificate 1"

  Scenario: Create a report about certificates issues
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source                                                                           |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_tool_certificate_issues |
    When I log in as "tenantadmin1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    Then I should see "Certificate issue"
    And I should see "Code" in the "#entity_tool_certificate_issue" "css_element"
    And I should see "Time created" in the "#entity_tool_certificate_issue" "css_element"
    And I should see "Expiry date" in the "#entity_tool_certificate_issue" "css_element"
    And I should see "Certificate: Certificate template"
    And I should see "User: Full name with profile link"
    And I should see "Certificate issue: Time created"
    And I should see "Certificate issue: Expiry date"
    And I should see "Certificate issue: Code"
    And I should see "Certificate 1"
    And I should not see "Certificate 2"
    And I should see "User 11"
    And I should see "User 12"
    And I should not see "User 21"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand conditions" "button"
    And I set the field "addconditonselect" to "Expiry date"
    And I set the field "tool_certificate_issue:expires_op" to "Is not empty"
    And I should not see "User 11"
    And I should not see "User 12"

  Scenario: Using Certificates issues datasource with certificate issues on different tenants
    Given shared space is enabled
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedCertificateIssues"
    And I set the field "Report source" in the "New report" "dialogue" to "Certificates issues"
    And I click on "Save" "button" in the "New report" "dialogue"
    # Admin should see issues from both tenants.
    And I should see "Certificate 1" in the "report-table" "table"
    And I should see "Tenant1" in the "Certificate 1" "table_row"
    And I should see "Certificate 2" in the "report-table" "table"
    And I should see "Tenant2" in the "Certificate 2" "table_row"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "user:tenant_op" to "is equal to"
    And I set the field "user:tenant" to "Tenant1"
    And I should see "Certificate 1" in the "report-table" "table"
    And I should not see "Certificate 2" in the "report-table" "table"
    And I press "Reset table"
    And I switch to tenant "Tenant1"
    And I click on "Edit content" "link" in the "SharedCertificateIssues" "table_row"
    # Tenantadmin1 should only see issues from Tenant1.
    And I should see "Certificate 1" in the "report-table" "table"
    And I should not see "Certificate 2" in the "report-table" "table"
    And I log out
