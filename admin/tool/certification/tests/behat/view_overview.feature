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
    Then "Learning" "tool_wp > Active tab" should exist
    And I should see "Nothing to display" in the "Learning" "tool_wp > Tab content"
    And I log out

  @javascript
  Scenario: User is allocated to some certifications
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | student1 | Tenant1 |
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  | program_tags |
      | Program1 | 0        | Tenant1 | black        |
      | Program2 | 0        | Tenant1 | red          |
    And the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program   | certification_tags |
      | Certification1 | 0        | Tenant1 | Program1  | white              |
      | Certification2 | 0        | Tenant1 | Program2  | red, green         |
    And the following "tool_certification > certification_users" exist:
      | certification  | user      |
      | Certification1 | student1  |
      | Certification2 | student1  |
    When I log in as "student1"
    Then "Learning" "tool_wp > Active tab" should exist
    And I should see "Program1" in the "Learning" "tool_wp > Tab content"
    And I should see "Certification1" in the "Learning" "tool_wp > Tab content"
    And I should see "Program2" in the "Learning" "tool_wp > Tab content"
    And I should see "Certification2" in the "Learning" "tool_wp > Tab content"
    And I click on "Program information" "button" in the "Program2" "tool_program > Dashboard item"
    And I should see "Certification2" in the "Program2" "dialogue"
    And I should see "red" in the "Program2" "dialogue"
    And I should see "green" in the "Program2" "dialogue"
    And I click on "Close" "button" in the "Program2" "dialogue"
    And I click on "Display" "button" in the "#wpdashboardContent" "css_element"
    And I follow "Collapsed"
    And I click on "Certifications" "button" in the "Program2" "tool_program > Dashboard item"
    And I should see "Certification2" in the "Program2" "dialogue"
    And I click on "Close" "button" in the "Program2" "dialogue"
    And I log out
