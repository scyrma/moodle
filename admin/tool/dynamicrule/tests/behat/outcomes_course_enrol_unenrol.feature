@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Dynamicrule outcomes course_enrol and course_unenrol work as expected
  In order to test the dynamicrule outcomes course_enrol and course_unenrol
  As a manager
  I need to create a rule to allocate one user to a course and check if outcomes work

  Background:
    Given "1" tenants exist with "3" users and "1" courses in each

  Scenario: Outcomes course enrol and course unenrol work as expected
    When I log in as "user11"
    And I change window size to "large"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You can not enrol yourself in this course"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I click on "Enrol users into a course" "link" in the "#ruleoutcomes" "css_element"
    And I press "Save changes"
    # We should get warning because no course has been selected
    Then I should see "You must supply a value here"
    And I click on ".form-autocomplete-downarrow" "css_element"
    Then I click on "Course11" "text" in the ".form-autocomplete-suggestions" "css_element"
    # Check Enable on End date
    And I click on "enddate[enabled]" "checkbox"
    # Check Enable on Duration
    And I click on "duration[enabled]" "checkbox"
    And I set the field "duration[number]" to "1"
    And I press "Save changes"
    # We should get warning because both enddate and duration are enabled
    And I should see "You must choose enrolment end date OR duration, not both"
    And I click on "enddate[enabled]" "checkbox"
    And I click on "duration[enabled]" "checkbox"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    # Check that user11 is enrolled
    And I am on "Course11" course homepage
    And I click on "Participants" "link"
    Then I should see "Participants" in the "#page-content" "css_element"
    And I should see "User 11"
    And I should see "No groups" in the "User 11" "table_row"
    And I should not see "User 12"
    # Enrol User 12 manually
    And I press "Enrol users"
    When I set the field "Select users" to "user12"
    And I should see "User 12"
    And I click on "Enrol users" "button" in the "Enrol users" "dialogue"
    Then I should see "Active" in the "User 12" "table_row"
    # Create a rule to unenrol user with a condition that both user11 and user12 satisfy
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule2 |
    And I press "Save" in the modal form dialogue
    And I should see "Add conditions to this rule"
    And I follow "User enrolled"
    And I should see "Condition is not saved"
    And I click on ".form-autocomplete-downarrow" "css_element"
    Then I click on "Course11" "text" in the ".form-autocomplete-suggestions" "css_element"
    And I click on "timeenrolled[enabled]" "checkbox"
    And I press "Save changes"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I click on "Unenrol users from a course" "link" in the "#ruleoutcomes" "css_element"
    And I click on ".form-autocomplete-downarrow" "css_element"
    Then I click on "Course11" "text" in the ".form-autocomplete-suggestions" "css_element"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    And I log in as "tenantadmin1"
    And I am on "Course11" course homepage
    And I click on "Participants" "link"
    Then I should see "Participants" in the "#page-content" "css_element"
    And I should see "User 11"
    And I should see "Suspended" in the "User 11" "table_row"
    And I should see "User 12"
    And I should not see "Suspended" in the "User 12" "table_row"
    And I should see "Active" in the "User 12" "table_row"
    And I log out