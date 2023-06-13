@tool @tool_tenant @moodleworkplace @javascript @mod_url
Feature: Tenant information can be added as parameters in the URL resource

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
    And the following "roles" exist:
      | shortname        | name                | archetype |
      | tenantaddmanager | Tenants add manager |           |
    And the following "role assigns" exist:
      | user  | role              | contextlevel | reference |
      | user1 | tenantaddmanager  | System       |           |
    And the following "permission overrides" exist:
      | capability             | permission | role              | contextlevel | reference |
      | tool/tenant:manage     | Allow      | tenantaddmanager  | System       |           |
      | moodle/site:configview | Allow      | tenantaddmanager  | System       |           |
    And the following config values are set as admin:
      | displayoptions | 0,1,2,3,4,5,6 | url |
      | tenantdata  | 1             | url |
    And the following "tool_tenant > tenants" exist:
      | name      | showinloginselector | sitename    | idnumber |
      | Nike      | 1                   | Nike Site   | 0000Nike |
      | Adidas    | 1                   | Adidas Site | 00Adidas |
      | Reebok    | 1                   | Reebok Site | 00Reebok |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | 0        | 1                | 1                        |
    And the following "course enrolments" exist:
      | user   | course  | role            |
      | user1  | C1      | editingteacher  |
    Given the following "activity" exists:
      | activity       | url                 |
      | course         | C1                  |
      | idnumber       | Music history       |
      | name           | Music history       |
      | intro          | URL description     |
      | externalurl    | https://moodle.org/ |
      | completion     | 2                   |
      | completionview | 1                   |
      | display        | 0                   |
    When I log in as "user1"
    And I am on "Course 1" course homepage with editing mode on
    And I am on the "Music history" "url activity" page
    And I follow "Settings"
    And I click on "URL variables" "link"
    And I set the field "parameter_0" to "idnumber"
    And I set the field "variable_0" to "Tenant ID number"
    And I click on "Save and display" "button"

  Scenario Outline: Tenant id number is correctly added to the url
    When I log in as "user1"
    And I switch to tenant "<tenantname>"
    And I am on "Course 1" course homepage
    And I am on the "Music history" "url activity" page
    Then I should see "Click https://moodle.org/?idnumber=<idnumber> link to open resource"
    Examples:
      | tenantname | idnumber |
      | Nike       | 0000Nike |
      | Adidas     | 00Adidas |
      | Reebok     | 00Reebok |
