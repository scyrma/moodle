@block @block_myteams @moodleworkplace
Feature: The 'My teams' block allows users to view their teams information
  In order to enable the logged in user block
  As a user
  I can add the 'My teams' block to show my teams information

  Background:
    Given "2" tenants exist with "7" users and "0" courses in each
        # This will create users: tenantadmin1, user11 .... , user16, tenantadmin2, user21 .... user26.
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user14" has a department lead position over users "user15" with permissions "2"

  Scenario: View the logged in user block by a logged in user
    Given I log in as "user11"
    When I follow "Dashboard"
    And I press "Customise this page"
    And I add the "My teams" block
    And I configure the "My teams" block
    And I set the following fields to these values:
      | Region | content |
    And I press "Save changes"
    And I should see "User 12" in the "My teams" "block"
    And I should see "User 13" in the "My teams" "block"
