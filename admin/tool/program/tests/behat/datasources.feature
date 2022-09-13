@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Check datasources for programs
  In order to check datasources
  As a manager
  I need be able to create reports with them

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  | program_tags |
      | Program1 | 0        | Tenant1 | tag 1,tag 2  |
      | Program2 | 0        | Tenant1 | tag 2        |
    And the following "users" exist:
      | username   | firstname | lastname | email                |
      | user1      | User      | a        | user1@example.com    |
      | user2      | User      | b        | user2@example.com    |
      | user3      | User      | c        | user3@example.com    |
      | orgmanager | User      | d        | user4@example.com    |
      | user4      | User      | e        | user3@example.com    |
      | manager1   | Manager   | 1        | manager1@example.com |
      | manager2   | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user1      | Tenant1 |
      | user2      | Tenant1 |
      | user3      | Tenant1 |
      | orgmanager | Tenant1 |
      | user4      | Tenant1 |
      | manager1   | Tenant1 |
      | manager2   | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user2  |
      | Program2 | user2  |
      | Program1 | user4  |
    And the following "roles" exist:
      | shortname      | name            | archetype |
      | programmanager | program manager |           |
    And the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | manager1 | programmanager | System       |           |
      | manager2 | manager        | System       |           |
      | user1    | user           | System       |           |
      | user2    | user           | System       |           |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1 | Framework_t1   |
      | Tenant1    | Deparment_t1_d2 | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2  | Position_t1_f1 |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f3  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f4  | Position_t1_f3 |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user       | department      | position     |
      | manager1   | Deparment_t1_d1 | Position_t1_f1 |
      | manager2   | Deparment_t1_d1 | Position_t1_f1 |
      | orgmanager | Deparment_t1_d1 | Position_t1_f1 |
      | user1      | Deparment_t1_d1 | Position_t1_f2 |
      | user2      | Deparment_t1_d1 | Position_t1_f2 |
      | manager2   | Deparment_t1_d2 | Position_t1_f3 |
      | user4      | Deparment_t1_d2 | Position_t1_f4 |
    And the following "permission overrides" exist:
      | capability              | permission | role           | contextlevel | reference |
      | moodle/site:configview  | Allow      | programmanager | System       |           |
      | moodle/reportbuilder:editall | Allow      | programmanager | System       |           |
# TODO.
  Scenario: Program manager can create a user allocation and a programs report
    Given the following "tool_tenant > reports" exist:
      | name    | tenant  | source                                                               |
      | Report1 | Tenant1 | tool_program\reportbuilder\datasource\programs_allocation_completion |
      | Report2 | Tenant1 | tool_program\reportbuilder\datasource\programs                       |
    When I log in as "manager1"
    And I navigate to "Custom reports" in workplace launcher
    And I click on "Report1" "link" in the "Report1" "table_row"
    Then I should see "Program"
    And I should see "User allocation"
    And I should see "User completion"
    And I should see "User"
    And I should see "Job"
    And I click on "Add column 'Tags'" "link"
    And I should see "tag 1" in the "Program1" "table_row"
    And I should see "tag 2" in the "Program1" "table_row"
    And I should see "tag 2" in the "Program2" "table_row"
    And I should see "ID number" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Program name" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Description" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Visible" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Archived" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Start date" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Due date" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "End date" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Program status" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Program progress" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Suspended" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Suspended on" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Allocation source" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Allocation date" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Last modified on" in the ".reportbuilder-sidebar-menu #card_program_user" "css_element"
    And I should see "Completed" in the ".reportbuilder-sidebar-menu #card_program_completion" "css_element"
    And I should see "Completion date" in the ".reportbuilder-sidebar-menu #card_program_completion" "css_element"
    And I should see "Days taking program" in the ".reportbuilder-sidebar-menu #card_program_completion" "css_element"
    And I should see "Days since allocation" in the ".reportbuilder-sidebar-menu #card_program_completion" "css_element"
    Then I click on "[data-action='report-add-column'][data-unique-identifier='program:fullname']" "css_element"
    Then I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "[data-action='report-add-column'][data-unique-identifier='user:fullname']" "css_element"
    And I should see "User a" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "User b" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "[data-action='report-add-column'][data-unique-identifier='program_user:duedate']" "css_element"
    And I should see "Due date" in the "[data-region='reportbuilder-table']" "css_element"
    And I click on "Close 'Report1' editor" "button"
    And I navigate to "Custom reports" in workplace launcher
    And I click on "Report2" "link" in the "Report2" "table_row"
    Then I should see "Program"
    And I should see "User"
    And I should see "Course"
    And I should see "Program name" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Program name with image" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Program image" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "ID number" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Tags" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Description" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Start date" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Due date" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "End date" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Archived" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Archived on" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Allow direct allocation" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Allocation start date" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Allocation end date" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Visible" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Last modified on" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    And I should see "Created on" in the ".reportbuilder-sidebar-menu #card_program" "css_element"
    Then I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "[data-action='report-add-column'][data-unique-identifier='program:archived']" "css_element"
    Then I should see "Archived" in the "[data-region='reportbuilder-table']" "css_element"
    Then I should see "No" in the "[data-region='reportbuilder-table']" "css_element"
    And I click on "Close 'Report2' editor" "button"
    And I log out
