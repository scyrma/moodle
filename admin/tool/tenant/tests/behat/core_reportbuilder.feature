@tool @tool_tenant @core_reportbuilder @moodleworkplace
Feature: Tenant admin is able to edit report audiences and schedule view as user

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following "tool_tenant > reports" exist:
      | name           | tenant  | source                                   |
      | Report1        | Tenant1 | core_user\reportbuilder\datasource\users |

  Scenario: Capabilities to schedule as a user are available in tenant administrator role
    When I log in as "admin"
    And I navigate to "Users > Permissions > Define roles" in site administration
    And I click on "Edit Tenant administrator role" "link"
    And I should see "moodle/reportbuilder:edit"
    And I should see "moodle/reportbuilder:scheduleviewas"

  @javascript
  Scenario: Tenant admin with capabilities can only see tenant users and roles in audience.
    Given I am on the "Report1" "reportbuilder > Editor" page logged in as "tenantadmin1"
    When I click on the "Audience" dynamic tab
    And I should not see "Site administrators"
    And I click on "Add audience 'Manually added users'" "link"
    And I open the autocomplete suggestions list
    And "Tenantadmin 1" "autocomplete_suggestions" should exist
    And "User 11" "autocomplete_suggestions" should exist
    And "User 12" "autocomplete_suggestions" should exist
    And "Tenantadmin 2" "autocomplete_suggestions" should not exist
    And "User 21" "autocomplete_suggestions" should not exist
    And "User 22" "autocomplete_suggestions" should not exist
    And "Admin User" "autocomplete_suggestions" should not exist

  @javascript
  Scenario: Tenant admin with capabilities can only see tenant users in Schedule.
    Given I am on the "Report1" "reportbuilder > Editor" page logged in as "tenantadmin1"
    When I click on the "Schedules" dynamic tab
    And I press "New schedule"
    And I set the field "View report data as" to "Select user"
    And I open the autocomplete suggestions list
    And "Tenantadmin 1" "autocomplete_suggestions" should exist
    And "User 11" "autocomplete_suggestions" should exist
    And "User 12" "autocomplete_suggestions" should exist
    And "Tenantadmin 2" "autocomplete_suggestions" should not exist
    And "User 21" "autocomplete_suggestions" should not exist
    And "User 22" "autocomplete_suggestions" should not exist
    And "Admin User" "autocomplete_suggestions" should not exist

  @javascript
  Scenario: Tenant admin with capabilities can only see tenant cohort in audience.
    Given the following "cohorts" exist:
      | name                 | idnumber | contextlevel | reference |
      | Cohort in Cat 1      | CHCATA   | Category     | CAT1      |
      | Cohort in Cat 2      | CHCATB   | Category     | CAT2      |
    And I am on the "Report1" "reportbuilder > Editor" page logged in as "tenantadmin1"
    When I click on the "Audience" dynamic tab
    And I click on "Add audience 'Member of cohort'" "link"
    And I open the autocomplete suggestions list
    And "Cohort in Cat 1" "autocomplete_suggestions" should exist
    And "Cohort in Cat 2" "autocomplete_suggestions" should not exist

  @javascript
  Scenario: Site administrator can list any users in shared tenant.
    Given shared space is enabled
    And the following "tool_tenant > reports" exist:
      | name           | tenant  | source                                   |
      | Shared Report  | -       | core_user\reportbuilder\datasource\users |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "Edit report content" action in the "Shared Report" report row
    Then I click on the "Audience" dynamic tab
    And I click on "Add audience 'Manually added users'" "link"
    And I open the autocomplete suggestions list
    And "Tenantadmin 1" "autocomplete_suggestions" should exist
    And "User 11" "autocomplete_suggestions" should exist
    And "User 12" "autocomplete_suggestions" should exist
    And "Tenantadmin 2" "autocomplete_suggestions" should exist
    And "User 21" "autocomplete_suggestions" should exist
    And "User 22" "autocomplete_suggestions" should exist
    And "Admin User" "autocomplete_suggestions" should exist
    Then I click on the "Schedules" dynamic tab
    And I press "Confirm"
    And I press "New schedule"
    And I set the field "View report data as" to "Select user"
    And I open the autocomplete suggestions list
    And "Tenantadmin 1" "autocomplete_suggestions" should exist
    And "User 11" "autocomplete_suggestions" should exist
    And "User 12" "autocomplete_suggestions" should exist
    And "Tenantadmin 2" "autocomplete_suggestions" should exist
    And "User 21" "autocomplete_suggestions" should exist
    And "User 22" "autocomplete_suggestions" should exist
    And "Admin User" "autocomplete_suggestions" should exist
