@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Edit certification users allocations
  In order to edit the users allocation
  As a manager
  I need to see all existing allocations

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
      | Program3 | 0        | Tenant1 |
      | Program4 | 0        | Tenant1 |
    Given the following "tool_certification > certifications" exist:
      | fullname       | archived | tenant  | program   | requirerecertification | recertificationprogram  | expirydateabsolute |
      | Certification1 | 0        | Tenant1 | Program1  | 1                      | Program3                | +5 day             |
      | Certification2 | 0        | Tenant2 | Program2  | 0                      | Program2                | +5 day             |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | a        | user1@example.com    |
      | user2    | User      | b        | user2@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | user2    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    Given the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification1 | user2  |
    Given user "manager1" has a department lead position over users "user1,user2" with permissions "3"
    And the following "roles" exist:
      | shortname           | name                 | archetype |
      | certificationeditor | Certification editor |           |
    And the following "role assigns" exist:
      | user     | role                          | contextlevel | reference |
      | manager1 | tool_certification_manager    | System       |           |
      | manager2 | tool_certification_manager    | System       |           |
      | manager1 | tool_program_manager          | System       |           |

  @_file_upload
    # Depending on the different options some form elements are shown or hidden in the modal.
  Scenario: We edit one user allocation
    When I change window size to "large"
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should not see "Certification2"
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I should see "Full name" in the "table.report-table thead" "css_element"
    And I should see "Allocation source" in the "table.report-table thead" "css_element"
    And I should see "Certification status" in the "table.report-table thead" "css_element"
    And I should see "Expiry date" in the "table.report-table thead" "css_element"
    And I should see "Current program" in the "table.report-table thead" "css_element"
    And I should see "Due date" in the "table.report-table thead" "css_element"
    And I should see "Program status" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And the following should exist in the "report-table" table:
      | Full name | Certification status |
      | User a    | Open                 |
      | User b    | Open                 |
    # User is Suspended
    And I should not see "Suspended" in the "User a" "table_row"
    And I should see "Program1" in the "User a" "table_row"
    Then I click on "Edit" "link" in the "User a" "table_row"
    Then I should see "Allocation for 'User a'" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Certification status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Due date" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Recertification start date" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Grace period ends" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Current program" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Reset additional courses" in the ".modal-dialog .modal-body" "css_element"
    And I select "Suspended" from the "Status" singleselect
    And I set the following fields to these values:
      | duedatetype         | Select date |
      | duedate[day]        | 3           |
      | duedate[month]      | March       |
      | duedate[year]       | 2040        |
    Then I press "Save changes"
    Then I should see "Suspended" in the "User a" "table_row"
    And I should see "3/03/40" in the "User a" "table_row"
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I set the following fields to these values:
      | duedatetype         | 1 week after start date (default) |
    Then I press "Save changes"
    And I should see "##+2 days##%d/%m/%y##" in the "User a" "table_row"
    # User is suspended and certified
    Then I click on "Certify user" "link" in the "User a" "table_row"
    Then I press "Certify"
    Then I should see "Suspended" in the "User a" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I select "Default" from the "Status" singleselect
    Then I press "Save changes"
    And I should not see "Suspended" in the "User a" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    # User is certified
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I should see "Status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Certification status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Certified" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Recertification start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Due date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Recertification start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Grace period ends" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Current program" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Reset additional courses" in the ".modal-dialog .modal-body" "css_element"
    Then I press "Save changes"
    # Start recertification
    And I run the scheduled task "tool_certification\task\recertification"
    Then I click on "Recertification" "tool_wp > Tab"
    Then I click on "Users" "tool_wp > Tab"
    Then "Certify user" "link" should exist in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I should see "Status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Certification status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Certified" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Recertification start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Due date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Recertification start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Grace period ends" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Current program" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Reset additional courses" in the ".modal-dialog .modal-body" "css_element"
    And I set the following fields to these values:
      | expirydatetype         | Select date |
      | expirydate[day]        | 3           |
      | expirydate[month]      | March       |
      | expirydate[year]       | 2050        |
    Then I press "Save changes"
    And I should see "3/03/50" in the "User a" "table_row"
    And I should see "Program3" in the "User a" "table_row"
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I select "Never" from the "expirydatetype" singleselect
    Then I press "Save changes"
    And I should see "Never" in the "User a" "table_row"
    Then I click on "Recertification" "tool_wp > Tab"
    And I open the autocomplete suggestions list
    And I click on "Program4" item in the autocomplete list
    Then I press "Save changes"
    Then I click on "Users" "tool_wp > Tab"
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I set the following fields to these values:
      | currentprogram        | Program4           |
    And I should see "Reset additional courses" in the ".modal-dialog .modal-body" "css_element"
    And I click on "Reset additional courses" "checkbox"
    And I set the following fields to these values:
      | expirydatetype       | Select date      |
      | expirydate[day]      | ##-15 days##%d##  |
      | expirydate[month]    | ##-15 days##%B##  |
      | expirydate[year]     | ##-15 days##%Y##  |
    Then I press "Save changes"
    And I should see "Expired" in the "User a" "table_row"
    And I should see "Program4" in the "User a" "table_row"
    And I run the scheduled task "tool_certification\task\recertification"
    Then I click on "Recertification" "tool_wp > Tab"
    Then I click on "Users" "tool_wp > Tab"
    # User should be reallocated to program1 after grace period
    And the following should exist in the "report-table" table:
      | Full name | Current program | Certification status |
      | User a    | Program1        | Expired              |
    # Disable select a different program from recertification
    Then I click on "Recertification" "tool_wp > Tab"
    And I set the following fields to these values:
      | recertdifferentprogram              | No   |
    Then I press "Save changes"
    Then I click on "Users" "tool_wp > Tab"
    Then I click on "Edit" "link" in the "User a" "table_row"
    And I should not see "Grace period ends" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Current program" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Reset additional courses" in the ".modal-dialog .modal-body" "css_element"
    Then I press "Save changes"
    Then I log out
    Then I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification2"
    And I should not see "Certification1"
    Then I log out
