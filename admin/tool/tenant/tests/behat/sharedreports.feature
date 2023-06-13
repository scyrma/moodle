@tool @tool_tenant @moodleworkplace @javascript
Feature: Shared space in core reportbuilder
  As a global admin
  I want to be able to create custom reports in the Shared space

  Background:
    Given "2" tenants exist with "4" users and "2" courses in each
    And shared space is enabled

  Scenario: Creating and viewing reports in shared space as administrator
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    # Admin should see users from both tenants.
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should see "Tenantadmin 2"
    And I should see "User 2"
    # Schedules tab should exist in Shared space.
    And I should see "Schedules"
    # Check that tenant filter shows up in Access tab.
    # TODO WP-3693 Access tab in shared reports
#    And I click on the "Access" dynamic tab
#    And I should see "Tenantadmin 1"
#    And I should see "Tenant1" in the "Tenantadmin 1" "table_row"
#    And I should see "Tenantadmin 2"
#    And I click on "Filters" "button"
#    And I set the following fields in the "Full name" "core_reportbuilder > Filter" to these values:
#      | user:tenant_operator | Does not contain |
#      | user:tenant    | User 2           |
#    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
#    And I should see "Tenantadmin 1"
#    And I should not see "Tenantadmin 2"
    And I click on "Close 'SharedReport' editor" "button"
    And I navigate to "Custom reports" in workplace launcher
    And I should see "SharedReport"
    And I should not see "Shared space" in the "SharedReport" "table_row"
    And I open the action menu in "SharedReport" "table_row"
    And I should see "Edit report content"
    And I switch to tenant "Tenant1"
    And I navigate to "Custom reports" in workplace launcher
    And I should see "SharedReport"
    And I should see "Shared space" in the "SharedReport" "table_row"
    And I follow "SharedReport"
    # Admin should see users only from tenant 1.
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should not see "Tenantadmin 2"
    And I should not see "User 2"
    # Check that tenant filter does not show up in Access tab.
    # TODO WP-3693 Access tab in shared reports
#    And I click on the "Access" dynamic tab
#    And I should see "Tenantadmin 1"
#    And I should not see "Tenantadmin 2"
#    And I click on "Filters" "button"
#    And I should not see "Tenant" in the "[data-region='report-filters']" "css_element"
    # TODO WP-3696 "Edit in shared space" button
#    And I press "Edit in Shared space"
#    And I should see "Shared space" in the ".navbar" "css_element"
#    And I log out

  Scenario: Tenant administrator can only see users from their tenant
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Custom reports" in workplace launcher
    And I follow "SharedReport"
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should not see "Tenantadmin 2"
    And I should not see "User 2"
    And I click on "Filters" "button"
    And I should see "Has current jobs" in the "[data-region='report-filters']" "css_element"
    And I should not see "Tenant" in the "[data-region='report-filters']" "css_element"

  Scenario: Admin can filter users on a shared report by their tenant
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I press "Switch to preview mode"
    And I click on "Filters" "button"
    And I set the field "tenant:name_operator" to "Is equal to"
    And I set the field "tenant:name_value" to "Tenant2"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "Tenantadmin 2"
    And I should see "User 21"
    And I should see "User 22"
    And I should see "User 23"
    And I should not see "Tenantadmin 1"
    And I should not see "User 11"
    And I should not see "User 12"
    And I should not see "User 13"
    And I set the field "tenant:name_value" to "Tenant1"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should not see "Tenantadmin 2"
    And I should see "Tenantadmin 1"

  Scenario: Report not shared should not show up in subtenants
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "ReportNotShared"
    And I set the field "Report source" in the "New report" "dialogue" to "Users"
    And I click on "Available in all tenants" "checkbox"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I click on "Close 'ReportNotShared' editor" "button"
    And I switch to tenant "Tenant1"
    And I navigate to "Custom reports" in workplace launcher
    And I should not see "ReportNotShared"
    And I should see "Nothing to display"

