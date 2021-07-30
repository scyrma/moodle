@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Ensure that actions in programs manager view work as expected
  In order to ensure actions in programs list work
  As a manager
  I need to test archive, restore and delete from the programs manager view

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | user11   | tool_program_manager | System       |           |
      | user21   | tool_program_manager | System       |           |

  Scenario: Archive and restore existing programs
    Given the following "tool_program > programs" exist:
      | fullname | tenant  | idnumber |
      | Program1 | Tenant1 | num1     |
      | Program2 | Tenant1 | num2     |
      | Program3 | Tenant1 | num3     |
    And the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
      | Certification2 | 0        | Tenant1 | Program1  |
      | Certification3 | 1        | Tenant1 | Program1  |
      | Certification4 | 1        | Tenant1 | Program3  |
    When I log in as "user11"
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archived" "link"
    And I should see "Nothing to display"
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    And I should see "Certification1" in the "Program1" "table_row"
    And I should see "Certification2" in the "Program1" "table_row"
    And I should see "Certification3" in the "Program1" "table_row"
    And I should see "Program3"
    And I should see "Certification4" in the "Program3" "table_row"
    Then I click on "Archive" "link" in the "Program2" "table_row"
    Then I press "Archive"
    Then I should not see "Program2"
    And I should see "Program3"
    # Change idnumber in Program 1 to match the one in Program 2
    Then I click on "Edit details" "link" in the "Program1" "table_row"
    And I set the field "Program ID number" to "num2"
    Then I press "Save"
    # Program1 contains non archived certifications therefore cannot be archived
    And "Archive" "link" should not exist in the "Program1" "table_row"
    # Program3 contains one archived certifications therefore can be archived
    And "Archive" "link" should exist in the "Program3" "table_row"
    Then I click on "Archived" "link"
    Then I should not see "Program1"
    And the following should exist in the "report-table" table:
      | Program name | Archived on         |
      | Program2     | ##today##%d/%m/%y## |
    # Restore Program1
    Then I click on "Restore" "link" in the "Program2" "table_row"
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    # Check that restore has clean up idnumber because was the same as Program1
    Then I click on "Edit details" "link" in the "Program2" "table_row"
    And the field "Program ID number" matches value ""
    Then I press "Save"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  Scenario: Delete existing archived programs
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 1        | Tenant1 |
      | Program2 | 1        | Tenant1 |
      | Program3 | 1        | Tenant2 |
    When I log in as "user11"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archived" "link"
    And I should see "Program1"
    And I should see "Program2"
    And I should not see "Program3"
    Then I click on "Delete" "link" in the "Program2" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I click on "Archived" "link"
    Then I should not see "Program2"
    And I should see "Program1"
    Then I click on "Delete" "link" in the "Program1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I click on "Archived" "link"
    Then I should not see "Program1"
    And I should see "Nothing to display"
    And I log out
    Then I log in as "user21"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Archived" "link"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should see "Program3"
    Then I click on "Delete" "link" in the "Program3" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Program3"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @_file_upload
  Scenario: Test program edit details modal
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant1 |
      | Program3 | 0        | Tenant2 |
    When I log in as "user11"
    And I follow "Manage private files..."
    And I upload "lib/editor/atto/tests/fixtures/moodle-logo.png" file to "Files" filemanager
    And I click on "Save changes" "button"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should see "Active programs"
    And I should see "Program2"
    And I should not see "Program3"
    Then I click on "Edit details" "link" in the "Program1" "table_row"
    And I should see "Edit program"
    And I should see "Program name"
    Then I set the field "fullname" to "Program1new"
    And I upload "admin/tool/program/tests/fixtures/program_image.png" file to "Program image" filemanager
    And I click on "Insert or edit image" "button" in the "[data-fieldtype=editor]" "css_element"
    And I click on "Browse repositories..." "button"
    And I click on "Private files" "link" in the ".moodle-dialogue-base[aria-hidden='false'] .fp-repo-area" "css_element"
    And I click on "moodle-logo.png" "link"
    And I click on "Select this file" "button" in the ".moodle-dialogue-base[aria-hidden='false']" "css_element"
    And I set the field "Describe this image for someone who cannot see it" to "How to submit"
    And I click on "Save image" "button"
    Then I press "Save"
    And I should see "Program1new"
    Then I click on "Edit content" "link" in the "Program1new" "table_row"
    And I should not see "Details"
    And I should see "Content"
    And I should see "Schedule"
    And I should see "Users"
    Then I click on "Edit details" "button"
    And I should see "Edit program" in the ".modal-dialog .modal-title" "css_element"
    Then I set the field "fullname" to "newProgram1"
    Then I press "Save"
    And I should see "Content"
    And I should see "Schedule"
    Then I navigate to "Programs" in workplace launcher
    And I should see "newProgram1"
    And I should see "Program2"
    And I should not see "Program3"
    And I log out
    When I log in as "user21"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    And I should see "Active programs"
    And I should not see "Program1"
    And I should not see "Program2"
    And I should see "Program3"
    And I log out

  Scenario: Use program manager filters
    Given the following "tool_program > programs" exist:
      | fullname | tenant  | program_tags  |
      | Program1 | Tenant1 | red           |
      | Program2 | Tenant1 | blue          |
      | Program3 | Tenant1 | blue          |
    And the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
    When I log in as "user11"
    And I navigate to "Programs" in workplace launcher
    And the following should exist in the "report-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program1      | red    | Certification1             |
      | Program2      | blue   |                            |
      | Program3      | blue   |                            |
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Program name field limiter" to "is equal to"
    And I set the field "Program name value" to "Program2"
    And I press the enter key
    Then the following should exist in the "report-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program2      | blue   |                            |
    And the following should not exist in the "report-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program1      | red    | Certification1             |
      | Program3      | blue   |                            |
    Then I press "Reset table"
    And I set the field "Associated certification field limiter" to "contains"
    And I set the field "Associated certification value" to "Certification1"
    And I press the enter key
    Then the following should exist in the "report-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program1      | red    | Certification1             |
    And the following should not exist in the "report-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program2      | blue   |                            |
      | Program3      | blue   |                            |
