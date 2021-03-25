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
    Given the following "tool_certification > certifications" exist:
      | fullname        | tenant  | program  |
      | Noughts&Crosses | Tenant1 | Program1 |
    Given the following "tool_certification > certification_users" exist:
      | certification   | user    |
      | Noughts&Crosses | user11  |
      | Noughts&Crosses | user12  |
    Given user "user13" has a department lead position over users "user11,user12" with permissions "7"

  Scenario: User completes certification and user and manager can view the correct certification status
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should see "Program1"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    And I set the field "Select courses" to "Course11"
    And I press "Save changes"
    And I log out
    When I log in as "user11"
    Then I click on ".enrol_to_course" "css_element"
    And I should see "Course11"
    And I should see "Announcements"
    And I should see "Topic 1"
    And I should see "Topic 2"
    And I click on "[data-modulename='URL1']" "css_element"
    And I click on "[data-modulename='URL2']" "css_element"
    And I log out
    And I run the scheduled task "core\task\completion_regular_task"
    And I wait "1" seconds
    And I run the scheduled task "core\task\completion_regular_task"
    When I log in as "user11"
    And I should see "100%"
    And I should see "Completed"
    And I should see "\"Noughts&Crosses\" is completed."
    And I log out
    When I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Users" "link" in the "Noughts&Crosses" "table_row"
    And I should see "-" in the "User 11" "table_row"
    And I should see "Certified" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I should not see "Certified" in the "User 12" "table_row"
    And I log out
    And I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Archive" "link" in the "Noughts&Crosses" "table_row"
    Then I click on "Archive" "button" in the ".modal-dialog" "css_element"
    Then I click on "Archived" "link"
    And I should see "Noughts&Crosses"
    Then I click on "Restore" "link" in the "Noughts&Crosses" "table_row"
    And I should not see "Noughts"
    Then I click on "Active" "link"
    And I click on "Users" "link" in the "Noughts&Crosses" "table_row"
    And I should see "-" in the "User 11" "table_row"
    And I should see "Certified" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I should not see "Certified" in the "User 12" "table_row"
    And I log out
    And I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Progress report" "link" in the "Noughts&Crosses" "table_row"
    And I should see "User 11"
    And I should see "Certified" in the "User 11" "table_row"
    And I should see "User 12"
    And I should see "Open" in the "User 12" "table_row"
    And I should see "0%" in the "User 12" "table_row"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I press "User 11"
    And I click on "1 certified certifications" "link"
    And I should see "Certifications : User 11"
    And I should see "Noughts&Crosses"
    And I should see "Certified" in the "Noughts&Crosses" "table_row"
    And I log out
