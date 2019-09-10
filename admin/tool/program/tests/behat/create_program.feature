@tool @tool_program @moodleworkplace @theme_workplace
Feature: Create program
  In order to create a program
  As a manager
  I need to add a program from the Programs manager view

  Background:
    Given "2" tenants exist with "1" users and "0" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
      | manager1 | tool_dynamicrule_manager | System       |           |
      | manager2 | tool_program_manager | System       |           |
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
    And the following "courses" exist:
      | fullname | shortname | format | category |
      | Course 1 | C1        | topics | CAT1     |
      | Course 2 | C2        | topics | CAT1     |
      | Course 3 | C3        | topics | CAT1     |
      | Course hi & bye | C4 | topics | CAT1     |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1  | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0          |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2    | Position_t1_f1   |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user     | department     | position     |
      | manager1 | Deparment_t1_d1 | Position_t1_f1 |
      | user1    | Deparment_t1_d1 | Position_t1_f2 |

  @javascript
  Scenario: Create a program with basic information
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    Then I click on "Add new program" "link"
    Then I should see "New program"
    And I set the field "Program name" to "Program example 1"
    And I set the field "Program ID number" to "1"
    And I set the field "Program description" to "This is the description of Program 1"
    And I set the field "Program tags" to "Tag example 1"
    And I should see "Program visibility"
    And I should see "Allow direct allocation"
    Then I press "Save" in the modal form dialogue
    Then I click on "Content" "link"
    And I should see "Content" in the "#program_content_tab" "css_element"
    Then I press "Add a set or a course"
    Then I click on "Set" "link" in the ".dropdown-menu.show" "css_element"
    Then I should see "Add set"
    And I should see "Select courses"
    Then I set the field "setname" to "Set example 1"
    Then I open the autocomplete suggestions list in the dialog
    And I should see "Course 1" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Course 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Course 3" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Course hi & bye" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I click on "Course 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Course 2" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Course hi & bye" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select courses"
    Then I press "Save changes" in the modal form dialogue
    Then I should see "Course 1" in the "#program_content_tab" "css_element"
    And I should see "Course 2" in the "#program_content_tab" "css_element"
    And I should see "Course hi & bye" in the "#program_content_tab" "css_element"
    Then I click on "Schedule" "link"
    And I should see "Availability"
    And I should see "Start date"
    And I should see "Due date"
    And I should see "End date"
    And I should see "Allocation window"
    And I select "After user allocation date" from the "startdatetype" singleselect
    Then I set the field "startdaterelative[number]" to "1"
    And I select "days" from the "startdaterelative[timeunit]" singleselect
    And I select "After start date" from the "duedatetype" singleselect
    Then I set the field "duedaterelative[number]" to "1"
    And I select "days" from the "duedaterelative[timeunit]" singleselect
    And I select "After due date" from the "enddatetype" singleselect
    Then I set the field "enddaterelative[number]" to "1"
    And I select "days" from the "enddaterelative[timeunit]" singleselect
    Then I press "Save changes"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Users"
    And I should see "Nothing to display"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I should see "User 1" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I should see "Full name"
    And I should see "Due date"
    And I should see "Allocation source"
    And I should see "Certification status"
    And I should see "Program status"
    And I should see "Actions"
    Then I should see "User 1"
    Then I should see "Future allocation" in the "User 1" "table_row"
    And I should see "Manual" in the "User 1" "table_row"
    Then I click on "Schedule" "link"
    And I select "Not set" from the "startdatetype" singleselect
    Then I press "Save changes"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    Then I should see "Open" in the "User 1" "table_row"
    Then I click on "Schedule" "link"
    And I select "Select date" from the "allocationstartdatetype" singleselect
    And I select "2050" from the "allocationstartdateabsolute[year]" singleselect
    Then I press "Save changes"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Users"
    And "Allocate users" "link" should not exist
    Then I click on "Dynamic rules" "link" in the "region-main" "region"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to program"
    And I should see "Users that have status 'Completed' in program"
    And I should see "Users that have status 'Overdue' in program"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Program example 1"
    And I should see "Tag example 1"
    Then I log out
    Then I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    And I should not see "Program example 1"
    And I should not see "Tag example 1"
    Then I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out