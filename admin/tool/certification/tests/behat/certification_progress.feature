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
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    Given the following tool certification data "certifications" exist:
      | fullname       | tenant  | program  |
      | Certification1 | Tenant1 | Program1 |
    Given the following users allocations to certifications exist:
      | certification  | user    |
      | Certification1 | user11  |
      | Certification1 | user12  |
    Given user "user13" has a department manager position over users "user11,user12" with permissions "7"

  Scenario: User completes certification and user and manager can view the correct certification status
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should see "Program1"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course11" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    And I log out
    When I log in as "user11"
    Then I click on ".enrol_to_course" "css_element"
    And I should see "Course11"
    And I click on "Expand all" "button"
    And I should see "Announcements"
    And I should see "Topic 1"
    And I should see "Topic 2"
    And I click on "[data-modulename='URL1']" "css_element"
    And I click on "[data-modulename='URL2']" "css_element"
    And I log out
#    And I trigger cron
    When I log in as "user11"
    And I should see "100%"
    And I should see "Completed"
    And I should see "\"Certification1\"  certification is completed."
    And I log out
    When I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    And I click on "Allocate users" "link" in the "Certification1" "table_row"
    And I should see "-" in the "User 11" "table_row"
    And I should see "Certified" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I should not see "Certified" in the "User 12" "table_row"
    And I log out
    And I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    Then I click on "Archive" "button" in the ".confirmation-dialogue" "css_element"
    Then I click on "Archived" "link"
    And I should see "Certification1"
    Then I click on "Restore" "link" in the "Certification1" "table_row"
    And I should not see "Certification1"
    Then I click on "Active" "link"
    And I click on "Allocate users" "link" in the "Certification1" "table_row"
    And I should see "-" in the "User 11" "table_row"
    And I should see "Certified" in the "User 11" "table_row"
    And I should see "Open" in the "User 12" "table_row"
    And I should not see "Certified" in the "User 12" "table_row"
    And I log out
    And I log in as "user13"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Progress report" "link" in the "Certification1" "table_row"
    And I should see "User 11"
    And I should see "Certified" in the "User 11" "table_row"
    And I should see "User 12"
    And I should see "Open" in the "User 12" "table_row"
    And I should see "0%" in the "User 12" "table_row"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User 11"
    And I click on "//button[contains(.,'User 11')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Certified certifications: 1"
    And I click on "Certified certifications: 1" "link"
    And I should see "Certifications : User 11"
    And I should see "Certification1"
    And I should see "Certified" in the "Certification1" "table_row"
    And I log out