# Uncomment when step works with tenants and datasource for allocations is created.
#  Scenario: System manager can create a user allocation and a programs report
#    Given the following "core_reportbuilder > Report" exist:
#      | name    | tenant  | source                                                                      |
##      | Report3 | Tenant1 | tool_program\reportbuilder\datasource\report_programs_allocation_completion |
#      | Report4 | Tenant1 | tool_program\reportbuilder\datasource\programs                       |
#    When I log in as "manager2"
#    And I navigate to "Report builder" in workplace launcher
#    And I click on "Edit content" "link" in the "Report3" "table_row"
#    Then I should see "Program"
#    And I should see "ID number" in the "#entity_tool_program" "css_element"
#    And I should see "Program name" in the "#entity_tool_program" "css_element"
#    And I should see "Description" in the "#entity_tool_program" "css_element"
#    And I should see "Visible" in the "#entity_tool_program" "css_element"
#    And I should see "Archived" in the "#entity_tool_program" "css_element"
#    And I should see "Start date" in the "#entity_tool_program_users" "css_element"
#    And I should see "Due date" in the "#entity_tool_program_users" "css_element"
#    And I should see "End date" in the "#entity_tool_program_users" "css_element"
#    And I should see "Program status" in the "#entity_tool_program_users" "css_element"
#    Then I click on "[data-field='tool_program:fullname']" "css_element"
#    Then I should see "Program1" in the "[data-region='report-table']" "css_element"
#    Then I click on "[data-field='user:fullname']" "css_element"
#    And I should see "User a" in the "[data-region='report-table']" "css_element"
#    And I should see "User b" in the "[data-region='report-table']" "css_element"
#    Then I click on "[data-field='tool_program_users:duedate']" "css_element"
#    And I should see "Due date" in the "[data-region='report-table']" "css_element"
#    # Ensure that program progress modal opens on custom reports.
#    And I click on "Add field 'Program progress with report links' to the report" "link"
#    And I press "Progress overvie" action in the "Program1" report row
#    And I should see "Complete all in order" in the "Program1: Progress overview" "dialogue"
#    And I click on "Close" "button" in the "Program1: Progress overview" "dialogue"
#    # TODO.
#    And I navigate to "Report builder" in workplace launcher
#    And I click on "Edit content" "link" in the "Report4" "table_row"
#    And I should see "ID number" in the "#entity_tool_program" "css_element"
#    And I should see "Program name" in the "#entity_tool_program" "css_element"
#    And I should see "Description" in the "#entity_tool_program" "css_element"
#    And I should see "Visible" in the "#entity_tool_program" "css_element"
#    And I should see "Allocation start date" in the "#entity_tool_program" "css_element"
#    And I should see "Allocation end date" in the "#entity_tool_program" "css_element"
#    And I should see "Archived" in the "#entity_tool_program" "css_element"
#    And I should see "Archived on" in the "#entity_tool_program" "css_element"
#    And I should see "Allow direct allocation" in the "#entity_tool_program" "css_element"
#    And I should see "Start date" in the "#entity_tool_program" "css_element"
#    And I should see "Due date" in the "#entity_tool_program" "css_element"
#    And I should see "End date" in the "#entity_tool_program" "css_element"
#    Then I click on "[data-field='tool_program:fullname']" "css_element"
#    Then I should see "Program1" in the "[data-region='report-table']" "css_element"
#    Then I click on "[data-field='tool_program:archived']" "css_element"
#    Then I should see "Not archived" in the "[data-region='report-table']" "css_element"
#    And I log out

  Scenario: Filter programs custom report by tag
    Given the following "tool_tenant > reports" exist:
      | name    | tenant  | source                                         |
      | Report1 | Tenant1 | tool_program\reportbuilder\datasource\programs |
    When I log in as "manager1"
    And I navigate to "Custom reports" in workplace launcher
    # Add the filter to the report.
    And I click on "Report1" "link" in the "Report1" "table_row"
    When I click on "Show/hide 'Filters'" "button"
    # TODO WP-3702 uncomment
    #And I set the field "Select a filter" to "Tags"
    ## Switch to preview, use filter.
    #And I click on "Switch to preview mode" "button"
    #And I click on "Filters" "button"
    #And I set the field "Tags" to "tag 1"
    #And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    #Then I should see "Program1" in the "reportbuilder-table" "table"
    #And I should not see "Program2" in the "reportbuilder-table" "table"
