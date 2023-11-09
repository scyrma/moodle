@tool @tool_organisation @moodleworkplace @javascript
Feature: Bulk actions for people management
  As a manager
  I want to be able to use the bulk actions for people

  Background:
    Given "1" tenants exist with "5" users and "0" courses in each
    And the following departments exist in organisation structure:
      | tenant  | name         | parent      |
      | Tenant1 | Framework1   |             |
      | Tenant1 | Department1  | Framework1  |
    And the following positions exist in organisation structure:
      | tenant  | name       | parent     | globalmanager | departmentmanager |
      | Tenant1 | Framework1 |            |      0        |       0           |
      | Tenant1 | Position1  | Framework1 |      0        |       0           |
      | Tenant1 | Position11 | Position1  |      0        |       0           |
      | Tenant1 | Position12 | Position1  |      0        |       0           |
    And the following job assignments exist in organisation structure:
      | user   | department  | position   | startdate                |
      | user13 | Department1 | Position1  | ##3 days ago##%Y-%m-%d## |
      | user13 | Department1 | Position11 | ##5 days ago##%Y-%m-%d## |

  Scenario: Add a job to multiple users action
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 11" "table_row"
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 12" "table_row"
    And I set the field "With selected users..." to "Add a job"
    And I set the following fields in the "New job for selected users" "dialogue" to these values:
      | positionid       | Position11         |
      | departmentid     | Department1        |
      | startdate[day]   | ##3 days ago##%d## |
      | startdate[month] | ##3 days ago##%B## |
      | startdate[year]  | ##3 days ago##%Y## |
    And I press "Save"
    Then I should see "Jobs created" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      | Jobs assigned            |
      | User 11                | user11@invalid.com | Position11 · Department1 |
      | User 12                | user12@invalid.com | Position11 · Department1 |

  Scenario: End jobs action
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 11" "table_row"
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 13" "table_row"
    And I set the field "With selected users..." to "Set jobs as finished"
    And I set the following fields in the "Set jobs as finished" "dialogue" to these values:
      | enddate[day]   | ##4 days ago##%d## |
      | enddate[month] | ##4 days ago##%B## |
      | enddate[year]  | ##4 days ago##%Y## |
    And I press "Proceed"
    Then I should see "User 13 has a job with start date after the specified end date."
    And I set the following fields in the "Set jobs as finished" "dialogue" to these values:
      | enddate[day]   | ##yesterday##%d## |
      | enddate[month] | ##yesterday##%B## |
      | enddate[year]  | ##yesterday##%Y## |
    And I press "Proceed"
    Then I should see "Jobs updated" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      | Jobs assigned                       |
      | User 13                | user13@invalid.com | Position1 · Department1 Not active  |
      | User 13                | user13@invalid.com | Position11 · Department1 Not active |

  Scenario: Transfer to a new job action
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 11" "table_row"
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 13" "table_row"
    And I set the field "With selected users..." to "Transfer to a new job"
    And I set the following fields in the "Transfer to a new job" "dialogue" to these values:
      | positionid       | Position12         |
      | departmentid     | Department1        |
      | startdate[day]   | ##4 days ago##%d## |
      | startdate[month] | ##4 days ago##%B## |
      | startdate[year]  | ##4 days ago##%Y## |
    And I press "Proceed"
    Then I should see "User 13 has a job with start date after the specified end date."
    And I set the following fields in the "Transfer to a new job" "dialogue" to these values:
      | startdate[day]   | ##yesterday##%d## |
      | startdate[month] | ##yesterday##%B## |
      | startdate[year]  | ##yesterday##%Y## |
    And I press "Proceed"
    Then I should see "Users transfered to a new job" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      | Jobs assigned                       |
      | User 11                | user11@invalid.com | Position12 · Department1            |
      | User 13                | user13@invalid.com | Position12 · Department1            |
      | User 13                | user13@invalid.com | Position1 · Department1 Not active  |
      | User 13                | user13@invalid.com | Position11 · Department1 Not active |

  Scenario: Assign a manager manually action
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 14" "table_row"
    And I set the field "With selected users..." to "Assign a manager manually"
    And I open the autocomplete suggestions list in the "Assign manager" "dialogue"
    And I click on "User 11" item in the autocomplete list
    And I press the escape key
    And I click on "Keep existing managers and add new ones" "radio" in the "Assign manager" "dialogue"
    And I click on "Save" "button" in the "Assign manager" "dialogue"
    Then I should see "Successfully assigned" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      |
      | User 11Is manager      | user11@invalid.com |
      | User 12                | user12@invalid.com |
      | User 13                | user13@invalid.com |
      | User 14                | user14@invalid.com |
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 14" "table_row"
    And I set the field "With selected users..." to "Assign a manager manually"
    And I open the autocomplete suggestions list in the "Assign manager" "dialogue"
    And I click on "User 12" item in the autocomplete list
    And I press the escape key
    And I click on "Keep existing managers and add new ones" "radio" in the "Assign manager" "dialogue"
    And I click on "Save" "button" in the "Assign manager" "dialogue"
    Then I should see "Successfully assigned" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      |
      | User 11Is manager      | user11@invalid.com |
      | User 12Is manager      | user12@invalid.com |
      | User 13                | user13@invalid.com |
      | User 14                | user14@invalid.com |
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 14" "table_row"
    And I set the field "With selected users..." to "Assign a manager manually"
    And I open the autocomplete suggestions list in the "Assign manager" "dialogue"
    And I click on "User 13" item in the autocomplete list
    And I press the escape key
    And I click on "Replace existing managers" "radio" in the "Assign manager" "dialogue"
    And I click on "Save" "button" in the "Assign manager" "dialogue"
    Then I should see "Successfully assigned" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      |
      | User 11                | user11@invalid.com |
      | User 12                | user12@invalid.com |
      | User 13Is manager      | user13@invalid.com |
      | User 14                | user14@invalid.com |

  Scenario: Unassign manually assigned managers action
    Given user "user11" is a manually assigned manager over users "user13,user14" with permissions "1"
    And user "user13" is a manually assigned manager over users "user14,user12" with permissions "1"
    And user "user14" is a manually assigned manager over users "user12" with permissions "1"
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      |
      | User 11Is manager      | user11@invalid.com |
      | User 12                | user12@invalid.com |
      | User 13Is manager      | user13@invalid.com |
      | User 14Is manager      | user14@invalid.com |
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 11" "table_row"
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 13" "table_row"
    And I click on "input[name='report-select-row[]']" "css_element" in the "User 14" "table_row"
    And I set the field "With selected users..." to "Un-assign managers"
    And I click on "Un-assign managers" "button" in the "Confirm" "dialogue"
    Then I should see "Manually assigned managers unassigned" in the ".toast-message" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address      |
      | User 11                | user11@invalid.com |
      | User 12                | user12@invalid.com |
      | User 13Is manager      | user13@invalid.com |
      | User 14Is manager      | user14@invalid.com |
