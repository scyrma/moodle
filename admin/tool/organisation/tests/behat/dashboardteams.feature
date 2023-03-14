@tool @tool_organisation @moodleworkplace @theme_workplace @javascript
Feature: Viewing one's team on the dashboard
  As an organisation manager
  I should be able to view my teams on the dashboard

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
        # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user14" has a department lead position over users "user15" with permissions "2"

  # TODO Move this to the "My teams" block.
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
    And I select "My teams" from primary navigation
    Then I should see "User 12"
    And I should see "User 13"
    And I should <user11expect> "User 18"
    And I log out
    And I log in as "user16"
    And I select "My teams" from primary navigation
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
