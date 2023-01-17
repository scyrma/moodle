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
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Tags | Program name       |
      | Certification1     |      | A program fullname |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification2     |      |
      | Certification3     |      |
    # Archive certification
    Then I press "Archive" action in the "Certification1" report row
    Then I click on "Archive" "button" in the "Confirm" "dialogue"
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
    And I navigate to "Archived" in current page administration
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Archived on      |
      | Certification1     | ##today##%d/%m/%y## |
      | Certification3     |                  |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Archived on      |
      | Certification2     |                  |
    # Restore certification
    Then I press "Restore" action in the "Certification1" report row
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Archived on   |
      | Certification1     |               |
      | Certification2     |               |
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Archived on      |
      | Certification3     |                  |
    Then I navigate to "Active" in current page administration
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification1     |      |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification2     |      |
      | Certification3     |      |
    Then I log out
    # Manager from tenant 2
    And I log in as "user21"
    And I navigate to "Certifications" in workplace launcher
    And I should see "Nothing to display"
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification2     |      |
      | Certification3     |      |
    Then I navigate to "Archived" in current page administration
    And I should not see "Nothing to display"
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Archived on   |
      | Certification2     |               |
    And the following should not exist in the "reportbuilder-table" table:
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
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification2     |      |
    Then I press "Archive" action in the "Certification1" report row
    Then I click on "Archive" "button" in the "Confirm" "dialogue"
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification2     |      |
    Then I navigate to "Archived" in current page administration
    And the following should exist in the "reportbuilder-table" table:
      | Certification name | Archived on      |
      | Certification1     | ##today##%d/%m/%y## |
    Then I press "Delete" action in the "Certification1" report row
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    Then I navigate to "Archived" in current page administration
    And I should not see "Certification1"
    Then I navigate to "Active" in current page administration
    And I should not see "Certification1"
    Then I log out

  Scenario: Use certification manager filters
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
      | Program2 | 0        | Tenant1  |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | certification_tags  | program  |
      | Certification1 | 0        | Tenant1 |  red                | Program1 |
      | Certification2 | 0        | Tenant1 |  blue               | Program1 |
      | Certification3 | 0        | Tenant1 |  blue               | Program2 |
    When I log in as "user11"
    And I change window size to "large"
    And I navigate to "Certifications" in workplace launcher
    And the following should exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name  |
      | Certification1      | red    | Program1      |
      | Certification2      | blue   | Program1      |
      | Certification3      | blue   | Program2      |
    And I click on "Filters" "button"
    And I set the following fields in the "Certification name" "core_reportbuilder > Filter" to these values:
      | Certification name operator | Is equal to |
      | Certification name value    | Certification2   |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name  |
      | Certification2      | blue   | Program1      |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name  |
      | Certification1      | red    | Program1      |
      | Certification3      | blue   | Program2      |
    Then I click on "Reset" "button" in the "[data-region='report-filters']" "css_element"
    And I set the following fields in the "Program name" "core_reportbuilder > Filter" to these values:
      | Program name operator | Contains  |
      | Program name value    | Program2  |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name  |
      | Certification3      | blue   | Program2      |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name  |
      | Certification1      | red    | Program1      |
      | Certification2      | blue   | Program1      |
    Then I click on "Reset" "button" in the "[data-region='report-filters']" "css_element"
    And I set the field "Tags" to "red"
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name     |
      | Certification1      | red    | Program1         |
    And the following should not exist in the "reportbuilder-table" table:
      | Certification name  | Tags   | Program name     |
      | Certification2      | blue   | Program1         |
      | Certification3      | blue   | Program2         |

  Scenario: Archive certification from kebab action menu
    Given the following "tool_certification > certifications" exist:
      | fullname       | tenant  |
      | Certification1 | Tenant1 |
      | Certification2 | Tenant1 |
    When I log in as "user11"
    And I navigate to "Certifications" in workplace launcher
    Then I should see "Certification1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Certification2" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "Certification1" "link" in the "Certification1" "table_row"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I choose "Archive" in the open action menu
    And I click on "Archive" "button" in the "Confirm" "dialogue"
    And I wait to be redirected
    Then I should see "Archived certifications"
    And I should see "Certification1" in the "[data-region='reportbuilder-table']" "css_element"
    And I navigate to "Active" in current page administration
    And I should see "Active certifications"
    And I should not see "Certification1" in the "[data-region='reportbuilder-table']" "css_element"
    And I log out

  Scenario: Duplicate certification from kebab action menu
    Given the following "tool_certification > certifications" exist:
      | fullname       | tenant  | idnumber |
      | Certification1 | Tenant1 |          |
      | Certification2 | Tenant1 |          |
    When I log in as "user11"
    And I navigate to "Certifications" in workplace launcher
    Then I should see "Certification1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should not see "Certification1 Copy" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Certification2" in the "[data-region='reportbuilder-table']" "css_element"
    Then I click on "Certification1" "link" in the "Certification1" "table_row"
    And I click on "Actions" "icon" in the "#page-header" "css_element"
    And I choose "Duplicate" in the open action menu
    And I click on "OK" "button" in the "Confirm" "dialogue"
    And I set the field "fullname" to "Certification Copy 1"
    And I click on "Save" "button" in the ".modal.show .modal-footer" "css_element"
    And I wait to be redirected
    Then I should see "Certification Copy 1" in the "#page-header" "css_element"
    And I navigate to "Certifications" in workplace launcher
    And I should see "Certification1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Certification Copy 1" in the "[data-region='reportbuilder-table']" "css_element"
    And I should see "Certification2" in the "[data-region='reportbuilder-table']" "css_element"
    And I log out
