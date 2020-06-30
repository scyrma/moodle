@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: View programs overview
  In order to see the programs
  As a student
  I can view my programs on the dashboard

  Background:
    Given the following "users" exist:
      | username | firstname | email                |
      | student1 | Student   | student1@example.com |

  Scenario: User is not allocated to any program
    When I log in as "student1"
    Then I should see "Course overview"
    And I should not see "Programs"

  Scenario: User is allocated to some programs
    Given the following tool program data "programs" exist:
      | fullname |
      | Program1 |
      | Program2 |
    And the following users allocations to programs exist:
      | user     | program  |
      | student1 | Program1 |
      | student1 | Program2 |
    When I log in as "student1"
    Then I should see "Programs"
    And I should see "Program1"
    And I should see "Program2"

  Scenario: User should see the filter for program status in the dashboard
    Given the following tool program data "programs" exist:
      | fullname                      | duedatetype           | duedaterelative | generatecourses |
      | ProgramCompleted              | none                  | 1 weeks         | 1               |
      | ProgramNotCompleted           | none                  | 1 weeks         | 1               |
      | ProgramWithDueDateOverdue     | after_user_allocation | 0 weeks         | 1               |
      | ProgramWithDueDateNotOverdue  | after_user_allocation | 2 weeks         | 1               |
      | ProgramUpcomingDueDate        | after_user_allocation | 1 days          | 1               |
    And the following users allocations to programs exist:
      | user     | program                      |
      | student1 | ProgramCompleted             |
      | student1 | ProgramNotCompleted          |
      | student1 | ProgramWithDueDateOverdue    |
      | student1 | ProgramWithDueDateNotOverdue |
      | student1 | ProgramUpcomingDueDate        |
    And the following tool program user allocations are completed:
      | program           | user      |
      | ProgramCompleted  | student1  |
    When I log in as "student1"
    Then I should see "Programs"
    And I should see "ProgramCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateNotOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramUpcomingDueDate" in the ".programs-view" "css_element"
    And I should see "All" in the ".programs-status-filter" "css_element"
    And I should not see "Completed" in the ".programs-status-filter" "css_element"
    # Check 'Completed'
    And I click on "Program status" "button"
    And I follow "Completed"
    And I should see "ProgramCompleted" in the ".programs-view" "css_element"
    And I should not see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should not see "ProgramWithDueDateOverdue" in the ".programs-view" "css_element"
    And I should not see "ProgramWithDueDateNotOverdue" in the ".programs-view" "css_element"
    And I should not see "ProgramUpcomingDueDate" in the ".programs-view" "css_element"
    # Check 'Not completed'
    And I click on "Program status" "button"
    And I follow "Not completed"
    And I should not see "ProgramCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateNotOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramUpcomingDueDate" in the ".programs-view" "css_element"
    # Check 'With due date'
    And I click on "Program status" "button"
    And I follow "With due date"
    And I should not see "ProgramCompleted" in the ".programs-view" "css_element"
    And I should not see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateNotOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramUpcomingDueDate" in the ".programs-view" "css_element"
    # Check 'Close to due date'
    And I click on "Program status" "button"
    And I follow "Upcoming due date"
    And I should not see "ProgramCompleted" in the ".programs-view" "css_element"
    And I should not see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should not see "ProgramWithDueDateOverdue" in the ".programs-view" "css_element"
    And I should not see "ProgramWithDueDateNotOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramUpcomingDueDate" in the ".programs-view" "css_element"
    # Check 'All'
    And I click on "Program status" "button"
    And I follow "All"
    And I should see "ProgramCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramWithDueDateNotOverdue" in the ".programs-view" "css_element"
    And I should see "ProgramUpcomingDueDate" in the ".programs-view" "css_element"
    # Check 'Completed' and search at same time
    And I click on "Program status" "button"
    And I follow "Completed"
    And I set the field "programs-search-input" to "NotCompleted"
    And I should not see "ProgramCompleted" in the ".programs-view" "css_element"
    And I log out

  Scenario: Filter for program status in the dashboard should show Nothing to display if no results
    Given the following tool program data "programs" exist:
      | fullname              | duedatetype   | duedaterelative |
      | ProgramNotCompleted   | none          | 1 weeks         |
    And the following users allocations to programs exist:
      | user     | program                      |
      | student1 | ProgramNotCompleted          |
    When I log in as "student1"
    Then I should see "Programs"
    And I should see "ProgramNotCompleted" in the ".programs-view" "css_element"
    # Check 'Completed'
    And I click on "Program status" "button"
    And I follow "Completed"
    And I should not see "ProgramNotCompleted" in the ".programs-view" "css_element"
    And I should see "Nothing to display" in the ".programs-view" "css_element"
    And I log out

  Scenario: User should see the filter for program search and sorting in the dashboard
    Given the following tool program data "programs" exist:
      | fullname  | duedatetype           | duedaterelative |
      | ProgramA1 | none                  | 0 weeks         |
      | ProgramB2 | after_user_allocation | 2 weeks         |
      | ProgramC3 | after_user_allocation | 5 weeks         |
      | ProgramD4 | after_user_allocation | 3 weeks         |
    And the following users allocations to programs exist:
      | user     | program   |
      | student1 | ProgramA1 |
      | student1 | ProgramB2 |
      | student1 | ProgramC3 |
      | student1 | ProgramD4 |
    When I log in as "student1"
    Then I should see "Programs"
    # Program search
    And I set the field "programs-search-input" to "A1"
    Then I should see "ProgramA1" in the "#wpdashboardContent" "css_element"
    And I should not see "ProgramB2" in the "#wpdashboardContent" "css_element"
    And I should not see "ProgramC3" in the "#wpdashboardContent" "css_element"
    And I set the field "programs-search-input" to "C3"
    Then I should see "ProgramC3" in the "#wpdashboardContent" "css_element"
    And I should not see "ProgramA1" in the "#wpdashboardContent" "css_element"
    And I should not see "ProgramD4" in the "#wpdashboardContent" "css_element"
    And I set the field "programs-search-input" to ""
    # Program sort
    Then I click on "Program sort" "button"
    And I follow "Program name"
    # Using user preferences saved bc appears to be a problem changing the dom with this 'should appear before' step
    Then I follow "Dashboard"
    And "ProgramA1" "text" should appear before "ProgramB2" "text"
    And "ProgramB2" "text" should appear before "ProgramC3" "text"
    And "ProgramC3" "text" should appear before "ProgramD4" "text"
    Then I click on "Program sort" "button"
    And I follow "Due date"
    Then I follow "Dashboard"
    And "ProgramB2" "text" should appear before "ProgramD4" "text"
    And "ProgramD4" "text" should appear before "ProgramC3" "text"
    And "ProgramC3" "text" should appear before "ProgramA1" "text"
    # Program display List (Collapsed)
    And I should see "Complete all in order" in the "#wpdashboardContent" "css_element"
    Then I click on "Program display" "button"
    Then I follow "Collapsed"
    And I should see "ProgramA1" in the "#wpdashboardContent" "css_element"
    And I should see "Complete all in order" in the "#wpdashboardContent" "css_element"

  Scenario: User should be able to open and see the course information modal
    Given "1" tenants exist with "2" users and "0" courses in each
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | student1 | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
    And the following "courses" exist:
      | fullname | shortname | format | category |
      | Course 1 | C1        | topics | CAT1     |
    And the following program courses exist:
      | program  | course |
      | Program1 | C1     |
    And the following users allocations to programs exist:
      | user     | program  |
      | student1 | Program1 |
    When I log in as "student1"
    Then I follow "Dashboard"
    Then I should see "Programs"
    And I change window size to "large"
    Then I click on ".enrol_to_course" "css_element"
    Then I follow "Dashboard"
    And I click on "Course information" "button"
    And I should see "Course 1" in the ".modal-dialog .modal-body" "css_element"
    And I should see "0% Completed" in the ".modal-dialog .modal-body" "css_element"
    And I follow "Go to course"
