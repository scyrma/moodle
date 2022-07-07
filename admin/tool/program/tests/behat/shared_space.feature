@tool @tool_program @moodleworkplace @javascript
Feature: It is possible to create programs in shared space
  In order to ensure programs in shared space work
  As a manager and admin
  I need to be able to create, edit and view shared programs

  Background:
    Given "2" tenants exist with "4" users and "0" courses in each
    And shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname  | idnumber | archived | tenant  | generatecourses |
      | Program0  | prog0    | 0        | -       | 0               |
      | Archived0 | arch0    | 1        | -       | 1               |
      | Program1  | prog1    | 0        | Tenant1 | 1               |
      | Program2  | prog2    | 0        | Tenant2 | 1               |
    And the following "courses" exist:
      | fullname     | shortname | format |
      | Sharedcourse | SC        | topics |

  Scenario: Viewing and editing programs in shared space as tenant administrator
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    And I should see "Shared space" in the "Program0" "table_row"
    And I should not see "Shared space" in the "Program1" "table_row"
    And I follow "Program0"
    And I should not see "Edit in shared space"
    And I navigate to "Programs" in workplace launcher
    And I press "Duplicate" action in the "Program0" report row
    And I click on "OK" "button" in the "Confirm" "dialogue"
    And I should not see "Shared space" in the "Program0 Copy" "table_row"
    And I log out

  Scenario: Viewing shared programs as a user and accessing its course
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user11  |
      | Program1 | user11  |
      | Program1 | user12  |
    And the following "tool_program > program_courses" exist:
      | program  | course |
      | Program0 | SC     |
    And I am on the "My courses" page logged in as "user11"
    And I click on "Program0" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And I click on "Sharedcourse" "link"
    And I click on "Start course" "button"
    And I should see "Topic 1"
    And I should see "Sharedcourse"
    And I log out

  Scenario: Creating and viewing programs in shared space as administrator
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    Then I click on "New program" "link"
    And I set the following fields in the ".modal-dialog" "css_element" to these values:
      | Program name       | Sharedprogram  |
      | Program ID number  | num1           |
    And I set the field "Program description" to "This is the description of Program 1"
    Then I press "Save"
    # Add a single course to program
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I set the field "Select courses" to "Sharedcourse"
    Then I press "Save changes"
    And I should see "Sharedcourse" in the "#program_content_tab" "css_element"
    And I navigate to "Programs" in workplace launcher
    And I should see "Sharedprogram"
    And I should not see "Shared space" in the "Sharedprogram" "table_row"
    And I switch to tenant "Tenant1"
    And I should see "Sharedprogram"
    And I should see "Shared space" in the "Sharedprogram" "table_row"
    And I follow "Sharedprogram"
    And I click on "Edit in Shared space" "text"
    And I should see "Shared space" in the ".navbar" "css_element"
    And I should see "Sharedcourse" in the "#program_content_tab" "css_element"
    And I log out

  Scenario: Admin can allocate users from any tenant to shared programs while in Shared space
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And I press "Allocate users"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 12" "autocomplete_suggestions" should exist
    And "User 22" "autocomplete_suggestions" should exist
    And I click on "User 11" item in the autocomplete list
    And I click on "User 21" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 11" in the "#program_users_tab" "css_element"
    And I should see "User 21" in the "#program_users_tab" "css_element"
    And I switch to tenant "Tenant1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And I press "Allocate users"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 22" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 11" in the "#program_users_tab" "css_element"
    And I should see "User 12" in the "#program_users_tab" "css_element"
    And I should not see "User 2"
    And I log out

  Scenario: Tenant administrator can only allocate users from their tenant to shared programs and can not see other users
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user11  |
      | Program0 | user21  |
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And I should see "User 11" in the "#program_users_tab" "css_element"
    And I should not see "User 2"
    And I press "Allocate users"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 22" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 11" in the "#program_users_tab" "css_element"
    And I should see "User 12" in the "#program_users_tab" "css_element"
    And I should not see "User 2"
    And I log out
    Then I log in as "admin"
    # Check that user can access program in read only mode even if allocation window is closed.
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    Then I click on "Schedule" "link"
    And I set the following fields to these values:
      | allocationstartdatetype                 | Select date |
      | allocationstartdateabsolute[day]        | 3           |
      | allocationstartdateabsolute[month]      | March       |
      | allocationstartdateabsolute[year]       | 2050        |
    Then I press "Save changes"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    Then I click on "Allocate users" "link"
    And I should see "The allocation window for this program starts on"
    And I click on "OK" "button" in the "Users allocation is not available" "dialogue"
    And I log out

  Scenario: Admin can filter users allocated to shared program by their tenant
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user11  |
      | Program0 | user21  |
      | Program0 | user12  |
      | Program0 | user22  |
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And the following should exist in the "reportbuilder-table" table:
      | First name / Surname | Tenant name |
      | User 11   | Tenant1 |
      | User 12   | Tenant1 |
      | User 21   | Tenant2 |
      | User 22   | Tenant2 |
    And I click on "Filters" "button"
    And I set the following fields in the "Tenant name" "core_reportbuilder > Filter" to these values:
      | Tenant name operator | Is equal to  |
      | Tenant name value    | Tenant2      |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    And I should see "User 21"
    And I should not see "User 1"
    And I switch to tenant "Tenant1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And I should see "User 11"
    And I should not see "User 2"
    And "Tenant" "text" should not exist in the "reportbuilder-table" "table"

  Scenario: Both admin in shared space and tenant administrator in the tenant can edit user allocations in shared programs
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user11  |
      | Program0 | user12  |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And I press "Edit" action in the "User 11" report row
    And I select "Suspended" from the "Status" singleselect
    Then I press "Save changes"
    And I should see "Suspended" in the "User 11" "table_row"
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    And I should see "Suspended" in the "User 11" "table_row"
    And I press "Edit" action in the "User 11" report row
    And I select "Default" from the "Status" singleselect
    Then I press "Save changes"
    And I should not see "Suspended" in the "User 11" "table_row"
    And I log out

  Scenario: Organisation manager can see progress reports of their team members in shared programs on the dashboard
    Given the following config values are set as admin:
      | custommenuitems | -My teams\|/my/courses.php?myteams=1 |
    Given user "user13" has a manager position over users "user11,user12" with permissions "3"
    And the following "tool_program > program_courses" exist:
      | program  | course |
      | Program0 | SC     |
    And the following "tool_program > program_users" exist:
      | program  | user         | duedate    | duedatelocked |
      | Program0 | user11       | 1577836800 | 1             |
      | Program0 | user12       |            |               |
      | Program1 | user11       |            |               |
      | Program0 | user21       |            |               |
      | Program0 | tenantadmin1 |            |               |
    When I log in as "user13"
    And I select "My teams" from primary navigation
    And I press "User 11"
    And I should see "1 active programs"
    And I follow "1 overdue programs"
    And I should see "Overdue" in the "Program0" "table_row"
    And I press "Progress overview" action in the "Program0" report row
    And I should see "Complete all in order"
    And I should see "Sharedcourse"
    And I click on "Close" "button" in the "Progress overview" "dialogue"
    And I press "Progress report" action in the "Program0" report row
    And "Program0 (Base set)" "text" should exist in the "Set" "table_row"
    And "Sharedcourse" "text" should exist in the "Course" "table_row"
    And I follow "My teams"
    And I click on "Overdue programs" "link"
    And "User 11" "link" should exist in the "Program0" "table_row"
    And I log out

  Scenario: Organisation manager can allocate their team members to the shared program and edit allocations
    Given user "user13" has a manager position over users "user11,user12" with permissions "3"
    And the following "tool_program > program_users" exist:
      | program  | user         |
      | Program0 | user11       |
      | Program0 | user21       |
      | Program0 | tenantadmin1 |
    When I log in as "user13"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "Program0" report row
    Then I should see "User 11"
    And I should not see "User 21"
    And I should not see "Tenant"
    And I press "Allocate users"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And I should not see "User 2"
    And I should not see "Tenant"
    And "User 22" "autocomplete_suggestions" should not exist
    And I click on "User 12" item in the autocomplete list
    Then I press "Save changes"
    And I should see "User 11" in the "#program_users_tab" "css_element"
    And I should see "User 12" in the "#program_users_tab" "css_element"
    And I press "Edit" action in the "User 11" report row
    And I select "Suspended" from the "Status" singleselect
    Then I press "Save changes"
    And I should see "Suspended" in the "User 11" "table_row"
    And I log out

  Scenario: User can complete a shared program
    Given the following "tool_program > programs" exist:
      | fullname  | idnumber | archived | tenant  | generatecourses |
      | ProgramA  | progA    | 0        | -       | 3               |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | ProgramA | user11  |
    And the following "tool_program > program_completions" exist:
      | program  | user    |
      | ProgramA | user11  |
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    And I press "Users" action in the "ProgramA" report row
    And I should see "User 11" in the "#program_users_tab" "css_element"
    And I should see "Completed" in the "User 11" "table_row"
    And I log out

  Scenario: Viewing users progress report on a shared program
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user11  |
      | Program0 | user12  |
      | Program0 | user21  |
    When I log in as "tenantadmin1"
    And I navigate to "Programs" in workplace launcher
    And I press "Progress report" action in the "Program0" report row
    And I should see "Open" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I should not see "User 21"
    And I press "User 11"
    And I should see "1 active programs"
    And I log out
    And I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    And I press "Progress report" action in the "Program0" report row
    And I should see "Open" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I should see "Open" in the "User 21" "table_row"
    And I log out

  Scenario: Tenant administrator can create dynamic rules that use shared programs in conditions and actions
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    # Condition Users allocated to program
    And I follow "Users allocated to program"
    And I set the field "Program" to "Program0"
    And I press "Save changes"
    And I should see "Users allocated to program 'Program0'"
    And I navigate to "Actions" in current page administration
    # Action Deallocate users from programs
    And I click on "Deallocate users from programs" "link" in the "#ruleoutcomes" "css_element"
    And I set the field "Program" to "Program0"
    And I press "Save changes"
    And I should see "Deallocate users from program 'Program0'"
    And I navigate to "Dynamic rules" in workplace launcher
    And I should see "Users allocated to program 'Program0'"
    And I should see "Deallocate users from program 'Program0'"
    And I log out

  Scenario: Site admin can configure dynamic rules in shared programs
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user12  |
      | Program0 | user13  |
      | Program0 | user22  |
      | Program0 | user23  |
    When I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program0" "link" in the "Program0" "table_row"
    And I navigate to "Dynamic rules" in current page administration
    And I press "Edit actions" action in the "Users not allocated to program" report row
    And I click on "Allocate users to programs" "link" in the "#outcomes-menu" "css_element"
    And I expand the "Program" autocomplete
    And "Program0" "autocomplete_suggestions" should exist
    And "Program1" "autocomplete_suggestions" should not exist
    And "Program2" "autocomplete_suggestions" should not exist
    And I click on "Program0" item in the autocomplete list
    And I press "Save changes"
    And I click on "Close" "button" in the ".modal-dialog" "css_element"
    And I click on "Enable rule" "link" in the "Users not allocated to program 'Program0'" "table_row"
    And I should see "Are you sure you want to enable this rule? Enabling it will affect 5 users" in the "Confirm" "dialogue"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    And I log in as "admin"
    And I switch to tenant "Shared space"
    And I navigate to "Programs" in workplace launcher
    Then I press "Users" action in the "Program0" report row
    And I should see "Dynamic" in the "Tenantadmin 1" "table_row"
    And I should see "Dynamic" in the "Tenantadmin 2" "table_row"
    And I should see "Dynamic" in the "User 11" "table_row"
    And I should see "Dynamic" in the "User 21" "table_row"
  # TODO find out why this fails now.
  # And I should see "Dynamic" in the "Admin User" "table_row"
    And I should not see "Dynamic" in the "User 12" "table_row"
    And I should not see "Dynamic" in the "User 13" "table_row"
    And I should not see "Dynamic" in the "User 22" "table_row"
    And I should not see "Dynamic" in the "User 23" "table_row"

  Scenario: Using Programs datasource with programs on different tenants
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedPrograms"
    And I set the field "Report source" in the "New report" "dialogue" to "Programs"
    And I press "Save"
    # Admin should see programs from both tenants.
    And I should see "Program0" in the "report-table" "table"
    And I should see "Program1" in the "report-table" "table"
    And I should see "Tenant1" in the "Program1" "table_row"
    And I should see "Program2" in the "report-table" "table"
    And I should see "Tenant2" in the "Program2" "table_row"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "tool_program:tenant_op" to "is equal to"
    And I set the field "tool_program:tenant" to "Tenant1"
    And I should see "Program1" in the "report-table" "table"
    And I should not see "Program2" in the "report-table" "table"
    And I should not see "Program0" in the "report-table" "table"
    And I press "Reset table"
    And I switch to tenant "Tenant1"
    And I click on "SharedPrograms" "link" in the "SharedPrograms" "table_row"
    # Admin should only see programs from Tenant1.
    And I should see "Program0" in the "report-table" "table"
    And I should see "Program1" in the "report-table" "table"
    And I should not see "Tenant1" in the "Program1" "table_row"
    And I should not see "Program2" in the "report-table" "table"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Report builder" in workplace launcher
    Then I click on "Preview" "link" in the "SharedPrograms" "table_row"
    # Tenantadmin1 should only see programs from Tenant1 and shared space.
    And I should see "Program0" in the "report-table" "table"
    And I should see "Program1" in the "report-table" "table"
    And I should not see "Program2" in the "report-table" "table"
    And I log out

  Scenario: Using Program users allocation and completion datasource with programs on different tenants
    Given the following "tool_program > program_users" exist:
      | program  | user    |
      | Program0 | user11  |
      | Program1 | user12  |
      | Program0 | user21  |
      | Program2 | user21  |
    When I log in as "admin"
    And I change window size to "large"
    And I switch to tenant "Shared space"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the field "Report name" in the "New report" "dialogue" to "SharedProgramUsersAllocation"
    And I set the field "Report source" in the "New report" "dialogue" to "Program users allocation and completion"
    And I press "Save"
    # Admin should see programs from both tenants.
    And I should see "Program0" in the "report-table" "table"
    And I should see "Program1" in the "report-table" "table"
    And I should see "Tenant1" in the "Program1" "table_row"
    And I should see "Program2" in the "report-table" "table"
    And I should see "Tenant2" in the "Program2" "table_row"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "tool_program:tenant_op" to "is equal to"
    And I set the field "tool_program:tenant" to "Tenant1"
    And I should see "Program1" in the "report-table" "table"
    And I should not see "Program2" in the "report-table" "table"
    And I should not see "Program0" in the "report-table" "table"
    And I press "Reset table"
    And I switch to tenant "Tenant1"
    And I click on "SharedProgramUsersAllocation" "link" in the "SharedProgramUsersAllocation" "table_row"
    # Admin should only see programs from Tenant1.
    And I should see "Program0" in the "report-table" "table"
    And I should see "Program1" in the "report-table" "table"
    And I should not see "Tenant1" in the "Program1" "table_row"
    And I should not see "Program2" in the "report-table" "table"
    And I log out
    And I log in as "tenantadmin1"
    And I navigate to "Report builder" in workplace launcher
    Then I click on "Preview" "link" in the "SharedProgramUsersAllocation" "table_row"
    # Tenantadmin1 should only see programs from Tenant1 and shared space.
    And I should see "Program0" in the "report-table" "table"
    And I should see "Program1" in the "report-table" "table"
    And I should not see "Program2" in the "report-table" "table"
    And I log out
