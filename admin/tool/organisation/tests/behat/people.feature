@tool @tool_organisation @moodleworkplace @javascript
Feature: Organisation structure people management
  As a manager
  I want to be able to see all the people and their jobs

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
      | Tenant2 |
    And the following "tool_tenant > users" exist:
      | username | firstname | lastname | email                  | tenant  |
      | user10   | User      | 10       | user10@address.invalid | Tenant1 |
      | user11   | User      | 11       | user11@address.invalid | Tenant1 |
      | user12   | User      | 12       | user12@address.invalid | Tenant1 |
      | user13   | User      | 13       | user13@address.invalid | Tenant1 |
      | user21   | User      | 21       | user21@address.invalid | Tenant2 |
      | user22   | User      | 22       | user22@address.invalid | Tenant2 |
      | user23   | User      | 23       | user23@address.invalid | Tenant2 |
    And the following departments exist in organisation structure:
      | tenant  | name         | parent      |
      | Tenant1 | Framework1   |             |
      | Tenant1 | Department1  | Framework1  |
      | Tenant1 | Department11 | Department1 |
      | Tenant2 | Framework2   |             |
      | Tenant2 | Department2  | Framework2  |
    And the following positions exist in organisation structure:
      | tenant  | name       | parent     | globalmanager | departmentmanager |
      | Tenant1 | Framework1 |            |      0        |       0           |
      | Tenant1 | Position1  | Framework1 |      1        |       1           |
      | Tenant1 | Position11 | Position1  |      0        |       0           |
      | Tenant1 | Position12 | Position1  |      0        |       0           |
      | Tenant2 | Framework2 |            |      0        |       0           |
      | Tenant2 | Position2  | Framework2 |      1        |       1           |
      | Tenant2 | Position21 | Position2  |      0        |       0           |
      | Tenant2 | Position22 | Position2  |      0        |       0           |
    And the following job assignments exist in organisation structure:
      | user   | department  | position   |
      | user10 | Department11| Position11  |
      | user11 | Department1 | Position1  |
      | user12 | Department1 | Position11 |
      | user12 | Department1 | Position12 |
      | user21 | Department2 | Position2  |
      | user22 | Department2 | Position21 |
      | user22 | Department2 | Position22 |

  Scenario: See full list of people
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned           |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1 |
      | User 13                | user13@address.invalid |                         |
    And I should see "Position11 · Department1" in the "User 12" "table_row"
    And I should see "Position12 · Department1" in the "User 12" "table_row"
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          |
      | User 21Is manager      | user21@address.invalid |
      | User 22                | user22@address.invalid |
      | User 23                | user23@address.invalid |
    Then I switch to tenant "Tenant2"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned           |
      | User 21Is manager      | user21@address.invalid | Position2 · Department2 |
      | User 23                | user23@address.invalid |                         |
    And I should see "Position21 · Department2" in the "User 22" "table_row"
    And I should see "Position22 · Department2" in the "User 22" "table_row"
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          |
      | User 11Is manager      | user11@address.invalid |
      | User 12                | user12@address.invalid |
      | User 13                | user13@address.invalid |

  Scenario: Use position and department filters in people tab
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I change window size to "large"
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 12                | user12@address.invalid | Position11 · Department1  |
      | User 13                | user13@address.invalid |                           |
    And I click on "Filters" "button"
    When I set the following fields in the "Position" "core_reportbuilder > Filter" to these values:
      | Position               | Position1 |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And I should see "Filters (1)" in the "#dropdownFiltersButton" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
    When I set the following fields in the "Position" "core_reportbuilder > Filter" to these values:
      | Include subpositions   | 1          |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned               |
      | User 10                | user10@address.invalid | Position11 · Department11   |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1     |
      | User 12                | user12@address.invalid | Position11 · Department1    |
    When I set the following fields in the "Department" "core_reportbuilder > Filter" to these values:
      | Department             | Department11           |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned               |
      | User 10                | user10@address.invalid | Position11 · Department11   |
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned               |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1     |
      | User 12                | user12@address.invalid | Position11 · Department1    |

  Scenario: Use is manager filters in people tab
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I change window size to "large"
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 12                | user12@address.invalid | Position11 · Department1  |
      | User 13                | user13@address.invalid |                           |
    And I click on "Filters" "button"
    And I set the following fields in the "Is manager" "core_reportbuilder > Filter" to these values:
      | Is manager operator    | Yes                    |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And I should see "Filters (1)" in the "#dropdownFiltersButton" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 12                | user12@address.invalid | Position11 · Department1  |
      | User 13                | user13@address.invalid |                           |
    And I set the following fields in the "Is manager" "core_reportbuilder > Filter" to these values:
      | Is manager operator    | No                     |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 12                | user12@address.invalid | Position11 · Department1  |
      | User 13                | user13@address.invalid |                           |

  Scenario: Use people without jobs filters in people tab
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I change window size to "large"
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 12                | user12@address.invalid | Position11 · Department1  |
      | User 13                | user13@address.invalid |                           |
    And I click on "Filters" "button"
    And I set the following fields in the "Show people with jobs" "core_reportbuilder > Filter" to these values:
      | Show people with jobs operator | No |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And I should see "Filters (1)" in the "#dropdownFiltersButton" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 13                | user13@address.invalid |                           |
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 12                | user12@address.invalid | Position11 · Department1  |
    And I set the following fields in the "Show people with jobs" "core_reportbuilder > Filter" to these values:
      | Show people with jobs operator | Yes |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 13                | user13@address.invalid |                           |
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 12                | user12@address.invalid | Position11 · Department1  |

  Scenario: Use people without managers filters in people tab
    When I log in as "admin"
    And I switch to tenant "Tenant1"
    And I navigate to "Users > Organisation > Organisation structure" in site administration
    And I change window size to "large"
    Then the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 12                | user12@address.invalid | Position11 · Department1  |
      | User 13                | user13@address.invalid |                           |
    And I click on "Filters" "button"
    And I set the following fields in the "Show people with managers" "core_reportbuilder > Filter" to these values:
      | Show people with managers operator | No |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And I should see "Filters (1)" in the "#dropdownFiltersButton" "css_element"
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 13                | user13@address.invalid |                           |
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 12                | user12@address.invalid | Position11 · Department1  |
    And I set the following fields in the "Show people with managers" "core_reportbuilder > Filter" to these values:
      | Show people with managers operator | Yes |
    And I click on "Apply" "button" in the "[data-region='report-filters']" "css_element"
    Then I should see "Filters applied"
    And the following should not exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 11Is manager      | user11@address.invalid | Position1 · Department1   |
      | User 13                | user13@address.invalid |                           |
    And the following should exist in the "reportbuilder-table" table:
      | First name / Last name | Email address          | Jobs assigned             |
      | User 10                | user10@address.invalid | Position11 · Department11 |
      | User 12                | user12@address.invalid | Position11 · Department1  |
