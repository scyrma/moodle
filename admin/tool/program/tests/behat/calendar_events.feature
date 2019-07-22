@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Ensure calendar events are created when user is allocated into a program
  In order to check calendar events from a program
  As a user
  I need to be able to see the events in my calendar when I am allocated to a program

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
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
      | manager1 | tool_program_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | user1    | student                    | System       |           |

  Scenario: Create a program with basic information and check calendar events
    When I log in as "manager1"
    And I navigate to "Courses > Programs" in site administration
    Then I should see "Programs"
    Then I click on "Add new program" "link"
    Then I should see "New program"
    And I set the field "Program name" to "Program example 1"
    Then I press "Save" in the modal form dialogue
    Then I click on "Schedule" "link"
    And I should see "Allocation window"
    And I select "After user allocation date" from the "duedatetype" singleselect
    Then I set the field "duedaterelative[number]" to "1"
    And I select "weeks" from the "duedaterelative[timeunit]" singleselect
    And I select "After due date" from the "enddatetype" singleselect
    Then I set the field "enddaterelative[number]" to "1"
    And I select "weeks" from the "enddaterelative[timeunit]" singleselect
    Then I press "Save changes"
    And I navigate to "Courses > Programs" in site administration
    Then I click on ".edit_program" "css_element" in the "Program example 1" "table_row"
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
    And I should see "Due date for program Program example 1"
    And I am viewing site calendar
    And I view the calendar for "2" more weeks
    And I should see "End date for program Program example 1"
    And I log out