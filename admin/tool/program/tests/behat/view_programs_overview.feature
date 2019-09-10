@tool @tool_program @moodleworkplace @theme_workplace
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
    And the following tool program data "program_users" exist:
      | user     | program  |
      | student1 | Program1 |
      | student1 | Program2 |
    When I log in as "student1"
    Then I should see "Programs"
    And I should see "Program1"
    And I should see "Program2"