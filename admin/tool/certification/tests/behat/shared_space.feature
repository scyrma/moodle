@tool @tool_certification @moodleworkplace @javascript
Feature: It is possible to create certifications in shared space
  In order to ensure certifications in shared space work
  As a manager and admin
  I need to be able to create, edit and view shared certifications

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each
    And shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname | idnumber | archived | tenant  |
      | Program0 | prog0    | 0        | -       |
      | Archived0 | arch0   | 1        | -       |
      | Program1 | prog1    | 0        | Tenant1 |
      | Program2 | prog2    | 0        | Tenant2 |
      | Program3 | prog3    | 0        | -       |
    Given the following "tool_certification > certifications" exist:
      | fullname        | idnumber | archived | tenant  | program   |
      | Certification0  | cert0    | 0        | -       | Program0  |
      | Archived0       | arch0    | 1        | -       | Archived0 |
      | Certification1  | cert1    | 0        | Tenant1 | Program1  |
      | Certification2  | cert2    | 0        | Tenant2 | Program2  |
      | Certification3  | cert3    | 0        | -       | Program3  |

  # 0. When user is allocated to a shared certification, they can see the certification programs on their dashboard
  # and enrol in courses.
  Scenario: Viewing shared certification programs as a user and accessing its course
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification1 | user11  |
    And the following "courses" exist:
      | fullname     | shortname | format |
      | Sharedcourse | SC        | topics |
    And the following "tool_program > program_courses" exist:
      | program  | course |
      | Program0 | SC     |
    And I log in as "user11"
    And I am on homepage
    And I should see "Program0"
    And I should see "Program1"
    And I press "Enrol" for the "Sharedcourse" program course
    And I should see "My courses"
    And I should see "Topic 1"
    And I should see "Sharedcourse"
    And I log out

  #  1. Admin can create certification in shared space, it appears in the list of the certifications without badges,
  #  all editing options are available. When admin switches to another tenant they can see this certification with a badge.
  #  They don't see editing icons next to it and the certification content is not editable.
  #  There is a button"Edit in Shared space" that takes to shared space
  Scenario: Admin can create certification in shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "New certification" "link"
    # Basic details.
    Then I should see "New certification"
    And I set the following fields to these values:
      | Certification full name | Shared certification 1 |
      | Certification ID number | shared1                |
      | Certification tags      | Tag example 1          |
    And I set the field "Select program" to "Program0"
    And I press "Save"
    # Warning message about users should appear when viewing in edit mode.
    And I should see "Users who are currently taking this program will not be reallocated automatically"
    And I click on "Recertification" "tool_wp > Tab"
    # Warning message about Expiry date set to never should appear when viewing in edit mode.
    And I should see "The initial certification is set to never expire"
    And I navigate to "Certifications" in workplace launcher
    And I should not see "Shared space" in the "Shared certification 1" "table_row"
    # Check that all editing options are available.
    And "Edit content" "link" should exist in the "Shared certification 1" "table_row"
    And "Edit details" "link" should exist in the "Shared certification 1" "table_row"
    And "Duplicate" "link" should exist in the "Shared certification 1" "table_row"
    And "Users" "link" should exist in the "Shared certification 1" "table_row"
    And "Progress report" "link" should exist in the "Shared certification 1" "table_row"
    And "Archive" "link" should exist in the "Shared certification 1" "table_row"
    And I switch to tenant "Tenant1"
    And I should see "Shared space" in the "Shared certification 1" "table_row"
    And "Edit content" "link" should not exist in the "Shared certification 1" "table_row"
    And "Edit details" "link" should not exist in the "Shared certification 1" "table_row"
    And "Duplicate" "link" should exist in the "Shared certification 1" "table_row"
    And "Progress report" "link" should exist in the "Shared certification 1" "table_row"
    And "Archive" "link" should not exist in the "Shared certification 1" "table_row"
    And I click on "Users" "link" in the "Shared certification 1" "table_row"
    Then I click on "Edit in Shared space" "button"
    And I should see "Edit detail"
    And I log out

  # 2. Given shared certification exists. Tenant administrator can see this certification in the certifications list
  # (with shared badge) but the certification is not editable. There is no link to edit in shared space.
  # Tenant administrator can duplicate this certification and the copy will appear in this tenant's certifications list,
  # it will be editable. Tenant administrator can see archived shared certifications.
  Scenario: Viewing and editing certifications in shared space as tenant administrator
    When I log in as "tenantadmin1"
    And I navigate to "Certifications" in workplace launcher
    And I should see "Shared space" in the "Certification0" "table_row"
    And I should not see "Shared space" in the "Certification1" "table_row"
    And "Edit content" "link" should not exist in the "Certification0" "table_row"
    And "Edit content" "link" should exist in the "Certification1" "table_row"
    And I follow "Certification0"
    And I should not see "Edit details"
    And I should not see "Edit in shared space"
    And I navigate to "Certifications" in workplace launcher
    And I click on "Duplicate" "link" in the "Certification0" "table_row"
    Then I click on "OK" "button" in the ".modal-dialog" "css_element"
    Then I press "Save"
    And I navigate to "Certifications" in workplace launcher
    And I should not see "Shared space" in the "Certification0 (copy)" "table_row"
    And "Edit content" "link" should exist in the "Certification0 (copy)" "table_row"
    Then I click on "Archived" "link"
    And I should see "Archived0"
    And I log out

  #  3. Given shared certification exists. While in the shared space admin can allocate users from any tenant.
  #  When admin switches to a tenant they can only allocate users from this tenant.
  Scenario: While in shared space admin can allocate users to certifications from any tenant. In a tenant only users from this tenant.
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    Then I click on "Allocate users" "link"
    And I set the field "Select users" to "User 11,User 21"
    Then I press "Save changes"
    Then I should see "Users"
    And I should see "User 11"
    And I should see "User 21"
    And I switch to tenant "Tenant1"
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "User 11"
    And I should not see "User 21"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 22" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes"
    And I switch to tenant "Tenant2"
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "User 21"
    And I should not see "User 11"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 12" "autocomplete_suggestions" should not exist
    And I click on "User 22" item in the autocomplete list
    Then I press "Save changes"
    And I log out

  # 4. Given shared certification exists and some users are allocated (from multiple tenants). Tenant administrator can
  # allocate users from their tenant (only) to this certification and they can not see users from other tenants in the
  # allocated users view.
  Scenario: Tenant administrator can allocate users from their tenant (only) to this certification
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
    When I log in as "tenantadmin1"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 22" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 11"
    And I should see "User 12"
    And I should not see "User 21"
    And I log out

  # 5. Given shared certification exists and some users are allocated (from multiple tenants). In the shared space
  # admin can see the "Tenant" column in the certification user list and no Tenant column while in the tenant.
  # In the shared space admin can also filter users by tenant.
  Scenario: In the shared space admin can see the "Tenant" column in the certification user list
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "Tenant1" in the "User 11" "table_row"
    And I should see "Tenant2" in the "User 21" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "user:tenant_op" to "is equal to"
    And I set the field "user:tenant" to "Tenant2"
    And I should not see "User 11"
    And I should see "User 21"
    And I switch to tenant "Tenant1"
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should not see "Tenant1" in the "User 11" "table_row"
    And I should not see "User 21"
    And I log out

  # 6. Given shared certification exists and some users are allocated (from multiple tenants). In the shared space
  # admin can edit any user allocation. Tenant administrator can edit users allocations in their tenant.
  Scenario: In the shared space admin can edit any user allocation to the certification, tenant admin only in their tenant.
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    Then I click on "Edit" "link" in the "User 11" "table_row"
    And I set the field "Status" to "Suspended"
    And I press "Save changes"
    Then I click on "Edit" "link" in the "User 21" "table_row"
    And I set the field "Status" to "Suspended"
    And I press "Save changes"
    And I log out
    Then I log in as "tenantadmin1"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    Then I click on "Edit" "link" in the "User 11" "table_row"
    And I set the field "Status" to "Default"
    And I press "Save changes"
    And I log out

  # 7. Given shared certification exists and there is a manger over some users. These users are allocated to shared
  # certifications. Manager can see all the certifications and certification progress of their team on the dashboard.
  Scenario: Manager can see all the certifications and certification progress of their team on the dashboard
    Given user "user13" has a manager position over users "user11,user12" with permissions "3"
    And the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
    When I log in as "user13"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    Then I click on "Edit" "link" in the "User 11" "table_row"
    And I set the following fields to these values:
      | duedatetype         | Select date |
      | duedate[day]        | 3           |
      | duedate[month]      | March       |
      | duedate[year]       | 2020        |
    And I press "Save changes"
    Then I follow "Dashboard"
    And I press "User 11"
    And I follow "1 overdue programs"
    And I should see "Overdue" in the "Program0" "table_row"
    And I click on "Progress overview" "link" in the "Program0" "table_row"
    And I should see "Complete all in order"
    And I should see "Certification0"
    And I press "Close"
    And I click on "Progress report" "link" in the "Program0" "table_row"
    And "Program0 (Base set)" "text" should exist in the "Set" "table_row"
    And I follow "Dashboard"
    And I click on "Overdue programs" "link"
    And "User 11" "link" should exist in the "Program0" "table_row"
    And I log out

  # 8. Organisation manager can allocate their team members to the shared certifications and edit their allocations
  Scenario: Organisation manager can allocate their team members to the shared certifications and edit their allocations
    Given user "user13" has a manager position over users "user11,user12" with permissions "3"
    And the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
    When I log in as "user13"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I should see "User 11"
    And I should not see "User 21"
    And I should not see "Tenant"
    And I press "Allocate users"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 21" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 11"
    And I should see "User 12"
    And I click on "Edit" "link" in the "User 11" "table_row"
    And I select "Suspended" from the "Status" singleselect
    Then I press "Save changes"
    And I should see "Suspended" in the "User 11" "table_row"
    And I click on "Certification" "tool_wp > Tab"
    # Warning message about users should not appear when viewing in edit mode.
    And I should not see "Users who are currently taking this program will not be reallocated automatically"
    And I click on "Recertification" "tool_wp > Tab"
    # Warning message about Expiry date set to never should not appear when viewing in read only mode.
    And I should not see "The initial certification is set to never expire"
    And I log out

  # 9. Tenant administrator can see "User progress report" on the shared certification (linked from the user
  # certification allocation)
  Scenario: Tenant administrator can see "User progress report" on the shared certification
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
    When I log in as "tenantadmin1"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Users" "link" in the "Certification0" "table_row"
    And I click on "Progress report" "link" in the "User 11" "table_row"
    And I should see "Program0"
    And I log out

  # 12. Tenant administrator can create certifications that link to the shared programs.
  Scenario: Tenant administrator can create certifications that link to the shared programs
    When I log in as "tenantadmin1"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "New certification" "link"
    And I set the following fields to these values:
      | Certification full name | Certification 1 |
      | Certification tags      | Tag example 1   |
    And I set the field "Select program" to "Program0"
    And I press "Save"
    And I should see "Certification 1"
    And I log out

  # 17. Tenant administrator can export shared certifications from parent tenants but it would only export the allocated
  # users from the current tenant.
  Scenario: Export one shared certification with users
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
      | Certification0 | user22  |
    When I log in as "tenantadmin1"
    And I navigate to "Export and import" in workplace launcher
    And I press "Export"
    And I click on "Certifications" "radio"
    And I press "Next"
    And I click on "Certification user allocations" "checkbox"
    And I click on "Select manually..." "radio"
    And I set the following fields to these values:
      | Certifications | Certification0 |
    And I click on "Include shared entities" "checkbox"
    And I press "Next"
    And I should see "Instances (1)"
    And I should see "Certification0"
    And I press "Export"
    And I click on "Proceed" "button" in the "Confirm" "dialogue"
    And I should see "Scheduled" in the "Status" "table_row"
    And I click on "Close" "link_or_button"
    Then the following should exist in the "report-table" table:
      | Exporter       | Status    |
      | Certifications | Scheduled |
    And I run all adhoc tasks
    And I navigate to "Export and import" in workplace launcher
    And I click on "New import from this file" "link"
    And I should see "Certifications (1)"
    # Only the user from this tenant has been exported.
    And I should see "Certification user allocations (1)"
    And I press "Next"
    And I press "Next"
    And I should see "Will be imported"
    And I should see "Instances (1)"
    And I should see "Certification0"
    And I press "Import"
    And I press "Proceed"
    And I click on "Close" "link_or_button"
    And I log out

  Scenario: Site admin can configure dynamic rules in shared certifications
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user12  |
      | Certification0 | user13  |
      | Certification3 | user13  |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Edit content" "link" in the "Certification0" "table_row"
    And I click on "Dynamic rules" "tool_wp > Tab"
    And I click on "Edit actions of rule 'Users allocated to certification'" "link"
    And I click on "Allocate users to certifications" "link" in the "#outcomes-menu" "css_element"
    And I expand the "Certification" autocomplete
    And "Certification0" "autocomplete_suggestions" should exist
    And "Certification1" "autocomplete_suggestions" should not exist
    And "Certification2" "autocomplete_suggestions" should not exist
    And "Certification3" "autocomplete_suggestions" should exist
    And I click on "Certification3" item in the autocomplete list
    And I press "Save changes"
    And I click on "Close" "button" in the ".modal-dialog" "css_element"
    And I click on "Enable rule" "link" in the "Users allocated to certification 'Certification0'" "table_row"
    And I should see "Are you sure you want to enable this rule? Enabling it will affect 2 users" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Edit content" "link" in the "Certification0" "table_row"
    And I click on "User" "tool_wp > Tab"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And I click on "User 21" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 21"
    And I log out
    And I run the scheduled task "tool_dynamicrule\task\process_rules"
    And I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Certifications" in workplace launcher
    Then I click on "Edit content" "link" in the "Certification3" "table_row"
    And I click on "User" "tool_wp > Tab"
    And I should see "Dynamic" in the "User 12" "table_row"
    And I should see "Dynamic" in the "User 21" "table_row"
    And I should not see "Dynamic" in the "User 13" "table_row"

  Scenario: Using Certifications datasource with certifications on different tenants
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedCertifications"
    And I set the field "Report source" in the "New report" "dialogue" to "Certifications"
    And I press "Save"
    # Admin should see certifications from both tenants.
    And I should see "Certification0" in the "report-table" "table"
    And I should see "Certification1" in the "report-table" "table"
    And I should see "Tenant1" in the "Certification1" "table_row"
    And I should see "Certification2" in the "report-table" "table"
    And I should see "Tenant2" in the "Certification2" "table_row"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "tool_certification:tenant_op" to "is equal to"
    And I set the field "tool_certification:tenant" to "Tenant1"
    And I should see "Certification1" in the "report-table" "table"
    And I should not see "Certification2" in the "report-table" "table"
    And I should not see "Certification0" in the "report-table" "table"
    And I press "Reset table"
    And I switch to tenant "Tenant1"
    And I click on "Edit content" "link" in the "SharedCertifications" "table_row"
    # Admin should only see certifications from Tenant1.
    And I should see "Certification0" in the "report-table" "table"
    And I should see "Certification1" in the "report-table" "table"
    And I should not see "Tenant1" in the "Certification1" "table_row"
    And I should not see "Certification2" in the "report-table" "table"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Report builder" in workplace launcher
    Then I click on "Preview" "link" in the "SharedCertifications" "table_row"
    # Tenantadmin1 should only see certifications from Tenant1 and shared space.
    And I should see "Certification0" in the "report-table" "table"
    And I should see "Certification1" in the "report-table" "table"
    And I should not see "Certification2" in the "report-table" "table"
    And I log out

  Scenario: Using Certification users allocation and completion datasource with certifications on different tenants
    Given the following "tool_certification > certification_users" exist:
      | certification  | user    |
      | Certification0 | user11  |
      | Certification0 | user21  |
      | Certification1 | user11  |
      | Certification2 | user22  |
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedCertificationsAllocations"
    And I set the field "Report source" in the "New report" "dialogue" to "Certification users allocation and completion"
    And I press "Save"
    # Admin should see certifications from both tenants.
    And I should see "Certification0" in the "report-table" "table"
    And I should see "Certification1" in the "report-table" "table"
    And I should see "Tenant1" in the "Certification1" "table_row"
    And I should see "Certification2" in the "report-table" "table"
    And I should see "Tenant2" in the "Certification2" "table_row"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "tool_certification:tenant_op" to "is equal to"
    And I set the field "tool_certification:tenant" to "Tenant1"
    And I should see "Certification1" in the "report-table" "table"
    And I should not see "Certification2" in the "report-table" "table"
    And I should not see "Certification0" in the "report-table" "table"
    And I press "Reset table"
    And I switch to tenant "Tenant1"
    And I click on "Edit content" "link" in the "SharedCertificationsAllocations" "table_row"
    # Admin should only see certifications from Tenant1.
    And I should see "Certification0" in the "report-table" "table"
    And I should see "Certification1" in the "report-table" "table"
    And I should not see "Tenant1" in the "Certification1" "table_row"
    And I should not see "Certification2" in the "report-table" "table"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Report builder" in workplace launcher
    Then I click on "Preview" "link" in the "SharedCertificationsAllocations" "table_row"
    # Tenantadmin1 should only see certifications from Tenant1 and shared space.
    And I should see "Certification0" in the "report-table" "table"
    And I should see "Certification1" in the "report-table" "table"
    And I should not see "Certification2" in the "report-table" "table"
    And I log out
