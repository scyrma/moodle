@tool @tool_dynamicrule @enrol_dynamicrule @moodleworkplace @javascript
Feature: Dynamicrule outcomes course_enrol and course_unenrol work as expected
  In order to test the dynamicrule outcomes course_enrol and course_unenrol
  As a tenant admin
  I need to create a rule to allocate one user to a course and check if outcomes work

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each

  Scenario: Outcomes course enrol and course unenrol work as expected
    When I log in as "user11"
    And I change window size to "large"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You cannot enrol yourself in this course"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I follow "Enrol users into a course"
    And I press "Save changes"
    # We should get warning because no course has been selected
    Then I should see "You must supply a value here"
    And I set the field "Course" to "Course11"
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
    And I press "Save"
    And I should see "Add conditions to this rule"
    And I follow "User enrolled"
    And I should see "Condition is not saved"
    And I set the field "Course" to "Course11"
    And I click on "timeenrolled[enabled]" "checkbox"
    And I press "Save changes"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I follow "Unenrol users from a course"
    And I set the field "Course" to "Course11"
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

  Scenario: Tenant users are enrolled correctly using dynamicrule enrol method
    Given the following "categories" exist:
      | name | category | idnumber |
      | Cat 1 | 0 | c1 |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course A | CourseA | c1 |
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
    When I log in as "admin"
    And I am on "Course A" course homepage
    And I click on "Participants" "link"
    # Enrol User 11 manually
    And I press "Enrol users"
    When I set the field "Select users" to "user11"
    And I should see "User 11"
    And I click on "Enrol users" "button" in the "Enrol users" "dialogue"
    Then I should see "1 participants found"
    And I should see "Active" in the "User 11" "table_row"
    # Change tenant category to "no category"
    And I navigate to "All tenants" in workplace launcher
    And I follow "Edit tenant 'Tenant1'"
    And I set the following fields to these values:
      | No category | 1 |
    And I press "Save"
    # Switch tenant
    Then I click on ".tenantswitch .dropdown-toggle" "css_element"
    And I click on "Tenant1" "link" in the ".tenantswitch .dropdown-menu.show" "css_element"
    # Create dynamic rule
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User not enrolled"
    And I set the field "Course" to "Course A"
    And I press "Save changes"
    And I should see "Users who are not enrolled in course 'Course A'"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course A"
    And I press "Save changes"
    # Enable rule
    And I click on "Enable" "button"
    Then I should see "Conditions will be locked when at least one user is affected by this rule. Are you sure you want to enable this rule?" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    # Check that users were enrolled.
    And I log in as "admin"
    And I am on "Course A" course homepage
    And I click on "Participants" "link"
    Then I should see "4 participants found"
    And I should see "Active" in the "User 11" "table_row"
    And I should see "Active" in the "User 12" "table_row"
    And I should see "Active" in the "User 13" "table_row"
    And I should see "Active" in the "Tenantadmin 1" "table_row"
    Then "Manual enrolments" "icon" should exist in the "User 11" "table_row"
    And I log out
    # Add one more user to tenant.
    And the following users allocations to tenants exist:
      | user  | tenant  |
      | user1 | Tenant1 |
    And I run the scheduled task "tool_dynamicrule\task\process_rules"
    # Check that extra user was enrolled.
    And I log in as "admin"
    And I am on "Course A" course homepage
    And I click on "Participants" "link"
    Then I should see "5 participants found"
    And I should see "Active" in the "User 1" "table_row"
    And I should see "Active" in the "User 11" "table_row"
    And I should see "Active" in the "User 12" "table_row"
    And I should see "Active" in the "User 13" "table_row"
    And I should see "Active" in the "Tenantadmin 1" "table_row"
    Then "Manual enrolments" "icon" should exist in the "User 11" "table_row"
    And I log out

  Scenario: User can access course if has been enroled using dynamicrule enrol method
    # User11 cannot access directly the course but can access it from the dashboard.
    When I log in as "user11"
    And I change window size to "large"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You cannot enrol yourself in this course"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I should see "0 total matches"
    And I should see "Add conditions to this rule"
    And I follow "User last login"
    And I should see "Condition is not saved"
    And I set the field "lastlogintype" to "Ever"
    And I press "Save changes"
    And I should not see "Add conditions to this rule"
    And I follow "Actions"
    Then I should see "Add actions to this rule"
    And I follow "Enrol users into a course"
    And I set the field "Course" to "Course11"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    And I log in as "user11"
    And I am on "Course11" course homepage
    Then I should see "Course11"
    And I should not see "You cannot enrol yourself in this course"
    And I should see "Topic 1"
    And I should see "Topic 2"
    And I log out
    And I log in as "tenantadmin1"
    And I am on "Course11" course homepage
    And I click on "Participants" "link"
    Then I should see "Participants" in the "#page-content" "css_element"
    And I click on "#region-main-settings-menu [role=button]" "css_element"
    And I choose "Enrolment methods" in the open action menu
    And I should see "Enrolment methods"
    And I should see "Dynamic rules (Rule1)"
    And "Edit" "icon" should not exist in the "Dynamic rules" "table_row"
    And "Delete" "icon" should not exist in the "Dynamic rules" "table_row"
    And I click on "Participants" "link"
    And I should see "User 11"
    And "Edit" "icon" should not exist in the "User 11" "table_row"
    And "Delete" "icon" should not exist in the "User 11" "table_row"
    And "Enable" "icon" should not exist in the "User 11" "table_row"
    And I log out
    Then I log in as "user12"
    And I am on "Course11" course homepage
    And I should see "Course11"
    And I should see "You cannot enrol yourself in this course"
    And I log out
    And I log in as "tenantadmin1"
    When I backup "Course11" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name | Course11 restored in a new course |
    Then I should see "Course11 restored in a new course"
    And I click on "Participants" "link"
    And I should see "User 11"
    And I navigate to "Enrolment methods" in current page administration
    And I should see "Enrolment methods"
    And I should not see "Dynamic rules"
    And I should see "2" in the "Manual enrolments" "table_row"
    And I log out
