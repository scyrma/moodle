@tool @tool_reportbuilder @moodleworkplace @javascript
Feature: Shared space in reportbuilder
  As a global admin
  I want to be able to create custom reports in the Shared space

  Background:
    Given "2" tenants exist with "4" users and "2" courses in each
    And shared space is enabled

  Scenario: Creating and viewing reports in shared space as administrator
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users list"
    And I click on "Save" "button" in the "New report" "dialogue"
    # Admin should see users from both tenants.
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should see "Tenantadmin 2"
    And I should see "User 2"
    # Schedules tab should exist in Shared space.
    And I should see "Schedules"
    # Check that tenant filter shows up in Access tab.
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    And I should see "Tenantadmin 1"
    And I should see "Tenant1" in the "Tenantadmin 1" "table_row"
    And I should see "Tenantadmin 2"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "user:tenant_op" to "is equal to"
    And I set the field "user:tenant" to "Tenant1"
    And I should see "Tenantadmin 1"
    And I should not see "Tenantadmin 2"
    And I navigate to "Report builder" in workplace launcher
    And I should see "SharedReport"
    And I should not see "Shared space" in the "SharedReport" "table_row"
    And "Edit content" "link" should exist in the "SharedReport" "table_row"
    And I switch to tenant "Tenant1"
    And I should see "SharedReport"
    And I should see "Shared space" in the "SharedReport" "table_row"
    And I click on "Edit content" "link" in the "SharedReport" "table_row"
    # Admin should see users only from tenant 1.
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should not see "Tenantadmin 2"
    And I should not see "User 2"
    # Check that tenant filter does not show up in Access tab.
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    And I should see "Tenantadmin 1"
    And I should not see "Tenantadmin 2"
    And I click on "Show/hide filters sidebar" "button"
    And I should not see "Tenant" in the ".filters-list" "css_element"
    And I press "Edit in Shared space"
    And I should see "Shared space" in the ".navbar" "css_element"
    And I log out

  Scenario: Tenant administrator can only see users from their tenant
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users list"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Preview" "link" in the "SharedReport" "table_row"
    And I should see "Tenantadmin 1"
    And I should see "User 1"
    And I should not see "Tenantadmin 2"
    And I should not see "User 2"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Has current jobs" in the ".filters-list" "css_element"
    And I should not see "Tenant" in the ".filters-list" "css_element"

  Scenario: Admin can filter users on a shared report by their tenant
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedReport"
    And I set the field "Report source" in the "New report" "dialogue" to "Users list"
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "user:tenant_op" to "is equal to"
    And I set the field "user:tenant" to "Tenant2"
    And I should see "Tenantadmin 2"
    And I should see "User 21"
    And I should see "User 22"
    And I should see "User 23"
    And I should not see "Tenantadmin 1"
    And I should not see "User 11"
    And I should not see "User 12"
    And I should not see "User 13"
    And I set the field "user:tenant" to "Tenant1"
    And I should not see "Tenantadmin 2"
    And I should see "Tenantadmin 1"

  Scenario: Report not shared should not show up in subtenants
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "ReportNotShared"
    And I set the field "Report source" in the "New report" "dialogue" to "Users list"
    And I click on "Available in all tenants" "checkbox"
    And I click on "Save" "button" in the "New report" "dialogue"
    And I switch to tenant "Tenant1"
    And I navigate to "Report builder" in workplace launcher
    And I should not see "ReportNotShared"
    And I should see "Nothing to display"

  Scenario: Report not shared should not show Audience and Schedule tabs in Shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Report name              | My report  |
      | Report source            | Users list |
      | Available in all tenants | 1          |
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I should see "Audience"
    And I should see "Schedule"
    And I press "Edit details"
    # Set the report to Not shared.
    And I set the following fields in the "Edit report 'My report'" "dialogue" to these values:
      | Available in all tenants | 0 |
    And I click on "Save" "button" in the "Edit report 'My report'" "dialogue"
    And I should not see "Audience"
    And I should not see "Schedule"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    And I should see "Nothing to display"

  Scenario: Using course participants datasource with courses on different tenants
    Given the following "course enrolments" exist:
      | user    | course  | role            |
      | user11  | C11      | student         |
      | user21  | C21      | student         |
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedCourseEnrolment"
    And I set the field "Report source" in the "New report" "dialogue" to "Course participants"
    And I click on "Save" "button" in the "New report" "dialogue"
    # Admin should see users and courses from both tenants.
    And I should see "Course11" in the "report-table" "table"
    And I should see "Tenant1" in the "Course11" "table_row"
    And I should see "Course21" in the "report-table" "table"
    And I should see "Tenant2" in the "Course21" "table_row"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "user:tenant_op" to "is equal to"
    And I set the field "user:tenant" to "Tenant1"
    And I should see "Course11" in the "report-table" "table"
    And I should not see "Course21" in the "report-table" "table"
    And I press "Reset table"
    And I switch to tenant "Tenant1"
    And I click on "Edit content" "link" in the "SharedCourseEnrolment" "table_row"
    # Tenantadmin1 should only see users and courses from Tenant1.
    And I should see "Course11" in the "report-table" "table"
    And I should not see "Course21" in the "report-table" "table"
    And I log out

  Scenario: Manually added users audience type is not shown in Shared Space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Report name              | ReportShared |
      | Report source            | Users list   |
      | Available in all tenants | 1            |
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I should not see "Manually added users"

  Scenario: Configure report audience with All users audience type in a Shared report
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Report name              | ReportShared |
      | Report source            | Users list   |
      | Available in all tenants | 1            |
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "All users" "link" in the "#audiences-menu" "css_element"
    And I press "Save changes"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    # In shared space Access tab should list users from all tenants set in the audience.
    And the following should exist in the "report-table" table:
      | Full name   | Tenant  |
      | User 11     | Tenant1 |
      | User 22     | Tenant2 |
    And I switch to tenant "Tenant1"
    And I click on "Edit content" "link" in the "ReportShared" "table_row"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    # In Tenant1 Access tab should list only users set in the audience from Tenant1.
    And I should see "User 11" in the "report-table" "table"
    And I should not see "User 22" in the "report-table" "table"
    And I switch to tenant "Tenant2"
    And I click on "Edit content" "link" in the "ReportShared" "table_row"
    And I click on "Access" "link" in the "[role=tablist]" "css_element"
    # In Tenant2 Access tab should list only users set in the audience from Tenant2.
    And I should not see "User 11" in the "report-table" "table"
    And I should see "User 22" in the "report-table" "table"

  Scenario: User set in audience can not access shared report if report is not shared anymore
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the following fields in the "New report" "dialogue" to these values:
      | Report name              | My Report  |
      | Report source            | Users list |
      | Available in all tenants | 1          |
    And I click on "Save" "button" in the "New report" "dialogue"
    Then I click on "Audience" "link" in the "[role=tablist]" "css_element"
    And I click on "All users" "link" in the "#audiences-menu" "css_element"
    And I press "Save changes"
    And I log out
    And I log in as "user11"
    And I navigate to "Custom reports" in workplace launcher
    And I should see "My Report"
    And I log out
    And I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit details" "link" in the "My Report" "table_row"
    # Set the report to Not shared.
    And I set the following fields in the "Edit report 'My Report'" "dialogue" to these values:
      | Available in all tenants | 0 |
    And I click on "Save" "button" in the "Edit report 'My Report'" "dialogue"
    And I log out
    And I log in as "user11"
    And "#workplace-menulink" "css_element" should not exist
