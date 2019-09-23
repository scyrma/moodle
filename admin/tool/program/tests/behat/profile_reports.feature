@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Check user report capabilities on profile
  In order to check reports on profile
  As a manager
  I need have permission to see the user reports

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
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
      | manager2 | Tenant1 |
    And the following users allocations to programs exist:
      | user     | program  |
      | user1    | Program1 |

  Scenario: In programs manager has permission only over its users
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
    Given user "manager1" has a department manager position over users "user1" with permissions "3"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I should not see "User b"
    And I click on "//button[contains(.,'User a')]//*[contains(@title,'Expand')]" "xpath_element"
    And I should see "Job assignments"
    And I should see "Active programs: 1"
    Then I click on "Active programs: 1" "link"
    And I should see "Program name"
    And I should see "Associated certifications"
    And I should see "Expiry date"
    And I should see "Due date"
    And I should see "Program status"
    And I should see "Program progress"
    And I should see "Completion date"
    And I should see "Program1"
    And I should see "Open"
    And I click on "Progress overview" "link" in the "Program1" "table_row"
    And I should see "Program1"
    And I should see "Progress overview"
    And I should see "Complete all in order"
    And I press "Close"
    And I click on "Progress report" "link" in the "Program1" "table_row"
    And I should see "Type"
    And I should see "Name"
    And I should see "Completion criteria"
    And I should see "Parent name"
    And I should see "Progress"
    And I should see "Program1 (Base set)" in the "Set" "table_row"
    And I should see "Complete all in order" in the "Set" "table_row"
    And I should see "0%" in the "Set" "table_row"
    And I log out
    When I log in as "manager2"
    Then I follow "Dashboard"
    And I should not see "Teams"
    And I should not see "User a"
    And I should not see "User b"
    And I should not see "Active programs: 1"
    And I log out

  Scenario: In programs manager can view overdue allocations report
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
    Given user "manager1" has a department manager position over users "user1" with permissions "3"
    Given the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager2 | tool_program_manager | System       |           |
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should see "User a"
    And I should see "Program1" in the "User a" "table_row"
    And I should see "Open" in the "User a" "table_row"
    And I should see "0%" in the "User a" "table_row"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on ".edit_program" "css_element" in the "Program1" "table_row"
    Then I click on "Schedule" "link"
    And I select "After user allocation date" from the "duedatetype" singleselect
    Then I set the field "duedaterelative[number]" to "0"
    And I select "days" from the "duedaterelative[timeunit]" singleselect
    Then I press "Save changes"
    And I log out
    And I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should see "User a"
    And I should see "Program1" in the "User a" "table_row"
    And I should see "Overdue" in the "User a" "table_row"
    And I log out
    And I log in as "manager2"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    And I should see "Program1"
    Then I click on ".edit_program" "css_element" in the "Program1" "table_row"
    Then I click on "Schedule" "link"
    And I select "After user allocation date" from the "duedatetype" singleselect
    Then I set the field "duedaterelative[number]" to "2"
    And I select "month" from the "duedaterelative[timeunit]" singleselect
    Then I press "Save changes"
    And I log out
    And I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should not see "User a"
    And I log out

  Scenario: In programs manager with only allocate permission can view overdue allocations report
    Given user "manager1" has a department manager position over users "user1" with permissions "1"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should see "User a"
    And I should see "Program1" in the "User a" "table_row"
    And I log out

  Scenario: In programs manager with only view user reports permission can view overdue allocations report
    Given user "manager1" has a department manager position over users "user1" with permissions "2"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should see "User a"
    And I should see "Program1" in the "User a" "table_row"
    And I log out

  Scenario: In programs manager with only receive notifications can not view overdue allocations report
    Given user "manager1" has a department manager position over users "user1" with permissions "4"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I should not see "Full completion report"
    And I log out

  Scenario: In programs manager with both allocate and view reports permissions can view overdue allocations report
    Given user "manager1" has a department manager position over users "user1" with permissions "3"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should see "User a"
    And I should see "Program1" in the "User a" "table_row"
    And I log out

  Scenario: In programs manager with all permission can view overdue allocations report
    Given user "manager1" has a department manager position over users "user1" with permissions "7"
    When I log in as "manager1"
    Then I follow "Dashboard"
    And I should see "Teams"
    And I should see "User a"
    And I click on "Full completion report" "link"
    And I should see "Full completion report"
    And I should see "User a"
    And I should see "Program1" in the "User a" "table_row"
    And I log out