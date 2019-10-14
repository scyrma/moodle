@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Ensure calendar events are created when user is allocated into a certification
  In order to check calendar events from a certification
  As a user
  I need to be able to see the events in my calendar when I am allocated to a certification

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | user1    | student                    | System       |           |

  Scenario: Create a certification with basic information
    When I log in as "manager1"
    And I navigate to "Courses > Certifications" in site administration
    Then I should see "Certifications"
    Then I click on "Add new certification" "link"
    Then I should see "New certification"
    And I set the field "Certification full name" to "Certification example 1"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program1" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    And I select "Allocation date" from the "startdatetype" singleselect
    And I set the field "duedaterelative[number]" to "1"
    And I select "weeks" from the "duedaterelative[timeunit]" singleselect
    And I select "After due date" from the "expirydatetype" singleselect
    And I set the field "expirydaterelative[number]" to "1"
    And I select "week" from the "expirydaterelative[timeunit]" singleselect
    Then I press "Save" in the modal form dialogue
    And I navigate to "Courses > Certifications" in site administration
    Then I click on ".edit_certification" "css_element" in the "Certification example 1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Users"
    And I should see "Nothing to display"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "User 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I log out
    Then I log in as "user1"
    And I am viewing site calendar
    And I view the calendar for "1" more weeks
    And I should see "Due date for certification Certification example 1"
    And I am viewing site calendar
    And I view the calendar for "2" more weeks
    And I should not see "Expiry date for certification Certification example 1"
    And I log out
    Then I log in as "manager1"
    And I navigate to "Courses > Certifications" in site administration
    Then I click on ".allocate_users" "css_element" in the "Certification example 1" "table_row"
    Then I click on ".confirm_certify_user" "css_element" in the "User 1" "table_row"
    Then I press "Certify"
    And I log out
    Then I log in as "user1"
    And I am viewing site calendar
    And I view the calendar for "1" more weeks
    And I should see "Due date for certification Certification example 1"
    And I am viewing site calendar
    And I view the calendar for "2" more weeks
    And I should see "Expiry date for certification Certification example 1"
    And I log out