@block @block_myavailable @moodleworkplace @javascript
Feature: The 'New available courses' block allows users to view their current courses
  In order to view information about my courses in progress
  As a user
  I can add the 'New available courses' block to my dashboard

  Background:
    Given "1" tenants exist with "3" users and "9" courses in each
    And the following "course enrolments" exist:
      | user   | course | role    |
      | user12 | C11    | student |
    And the following "tool_program > programs" exist:
      | fullname   | tenant  | completioncriteria |
      | Program 01 | Tenant1 | 1                  |
    And the following "tool_program > program_sets" exist:
      | name    | program    | set   | completioncriteria | sortorder |
      | Set1    | Program 01 |       | 1                  | 3         |
      | Set1-A  | Program 01 | Set1  | 0                  | 2         |
      | Set2    | Program 01 |       | 0                  | 4         |
      | Set2-A  | Program 01 | Set2  | 0                  | 2         |
    And the following "tool_program > program_courses" exist:
      | program      | course  | set    | sortorder |
      | Program 01   | C12     |        |      1    |
      | Program 01   | C13     |        |      2    |
      | Program 01   | C14     | Set1   |      1    |
      | Program 01   | C15     | Set1-A |      1    |
      | Program 01   | C16     | Set1-A |      2    |
      | Program 01   | C17     | Set2   |      1    |
      | Program 01   | C18     | Set2-A |      1    |
      | Program 01   | C19     | Set2-A |      2    |
    And the following "tool_program > program_users" exist:
      | program    | user    |
      | Program 01 | user12  |

  Scenario: View empty block by a logged in user
    When I log in as "user11"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "New available courses" block
    And I configure the "New available courses" block
    And I set the following fields to these values:
      | Region | content |
    And I press "Save changes"
    And I switch editing mode off
    Then I should see "You have no new available courses" in the "New available courses" "block"

  Scenario: View block with courses and programs by a logged in user
    When I log in as "user12"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "New available courses" block
    And I configure the "New available courses" block
    And I set the following fields to these values:
      | Region | content |
    And I press "Save changes"
    And I switch editing mode off
    Then I should see "New available courses (6)" in the "New available courses" "block"
    And I should see "Course11" in the "New available courses" "block"
    And I am on "Course11" course homepage
    And I follow "Dashboard"
    Then I should see "New available courses (5)" in the "New available courses" "block"
    And I should not see "Course11" in the "New available courses" "block"
    # I should not see courses which are not accessible to the user. These courses are locked by the completion order.
    And I should not see "Course16" in the "New available courses" "block"
    And I should not see "Course18" in the "New available courses" "block"
    And I should not see "Course19" in the "New available courses" "block"
    # Complete all in any order makes it show all courses in the program.
    And I should see "Course12" in the "New available courses" "block"
    And I should see "Course13" in the "New available courses" "block"
    # Set1 is set to complete all in any order, so it will show the courses in the set.
    And I should see "Course14" in the "New available courses" "block"
    # The rest of the courses are not visible on first sight.
    And I should not see "Course15" in the "New available courses" "block"
    And I should not see "Course15" in the "New available courses" "block"
    # I click right arrow to see the rest of the courses.
    And I click on "Next page" "button" in the "New available courses" "block"
    # Make sure that unavailable courses are still not shown.
    And I should not see "Course16" in the "New available courses" "block"
    And I should not see "Course18" in the "New available courses" "block"
    And I should not see "Course19" in the "New available courses" "block"
    # Set1-A is set to complete all in order, so it will show the first course in the set.
    And I should see "Course15" in the "New available courses" "block"
    # Set2 is set to complete all in order, so it will show the first course in the set.
    And I should see "Course17" in the "New available courses" "block"

  Scenario: Show hidden courses only to users with permission to view them
    When the following "courses" exist:
      | fullname      | shortname     | enablecompletion | visible  |
      | Hidden course | hiddencourse  | 1                | 0        |
    And the following "course enrolments" exist:
      | user    | course       | role    |
      | admin   | hiddencourse | student |
      | user11  | hiddencourse | student |
      | user11  | C11          | student |
    And I log in as "admin"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "New available courses" block
    Then I should see "New available courses (1)" in the "New available courses" "block"
    And I should see "Hidden from learners" in the "Hidden course" "block_myavailable > Course card"
    And I log out
    And I log in as "user11"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "New available courses" block
    Then I should see "New available courses (1)" in the "New available courses" "block"
    And I should not see "Hidden course" in the "New available courses" "block"
    And I should see "Course1" in the "New available courses" "block"
