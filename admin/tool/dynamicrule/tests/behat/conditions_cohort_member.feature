@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Dynamicrule conditions cohort_member and cohort_not_member work as expected
  In order to test the dynamicrule outcomes cohort_member and cohort_not_member
  As a tenant admin
  I need to create a rule with cohort member condition check if outcomes work

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each

  Scenario: Add a rule with cohort member condition
    When I log in as "admin"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And "No available cohorts" "icon" should exist
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | Test cohort |
      | Context     | System |
      | Description | Test cohort description |
    And I press "Save changes"
    And I navigate to "Dynamic rules" in site administration
    And I follow "Edit rule 'Rule1'"
    And "No available cohorts" "icon" should not exist
    And I follow "User is member of cohort"
    # Test cohort is the only system cohort available and is already selected when loading autocomplete element.
    And "Test cohort" "autocomplete_selection" should exist
    And I press "Save changes"
    Then I should see "Users who are members of cohort 'Test cohort'"

  Scenario: Add a rule with not a cohort member condition
    When I log in as "admin"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And "No available cohorts" "icon" should exist
    And I navigate to "Users > Accounts > Cohorts" in site administration
    And I follow "Add new cohort"
    And I set the following fields to these values:
      | Name        | Test cohort |
      | Context     | System |
      | Description | Test cohort description |
    And I press "Save changes"
    And I navigate to "Dynamic rules" in site administration
    And I follow "Edit rule 'Rule1'"
    And "No available cohorts" "icon" should not exist
    And I follow "User is not member of cohort"
    # Test cohort is the only system cohort available and is already selected when loading autocomplete element.
    And "Test cohort" "autocomplete_selection" should exist
    And I press "Save changes"
    Then I should see "Users who are not members of cohort 'Test cohort'"
