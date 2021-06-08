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
    And the following "tool_program > programs" exist:
      | fullname | archived | tenant  | generatecourses |
      | Program1 | 0        | Tenant1 | 1               |
      | Program2 | 0        | Tenant2 | 1               |
      | Program3 | 0        | Tenant1 | 1               |
    Given the following "tool_certification > certifications" exist:
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
    Given the following "tool_certification > certification_users" exist:
      | certification  | user   |
      | Certification1 | user1  |
      | Certification3 | user1  |
    And the following "tool_program > program_completions" exist:
      | program  | user   |
      | Program3 | user1  |
    # Completing the program will result in user being certified.
    Given user "manager1" has a department lead position over users "user1" with permissions "3"
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

  Scenario: Certify and revoke one user allocation
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
    And the following should exist in the "report-table" table:
      | Certification name | Tags |
      | Certification1     |      |
      | Certification3     |      |
    And the following should not exist in the "report-table" table:
      | Certification name | Tags |
      | Certification2     |      |
    Then I click on "Edit content" "link" in the "Certification1" "table_row"
    And I set the following fields to these values:
      | expirydatetype              | Select date   |
      | expirydateabsolute[day]     | 3             |
      | expirydateabsolute[month]   | March         |
      | expirydateabsolute[year]    | 2022          |
    Then I press "Save changes"
    Then I click on "Users" "tool_wp > Tab"
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
    Then I click on "Users" "link" in the "Certification3" "table_row"
    And I should see "-" in the "User a" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    And "Revoke certification" "link" should not exist in the "User a" "table_row"
    And "Certify user" "link" should not exist in the "User a" "table_row"
    Then I log out

  Scenario: Certify and recertify user allocation and check that certify and revoke work as expected
    When I log in as "manager1"
    Then I navigate to "Certifications" in workplace launcher
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
    Then I click on "Users" "tool_wp > Tab"
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
    Then I click on "Users" "link" in the "Certification1" "table_row"
    And I should see "Certified" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then "Certify user" "link" should exist in the "User a" "table_row"
    And I should see "##+1 days##%d/%m/%y##" in the "User a" "table_row"
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
    And I should see "##+1 days##%d/%m/%y##" in the "User a" "table_row"
    Then "Revoke certification" "link" should exist in the "User a" "table_row"
    Then "Certify user" "link" should not exist in the "User a" "table_row"
    Then I click on "Revoke certification" "link" in the "User a" "table_row"
    And I should see "Revoke certification"
    Then I press "Yes"
    Then "Revoke certification" "link" should not exist in the "User a" "table_row"
    Then "Certify user" "link" should exist in the "User a" "table_row"
    And I should not see "Certified" in the "User a" "table_row"
    And I log out
