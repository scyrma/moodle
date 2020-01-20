@tool @tool_certification @moodleworkplace @theme_workplace @javascript
Feature: Certify and revoke a certification and access to the certification log of the user
  In order to test that manager view the correct certification user log
  As a manager
  I need to modify a certification allocation and view certification user log

  Background:
    Given "1" tenants exist with "4" users and "1" courses in each
    And the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
    And the following tool certification data "certifications" exist:
      | fullname                | archived | tenant  | program   | startdatetype        | startdaterelative | duedatetype      | duedaterelative | expirydatetype | expirydaterelative |
      | Certification1          | 0        | Tenant1 | Program1  | user_allocation_date | 0 week            | after_start_date | 0 week          | after_due_date | 0 week             |
    And the following users allocations to certifications exist:
      | certification  | user     |
      | Certification1 | user12   |
      | Certification1 | user13   |
    And user "user11" has a department manager position over users "user12,user13" with permissions "3"

  Scenario: User modifies a certification allocation and can view the correct log
    When I log in as "tenantadmin1"
    And I change window size to "large"
    Then I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course11" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    Then I click on "Certify user" "link" in the "User 12" "table_row"
    And I set the following fields to these values:
      | expirydatetype                | Select date   |
      | expirydateabsolute[day]       | 1             |
      | expirydateabsolute[month]     | January       |
      | expirydateabsolute[year]      | 2050          |
    And I press "Certify" in the modal form dialogue
    Then I click on "Revoke certification" "link" in the "User 12" "table_row"
    And I press "Yes"
    Then I click on "Edit" "link" in the "User 12" "table_row"
    And I select "Suspended" from the "status" singleselect
    Then I press "Save changes" in the modal form dialogue
    And I log out
    Then I log in as "user13"
    And I click on "Enrol" "button"
    And I click on "Expand all" "button"
    And I click on "[data-modulename='URL1']" "css_element"
    And I click on "[data-modulename='URL2']" "css_element"
    Then I follow "Dashboard"
    And I log out
    Then I log in as "user11"
    Then I navigate to "Certifications" in workplace launcher
    Then I click on "Allocate users" "link" in the "Certification1" "table_row"
    And I click on "Certification activity log" "link" in the "User 12" "table_row"
    Then I should see "Was allocated to the certification"
    And I should see "Manually certified User 12 (expires on 1/01/50)"
    And I should see "Revoked User 12's certification"
    And I should see "User was suspended"
    And "Download" "button" should exist
    Then I press "Close"
    Then I click on "Certification activity log" "link" in the "User 13" "table_row"
    Then I should see "Was allocated to the certification"
    And I should see "Became certified"
    And I should see "Certification expired"