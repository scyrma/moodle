@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Complete a certification and check if user and manager can view the correct status
  In order to test that user and manager view the correct status even after we archive and restore it
  As a manager
  I need to create a certification and complete it

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_program_manager       | System       |           |
      | manager1 | tool_certification_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
    And the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program1  | C11     |
    Given the following "tool_certification > certifications" exist:
      | fullname        | tenant  | program  |
      | Noughts&Crosses | Tenant1 | Program1 |
    Given the following "tool_certification > certification_users" exist:
      | certification   | user    |
      | Noughts&Crosses | user11  |
      | Noughts&Crosses | user12  |
    And the following "tool_program > program_completions" exist:
      | program   | user    |
      | Program1  | user11  |
    Given user "user13" has a department lead position over users "user11,user12" with permissions "7"

  Scenario: User completes certification and user and manager can view the correct certification status
    When I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    And I press "Users" action in the "Noughts&Crosses" report row
    And the following should exist in the "reportbuilder-table" table:
      | First name/Surname  | Certification status  | Program status  |
      | User 11             | Certified             | -               |
      | User 12             | Open                  | Open            |
    And I log out
    And I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I press "Users" action in the "Noughts&Crosses" report row
    And the following should exist in the "reportbuilder-table" table:
      | First name/Surname  | Certification status  | Program status  |
      | User 11             | Certified             | -               |
      | User 12             | Open                  | Open            |
    And I log out
    And I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    Then I press "Progress report" action in the "Noughts&Crosses" report row
    And the following should exist in the "reportbuilder-table" table:
      | First name/Surname  | Certification status  | Program progress  |
      | User 11             | Certified             | -                 |
      | User 12             | Open                  | 0%                |
    Then I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 11" "table_row"
    And I should see "Certifications" in the "User 11" "table_row"
    And I should see "Noughts&Crosses · 100% completed" in the "User 11" "table_row"
    And I click on "//span[text()='Certifications']/following::span/a[text()='Full report']" "xpath_element"
    And I should see "User 11"
    And I should see "Noughts&Crosses"
    And I should see "Certified" in the "Noughts&Crosses" "table_row"
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 11" "table_row"
    And I click on "See profile" "link" in the "User 11" "table_row"
    And I click on "1 certified certifications" "link"
    And I should see "User 11"
    And I should see "Noughts&Crosses"
    And I should see "Certified" in the "Noughts&Crosses" "table_row"
    And I log out
