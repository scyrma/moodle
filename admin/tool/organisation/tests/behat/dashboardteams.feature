@tool @tool_organisation @moodleworkplace @theme_workplace @javascript
Feature: Viewing one's team on the dashboard
  As an organisation manager
  I should be able to view my teams on the dashboard

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
        # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a global manager position over users "user12,user13" with permissions "7"
    And user "user14" has a department manager position over users "user15" with permissions "2"

  Scenario: Messaging team members from the dashboard
    When I log in as "user11"
    Then "Send message" "link" should not exist in the "User 12" "table_row"
    And I log out
    And I log in as "admin"
    And I set the following administration settings values:
      | messagingallusers | true |
    And I log out
    And I log in as "user11"
    And I click on "Send message" "link" in the "User 12" "table_row"
    And I send "Welcome to Moodle Workplace" message in the message area
    And I log out
    And I log in as "user12"
    And I should see "1" in the "//*[@title='Toggle messaging drawer']/../*[@data-region='count-container']" "xpath_element"
    And I open messaging
    And I should see "1" in the "Private" "group_message_tab"
    And "User 11" "group_message" should exist
    And I should see "1" in the "User 11" "group_message"
    And I select "User 11" conversation in messaging
    And I should see "Welcome to Moodle Workplace" in the "User 11" "group_message_conversation"
    And I log out

  Scenario: Filtering and messaging team members from the dashboard
    And I log in as "admin"
    And I set the following administration settings values:
      | messagingallusers | true |
    And I log out
    And I log in as "user11"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Department" to "New department 2"
    And I follow "Reset all"
    And I click on "Send message" "link" in the "User 12" "table_row"
    And I send "Welcome to Moodle Workplace" message in the message area
    And I log out
    And I log in as "user12"
    And I open messaging
    And I select "User 11" conversation in messaging
    And I should see "Welcome to Moodle Workplace" in the "User 11" "group_message_conversation"
    And I log out
