@block @block_myteams @moodleworkplace
Feature: The team overview block allows users to view their teams information
  In order to view information about my teams
  As a user
  I can add the team overview block to my dashboard

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
    # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And the following "users" exist:
      | username | firstname | lastname    | email              | lastaccess              |
      | user17   | User      | 17          | user17@example.com | ##2022-07-14 09:28:00## |
    And the following users allocations to tenants exist:
      | user   | tenant  |
      | user17 | Tenant1 |
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user12" has a manager position over users "user17" with permissions "7"
    And user "user14" has a department lead position over users "user15" with permissions "2"

  Scenario: View the team overview block as a manager
    Given I log in as "user11"
    When I follow "Dashboard"
    And I switch editing mode on
    And I add the "Team overview" block
    And I configure the "Team overview" block
    And I set the following fields to these values:
      | Region | content |
    And I press "Save changes"
    Then I should see "User 12" in the "Team overview" "block"
    And I should see "User 13" in the "Team overview" "block"
    And I should not see "User 15" in the "Team overview" "block"

  @javascript
  Scenario: Message team members from team overview block
    When I log in as "user11"
    And I select "My teams" from primary navigation
    Then "Send message" "link" should not exist in the "User 12" "table_row"
    And I log out
    # Now enable relevant config.
    And the following config values are set as admin:
      | messagingallusers | 1 |
    And I log in as "user11"
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 12" "table_row"
    And I click on "Send message" "link" in the "User 12" "table_row"
    And I send "Welcome to Moodle Workplace" message in the message area
    # Because the above step gets confused about which button to press.
    And I click on "Send message" "button" in the "[data-region=message-drawer]" "css_element"
    And I log out
    And I log in as "user12"
    And I open messaging
    And "User 11" "core_message > Message" should exist
    And I should see "1" in the "User 11" "core_message > Message"
    And I select "User 11" conversation in messaging
    And I should see "Welcome to Moodle Workplace" in the "User 11" "group_message_conversation"

  @javascript
  Scenario: Show users course progress
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion |
      | Course 1 | C1        | 0        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user13   | C1     | student        |
      | user12   | C1     | student        |
    And the following "activity" exists:
      | course        | C1                     |
      | activity      | forum                  |
      | name          | Test forum name        |
      | description   | Test forum description |
      | idnumber      | forum1                 |
      | completion    | 1                      |
    And I log in as "user12"
    And I am on "Course 1" course homepage
    When I toggle the manual completion state of "Test forum name"
    Then the manual completion button of "Test forum name" is displayed as "Done"
    And I log out
    And I log in as "user11"
    When I follow "Dashboard"
    And I switch editing mode on
    And I add the "Team overview" block
    And I configure the "Team overview" block
    And I set the following fields to these values:
      | Region | content |
    And I press "Save changes"
    And I should see "Last access" in the ".block_myteams-userinfo" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I click on "[data-toggle=collapse]" "css_element" in the "User 12" "table_row"
    And I should see "Courses" in the "User 12" "table_row"
    And I should see "Course 1 · 95% completed" in the "User 12" "table_row"
    And I should see "Full report" in the "User 12" "table_row"
    And I click on "[data-toggle=collapse]" "css_element" in the "User 13" "table_row"
    And I should see "Courses" in the "User 13" "table_row"
    And I should see "Course 1 · 0% completed" in the "User 13" "table_row"
    And I should see "Full report" in the "User 13" "table_row"

  @javascript
  Scenario: Observe capability to view users profile
    When I log in as "admin"
    And I navigate to "Users > Organisation > Manage tenants" in site administration
    And I click on "Tenant1" "link" in the "Tenant1" "tool_wp > Table tree node"
    And I set the field "Select user 'User 12'" to "1"
    # Moving user12 to Tenant2 prevent show user profile in another tenant
    # given that tenant plugin apply a callback to control_view_profile to check this.
    Then I set the field "With selected users..." to "Tenant2"
    And I click on "Allocate users" "button" in the "Confirm" "dialogue"
    And I log out
    When I log in as "user11"
    And I select "My teams" from primary navigation
    And I click on "[data-toggle=collapse]" "css_element" in the "User 12" "table_row"
    Then "See profile" "link" should not exist in the "User 12" "table_row"
    And I click on "[data-toggle=collapse]" "css_element" in the "User 13" "table_row"
    And "See profile" "link" should exist in the "User 13" "table_row"
    And I log out

  @javascript
  Scenario: View last access tooltip
    When I log in as "user12"
    And I select "My teams" from primary navigation
    And I hover "button.icon-info[data-toggle='tooltip']" "css_element"
    And I should see "14/07/22, 09:28"
