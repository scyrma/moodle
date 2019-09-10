@tool @tool_program @moodleworkplace @javascript
Feature: Manage users allocations in programs
  In order to see the users allocation in programs
  As a manager
  I need to see all existing allocations

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
      | Program3 | 0        | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | user3    | User      | 3        | user3@example.com    |
      | user4    | User      | 4        | user4@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant2 |
      | user3    | Tenant1 |
      | user4    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    Given user "manager1" has a department manager position over users "user1,user3" with permissions "3"

  Scenario: There are no existing allocations
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on ".allocate_users" "css_element" in the "Program1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    Then I should see "Users"
    Then I should see "Nothing to display"
    Then I log out

  Scenario: We allocate two users and delete one allocation
    When I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    And I should not see "Program2"
    Then I click on ".allocate_users" "css_element" in the "Program1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And I should see "User 1" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User 3" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 4" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "Manager 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    Then I should see "Users"
    And I should see "Full name" in the "table.report-table thead" "css_element"
    And I should see "Due date" in the "table.report-table thead" "css_element"
    And I should see "Allocation source" in the "table.report-table thead" "css_element"
    And I should see "Program status" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And I should see "User 1"
    Then I click on "Allocate users" "link"
    And I open the autocomplete suggestions list in the dialog
    And I should not see "User 1" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 2" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "User 3" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "User 4" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 3" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I should see "User 1"
    And I should see "User 3"
    Then I click on ".confirm_deallocate_user" "css_element" in the "User 1" "table_row"
    And I click on "Yes" "button" in the "Delete user allocation" "dialogue"
    Then I should see "Users"
    And I should not see "User 1"
    And I should see "User 3"
    Then I log out
    # Check notifications are triggered.
    Then I log in as "user3"
    And I am on site homepage
    When I click on ".popover-region-notifications" "css_element"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    Then I should see "Allocated to program 'Program1'"
    And I should see "You have been allocated to program 'Program1'"
    And I log out
    Then I log in as "user1"
    And I am on site homepage
    When I click on ".popover-region-notifications" "css_element"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    Then I should see "Deallocated from program 'Program1'"
    And I should see "You have been deallocated from program 'Program1'"
    And I log out

  Scenario: Cannot delete allocation if comes from a dynamic rule
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | manager1 | tool_program_manager       | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Program not completed
    And I follow "Program not completed"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program1" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program1" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to programs
    And I click on "Allocate users to programs" "link" in the "#ruleoutcomes" "css_element"
    And I click on ".form-autocomplete-downarrow" "css_element" in the ".select_program_field" "css_element"
    And I should see "Program3" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    Then I click on "Program3" "text" in the ".select_program_field .form-autocomplete-suggestions" "css_element"
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Go back to programs
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".allocate_users" "css_element" in the "Program1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    # Allocate user
    Then I click on "Allocate users" "link"
    And I open the autocomplete suggestions list in the dialog
    And I click on "User 1" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "User 3" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    Then I press "Save changes" in the modal form dialogue
    And I log out
    And I trigger cron
    Then I log in as "manager1"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".allocate_users" "css_element" in the "Program3" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "User 1"
    And ".edit_user" "css_element" should exist in the "User 1" "table_row"
    And ".confirm_deallocate_user" "css_element" should not exist in the "User 1" "table_row"
    And I log out

  Scenario: Cannot delete allocation if allocation window is closed
    Given the following users allocations to programs exist:
      | program  | user   |
      | Program1 | user1  |
      | Program1 | user3  |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Courses > Programs" in site administration
    Then I click on ".allocate_users" "css_element" in the "Program1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And ".edit_user" "css_element" should exist in the "User 1" "table_row"
    And ".confirm_deallocate_user" "css_element" should exist in the "User 1" "table_row"
    And ".edit_user" "css_element" should exist in the "User 3" "table_row"
    And ".confirm_deallocate_user" "css_element" should exist in the "User 3" "table_row"
    Then I click on "Schedule" "link"
    And I select "Select date" from the "allocationenddatetype" singleselect
    And I select "3" from the "allocationenddateabsolute[day]" singleselect
    And I select "March" from the "allocationenddateabsolute[month]" singleselect
    And I select "2019" from the "allocationenddateabsolute[year]" singleselect
    Then I press "Save changes"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And ".edit_user" "css_element" should exist in the "User 1" "table_row"
    And ".confirm_deallocate_user" "css_element" should not exist in the "User 1" "table_row"
    And ".edit_user" "css_element" should exist in the "User 3" "table_row"
    And ".confirm_deallocate_user" "css_element" should not exist in the "User 3" "table_row"
    And I log out
