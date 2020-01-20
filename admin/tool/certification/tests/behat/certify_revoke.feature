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
      | fullname       | archived | tenant  | program   | idnumber | requirerecertification | recertificationprogram  |
      | Certification1 | 0        | Tenant1 | Program1  |  num1    | 1                      | Program3                |
      | Certification2 | 0        | Tenant2 | Program2  |  num2    | 0                      | Program2                |
      | Certification3 | 0        | Tenant1 | Program3  |  num3    | 0                      | Program3                |
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
    Given user "manager1" has a department manager position over users "user1" with permissions "3"
    And the following "roles" exist:
      | shortname           | name                 | archetype |
      | certificationeditor | Certification editor |           |
    And the following "role assigns" exist:
      | user     | role                | contextlevel | reference |
      | manager1 | certificationeditor | System       |           |
      | manager2 | certificationeditor | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role                | contextlevel | reference |
      | moodle/site:configview  | Allow      | certificationeditor | System       |           |
      | tool/certification:edit | Allow      | certificationeditor | System       |           |

  Scenario: We edit certify and revoke one user allocation
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should not see "Certification2"
    And I should see "Certification3"
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    And I set the following fields to these values:
      | expirydatetype              | Select date   |
      | expirydateabsolute[day]     | 3             |
      | expirydateabsolute[month]   | March         |
      | expirydateabsolute[year]    | 2022          |
    Then I press "Save changes"
    Then I click on "Users" "link"
    # We certify user with expiry date Never.
    Then I click on "Certify user" "link" in the "User a" "table_row"
    And I should see "Certify" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Mark certification as completed without waiting for the program completion" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Expiry date" in the ".modal-dialog .modal-body" "css_element"
    And I select "Never" from the "expirydatetype" singleselect
    Then I press "Certify"
    And I should see "Certified" in the "User a" "table_row"
    And I should see "Never" in the "User a" "table_row"
    Then I click on "Revoke certification" "link" in the "User a" "table_row"
    And I should see "Revoke certification"
    Then I press "Yes"
    And I should not see "Certified" in the "User a" "table_row"
    Then "Revoke certification" "link" should not exist in the "User a" "table_row"
    # We certify user with Default expiry date.
    Then I click on "Certify user" "link" in the "User a" "table_row"
    And I select "3/03/22 (Default)" from the "expirydatetype" singleselect
    Then I press "Certify"
    And I should see "Certified" in the "User a" "table_row"
    And I should see "3/03/22" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    And "Certify user" "link" should not exist in the "User a" "table_row"
    Then I click on "Revoke certification" "link" in the "User a" "table_row"
    Then I press "Yes"
    And I should not see "Certified" in the "User a" "table_row"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification3" "table_row"
    And I should see "-" in the "User a" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    And "Revoke certification" "link" should not exist in the "User a" "table_row"
    And "Certify user" "link" should not exist in the "User a" "table_row"
    Then I log out
    Then I log in as "manager2"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Active" "link"
    And I should see "Certification2"
    And I should not see "Certification1"
    Then I log out

  Scenario: We certify and recertify user allocation and check that certify and revoke work as expected
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And I should see "Certifications"
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    Then I should see "Certification dates"
    And I set the following fields to these values:
      | startdatetype                 | Allocation date       |
      | duedaterelative[number]       | 1                     |
      | duedaterelative[timeunit]     | week                  |
      | expirydatetype                | After completion      |
      | expirydaterelative[number]    | 1                     |
      | expirydaterelative[timeunit]  | days                  |
    Then I press "Save changes"
    Then I click on "Users" "link"
    Then I click on "Certify user" "link" in the "User a" "table_row"
    And I should see "Certify" in the ".modal-dialog .modal-title" "css_element"
    And I should see "1 day after completion (Default)"
    Then I press "Certify"
    And I should see "Certified" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then "Certify user" "link" should not exist in the "User a" "table_row"
    And I log out
    And I run the scheduled task "tool_certification\task\recertification"
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then "Certify user" "link" should exist in the "User a" "table_row"
    And I should see "##+1 days##j/m/y##" in the "User a" "table_row"
    Then I click on "Certify user" "link" in the "User a" "table_row"
    And I should see "Certify" in the ".modal-dialog .modal-title" "css_element"
    And I should see "1 day after completion (Default)"
    Then I press "Certify"
    And I should see "Certified" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then "Certify user" "link" should not exist in the "User a" "table_row"
    # We revoke both completions.
    Then I click on "Revoke certification" "link" in the "User a" "table_row"
    And I should see "Revoke certification"
    Then I press "Yes"
    And I should see "Certified" in the "User a" "table_row"
    And I should see "##+1 days##j/m/y##" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then "Certify user" "link" should not exist in the "User a" "table_row"
    Then I click on "Revoke certification" "link" in the "User a" "table_row"
    And I should see "Revoke certification"
    Then I press "Yes"
    Then "Revoke certification" "link" should not exist in the "User a" "table_row"
    Then "Certify user" "link" should exist in the "User a" "table_row"
    And I should not see "Certified" in the "User a" "table_row"
    And I log out