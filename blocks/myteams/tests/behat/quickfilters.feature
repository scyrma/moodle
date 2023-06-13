@block @block_myteams @moodleworkplace
Feature: The team overview quickfilers allow users to filter and sort the results
  In order to filter information about my teams
  As a user
  I can filter, sort and search subordinate users in the team overview block

  Background:
    # This will create users: tenantadmin1, user11 .... , user14, tenantadmin2, user21 .... user24.
    Given "2" tenants exist with "5" users and "2" courses in each
    And the following "tool_tenant > users" exist:
      | username  | firstname | lastname | email                | lastaccess              | tenant  |
      | user15    | User      | 15       | user15@example.com   | ##2022-07-14 09:28:00## | Tenant1 |
    And user "user11" has a manager position over users "user12,user13" with permissions "7"
    And user "user11" has a department lead position over users "user15" with permissions "2"
    And the following positions exist in organisation structure:
      | tenant  | name            | parent          |
      | Tenant1 | New position 3B  | New position 3 |
    And the following job assignments exist in organisation structure:
      | user    | department       | position        |
      | user14  | New department 2 | New position 3B |
    And the following "course enrolments" exist:
      | user   | course | role    |
      | user12 | C11 | student |
      | user12 | C12 | student |
      | user14 | C11 | student |
      | user15 | C12 | student |

  @javascript
  Scenario: Filter team overview report
    When I log in as "user11"
    And I select "My teams" from primary navigation
    # Filter "Everybody reporting to me" is applied by default.
    Then I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should see "User 14" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"
    # Apply filter "Only my own direct reports".
    And I click on "Filter by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Only my own direct reports" "button" in the "[data-region='myteams-filter']" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    # Filter "Teammates with due learning" is applied in addition to current filter.
    And I click on "Filter by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Teammates with due learning" "button" in the "[data-region='myteams-filter']" "css_element"
    And I should see "Nothing to display"
    And I click on "Filter by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Teammates with due learning" "button" in the "[data-region='myteams-filter']" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    # Preferences are preserved after reload.
    And I reload the page
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    # Apply filter "Everybody reporting to me" again.
    And I click on "Filter by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Everybody reporting to me" "button" in the "[data-region='myteams-filter']" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"
    And I should see "User 14" in the "reportbuilder-table" "table"

  @javascript
  Scenario: Sort team overview report
    When I log in as "user11"
    And I select "My teams" from primary navigation
    # Apply filter "Only my own direct reports" before to check it works alongside sorting.
    And I click on "Filter by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Only my own direct reports" "button" in the "[data-region='myteams-filter']" "css_element"
    # "Sort by recent access" is applied by default.
    Then "User 15" "text" should appear before "User 13" "text"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    # Apply "Sort by name".
    And I click on "Sort by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Sort by name" "button" in the "[data-region='myteams-sort']" "css_element"
    And "User 13" "text" should appear before "User 15" "text"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    # Apply "Sort by recent access" again.
    And I click on "Sort by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Sort by recent access" "button" in the "[data-region='myteams-sort']" "css_element"
    And "User 15" "text" should appear before "User 13" "text"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    # Preferences are preserved after reload.
    And I reload the page
    And "User 15" "text" should appear before "User 13" "text"
    And I should not see "User 14" in the "reportbuilder-table" "table"

  @javascript
  Scenario: Search team overview report
    When I log in as "user11"
    And I select "My teams" from primary navigation
    # Apply filter "Only my own direct reports" before to check it works alongside searching.
    And I click on "Filter by" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Only my own direct reports" "button" in the "[data-region='myteams-filter']" "css_element"
    # Apply "15" search.
    And I set the field "Search" in the "[data-region=myteams-search]" "css_element" to "15"
    Then "Clear search input" "button" should be visible
    And I should not see "User 12" in the "reportbuilder-table" "table"
    And I should not see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"
    # Apply "14" search.
    And I set the field "Search" in the "[data-region=myteams-search]" "css_element" to "14"
    And I should see "Nothing to display"
    # Preferences are preserved after reload.
    And I reload the page
    And I should see "Nothing to display"
    # Clear the search
    And I click on "Clear search input" "button" in the "[data-region=myteams-search]" "css_element"
    And I should see "User 12" in the "reportbuilder-table" "table"
    And I should see "User 13" in the "reportbuilder-table" "table"
    And I should not see "User 14" in the "reportbuilder-table" "table"
    And I should see "User 15" in the "reportbuilder-table" "table"

  @javascript
  Scenario: See full reports in team overview block
    When I log in as "user11"
    And I select "My teams" from primary navigation
    And I click on "Reports..." "button" in the "[data-region='myteams-quickfilters']" "css_element"
    And I click on "Full course report" "button" in the "[data-region='myteams-quickfilters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | Course       | First name            |
      | Course11     | User 12               |
      | Course12     | User 12               |
      | Course11     | User 14               |
      | Course12     | User 15               |
