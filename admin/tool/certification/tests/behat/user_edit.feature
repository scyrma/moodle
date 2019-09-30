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
    Given the following tool program data "programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
      | Program2 | 0        | Tenant2 |
    Given the following tool certification data "certifications" exist:
      | fullname       | archived | tenant  | program   |
      | Certification1 | 0        | Tenant1 | Program1  |
      | Certification2 | 0        | Tenant2 | Program1  |
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
    Given the following users allocations to certifications exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification1 | user2  |
    Given user "manager1" has a department manager position over users "user1,user2" with permissions "3"
    And the following "roles" exist:
      | shortname           | name                 | archetype |
      | certificationeditor | Certification editor |           |
    And the following "role assigns" exist:
      | user     | role                | contextlevel | reference |
      | manager1 | certificationeditor | System       |           |
      | manager2 | certificationeditor | System       |           |
    And I log in as "admin"
    And I set the following system permissions of "Certification editor" role:
      | capability               | permission |
      | moodle/site:configview   | Allow      |
      | tool/certification:edit  | Allow      |
    And I log out

  Scenario: We edit one user allocation
    When I log in as "manager1"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification1"
    And I should not see "Certification2"
    Then I click on ".edit_certification" "css_element" in the "Certification1" "table_row"
    Then I click on "Users" "link" in the ".wptabs" "css_element"
    And I should see "Full name" in the "table.report-table thead" "css_element"
    And I should see "Due date" in the "table.report-table thead" "css_element"
    And I should see "Allocation source" in the "table.report-table thead" "css_element"
    And I should see "Certification status" in the "table.report-table thead" "css_element"
    And I should see "Actions" in the "table.report-table thead" "css_element"
    And I should see "User a" in the "[data-region='edit-certification-user-list'] table.report-table" "css_element"
    And I should see "User b" in the "[data-region='edit-certification-user-list'] table.report-table" "css_element"
    Then I should not see "Suspended"
    Then I click on ".edit_user" "css_element" in the "User a" "table_row"
    Then I should see "Allocation for 'User a'" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Due date" in the ".modal-dialog .modal-body" "css_element"
    And I should not see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I select "Suspended" from the "status" singleselect
    Then I press "Save changes" in the modal form dialogue
    Then I should see "Suspended" in the "User a" "table_row"
    Then I click on ".confirm_certify_user" "css_element" in the "User a" "table_row"
    And I should see "Certify"
    Then I press "Certify"
    Then I should see "Suspended" in the "User a" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    Then I click on ".edit_user" "css_element" in the "User a" "table_row"
    Then I should see "Allocation for 'User a'" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Status" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Start date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Due date" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I select "Select date" from the "expirydatetype" singleselect
    And I select "3" from the "expirydate[day]" singleselect
    And I select "March" from the "expirydate[month]" singleselect
    And I select "2050" from the "expirydate[year]" singleselect
    Then I press "Save changes" in the modal form dialogue
    And I should see "3/03/50" in the "User a" "table_row"
    Then I click on ".edit_user" "css_element" in the "User a" "table_row"
    And I select "Never" from the "expirydatetype" singleselect
    Then I press "Save changes" in the modal form dialogue
    And I should see "Never" in the "User a" "table_row"
    Then I press "Edit details"
    And I select "Select date" from the "expirydatetype" singleselect
    And I select "3" from the "expirydateabsolute[day]" singleselect
    And I select "March" from the "expirydateabsolute[month]" singleselect
    And I select "2022" from the "expirydateabsolute[year]" singleselect
    Then I press "Save" in the modal form dialogue
    Then I click on ".edit_user" "css_element" in the "User a" "table_row"
    And I select "3/03/22 (default)" from the "expirydatetype" singleselect
    Then I press "Save changes" in the modal form dialogue
    And I should see "3/03/22" in the "User a" "table_row"
    Then I log out
    Then I log in as "manager2"
    Then I navigate to "Courses > Certifications" in site administration
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification2"
    And I should not see "Certification1"
    Then I log out