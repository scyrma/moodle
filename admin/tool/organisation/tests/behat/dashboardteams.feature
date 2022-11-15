@tool @tool_organisation @moodleworkplace @theme_workplace @javascript
Feature: Viewing one's team on the dashboard
  As an organisation manager
  I should be able to view my teams on the dashboard

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
        # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user14" has a department lead position over users "user15" with permissions "2"

  Scenario: Messaging team members from the dashboard
    When I log in as "user11"
    Then "Send message" "link" should not exist in the "User 12" "table_row"
    And I log out
    And I log in as "admin"
    And I set the following administration settings values:
      | messagingallusers | true |
    And I log out
    And I log in as "user11"
    And I press "User 12"
    And I click on "Send message" "link" in the "User 12" "table_row"
    And I send "Welcome to Moodle Workplace" message in the message area
    And I log out
    And I log in as "user12"
    And I should see "1" in the "//*[@title='Toggle messaging drawer']/../*[@data-region='count-container']" "xpath_element"
    And I open messaging
    And I should see "1" in the "Private" "core_message > Message tab"
    And "User 11" "core_message > Message" should exist
    And I should see "1" in the "User 11" "core_message > Message"
    And I select "User 11" conversation in messaging
    And I should see "Welcome to Moodle Workplace" in the "User 11" "group_message_conversation"
    And I log out

  # Message button was not working after using filters (WP-707).
  Scenario: Messaging team members from the dashboard after applying filters
    And I log in as "admin"
    And I set the following administration settings values:
      | messagingallusers | true |
    And I log out
    And I log in as "user11"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Customise..." "radio"
    And I set the field "Department" to "New department 2"
    And I press "Reset table"
    And I press "User 12"
    And I click on "Send message" "link" in the "User 12" "table_row"
    And I send "Welcome to Moodle Workplace" message in the message area
    And I log out
    And I log in as "user12"
    And I open messaging
    And I select "User 11" conversation in messaging
    And I should see "Welcome to Moodle Workplace" in the "User 11" "group_message_conversation"
    And I log out

  # Organisation filter.
  Scenario: Teams members report from the dashboard after applying filters
    Given the following "users" exist:
      | username | firstname | lastname    | email               |
      | user100  | User100   | Lastname100 | user100@example.com |
      | user200  | User200   | Lastname200 | user200@example.com |
      | user300  | User300   | Lastname300 | user300@example.com |
      | user400  | User400   | Lastname400 | user400@example.com |
      | user500  | User500   | Lastname500 | user500@example.com |
      | user600  | User600   | Lastname600 | user600@example.com |
      | user700  | User700   | Lastname700 | user700@example.com |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user100    | Tenant1 |
      | user200    | Tenant1 |
      | user300    | Tenant1 |
      | user400    | Tenant1 |
      | user500    | Tenant1 |
      | user600    | Tenant1 |
      | user700    | Tenant1 |
    And the following departments exist in organisation structure:
      | tenant     | name             | parent           |
      | Tenant1    | Framework_t1     |                  |
      | Tenant1    | Department_t1_d1 | Framework_t1     |
      | Tenant1    | Department_t1_d2 | Framework_t1     |
      | Tenant1    | Department_t1_d3 | Department_t1_d1 |
    And the following positions exist in organisation structure:
      | tenant     | name              | parent           | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1      |                  |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1     |      1        |       0           |       1           |        0              |
      | Tenant1    | Position_t1_f2    | Position_t1_f1   |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f3    | Position_t1_f2   |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f4    | Position_t1_f3   |      0        |       0           |       0           |        0              |
    And the following job assignments exist in organisation structure:
      | user        | department        | position       |
      | user100     | Department_t1_d1 | Position_t1_f1 |
      | user200     | Department_t1_d1 | Position_t1_f2 |
      | user300     | Department_t1_d1 | Position_t1_f3 |
      | user400     | Department_t1_d2 | Position_t1_f2 |
      | user500     | Department_t1_d2 | Position_t1_f3 |
      | user600     | Department_t1_d2 | Position_t1_f4 |
      | user700     | Department_t1_d3 | Position_t1_f1 |
    And I log in as "user100"
    And I change window size to "large"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Show everybody reporting to me" "radio"
    And I should see "User200" in the "#teams" "css_element"
    And I should see "User300" in the "#teams" "css_element"
    And I should see "User400" in the "#teams" "css_element"
    And I should see "User500" in the "#teams" "css_element"
    And I should see "User600" in the "#teams" "css_element"
    And I should see "User700" in the "#teams" "css_element"
    Then I click on "Show my own direct reports only" "radio"
    And I should see "User200" in the "#teams" "css_element"
    And I should see "User300" in the "#teams" "css_element"
    And I should see "User400" in the "#teams" "css_element"
    And I should not see "User500" in the "#teams" "css_element"
    And I should not see "User600" in the "#teams" "css_element"
    And I should not see "User700" in the "#teams" "css_element"
    And I click on "Customise..." "radio"
    And I set the field "Position" to "Position_t1_f3"
    And I should not see "User200" in the "#teams" "css_element"
    And I should see "User300" in the "#teams" "css_element"
    And I should not see "User400" in the "#teams" "css_element"
    And I should see "User500" in the "#teams" "css_element"
    And I should not see "User600" in the "#teams" "css_element"
    And I should not see "User700" in the "#teams" "css_element"
    And I set the field "Department" to "Department_t1_d2"
    And I should not see "User200" in the "#teams" "css_element"
    And I should not see "User300" in the "#teams" "css_element"
    And I should not see "User400" in the "#teams" "css_element"
    And I should see "User500" in the "#teams" "css_element"
    And I should not see "User600" in the "#teams" "css_element"
    And I should not see "User700" in the "#teams" "css_element"
    And I click on "Include subpositions" "checkbox"
    And I should not see "User200" in the "#teams" "css_element"
    And I should not see "User300" in the "#teams" "css_element"
    And I should not see "User400" in the "#teams" "css_element"
    And I should see "User500" in the "#teams" "css_element"
    And I should see "User600" in the "#teams" "css_element"
    And I should not see "User700" in the "#teams" "css_element"
    And I press "Reset table"
    And I should see "User200" in the "#teams" "css_element"
    And I should see "User300" in the "#teams" "css_element"
    And I should see "User400" in the "#teams" "css_element"
    And I should not see "User500" in the "#teams" "css_element"
    And I should not see "User600" in the "#teams" "css_element"
    And I should not see "User700" in the "#teams" "css_element"
    And I click on "Customise..." "radio"
    And I set the field "Department" to "Department_t1_d1"
    And I click on "Include subdepartments" "checkbox"
    And I should see "User200" in the "#teams" "css_element"
    And I should see "User300" in the "#teams" "css_element"
    And I should not see "User400" in the "#teams" "css_element"
    And I should not see "User500" in the "#teams" "css_element"
    And I should not see "User600" in the "#teams" "css_element"
    And I should see "User700" in the "#teams" "css_element"
    And I log out

  Scenario Outline: Teams members report should not show suspended or not confirmed users to user without permissions
    Given the following "tool_tenant > users" exist:
      | tenant    | username    | firstname | lastname      | email                 | suspended     | confirmed   |
      | Tenant1   | user18      | User      | 18            | user18@example.com    | <suspended>   | <confirmed> |
    And the following "roles" exist:
      | shortname      | name             | archetype |
      | browseusers    | Browse users     |           |
    And the following "role assigns" exist:
      | user     | role               | contextlevel | reference |
      | user16   | browseusers        | System       |           |
    And the following "permission overrides" exist:
      | capability              | permission | role             | contextlevel | reference |
      | tool/tenant:browseusers | Allow      | browseusers      | System       |           |
    # permissions: 1-Allocate users to programs/certifications, 2-View users reports, 4-Receive notifications(1+2+4,1+4,etc)
    And user "user11" has a manager position over users "user18" with permissions "7"
    And user "user16" has a manager position over users "user12,user13,user18" with permissions "7"
    When I log in as "user11"
    Then I should see "User 12"
    And I should see "User 13"
    And I should <user11expect> "User 18"
    And I log out
    And I log in as "user16"
    And I should see "User 12"
    And I should see "User 13"
    And I should <user16expect> "User 18"
    And I log out
    Examples:
      | suspended | confirmed | user11expect  | user16expect  |
      | 0         | 0         | not see       | see           |
      | 0         | 1         | see           | see           |
      | 1         | 0         | not see       | see           |
      | 1         | 1         | not see       | see           |
