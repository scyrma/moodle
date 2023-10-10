@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Certify and revoke a certification and access to the certification log of the user
  In order to test that manager view the correct certification user log
  As a manager
  I need to modify a certification allocation and view certification user log

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    And the following "tool_program > programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following "tool_program > program_courses" exist:
      | program   | course  |
      | Program1  | C11     |
    And the following "tool_certification > certifications" exist:
      | fullname                | archived | tenant  | program   | startdatetype        | startdaterelative | duedatetype      | duedaterelative | expirydatetype | expirydaterelative |
      | Certification1          | 0        | Tenant1 | Program1  | user_allocation_date | 0 week            | after_start_date | 0 week          | after_due_date | 0 week             |
    And the following "tool_certification > certification_users" exist:
      | certification  | user     |
      | Certification1 | user12   |
      | Certification1 | user13   |
    And the following "tool_program > program_completions" exist:
      | program   | user    |
      | Program1  | user13  |
    And user "user11" has a department lead position over users "user12,user13" with permissions "3"

  Scenario: User modifies a certification allocation and can view the correct log
    When I log in as "tenantadmin1"
    And I change window size to "large"
    Then I navigate to "Certifications" in workplace launcher
    Then I press "Users" action in the "Certification1" report row
    Then I press "Certify user" action in the "User 12" report row
    And I set the following fields to these values:
      | expirydatetype                | Select date   |
      | expirydateabsolute[day]       | 1             |
      | expirydateabsolute[month]     | January       |
      | expirydateabsolute[year]      | 2050          |
    And I click on "Certify" "button" in the "Certify" "dialogue"
    Then I press "Revoke certification" action in the "User 12" report row
    And I press "Yes"
    Then I press "Edit" action in the "User 12" report row
    And I select "Suspended" from the "Status" singleselect
    Then I press "Save changes"
    And I log out
    Then I log in as "user11"
    Then I navigate to "Certifications" in workplace launcher
    Then I press "Users" action in the "Certification1" report row
    And I press "Certification activity log" action in the "User 12" report row
    Then I should see "Last allocation date"
    And I should see "Manually certified User 12 (expires on 1/01/50)"
    And I should see "Revoked User 12's certification"
    And I should see "User was suspended"
    And "Download" "button" should exist
    And I click on "Close" "button" in the "User 12 activity log" "dialogue"
    Then I press "Certification activity log" action in the "User 13" report row
    Then I should see "Last allocation date"
    And I should see "Became certified"
    And I should see "Certification expired"
