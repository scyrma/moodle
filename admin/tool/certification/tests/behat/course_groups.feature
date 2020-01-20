@tool @tool_certification @moodleworkplace @javascript
Feature: Users need to be added to course groups when allocated to certification
  In order to better monitor certification users in courses
  As a manager
  I need to be able to specify how groups should be created in program courses

  Background:
    Given "1" tenants exist with "4" users and "2" courses in each
    And the following "courses" exist:
      | fullname     | shortname    | summary | groupmode | category |
      | Sharedcourse | sharedcourse |         | 1         | 0        |
    And the following tool program data "programs" exist:
      | fullname  | tenant  | completioncriteria | autocreategroups |
      | Program11 | Tenant1 | 1                  | 1                |
      | Program12 | Tenant1 | 1                  | 1                |
    And the following tool certification data "certifications" exist:
      | fullname        | tenant  | program   | autocreategroups |
      | Certification11 | Tenant1 | Program11 | -1               |
      | Certification12 | Tenant1 | Program12 | 5                |
      | Certification13 | Tenant1 | Program12 | 5                |

  Scenario: Users from different certifications may be added to the same or different groups in courses
    Given the following tool program data "program_courses" exist:
      | program   | course       |
      | Program11 | C11          |
      | Program11 | sharedcourse |
      | Program12 | C12          |
      | Program12 | sharedcourse |
    And the following tool certification data "certification_users" exist:
      | certification   | user   |
      | Certification11 | user11 |
      | Certification12 | user11 |
      | Certification12 | user12 |
      | Certification13 | user12 |
    When I log in as "user11"
    And I press "Enrol" for the "Course11" program course
    And I navigate to course participants
    Then "User 11" row "Groups" column of "participants" table should contain "No groups"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse" program course
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Tenant1"
    And I am on homepage
    And I press "Enrol" for the "Course12" program course
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Certification12"
    And I log out
    And I log in as "user12"
    And I press "Enrol" for the "Course12" program course
    And I navigate to course participants
    And "User 11" row "Groups" column of "participants" table should contain "Certification12"
    And "User 12" row "Groups" column of "participants" table should contain "Certification12"
    And "User 12" row "Groups" column of "participants" table should contain "Certification13"
    And I am on homepage
    And I press "Enrol" for the "Sharedcourse" program course
    And I navigate to course participants
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1 - Certification12"
    And "User 12" row "Groups" column of "participants" table should contain "Tenant1 - Certification13"
    And I log out
