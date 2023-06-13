@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import cohorts
  As an administrator
  I want to export and import cohorts

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
      | Cohort in Cat 1      | CHCATA   | Category     | CAT1      |
      | Cohort in Cat 2      | CHCATB   | Category     | CAT2      |
    And the following "cohort members" exist:
      | user     | cohort |
      | user11   | CHS1   |
      | user11   | CHCATA |
      | user12   | CHS1   |
      | user12   | CHCATB |
      | user21   | CHS1   |
      | user22   | CHCATA |

  Scenario: Export and import system cohorts
    When I log in as "admin"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Cohorts" "radio"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one cohort"
    And I click on "All system cohorts" "radio"
    And I press "Next"
    And I should see "System cohort 1"
    And I should not see "Cohort in Cat 1"
    And I should not see "Cohort in Cat 2"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button" in the "region-main" "region"
    Then the following should exist in the "reportbuilder-table" table:
      | Exporter    | Status    |
      | Cohorts     | Scheduled |
    And I run all adhoc tasks
    And I navigate to "Export and import" in workplace launcher
    And I press "New import from this file" action in the "Cohorts" report row
    And I should see "Cohorts (1)"
    And I should see "Cohort members (3)"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one cohort"
    And I click on "Select all cohorts in this file" "radio"
    And I click on "Import all in system context" "radio"
    And I press "Next"
    And I should see "Cohorts with the same idnumber already exist"
    And I press "Next"
    And I should see "Will be imported"
    And I should see "Instances (1)"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button" in the "region-main" "region"

  Scenario Outline: Export all cohorts as admin
    When I log in as "admin"
    # Switching to this tenant means "Cohort in Cat 2" and system cohorts won't be included when exporting only this tenant.
    And I switch to tenant "Tenant1"
    And I navigate to "Migration" in workplace launcher
    And I press "Export"
    And I click on "Cohorts" "radio"
    And I set the field "Create export from" in the "//div[contains(@data-groupname,'cohorts')]" "xpath_element" to "<exportfrom>"
    And I press "Next"
    And I set the field "All cohorts" to "1"
    And I press "Next"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I run all adhoc tasks
    And I click on "Close" "link_or_button" in the "region-main" "region"
    And I press "View export" action in the "Cohorts" report row
    Then I should see "Success" in the "Status" "table_row"
    And I should see "Instances (<instancecount>)"
    And I should see "Cohort in Cat 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I <shouldornotsee> see "Cohort in Cat 2" in the "[data-region=\"exportimport-instances\"]" "css_element"
    And I <shouldornotsee> see "System cohort 1" in the "[data-region=\"exportimport-instances\"]" "css_element"
    Examples:
      | exportfrom     | instancecount | shouldornotsee |
      | Site           | 3             | should         |
      | Current tenant | 1             | should not     |
