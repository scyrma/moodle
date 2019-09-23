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
    And the following tool program data "programs" exist:
      | fullname  | tenant  | completioncriteria | autocreategroups |
      | Program11 | Tenant1 | 1                  | 1                |
      | Program12 | Tenant1 | 1                  | 1                |
      | Program21 | Tenant2 | 1                  | 1                |
      | Program22 | Tenant2 | 1                  | 1                |
      | Program31 | Tenant3 | 1                  | 3                |
      | Program32 | Tenant3 | 1                  | 3                |

  Scenario: Users from different tenants can enrol in shared courses and they will be in different groups
    Given the following tool program data "program_courses" exist:
      | program   | course  |
      | Program11 | C11     |
      | Program11 | shared1 |
      | Program11 | shared2 |
      | Program11 | shared3 |
      | Program21 | C21     |
      | Program21 | shared1 |
      | Program21 | shared2 |
      | Program21 | shared3 |
    And the following tool program data "program_users" exist:
      | program   | user   |
      | Program11 | user11 |
      | Program11 | user12 |
      | Program21 | user21 |
    When I log in as "user11"
    And I press "Enrol" for the "Course11" program course
    And I navigate to course participants
    Then "User 11" row "Groups" column of "participants" table should contain "No groups"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse1 nogroups" program course
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse2 visgroups" program course
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse3 sepgroups" program course
    And I log out
    And I log in as "user12"
    And I press "Enrol" for the "Sharedcourse1 nogroups" program course
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse2 visgroups" program course
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse3 sepgroups" program course
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1"
    And I log out
    And I log in as "user21"
    And I press "Enrol" for the "Sharedcourse1 nogroups" program course
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse2 visgroups" program course
    And I navigate to course participants
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I should not see "User 1"
    And I click on "Group: Tenant2" "text" in the ".form-autocomplete-selection" "css_element"
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1"
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse3 sepgroups" program course
    And I navigate to course participants
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I should not see "User 1"
    And I click on "Group: Tenant2" "text" in the ".form-autocomplete-selection" "css_element"
    And "User 21" row "Groups" column of "participants" table should contain "Tenant2"
    And I should not see "User 1"
    And I log out

  Scenario: Users in the same course enrolled via different programs can be added to different groups
    Given the following tool program data "program_courses" exist:
      | program   | course  |
      | Program31 | C31     |
      | Program32 | C31     |
      | Program31 | shared3 |
      | Program32 | shared3 |
    And the following tool program data "program_users" exist:
      | program   | user   |
      | Program31 | user31 |
      | Program32 | user32 |
    When I log in as "user31"
    And I press "Enrol" for the "Course31" program course
    And I navigate to course participants
    Then "User 31" row "Groups" column of "participants" table should contain "Program31"
    And I should not see "Tenant"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse3 sepgroups" program course
    And I navigate to course participants
    And "User 31" row "Groups" column of "participants" table should contain "Tenant3 - Program31"
    And I log out
    When I log in as "user32"
    And I press "Enrol" for the "Course31" program course
    And I navigate to course participants
    And "User 31" row "Groups" column of "participants" table should contain "Program31"
    And "User 32" row "Groups" column of "participants" table should contain "Program32"
    And I should not see "Tenant"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse3 sepgroups" program course
    And I navigate to course participants
    And "User 32" row "Groups" column of "participants" table should contain "Tenant3 - Program32"
    And I should not see "User 31"
    And I log out
