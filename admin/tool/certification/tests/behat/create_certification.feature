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
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant   |
      | Program1 | 0        | Tenant1  |
      | Program2 | 1        | Tenant1  |
      | Program3 | 0        | Tenant1  |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | 1        | user1@example.com    |
      | user2    | User      | 2        | user2@example.com    |
      | user3    | User      | 3        | user3@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
      | manager3 | Manager   | 3        | manager3@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
      | user3    | Tenant2 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
      | manager3 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_certification_manager | System       |           |
      | manager1 | tool_dynamicrule_manager   | System       |           |
      | manager2 | tool_certification_manager | System       |           |

  Scenario: Create a certification with basic information
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Certifications" in workplace launcher
    Then I should see "Certifications"
    Then I click on "New certification" "link"
    # Basic details.
    Then I should see "New certification"
    And I set the following fields to these values:
      | Certification full name | Certification example 1 |
      | Certification ID number | 1                       |
      | Certification tags      | Tag example 1           |
    And I set the field "Select program" to "Program1"
    And I press "Save"
    # Certification dates.
    Then I should see "Certification dates"
    And I set the following fields to these values:
      | startdatetype                 | Allocation date       |
      | duedaterelative[number]       | 1                     |
      | duedaterelative[timeunit]     | days                  |
      | expirydatetype                | After allocation date |
      | expirydaterelative[number]    | 1                     |
      | expirydaterelative[timeunit]  | days                  |
    Then I press "Save changes"
    Then I navigate to "Certifications" in workplace launcher
    And the following should exist in the "report-table" table:
      | Certification name      | Tags          | Program name |
      | Certification example 1 | Tag example 1 | Program1     |
    Then I click on "Edit content" "link" in the "Certification example 1" "table_row"
    # Recertification tab.
    Then I click on "Recertification" "tool_wp > Tab"
    # Basic details.
    And I set the following fields to these values:
      | requirerecertification              | Yes   |
      | recertdifferentprogram              | Yes   |
      | recertstartdaterelative[number]     | 1     |
      | recertstartdaterelative[timeunit]   | weeks |
      | recertgraceperiod[number]           | 1     |
      | recertgraceperiod[timeunit]         | weeks |
      | recertexpirydatetype                | After previous certification expiry date |
      | recertexpirydaterelative[number]    | 1     |
      | recertexpirydaterelative[timeunit]  | years |
    And I open the autocomplete suggestions list
    And "Program1" "autocomplete_suggestions" should not exist
    And I click on "Program3" item in the autocomplete list
    Then I press "Save changes"
    # Users tab.
    Then I click on "Users" "tool_wp > Tab"
    And I should see "Users"
    And I should see "Nothing to display"
    Then I click on "Allocate users" "link"
    Then I open the autocomplete suggestions list in the "Allocate users" "dialogue"
    And "User 3" "autocomplete_suggestions" should not exist
    And "Manager 3" "autocomplete_suggestions" should exist
    And I click on "User 1" item in the autocomplete list
    Then I press "Save changes"
    And the following should exist in the "report-table" table:
      | Full name | Due date | Allocation source | Certification status | Program status |
      | User 1    |          | Manual            | Open                 | Open           |
    # Allocation window
    Then I click on "Certification" "tool_wp > Tab"
    And  I should see "Allocation window"
    And I set the following fields to these values:
      | allocationstartdatetype                 | Select date |
      | allocationstartdateabsolute[day]        | 3           |
      | allocationstartdateabsolute[month]      | March       |
      | allocationstartdateabsolute[year]       | 2050        |
    Then I press "Save changes"
    Then I click on "Users" "tool_wp > Tab"
    Then I click on "Allocate users" "link"
    And I should see "The allocation window for this certification starts on"
    And I click on "OK" "button" in the "Users allocation is not available" "dialogue"
    # Dynamic rules tab
    Then I click on "Dynamic rules" "tool_wp > Tab"
    And I should see "Conditions"
    And I should see "Actions"
    And I should see "Users allocated to certification"
    And I should see "Users that have status 'Certified' in certification"
    And I should see "Users that do not have status 'Certified' in certification"
    And I should see "Users that have status 'Overdue' in certification"
    And I should see "Users that have status 'Expired' in certification"
    And I should see "Users that have status 'Suspended' in certification"
    And I should see "Users whose grace period ends in certification"
    And I should see "Users that have started a recertification period in certification"
    Then I navigate to "Certifications" in workplace launcher
    And I log out
    # Check permisions
    Then I log in as "manager2"
    Then I should not see "Certifications"
    And I log out
    Then I log in as "manager3"
    Then I should not see "Certifications"
    And I log out

  Scenario: Create a certification using duplicate certification
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certification1"
    Then I click on "Duplicate" "link" in the "Certification1" "table_row"
    Then I click on "OK" "button" in the ".modal-dialog" "css_element"
    And I should see "Certification1"
    And I set the field "Certification full name" to "Certification1B"
    And "Program1" "autocomplete_selection" should exist
    Then I press "Save"
    And I should see "This ID number is already used in another certification"
    And I set the field "Certification ID number" to "new1"
    Then I press "Save"
    Then I navigate to "Certifications" in workplace launcher
    Then I should see "Certification1"
    And I should see "Certification1B"
    And I log out
