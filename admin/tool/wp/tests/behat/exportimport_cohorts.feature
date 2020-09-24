@tool @tool_wp @moodleworkplace @javascript
Feature: Export and import cohorts
  As an administrator
  I want to export and import cohorts

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CATA     |
      | Cat 2 | 0        | CATB     |
    And the following "users" exist:
      | username | firstname | lastname | email              |
      | user1    | First     | User     | first@example.com  |
      | user2    | Second    | User     | second@example.com |
      | user3    | Third     | User     | third@example.com  |
      | user4    | Fourth    | User     | fourth@example.com  |
    And the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | System cohort 1      | CHS1     | System       |           |
      | System cohort 2      | CHS2     | System       |           |
      | Cohort in Cat 1      | CHCATA   | Category     | CATA      |
      | Cohort in Cat 2      | CHCATB   | Category     | CATB      |
    And the following "cohort members" exist:
      | user     | cohort |
      | user1   | CHS1   |
      | user1   | CHCATA |
      | user2   | CHS1   |
      | user2   | CHCATB |
      | user3   | CHS1   |
      | user4   | CHCATA |
      | user4   | CHS2   |

  Scenario: Export and import system cohorts
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Cohorts" "radio"
    And I press "Next"
    And I click on "Select manually..." "radio"
    And I press "Next"
    And I should see "Select at least one cohort"
    And I click on "All system cohorts" "radio"
    And I press "Next"
    And I should see "System cohort 1"
    And I should see "System cohort 2"
    And I should not see "Cohort in Cat 1"
    And I should not see "Cohort in Cat 2"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button"
    Then the following should exist in the "report-table" table:
      | Exporter    | Status    |
      | Cohorts     | Scheduled |
    And I run all adhoc tasks
    And I navigate to "Export and import" in workplace launcher
    And I click on "New import from this file" "link"
    And I should see "Cohorts (2)"
    And I should see "Cohort members (4)"
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
    And I should see "Instances (2)"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button"
