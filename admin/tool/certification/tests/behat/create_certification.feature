@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Create certification
  In order to create a certification
  As a manager
  I need to add a certification from the Certification manager view and allocate a user

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
      | Program2 | 1        | Tenant1  |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | manager3 | Manager   | 3        | manager3@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
      | manager3 | Tenant1 |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1  | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0          |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2    | Position_t1_f1   |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user  | department     | position     |
      | manager1 | Deparment_t1_d1 | Position_t1_f1 |
      | manager3 | Deparment_t1_d1 | Position_t1_f1 |
      | user1 | Deparment_t1_d1 | Position_t1_f2 |
      | user2 | Deparment_t1_d1 | Position_t1_f2 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | manager2 | tool_certification_manager | System       |           |

  Scenario: Create a certification with basic information
    When I log in as "manager1"
    And I navigate to "Courses > Certifications" in site administration
    Then I should see "Certifications"
    Then I click on "Add new certification" "link"
    Then I should see "New certification"
    And I set the field "Certification full name" to "Certification example 1"
    And I set the field "Certification ID number" to "1"
    And I set the field "Certification tags" to "Tag example 1"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program1" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    And I select "Allocation date" from the "startdatetype" singleselect
    And I set the field "duedaterelative[number]" to "1"
    And I select "days" from the "duedaterelative[timeunit]" singleselect
    And I select "After allocation date" from the "expirydatetype" singleselect
    And I set the field "expirydaterelative[number]" to "1"
    And I select "days" from the "expirydaterelative[timeunit]" singleselect
    Then I press "Save" in the modal form dialogue
    And I navigate to "Courses > Certifications" in site administration
    And I should see "Certification name"
    And I should see "Tags"
    And I should see "Program"
    And I should see "Actions"
    And I should see "Certification example 1"
    And I should see "Tag example 1"
    Then I click on ".edit_certification" "css_element" in the "Certification example 1" "table_row"
    Then I click on "Schedule" "link"
    And I should see "Start date"
    And I should see "End date"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Users"
    And I should see "Nothing to display"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I should see "User 1" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Manager 3" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I should see "Full name"
    And I should see "Due date"
    And I should see "Allocation source"
    And I should see "Certification status"
    And I should see "Actions"
    Then I should see "User 1"
    Then I should see "Open" in the "User 1" "table_row"
    And I should see "Manual" in the "User 1" "table_row"
    Then I click on "Schedule" "link"
    And I select "Select date" from the "allocationstartdatetype" singleselect
    And I select "3" from the "allocationstartdateabsolute[day]" singleselect
    And I select "March" from the "allocationstartdateabsolute[month]" singleselect
    And I select "2050" from the "allocationstartdateabsolute[year]" singleselect
    Then I press "Save changes"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Users"
    And "Allocate users" "link" should not exist
    Then I click on "Dynamic rules" "link" in the "region-main" "region"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to certification"
    And I should see "Users that have status 'Certified' in certification"
    And I should see "Users that have status 'Overdue' in certification"
    And I navigate to "Courses > Certifications" in site administration
    And I log out
    Then I log in as "manager2"
    Then I should not see "Certifications"
    And I log out
    Then I log in as "manager3"
    Then I should not see "Certifications"
    And I log out

  Scenario: Create a certification using duplicate certification
    When I log in as "manager1"
    And I navigate to "Courses > Certifications" in site administration
    Then I should see "Certifications"
    Then I click on "Add new certification" "link"
    Then I should see "New certification"
    And I set the field "Certification full name" to "Certification example 1"
    And I set the field "Certification ID number" to "1"
    And I set the field "Certification tags" to "Tag example 1"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program1" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    And I select "Allocation date" from the "startdatetype" singleselect
    And I set the field "duedaterelative[number]" to "1"
    And I select "days" from the "duedaterelative[timeunit]" singleselect
    And I select "After allocation date" from the "expirydatetype" singleselect
    And I set the field "expirydaterelative[number]" to "1"
    And I select "days" from the "expirydaterelative[timeunit]" singleselect
    Then I press "Save" in the modal form dialogue
    And I navigate to "Courses > Certifications" in site administration
    And I should see "Certification example 1"
    Then I click on ".duplicate_certification" "css_element" in the "Certification example 1" "table_row"
    Then I click on "OK" "button" in the ".confirmation-dialogue" "css_element"
    And I should see "New certification"
    And I should see "Certification example 1"
    And I set the field "Certification full name" to "Certification example 2B"
    And I should see "Tag example 1"
    And I should see "Program1" in the ".select_program_field" "css_element"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should not see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I press "Save" in the modal form dialogue
    And I should see "This ID number is already used in another certification"
    And I set the field "Certification ID number" to "new1"
    Then I press "Save" in the modal form dialogue
    And I should see "Allocation window"
    And I navigate to "Courses > Certifications" in site administration
    And I should see "Certification example 1"
    And I should see "Certification example 2B"
    And I log out