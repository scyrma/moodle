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
      | fullname                      | duedatetype           | duedaterelative |
      | ProgramCompleted              | none                  | 1 weeks         |
      | ProgramNotCompleted           | none                  | 1 weeks         |
      | ProgramWithDueDateOverdue     | after_user_allocation | 0 weeks         |
      | ProgramWithDueDateNotOverdue  | after_user_allocation | 2 weeks         |
      | ProgramUpcomingDueDate         | after_user_allocation | 1 days          |
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