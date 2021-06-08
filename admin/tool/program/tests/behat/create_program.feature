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
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_program_manager     | System       |           |
      | manager1 | tool_dynamicrule_manager | System       |           |
      | manager2 | tool_program_manager     | System       |           |
    And the following "tool_program > programs" exist:
      | fullname | tenant  | idnumber |
      | Program1 | Tenant1 | num1     |
    And the following "courses" exist:
      | fullname | shortname | format | category |
      | Course 1 | C1        | topics | CAT1     |
      | Course 2 | C2        | topics | CAT1     |
      | Course 3 | C3        | topics | CAT1     |
      | Course hi & bye | C4 | topics | CAT1     |
    And user "manager1" has a department lead position over users "user1" with permissions "3"

  @javascript
  Scenario: Create a program with basic information
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "New program" "link"
    Then I should see "New program"
    And I set the following fields to these values:
      | Program name          | Program example 1                     |
      | Program ID number     | num1                                  |
      | Program description   | This is the description of Program 1  |
      | Program tags          | Tag example 1                         |
    And I should see "Program visibility"
    And I should see "Allow direct allocation"
    Then I press "Save"
    And I should see "This ID number is already used in another program"
    And I set the field "Program ID number" to "new2"
    Then I press "Save"
    Then I click on "Content" "link"
    And I should see "Content" in the "#program_content_tab" "css_element"
    Then I press "Add a set or a course"
    Then I click on "Set" "link" in the ".dropdown-menu.show" "css_element"
    Then I should see "Add set"
    And I should see "Select courses"
    Then I set the field "setname" to "Set example 1"
    And I set the field "Select courses" to "Course 1,Course 2,Course hi & bye"
    Then I press "Save changes"
    Then I should see "Course 1" in the "#program_content_tab" "css_element"
    And I should see "Course 2" in the "#program_content_tab" "css_element"
    And I should see "Course hi & bye" in the "#program_content_tab" "css_element"
    Then I click on "Schedule" "link"
    And I should see "Availability"
    And I should see "Start date"
    And I should see "Due date"
    And I should see "End date"
    And I should see "Allocation window"
    And I set the following fields to these values:
      | startdatetype               | After user allocation date |
      | startdaterelative[number]   | 1                          |
      | startdaterelative[timeunit] | days                       |
      | duedatetype                 | After start date           |
      | duedaterelative[number]     | 1                          |
      | duedaterelative[timeunit]   | days                       |
      | enddatetype                 | After due date             |
      | enddaterelative[number]     | 1                          |
      | enddaterelative[timeunit]   | days                       |
    Then I press "Save changes"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Users"
    And I should see "Nothing to display"
    Then I click on "Allocate users" "link"
    And I set the field "Select users" to "User 1"
    Then I press "Save changes"
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
    Then I click on "Allocate users" "link"
    And I should see "The allocation window for this program starts on"
    And I click on "OK" "button" in the "Users allocation is not available" "dialogue"
    And I should see "Users"
    Then I click on "Dynamic rules" "link" in the "region-main" "region"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to program"
    And I should see "Users not allocated to program"
    And I should see "Users that have status 'Completed' in program"
    And I should see "Users that do not have status 'Completed' in program"
    And I should see "Users that have status 'Overdue' in program"
    And I should see "Users that have status 'Suspended' in program"
    And I navigate to "Programs" in workplace launcher
    And I should see "Program example 1"
    And I should see "Tag example 1"
    Then I log out
    Then I log in as "manager2"
    And I navigate to "Programs" in workplace launcher
    And I should not see "Program example 1"
    And I should not see "Tag example 1"
    Then I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Check calendar events for a user allocated into a program
    Given the following "tool_program > programs" exist:
      | fullname | tenant  | archived | startdatetype | startdaterelative | duedatetype           | duedaterelative | enddatetype    | enddaterelative |
      | Program2 | Tenant1 | 0        | none          | 0 weeks           | after_user_allocation | 1 weeks          | after_due      | 1 weeks         |
    And the following "tool_program > program_users" exist:
      | program   | user   |
      | Program2  | user1  |
    When I log in as "user1"
    And I am viewing site calendar
    And I view the calendar for "1" more weeks
    Then I should see "Due date for program Program2"
    And I am viewing site calendar
    And I view the calendar for "2" more weeks
    And I should see "End date for program Program2"
    And I log out
