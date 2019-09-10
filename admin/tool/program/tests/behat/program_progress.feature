@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Complete a program and check if user and manager can view the correct status
  In order to test that user and manager view the correct status
  As a manager
  I need to create a program and complete it

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_program_manager     | System       |           |
      | manager1 | tool_dynamicrule_manager | System       |           |
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    Given the following users allocations to programs exist:
      | program  | user    |
      | Program1 | user11  |
      | Program1 | user12  |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1 | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2  | Position_t1_f1 |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user   | department      | position       |
      | user13 | Deparment_t1_d1 | Position_t1_f1 |
      | user11 | Deparment_t1_d1 | Position_t1_f2 |
      | user12 | Deparment_t1_d1 | Position_t1_f2 |

  Scenario: User completes program and user and manager can view the correct program status
    When I log in as "manager1"
    And I change window size to "large"
    And I follow "Manage private files..."
    And I upload "lib/editor/atto/tests/fixtures/moodle-logo.png" file to "Files" filemanager
    And I click on "Save changes" "button"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    Then I click on "Edit details" "button"
    And I upload "admin/tool/program/tests/fixtures/program_image.png" file to "Program image" filemanager
    And I click on "Insert or edit image" "button" in the "[data-fieldtype=editor]" "css_element"
    And I click on "Browse repositories..." "button"
    And I click on "Private files" "link" in the ".moodle-dialogue-base[aria-hidden='false'] .fp-repo-area" "css_element"
    And I click on "moodle-logo.png" "link"
    And I click on "Select this file" "button" in the ".moodle-dialogue-base[aria-hidden='false']" "css_element"
    And I set the field "Describe this image for someone who cannot see it" to "How to submit"
    And I click on "Save image" "button"
    Then I press "Save" in the modal form dialogue
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    Then I click on "Course11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select courses"
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
    And I trigger cron
    When I log in as "user11"
    And I should see "100%"
    And I should see "Completed"
    And I log out
    When I log in as "user13"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Allocate users" "link" in the "Program1" "table_row"
    Then I should see "Completed" in the "User 11" "table_row"
    Then I should see "Open" in the "User 12" "table_row"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on "Progress report" "link" in the "Program1" "table_row"
    And I should see "User 11"
    And I should see "Completed" in the "User 11" "table_row"
    And I should see "100%" in the "User 11" "table_row"
    And I should see "User 12"
    And I should see "Open" in the "User 12" "table_row"
    And I should see "0%" in the "User 12" "table_row"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User 11"
    And I click on "//button[contains(.,'User 11')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Completed programs: 1"
    And I click on "Completed programs: 1" "link"
    And I should see "Programs : User 11"
    And I should see "Program1"
    And I should see "Completed" in the "Program1" "table_row"
    And I should see "100%" in the "Program1" "table_row"
    And I log out

  Scenario: User completes program and admin resets program completion for this user
    Given the following users allocations to tenants exist:
      | user  | tenant  |
      | admin | Tenant1 |
    When I log in as "manager1"
    And I change window size to "large"
    And I follow "Manage private files..."
    And I upload "lib/editor/atto/tests/fixtures/moodle-logo.png" file to "Files" filemanager
    And I click on "Save changes" "button"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    Then I click on "Edit details" "button"
    And I upload "admin/tool/program/tests/fixtures/program_image.png" file to "Program image" filemanager
    And I click on "Insert or edit image" "button" in the "[data-fieldtype=editor]" "css_element"
    And I click on "Browse repositories..." "button"
    And I click on "Private files" "link" in the ".moodle-dialogue-base[aria-hidden='false'] .fp-repo-area" "css_element"
    And I click on "moodle-logo.png" "link"
    And I click on "Select this file" "button" in the ".moodle-dialogue-base[aria-hidden='false']" "css_element"
    And I set the field "Describe this image for someone who cannot see it" to "How to submit"
    And I click on "Save image" "button"
    Then I press "Save" in the modal form dialogue
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    Then I click on "Course11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select courses"
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
    And I trigger cron
    When I log in as "user11"
    And I should see "100%"
    And I should see "Completed"
    And I log out
    And I log in as "admin"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".allocate_users" "css_element" in the "Program1" "table_row"
    And I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Completed" in the "User 11" "table_row"
    And I click on "Program reset" "link" in the "User 11" "table_row"
    And I click on "Yes" "button" in the ".confirmation-dialogue" "css_element"
    And I click on "Users" "link" in the ".wptabs" "css_element"
    And "Program reset" "link" should not exist in the "User 11" "table_row"
    And I log out
    And I trigger cron
    And I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".allocate_users" "css_element" in the "Program1" "table_row"
    And I click on "Users" "link" in the ".wptabs" "css_element"
    And "Program reset" "link" should exist in the "User 11" "table_row"
    And I should not see "Completed" in the "User 11" "table_row"
    And I should see "Open" in the "User 11" "table_row"
    And I log out