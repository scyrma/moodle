@tool @tool_dynamicrule @moodleworkplace @javascript
Feature: Dynamicrule outcome notification works as expected
  In order to test the dynamicrule outcomes notification
  As a manager
  I need to create a rule to send a notification

  Background:
    Given "1" tenants exist with "3" users and "0" courses in each

  Scenario: Outcomes notification work as expected
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | First name |
      | Value | User |
    And I press "Save changes"
    And I should see "2 total matches"
    And I follow "Actions"
    And I click on "Notification" "link" in the "#ruleoutcomes" "css_element"
    And I set the following fields to these values:
      | Subject | You win     |
      | Body    | Your <a href="#">name is {{userfullname}}</a> |
    And I press "Save changes"
    And I click on "Enable" "button"
    And I click on "Enable" "button" in the "Confirm" "dialogue"
    And I log out
    And I trigger cron
    And I log in as "user11"
    When I click on ".popover-region-notifications" "css_element"
    And I should see "You win"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    And I should see "Your name is User 11"
    # Just to check that the HTML in the message was preserved and it is an actual link
    And I click on "name is User 11" "link"
    And I log out
    And I log in as "user12"
    When I click on ".popover-region-notifications" "css_element"
    And I click on "View full notification" "link" in the ".popover-region-notifications" "css_element"
    And I should see "Your name is User 12"
    And I log out
