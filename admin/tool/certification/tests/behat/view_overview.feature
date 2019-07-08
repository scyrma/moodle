@tool @tool_certification @moodleworkplace @theme_workplace
Feature: View certifications on overview
  In order to see the certifications and programs
  As a student
  I can view my programs on the dashboard

  Background:
    Given the following "users" exist:
      | username | firstname | email                |
      | student1 | Student   | student1@example.com |

  Scenario: User is not allocated to any program or certification
    When I log in as "student1"
    Then I should see "Course overview"
    And I should not see "Programs"
    And I log out

  Scenario: User is allocated to some certifications
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | student1 | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant1 |
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
      | Certification2 | 0        | Tenant1 | Program2  |
    Given the following users allocations to certifications exist:
      | certification  | user      |
      | Certification1 | student1  |
      | Certification2 | student1  |

    When I log in as "student1"
    Then I should see "Programs"
    And I should see "Program1"
    And I should see "Certification1"
    And I should see "Program2"
    And I should see "Certification2"
    And I log out