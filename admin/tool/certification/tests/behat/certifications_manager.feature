@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Ensure that actions in certifications manager view work as expected
  In order to ensure actions in certifications list work
  As a manager
  I need to test archive, restore and delete from the certifications manager view

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | user11   | tool_certification_manager | System       |           |
      | user21   | tool_certification_manager | System       |           |
      | user11   | tool_program_manager       | System       |           |

  Scenario: Archive and restore one certification
    Given the following "tool_program > programs" exist:
      | fullname           | archived | tenant  | generatecourses |
      | A program fullname | 0        | Tenant1 | 1               |
      | Program1           | 0        | Tenant1 | 0               |
      | Program2           | 0        | Tenant2 | 0               |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program            |
      | Certification1 | 0        | Tenant1 | A program fullname |
      | Certification2 | 1        | Tenant2 | Program2           |
      | Certification3 | 1        | Tenant1 | Program1           |
    When I log in as "user11"
    And I navigate to "Certifications" in workplace launcher
    Then I should see "Active certifications"
    And the following should exist in the "report-table" table:
      | Certification name | Tags | Program name       |
      | Certification1     |      | A program fullname |
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification2     |      |
      | Certification3     |      |
    # Archive certification
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    Then I click on "Archive" "button" in the ".modal-dialog" "css_element"
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
    And I click on "Archived" "tool_wp > Tab"
    And the following should exist in the "report-table" table:
      | Certification name | Archived on      |
      | Certification1     | ##today##%d/%m/%y## |
      | Certification3     |                  |
    And the following should not exist in the "report-table" table:
      | Certification name | Archived on      |
      | Certification2     |                  |
    # Restore certification
    Then I click on "Restore" "link" in the "Certification1" "table_row"
    And the following should not exist in the "report-table" table:
      | Certification name | Archived on   |
      | Certification1     |               |
      | Certification2     |               |
    And the following should exist in the "report-table" table:
      | Certification name | Archived on      |
      | Certification3     |                  |
    Then I click on "Active" "tool_wp > Tab"
    And the following should exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification2     |      |
      | Certification3     |      |
    Then I log out
    # Manager from tenant 2
    And I log in as "user21"
    And I navigate to "Certifications" in workplace launcher
    And I should see "Nothing to display"
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification2     |      |
      | Certification3     |      |
    Then I click on "Archived" "tool_wp > Tab"
    And I should not see "Nothing to display"
    And the following should exist in the "report-table" table:
      | Certification name | Archived on   |
      | Certification2     |               |
    And the following should not exist in the "report-table" table:
      | Certification name | Archived on   |
      | Certification1     |               |
      | Certification3     |               |
    Then I log out

  Scenario: Delete one certification
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  |
      | Certification1 | 0        | Tenant1 |
      | Certification2 | 1        | Tenant2 |
    When I log in as "user11"
    Then I navigate to "Certifications" in workplace launcher
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification2     |      |
    Then I click on "Archive" "link" in the "Certification1" "table_row"
    Then I press "Archive"
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification2     |      |
    Then I click on "Archived" "tool_wp > Tab"
    And the following should exist in the "report-table" table:
      | Certification name | Archived on      |
      | Certification1     | ##today##%d/%m/%y## |
    Then I click on "Delete" "link" in the "Certification1" "table_row"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I click on "Archived" "tool_wp > Tab"
    And I should not see "Certification1"
    Then I click on "Active" "tool_wp > Tab"
    And I should not see "Certification1"
    Then I log out

  Scenario: Test certification edit details modal
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
      | Program2 | 0        | Tenant1  |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | idnumber |
      | Certification1 | 0        | Tenant1 |  num1    |
      | Certification2 | 0        | Tenant1 |  num2    |
      | Certification3 | 0        | Tenant2 |  num3    |
    When I log in as "user11"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And the following should exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification2     |      |
    And the following should not exist in the "report-table" table:
      | Certification name      | Tags |
      | Certification3          |      |
    Then I click on "Edit details" "link" in the "Certification1" "table_row"
    And I should see "Edit certification"
    And I should see "Certification full name"
    And I should see "Certification ID number"
    And I should see "Certification tags"
    And I set the following fields to these values:
      | fullname    | Certification1new   |
      | idnumber    | 123                 |
    Then I press "Save"
    And I should see "Certification1new"
    Then I click on "Edit content" "link" in the "Certification1new" "table_row"
    And I should not see "Details"
    And I should see "Users"
    Then I click on "Edit details" "button"
    And I should see "Edit certification" in the ".modal-dialog .modal-title" "css_element"
    Then I set the field "fullname" to "newCertification1"
    Then I click on "Save" "button" in the "Edit certification 'Certification1new'" "dialogue"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "New certification" "link"
    Then I should see "New certification"
    And I set the field "Certification full name" to "Certification example 4"
    And I set the field "Select program" to "Program1"
    Then I press "Save"
    Then I navigate to "Certifications" in workplace launcher
    And the following should exist in the "report-table" table:
      | Certification name      | Tags |
      | newCertification1       |      |
      | Certification2          |      |
      | Certification example 4 |      |
    And the following should not exist in the "report-table" table:
      | Certification name      | Tags |
      | Certification3          |      |
    And I log out
    When I log in as "user21"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I should see "Active certifications"
    And the following should exist in the "report-table" table:
      | Certification name | Tags |
      | Certification3     |      |
    And the following should not exist in the "report-table" table:
      | Certification name      | Tags |
      | Certification1          |      |
      | Certification2          |      |
      | Certification example 4 |      |
    And I log out
