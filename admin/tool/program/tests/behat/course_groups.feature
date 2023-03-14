@tool @tool_program @moodleworkplace @javascript
Feature: Users need to be added to course groups when allocated to program
  In order to better monitor program users in courses
  As a manager
  I need to be able to specify how groups should be created in program courses

  Background:
    Given "3" tenants exist with "4" users and "2" courses in each
    And the following "courses" exist:
      | fullname                | shortname | summary | groupmode | category |
      | Sharedcourse1 nogroups  | shared1   |         | 0         | 0        |
      | Sharedcourse2 visgroups | shared2   |         | 2         | 0        |
      | Sharedcourse3 sepgroups | shared3   |         | 1         | 0        |
    And the following "tool_program > programs" exist:
      | fullname  | tenant  | completioncriteria | autocreategroups |
      | Program11 | Tenant1 | 1                  | 1                |
      | Program12 | Tenant1 | 1                  | 1                |
      | Program21 | Tenant2 | 1                  | 1                |
      | Program22 | Tenant2 | 1                  | 1                |
      | Program31 | Tenant3 | 1                  | 3                |
      | Program32 | Tenant3 | 1                  | 3                |

  Scenario: Users from different tenants can enrol in shared courses and they will be in different groups
    Given the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program11 | C11     |
      | Program11 | shared1 |
      | Program11 | shared2 |
      | Program11 | shared3 |
      | Program21 | C21     |
      | Program21 | shared1 |
      | Program21 | shared2 |
      | Program21 | shared3 |
    And the following "tool_program > program_users" exist:
      | program   | user   |
      | Program11 | user11 |
      | Program11 | user12 |
      | Program21 | user21 |
    # Check user11.
    When I log in as "user11"
    And I am on "Course11" course homepage
    And I navigate to course participants
    Then "User 11" row "Groups" column of "participants" table should contain "No groups"
    # Access all the courses to generate the enrolments and groups.
    And I am on "Sharedcourse2 visgroups" course homepage
    And I am on "Sharedcourse3 sepgroups" course homepage
    And I am on "Sharedcourse1 nogroups" course homepage
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And I log out
    # Check user12.
    And I log in as "user12"
    # Access all the courses to generate the enrolments and groups.
    And I am on "Sharedcourse1 nogroups" course homepage
    And I am on "Sharedcourse2 visgroups" course homepage
    And I am on "Sharedcourse3 sepgroups" course homepage
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1"
    And I log out
    # Check user21.
    And I log in as "user21"
    And I am on "Sharedcourse1 nogroups" course homepage
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I am on "Sharedcourse2 visgroups" course homepage
    And I navigate to course participants
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I should not see "User 1" in the "participants" "table"
    And I click on "Clear filters" "button"
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I am on "Sharedcourse3 sepgroups" course homepage
    And I navigate to course participants
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I should not see "User 1" in the "participants" "table"
    And I click on "Clear filters" "button"
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I should not see "User 1" in the "participants" "table"

  Scenario: Users in the same course enrolled via different programs can be added to different groups
    Given the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program31 | C31     |
      | Program32 | C31     |
      | Program31 | shared3 |
      | Program32 | shared3 |
    And the following "tool_program > program_users" exist:
      | program   | user   |
      | Program31 | user31 |
      | Program32 | user32 |
    # Check user31.
    When I log in as "user31"
    And I am on "Course31" course homepage
    And I navigate to course participants
    Then "User 31" row "Groups" column of "participants" table should contain "Program31"
    And I should not see "Tenant" in the "participants" "table"
    And I am on "Sharedcourse3 sepgroups" course homepage
    And I navigate to course participants
    And "User 31" row "Groups" column of "participants" table should contain "Tenant3 - Program31"
    And I log out
    # Check user32.
    And I log in as "user32"
    And I am on "Course31" course homepage
    And I navigate to course participants
    And "User 31" row "Groups" column of "participants" table should contain "Program31"
    And "User 32" row "Groups" column of "participants" table should contain "Program32"
    And I should not see "Tenant" in the "participants" "table"
    And I am on "Sharedcourse3 sepgroups" course homepage
    And I navigate to course participants
    And "User 32" row "Groups" column of "participants" table should contain "Tenant3 - Program32"
    And I should not see "User 31" in the "participants" "table"
