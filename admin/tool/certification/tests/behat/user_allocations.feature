@tool @tool_certification @moodleworkplace @javascript
Feature: Manage users allocations
  In order to see the users allocation
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
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
      | Certification2 | 0        | Tenant2 | Program2  |
      | Certification3 | 0        | Tenant1 | Program1  |
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
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | manager1 | tool_certification_manager | System       |           |
    Given user "manager1" has a department manager position over users "user1,user3" with permissions "3"

  Scenario: There are no existing allocations
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I should see "Certification1"
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    Then I should see "Users"
    Then I should see "Nothing to display"
    Then I log out

  Scenario: We allocate two users and delete one allocation
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    And I should see "Certification1"
    And I should not see "Certification2"
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the dialog
    And "User 2" "autocomplete_suggestions" should not exist
    And "User 3" "autocomplete_suggestions" should exist
    And "User 4" "autocomplete_suggestions" should exist
    And "Manager 2" "autocomplete_suggestions" should not exist
    And I click on "User 1" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    Then I should see "Users"
    And I should see "User 1"
    Then I click on "Allocate users" "link"
    And I open the autocomplete suggestions list in the dialog
    And "User 1" "autocomplete_suggestions" should not exist
    And "User 2" "autocomplete_suggestions" should not exist
    And "User 3" "autocomplete_suggestions" should exist
    And "User 4" "autocomplete_suggestions" should exist
    And I click on "User 3" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    And I should see "User 1"
    And I should see "User 3"
    Then I click on "Delete" "link" in the "User 1" "table_row"
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
    Then I should see "Allocated to certification 'Certification1'"
    And I should see "You have been allocated to certification 'Certification1'"
    And I log out
    Then I log in as "user1"
    And I am on site homepage
    When I click on ".popover-region-notifications" "css_element"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    Then I should see "Deallocated from certification 'Certification1'"
    And I should see "You have been deallocated from certification 'Certification1'"
    And I log out

  Scenario: Cannot delete allocation if comes from a dynamic rule
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification1 | user3  |
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    # Condition Certification certified
    And I follow "Certification certified"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification1" item in the autocomplete list
    And I press "Save changes"
    And I follow "Actions"
    # Action Allocate users to certifications
    And I click on "Allocate users to certifications" "link" in the "#ruleoutcomes" "css_element"
    And I open the autocomplete suggestions list in the ".select_certification" "css_element"
    And I click on "Certification3" item in the autocomplete list
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    # Go back to certifications
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    Then I click on "Users" "link" in the "region-main" "region"
    # Certify User a
    Then I click on "Certify" "link" in the "User 1" "table_row"
    And I select "Never" from the "expirydatetype" singleselect
    Then I press "Certify"
    And I should see "Certified" in the "User 1" "table_row"
    And I log out
    And I run the scheduled task "tool_dynamicrule\task\process_rules"
    Then I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification3" "table_row"
    And I should see "User 1"
    And "Edit" "link" should exist in the "User 1" "table_row"
    And "Delete" "link" should not exist in the "User 1" "table_row"
    And I log out

  Scenario: Cannot delete allocation if allocation window is closed
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification1 | user3  |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And "Edit" "link" should exist in the "User 1" "table_row"
    And "Delete" "link" should exist in the "User 1" "table_row"
    And "Edit" "link" should exist in the "User 3" "table_row"
    And "Delete" "link" should exist in the "User 3" "table_row"
    Then I click on "Certification" "link" in the "region-main" "region"
    And I set the following fields to these values:
      | allocationenddatetype             | Select date |
      | allocationenddateabsolute[day]    | 3           |
      | allocationenddateabsolute[month]  | March       |
      | allocationenddateabsolute[year]   | 2019        |
    Then I press "Save changes"
    Then I click on "Users" "link" in the "region-main" "region"
    And "Edit" "link" should exist in the "User 1" "table_row"
    And "Delete" "link" should not exist in the "User 1" "table_row"
    And "Edit" "link" should exist in the "User 3" "table_row"
    And "Delete" "link" should not exist in the "User 3" "table_row"