@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Certify and revoke user certifications
  In order to certify or revoke a certification
  As a manager
  I need to see all existing users

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
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program   | idnumber |
      | Certification1 | 0        | Tenant1 | Program1  |  num1    |
      | Certification2 | 0        | Tenant2 | Program1  |  num2    |
      | Certification3 | 0        | Tenant1 | Program3  |  num3    |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | user1    | User      | a        | user1@example.com    |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | user1    | Tenant1 |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification3 | user1  |
    Given the following tool program user allocations are completed:
      | program  | user   |
      | Program3 | user1  |
    And the following tool certification user allocations are certified:
      | certification  | user   |
      | Certification3 | user1  |
    And the following departments exist in organisation structure:
      | tenant     | name            | parent         |
      | Tenant1    | Framework_t1    |                |
      | Tenant1    | Deparment_t1_d1 | Framework_t1   |
    And the following positions exist in organisation structure:
    # globalmanager and departmentmanager are boolean flag
    # globalpermissions and departmentpermission are accumulated numeric value assigned to globalmanager & departmentmanager
    # 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
      | tenant     | name            | parent         | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1    |                |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1  | Framework_t1   |      0        |       0           |       1           |        3              |
      | Tenant1    | Position_t1_f2  | Position_t1_f1 |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user     | department      | position       |
      | manager1 | Deparment_t1_d1 | Position_t1_f1 |
      | user1    | Deparment_t1_d1 | Position_t1_f2 |
    And the following "roles" exist:
      | shortname           | name                 | archetype |
      | certificationeditor | Certification editor |           |
    And the following "role assigns" exist:
      | user     | role                | contextlevel | reference |
      | manager1 | certificationeditor | System       |           |
      | manager2 | certificationeditor | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Certification editor" role:
      | capability              | permission |
      | moodle/site:configview  | Allow      |
      | tool/certification:edit | Allow      |
    And I log out

  Scenario: We edit one user allocation
    When I log in as "manager1"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    And I should see "Certification3"
    Then I click on ".edit_certification" "css_element" in the "Certification1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Fullname" in the "table.report-table thead" "css_element"
    And I should see "Due date" in the "table.report-table thead" "css_element"
    And I should see "Allocation source" in the "table.report-table thead" "css_element"
    And I should see "Certification status" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And I should see "User a" in the "[data-region='edit-certification-user-list'] table.report-table" "css_element"
    Then I should not see "Suspended"
    Then I press "Edit details"
    And I select "Select date" from the "expirydatetype" singleselect
    And I select "3" from the "expirydateabsolute[day]" singleselect
    And I select "March" from the "expirydateabsolute[month]" singleselect
    And I select "2022" from the "expirydateabsolute[year]" singleselect
    Then I press "Save" in the modal form dialogue
    Then I click on ".confirm_certify_user" "css_element" in the "User a" "table_row"
    And I should see "Certify" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Mark certification as completed without waiting for the program completion" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Leave user allocated to the program without due date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Suspend user from the program" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I select "Never" from the "expirydatetype" singleselect
    Then I press "Certify"
    And I should see "Certified" in the "User a" "table_row"
    And I should see "Never" in the "User a" "table_row"
    Then ".confirm_revoke_user" "css_element" should exist in the "User a" "table_row"
    Then I click on ".confirm_revoke_user" "css_element" in the "User a" "table_row"
    And I should see "Revoke certification"
    Then I press "Yes"
    And I should not see "Certified" in the "User a" "table_row"
    Then ".confirm_revoke_user" "css_element" should not exist in the "User a" "table_row"
    Then I click on ".confirm_certify_user" "css_element" in the "User a" "table_row"
    And I should see "Suspend user from the program"
    And I click on "Suspend user from the program" "radio"
    And I select "3/03/22 (Default)" from the "expirydatetype" singleselect
    Then I press "Certify"
    And I should see "Certified" in the "User a" "table_row"
    And I should see "Suspended" in the "User a" "table_row"
    And I should see "3/03/22" in the "User a" "table_row"
    Then ".confirm_revoke_user" "css_element" should exist in the "User a" "table_row"
    And ".confirm_certify_user" "css_element" should not exist in the "User a" "table_row"
    Then I click on ".confirm_revoke_user" "css_element" in the "User a" "table_row"
    Then I press "Yes"
    And I should not see "Certified" in the "User a" "table_row"
    And I should not see "Suspended" in the "User a" "table_row"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    Then I click on ".edit_certification" "css_element" in the "Certification3" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Completed" in the "User a" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    And ".confirm_revoke_user" "css_element" should not exist in the "User a" "table_row"
    And ".confirm_certify_user" "css_element" should not exist in the "User a" "table_row"
    Then I log out
    Then I log in as "manager2"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification2"
    And I should not see "Certification1"
    Then I log out