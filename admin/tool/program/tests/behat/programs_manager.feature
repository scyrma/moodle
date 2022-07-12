@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Ensure that actions in programs manager view work as expected
  In order to ensure actions in programs list work
  As a manager
  I need to test archive, restore and delete from the programs manager view

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
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
    Then I press "Archive" action in the "Program2" report row
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    Then I should not see "Program2"
    And I should see "Program3"
    # Change idnumber in Program 1 to match the one in Program 2
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Details" in current page administration
    And I set the field "Program ID number" to "num2"
    Then I press "Save changes"
    And I navigate to "Programs" in workplace launcher
    # Program1 contains non archived certifications therefore cannot be archived
    And "Archive" "link" should not exist in the "Program1" "table_row"
    # Program3 contains one archived certifications therefore can be archived
    And "Archive" "link" should exist in the "Program3" "table_row"
    Then I click on "Archived" "link"
    Then I should not see "Program1"
    And the following should exist in the "reportbuilder-table" table:
      | Program name | Archived on         |
      | Program2     | ##today##%d/%m/%y## |
    # Restore Program1
    Then I press "Restore" action in the "Program2" report row
    And I should not see "Program1"
    And I should not see "Program2"
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    # Check that restore has clean up idnumber because was the same as Program1
    Then I click on "Program2" "link" in the "Program2" "table_row"
    And I navigate to "Details" in current page administration
    And the field "Program ID number" matches value ""
    Then I press "Save changes"
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
    Then I press "Delete" action in the "Program2" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I click on "Archived" "link"
    Then I should not see "Program2"
    And I should see "Program1"
    Then I press "Delete" action in the "Program1" report row
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
    Then I press "Delete" action in the "Program3" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should not see "Program3"
    And I log out
    And I log in as "admin"
    And I navigate to "Reports > Logs" in site administration
    And I press "Get these logs"
    And I log out

  @_file_upload
  Scenario: Test program edit details tab
    Given the following "blocks" exist:
      | blockname     | contextlevel | reference | pagetypepattern | defaultregion |
      | private_files | System       | 1         | my-index        | side-post     |
    And the following "tool_program > programs" exist:
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
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I navigate to "Details" in current page administration
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
    Then I press "Save changes"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Program1new"
    Then I click on "Program1new" "link" in the "Program1new" "table_row"
    And I should see "Details"
    And I should see "Content"
    And I should see "Schedule"
    And I should see "Users"
    And I navigate to "Details" in current page administration
    Then I set the field "fullname" to "newProgram1"
    Then I press "Save changes"
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
    And I change window size to "large"
    And I navigate to "Programs" in workplace launcher
    And the following should exist in the "reportbuilder-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program1      | red    | Certification1             |
      | Program2      | blue   |                            |
      | Program3      | blue   |                            |
    And I click on "Filters" "button"
    And I set the following fields in the "Program name" "core_reportbuilder > Filter" to these values:
      | Program name operator | Is equal to |
      | Program name value    | Program2   |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program2      | blue   |                            |
    And the following should not exist in the "reportbuilder-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program1      | red    | Certification1             |
      | Program3      | blue   |                            |
    Then I click on "Reset" "button" in the "[data-region='report-filters']" "css_element"
    And I set the following fields in the "Associated certification" "core_reportbuilder > Filter" to these values:
      | Associated certification operator | Contains        |
      | Associated certification value    | Certification1  |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program1      | red    | Certification1             |
    And the following should not exist in the "reportbuilder-table" table:
      | Program name  | Tags   | Associated certifications  |
      | Program2      | blue   |                            |
      | Program3      | blue   |                            |
    Then I click on "Reset" "button" in the "[data-region='report-filters']" "css_element"
#    TODO WP-3147 apply back when tags filter is available.
#    And I set the field "Tags" to "red"
#    Then the following should exist in the "reportbuilder-table" table:
#      | Program name  | Tags   | Associated certifications  |
#      | Program1      | red    | Certification1             |
#    And the following should not exist in the "reportbuilder-table" table:
#      | Program name  | Tags   | Associated certifications  |
#      | Program2      | blue   |                            |
#      | Program3      | blue   |                            |

  Scenario: Archive programs from kebab action menu
    Given the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
    And the following "tool_program > program_users" exist:
      | program  | user    |
      | Program1 | user12  |
      | Program2 | user12  |
    When I am on the "My courses" page logged in as "user12"
    And I should see "Program1" in the "region-main" "region"
    And I should see "Program2" in the "region-main" "region"
    And I log out
    Then I log in as "user11"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Program2" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I choose "Archive" in the open action menu
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I wait to be redirected
    Then I should see "Archived programs"
    And I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    And I navigate to "Active" in current page administration
    And I should see "Active programs"
    And I should not see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    And I log out

  Scenario: Duplicate programs from kebab action menu
    Given the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
    When I log in as "user11"
    Then I navigate to "Programs" in workplace launcher
    And I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should not see "Program1 Copy" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Program2" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I choose "Duplicate" in the open action menu
    And I click on "OK" "button" in the "Confirm" "dialogue"
    And I wait to be redirected
    Then I should see "Program1 Copy" in the "#page-header" "css_element"
    And I navigate to "Programs" in workplace launcher
    And I should see "Program1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Program1 Copy" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Program2" in the "[data-region='reportbuilder-table']" "css_element"
    And I log out