#  # TODO WP-3693 Audiences and Access
#  Scenario: Report not shared should not show Audience and Schedule tabs in Shared space
#    When I log in as "admin"
#    And I switch to tenant "Shared space"
#    And I navigate to "Custom reports" in workplace launcher
#    And I press "New report"
#    And I set the following fields in the "New report" "dialogue" to these values:
#      | Report name              | My report  |
#      | Report source            | Users list |
#      | Available in all tenants | 1          |
#    And I click on "Save" "button" in the "New report" "dialogue"
#    Then I should see "Audience"
#    And I should see "Schedule"
#    And I press "Edit details"
#    # Set the report to Not shared.
#    And I set the following fields in the "Edit report 'My report'" "dialogue" to these values:
#      | Available in all tenants | 0 |
#    And I click on "Save" "button" in the "Edit report 'My report'" "dialogue"
#    And I should not see "Audience"
#    And I should not see "Schedule"
#    And I click on the "Access" dynamic tab
#    And I should see "Nothing to display"

  Scenario: Using course participants datasource with courses on different tenants
    Given the following "course enrolments" exist:
      | user    | course   | role            |
      | user11  | C11      | student         |
      | user21  | C21      | student         |
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Name                  | SharedCourseEnrolment |
      | Report source         | Course participants   |
    And I click on "Save" "button" in the "New report" "dialogue"
    When I click on "Show/hide 'Conditions'" "button"
    And I set the field "Select a condition" to "Username"
    And I set the following fields in the "Username" "core_reportbuilder > Condition" to these values:
      | Username operator | Is not empty |
    And I click on "Apply" "button" in the "[data-region='settings-conditions']" "css_element"
    # Admin should see users and courses from both tenants.
    And I should see "Tenant1" in the "Course11" "table_row"
    And I should see "Tenant2" in the "Course21" "table_row"
    And I click on "Switch to preview mode" "button"
    And I click on "Filters" "button" in the "[data-region='core_reportbuilder/report-header']" "css_element"
    And I set the following fields in the "Tenant name" "core_reportbuilder > Filter" to these values:
      | Tenant name operator | Is equal to  |
      | Tenant name value    | Tenant1      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "Course11" in the "reportbuilder-table" "table"
    And I should not see "Course21" in the "reportbuilder-table" "table"
    And I click on "Reset all" "button" in the "[data-region='report-filters']" "css_element"
    And I click on "Close 'SharedCourseEnrolment' editor" "button"
    And I switch to tenant "Tenant1"
    And I navigate to "Custom reports" in workplace launcher
    And I click on "SharedCourseEnrolment" "link" in the "SharedCourseEnrolment" "table_row"
    # Tenantadmin1 should only see users and courses from Tenant1.
    And I should see "Course11" in the "reportbuilder-table" "table"
    And I should not see "Course21" in the "reportbuilder-table" "table"
    And I log out

  # TODO WP-3693 Access
#  Scenario: Manually added users audience type is not shown in Shared Space
#    When I log in as "admin"
#    And I switch to tenant "Shared space"
#    And I navigate to "Custom reports" in workplace launcher
#    And I press "New report"
#    And I set the following fields in the "New report" "dialogue" to these values:
#      | Report name              | ReportShared |
#      | Report source            | Users list   |
#      | Available in all tenants | 1            |
#    And I click on "Save" "button" in the "New report" "dialogue"
#    Then I navigate to "Audience" in current page administration
#    And I should not see "Manually added users"

  Scenario: Configure report audience with All users audience type in a Shared report
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Name                     | ReportShared |
      | Report source            | Users        |
      | Available in all tenants | 1            |
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I click on the "Audience" dynamic tab
    And I click on "Add audience 'All users'" "link"
    And I press "Save changes"
    And I click on the "Access" dynamic tab
    # In shared space Access tab should list users from all tenants set in the audience.
    And I should see "User 11"
    And I should see "User 22"
    # TODO WP-3693 add tenant column to Access page
#    And the following should exist in the "report-table" table:
#      | Full name   | Tenant  |
#      | User 11     | Tenant1 |
#      | User 22     | Tenant2 |
    And I click on "Close 'ReportShared' editor" "button"
    And I switch to tenant "Tenant1"
    And I navigate to "Custom reports" in workplace launcher
    And I follow "ReportShared"
    # TODO WP-3693 Access
#    And I click on the "Access" dynamic tab
#    # In Tenant1 Access tab should list only users set in the audience from Tenant1.
#    And I should see "User 11" in the "report-table" "table"
#    And I should not see "User 22" in the "report-table" "table"
    And I switch to tenant "Tenant2"
    And I navigate to "Custom reports" in workplace launcher
    And I follow "ReportShared"
    # TODO WP-3693 Access
#    And I click on the "Access" dynamic tab
#    # In Tenant2 Access tab should list only users set in the audience from Tenant2.
#    And I should not see "User 11" in the "report-table" "table"
#    And I should see "User 22" in the "report-table" "table"

  Scenario: User set in audience can not access shared report if report is not shared anymore
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Name                     | My Report  |
      | Report source            | Users      |
      | Available in all tenants | 1          |
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I click on the "Audience" dynamic tab
    And I click on "Add audience 'All users'" "link"
    And I press "Save changes"
    And I log out
    And I log in as "user11"
    And I follow "Reports" in the user menu
    And I follow "My Report"
    And I should see "User 12"
    And I should not see "User 21"
    And I log out
    And I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Custom reports" in workplace launcher
    And I press "Edit report details" action in the "My Report" report row
    # Set the report to Not shared.
    And I set the following fields in the "Edit report details" "dialogue" to these values:
      | Available in all tenants | 0 |
    And I click on "Save" "button" in the "Edit report details" "dialogue"
    And I log out
    And I log in as "user11"
    And I follow "Reports" in the user menu
    And I should not see "My Report"

  Scenario: Tenant admin with no extra permissions should not see 'Tenant name' column in course participants datasource
    Given the following "course enrolments" exist:
      | user    | course  | role            |
      | user11  | C11      | student         |
      | user21  | C21      | student         |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "My report"
    And I set the field "Report source" in the "New report" "dialogue" to "Course participants"
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I should not see "Tenant name"

  Scenario: Admin should see 'Tenant name' column in course participants datasource
    Given the following "course enrolments" exist:
      | user    | course  | role            |
      | user11  | C11      | student         |
      | user21  | C21      | student         |
    When I log in as "admin"
    And I change window size to "large"
    And I navigate to "Custom reports" in workplace launcher
    And I press "New report"
    And I set the field "Name" in the "New report" "dialogue" to "My report"
    And I set the field "Report source" in the "New report" "dialogue" to "Course participants"
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I should see "Tenant name"
