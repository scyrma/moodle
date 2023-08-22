@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Manage report builder aggregations
  As an manager with permissions
  I want to be able to aggregate columns

  Background:
    Given the following tenants exist:
      | name   |
      | Tenant |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant |
      | manager1 | Tenant |
    And the following "roles" exist:
      | shortname                  | name                   | archetype |
      | tool_reportbuilder_manager | Report builder manager |           |
    And the following "permission overrides" exist:
      | capability              | permission | role                       | contextlevel | reference |
      | tool/reportbuilder:edit | Allow      | tool_reportbuilder_manager | System       |           |
      | moodle/site:configview  | Allow      | tool_reportbuilder_manager | System       |           |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |
    And the following "tool_reportbuilder > reports" exist:
      | name             | tenant | source                                                                     |
      | Example report   | Tenant | tool_reportbuilder\test\mock_aggregation                                   |
      | Example report 1 | Tenant | tool_reportbuilder\tool_reportbuilder\datasources\report_course_completion |
    And the following "users" exist:
      | username | firstname | lastname    | email               | picture | department   | institution   | city  | country | lastaccess |
      | user100  | User100   | Lastname100 | user100@example.com | 101    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user200  | User200   | Lastname200 | user200@example.com | 102    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user300  | User300   | Lastname300 | user300@example.com | 103    | Department 2 | Institution 2 | CITY2 | AU      | 1315958041 |
      | user400  | User400   | Lastname400 | user400@example.com | 104    | Department 2 | Institution 2 | CITY3 | AU      | 1425158941 |
      | user500  | User500   | Lastname500 | user500@example.com | 105    | Department 2 | Institution 2 | CITY4 | AU      | 1355958041 |
      | user600  | User600   | Lastname600 | user600@example.com | 106    | Department 2 | Institution 2 | CITY5 | FR      | 1425158941 |
      | user700  | User700   | Lastname700 | user700@example.com | 107    | Department 2 | Institution 2 | CITY6 | FR      | 1425158941 |
    And the following users allocations to tenants exist:
      | user    | tenant |
      | user100 | Tenant |
      | user200 | Tenant |
      | user300 | Tenant |
      | user400 | Tenant |
      | user500 | Tenant |
      | user600 | Tenant |
      | user700 | Tenant |

  # We are going to use the field 'picture' as a numeric and integer field.
  @javascript
  Scenario: Aggregate a column with type "number"
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'Phone' to the report" "button"
    And I click on "Add field 'Profile department' to the report" "button"
    # Check the type of field "number" can aggregate by Average.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Average"
    And I should see "101.5" in the "Department 1" "table_row"
    # Check the type of field "number" can aggregate by Count.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Count"
    And I should see "2" in the "Department 1" "table_row"
    # Check the type of field "number" can aggregate by Count unique.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Count unique"
    And I should see "2" in the "Department 1" "table_row"
    # Check the type of field "number" can aggregate by Comma separate values.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Comma separate values"
    And I should see "101" in the "Department 1" "table_row"
    And I should see "102" in the "Department 1" "table_row"
    # Check the type of field "number" can aggregate by Maximum.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Maximum"
    And I should see "102" in the "Department 1" "table_row"
    # Check the type of field "number" can aggregate by Minimum.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Minimum"
    And I should see "101" in the "Department 1" "table_row"
    # Check the type of field "number" can aggregate by Sum.
    And I click on "Select an aggregation for the column 'Phone'" "link"
    And I set the field "New aggregation for the column 'Phone'" to "Sum"
    And I should see "203" in the "Department 1" "table_row"
    And I log out

  @javascript
  Scenario: Aggregate a column with type "number" and filters
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'Profile department' to the report" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand conditions" "button"
    #And I click on "Select a condition" "text"
    And I set the field "Select a condition" to "Country"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Spain"
    And I click on "Add field 'Full name' to the report" "button"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count"
    And I should see "2" in the "Department 1" "table_row"
    And I set the field "Country value" to "Australia"
    And I should see "3" in the "Department 2" "table_row"
    And I set the field "Country value" to "France"
    And I should see "2" in the "Department 2" "table_row"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count unique"
    And I should see "2" in the "Department 2" "table_row"
    And I log out

  @javascript
  Scenario: Aggregate a column with type "text"
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'Country' to the report" "button"
    And I click on "Add field 'Country' to the report" "button"
    # Check the type of field "text" can aggregate by Count.
    And I click on "Select an aggregation for the column 'Country 1'" "link"
    And I set the field "New aggregation for the column 'Country 1'" to "Count"
    And I should see "3" in the "Australia" "table_row"
    And I should see "2" in the "Spain" "table_row"
    And I should see "2" in the "France" "table_row"
    # Check the type of field "text" can aggregate by Count unique.
    And I click on "Select an aggregation for the column 'Country 1'" "link"
    And I set the field "New aggregation for the column 'Country 1'" to "Count unique"
    And I should see "1" in the "Australia" "table_row"
    And I should see "1" in the "Spain" "table_row"
    And I should see "1" in the "France" "table_row"
    # Check the type of field "text" can aggregate by Comma separate values.
    And I click on "Select an aggregation for the column 'Country'" "link"
    And I set the field "New aggregation for the column 'Country'" to "Comma separate values"
    And I click on "Delete column 'Country 1'" "link"
    And I click on "Add field 'Profile department' to the report" "button"
    And I should see "Spain, Spain" in the "Department 1" "table_row"
    # And I should see "Australia, Australia, Australia, France, France" in the "Department 2" "table_row"
    And I should see "Australia" in the "Department 2" "table_row"
    And I should see "France" in the "Department 2" "table_row"
    # Check the type of field "text" can aggregate by Unique values.
    And I click on "Delete column 'Profile department'" "link"
    And I click on "Select an aggregation for the column 'Country'" "link"
    And I set the field "New aggregation for the column 'Country'" to "Unique values"
    And I should see "Australia" in the "Australia" "table_row"
    And I should see "Spain" in the "Spain" "table_row"
    And I should see "France" in the "France" "table_row"
    # Check options
    And I click on "Select an aggregation for the column 'Country'" "link"
    And "Maximum" "text" should not exist in the ".custom-select" "css_element"
    And "Minimum" "text" should not exist in the ".custom-select" "css_element"
    And "Sum" "text" should not exist in the ".custom-select" "css_element"
    And "Average" "text" should not exist in the ".custom-select" "css_element"
    And I log out

  @javascript
  Scenario: Aggregate a column with type "time"
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I change window size to "large"
    And I follow "Example report"
    And I click on "Add field 'Full name' to the report" "button"
    And I click on "Add field 'Last access' to the report" "button"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Comma separate values"
    #And I should see "User100 Lastname100,User200 Lastname200,User300 Lastname300" in the "14/09/11" "table_row"
    And I should see "User100 Lastname100" in the "14/09/11" "table_row"
    And I should see "User200 Lastname200" in the "14/09/11" "table_row"
    And I should see "User300 Lastname300" in the "14/09/11" "table_row"
    And I should see "User500 Lastname500" in the "20/12/12" "table_row"
    #And I should see "User400 Lastname400,User600 Lastname600,User700 Lastname700" in the "1/03/15" "table_row"
    And I should see "User400 Lastname400" in the "1/03/15" "table_row"
    And I should see "User600 Lastname600" in the "1/03/15" "table_row"
    And I should see "User700 Lastname700" in the "1/03/15" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I click on "Enable sorting on column 'Last access'" "button"
    And I click on "The column 'Last access' has ascending sort direction" "link"
    And "User400 Lastname400" "table_row" should appear before "User500 Lastname500" "table_row"
    And "User500 Lastname500" "table_row" should appear before "User100 Lastname100" "table_row"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count unique"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Unique values"
    # Check with condition
    And I click on "Expand conditions" "button"
    And I set the field "Select a condition" to "Country"
    And I set the field "Select a condition" to "Last access"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Spain"
    And I click on "Select an aggregation for the column 'Last access'" "link"
    And I set the field "New aggregation for the column 'Last access'" to "Count"
    And I click on "Select an aggregation for the column 'Last access'" "link"
    And I set the field "New aggregation for the column 'Last access'" to "Count unique"
    And I click on "Select an aggregation for the column 'Last access'" "link"
    And I set the field "New aggregation for the column 'Last access'" to "Maximum"
    And I click on "Select an aggregation for the column 'Last access'" "link"
    And I set the field "New aggregation for the column 'Last access'" to "Minimum"
    And I click on "Select an aggregation for the column 'Last access'" "link"
    And I set the field "New aggregation for the column 'Last access'" to "Unique values"
    # Check with filter TODO (WP-570)
    And I log out

  @javascript
  Scenario: Aggregate a column with type "boolean"
    Given the following "users" exist:
      | username | firstname | lastname | email             | auth   | confirmed | country |
      | user1    | User      | One      | one@example.com   | manual | 0         | BR      |
      | user2    | User      | Two      | two@example.com   | ldap   | 1         | BR      |
      | user3    | User      | Three    | three@example.com | manual | 1         | PE      |
      | user4    | User      | Four     | four@example.com  | ldap   | 0         | BR      |
    And the following users allocations to tenants exist:
      | user  | tenant  |
      | user1 | Tenant |
      | user2 | Tenant |
      | user3 | Tenant |
      | user4 | Tenant |
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'Confirmed' to the report" "button"
    And I click on "Add field 'Confirmed' to the report" "button"
    And I click on "Select an aggregation for the column 'Confirmed 1'" "link"
    And I set the field "New aggregation for the column 'Confirmed 1'" to "Count"
    Then I should see "10" in the "Yes" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand conditions" "button"
    And I set the field "Select a condition" to "Country"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Brazil"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "1" in the "Yes" "table_row"
    And I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Count unique"
    And I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Comma separate values"
    And I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Minimum"
    And I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Maximum"
    And I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Unique values"
    And I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Percentage"
    And I click on "Add field 'Country' to the report" "button"
    And I should see "33.33" in the "Brazil" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Confirmed"
    # TODO: test with sort (WP-570)
    And I log out

  @javascript
  Scenario Outline: Aggregate a user custom field
    Given the following "custom profile fields" exist:
      | datatype | shortname | name          |
      | text     | myfield   | My cool field |
    And the following "users" exist:
      | username | firstname | lastname | email             | profile_field_myfield |
      | user1    | User      | One      | user1@example.com | Cat                   |
      | user2    | User      | Two      | user2@example.com | Dog                   |
      | user3    | User      | Three    | user3@example.com | Dog                   |
    And the following users allocations to tenants exist:
      | user  | tenant |
      | user1 | Tenant |
      | user2 | Tenant |
      | user3 | Tenant |
    When I log in as "manager1"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'My cool field' to the report" "button"
    And I click on "Select an aggregation for the column 'My cool field'" "link"
    And I set the field "New aggregation for the column 'My cool field'" to "<aggregation>"
    Then I should see "<result>" in the "report-table" "table"
    And I log out
    Examples:
      | aggregation           | result        |
      | Count                 | 3             |
      | Count unique          | 2             |
      | Comma separate values | Cat, Dog, Dog |
    # Note that "Comma separate distinct values" isn't fully supported cross-DB (WP-1006).

  @javascript
  Scenario: Aggregate a fullname column (special column)
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'Full name' to the report" "button"
    #Test count
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count"
    Then I should see "8" in the "table" "css_element"
    And I click on "Add field 'Country' to the report" "button"
    And I should see "3" in the "Australia" "table_row"
    And I should see "2" in the "Spain" "table_row"
    And I should see "2" in the "France" "table_row"
    And I click on "Delete column 'Country'" "link"
    #Test Comma separates values
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Comma separate values"
    # Sort order in these kind of aggregation columns is not guaranteed.
    #Then I should see "Manager 1,User100 Lastname100,User200 Lastname200,User300 Lastname300,User400 Lastname400,User500 Lastname500,User600 Lastname600,User700 Lastname700"
    And I should see "User300 Lastname300" in the "User600 Lastname600" "table_row"
    And I should see "User400 Lastname400" in the "User600 Lastname600" "table_row"
    And I should see "User500 Lastname500" in the "User600 Lastname600" "table_row"
    And I should see "User200 Lastname200" in the "User600 Lastname600" "table_row"
    And I should see "User100 Lastname100" in the "User600 Lastname600" "table_row"
    And I should see "Manager 1" in the "User600 Lastname600" "table_row"
    And I should see "User700 Lastname700" in the "User600 Lastname600" "table_row"
    And I click on "Add field 'Country' to the report" "button"
    #And I should see "User300 Lastname300,User400 Lastname400,User500 Lastname500" in the "Australia" "table_row"
    And I should see "User300 Lastname300" in the "Australia" "table_row"
    And I should see "User400 Lastname400" in the "Australia" "table_row"
    And I should see "User500 Lastname500" in the "Australia" "table_row"
    And I should see "User200 Lastname200" in the "Spain" "table_row"
    And I should see "User100 Lastname100" in the "Spain" "table_row"
    And I should see "User600 Lastname600" in the "France" "table_row"
    And I should see "User700 Lastname700" in the "France" "table_row"
    And I click on "Delete column 'Country'" "link"
    #Test Unique values
    Given the following "users" exist:
      | username | firstname | lastname     | email               | picture | department   | institution   | city  | country |
      | user800  | Samename  | Samelastname | user100@example.com | 1      | Department 1 | Institution 1 | CITY1 | AL      |
      | user900  | Samename  | Samelastname | user200@example.com | 2      | Department 1 | Institution 1 | CITY1 | AL      |
    And the following users allocations to tenants exist:
      | user    | tenant |
      | user800 | Tenant |
      | user900 | Tenant |
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report"
    And I click on "Add field 'Country' to the report" "button"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count"
    And I should see "2" in the "Albania" "table_row"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count unique"
    And I should see "1" in the "Albania" "table_row"
    And I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Comma separate values"
    And I should see "Samename Samelastname" in the "Albania" "table_row"
    And I log out

  @javascript
  Scenario: Aggregate a course custom fields
    When I log in as "manager1"
    Given the following "custom field categories" exist:
      | name              | component   | area   | itemid |
      | Category for test | core_course | course | 0      |
    And the following "custom fields" exist:
      | name    | category          | type     | shortname | description | configdata            |
      | Field 1 | Category for test | text     | f1        | d1          |                       |
      | Field 2 | Category for test | textarea | f2        | d2          |                       |
      | Field 3 | Category for test | checkbox | f3        | d3          |                       |
      | Field 4 | Category for test | date     | f4        | d4          |                       |
      | Field 5 | Category for test | select   | f5        | d5          | {"options":"a\nb\nc"} |
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I follow "Example report 1"
    And I change window size to "large"
    # Try different aggregation types for Field 1 (text)
    And I click on "Add field 'Field 1' to the report" "link"
    And I click on "Select an aggregation for the column 'Field 1'" "link"
    And I set the field "New aggregation for the column 'Field 1'" to "Count"
    And I click on "Select an aggregation for the column 'Field 1'" "link"
    And I set the field "New aggregation for the column 'Field 1'" to "Count unique"
    And I click on "Select an aggregation for the column 'Field 1'" "link"
    And I set the field "New aggregation for the column 'Field 1'" to "Comma separate values"
    And I click on "Select an aggregation for the column 'Field 1'" "link"
    And I set the field "New aggregation for the column 'Field 1'" to "Unique values"
    # Try different aggregation types for Field 2 (textarea)
    And I click on "Add field 'Field 2' to the report" "link"
    And I click on "Select an aggregation for the column 'Field 2'" "link"
    And I set the field "New aggregation for the column 'Field 2'" to "Count"
    And I click on "Select an aggregation for the column 'Field 2'" "link"
    And I set the field "New aggregation for the column 'Field 2'" to "Count unique"
    And I click on "Select an aggregation for the column 'Field 2'" "link"
    And I set the field "New aggregation for the column 'Field 2'" to "Comma separate values"
    And I click on "Select an aggregation for the column 'Field 2'" "link"
    And I set the field "New aggregation for the column 'Field 2'" to "Unique values"
    # Try different aggregation types for Field 3 (checkbox)
    And I click on "Add field 'Field 3' to the report" "link"
    And I click on "Select an aggregation for the column 'Field 3'" "link"
    And I set the field "New aggregation for the column 'Field 3'" to "Count"
    And I click on "Select an aggregation for the column 'Field 3'" "link"
    And I set the field "New aggregation for the column 'Field 3'" to "Count unique"
    And I click on "Select an aggregation for the column 'Field 3'" "link"
    And I set the field "New aggregation for the column 'Field 3'" to "Comma separate values"
    And I click on "Select an aggregation for the column 'Field 3'" "link"
    And I set the field "New aggregation for the column 'Field 3'" to "Unique values"
    # Try different aggregation types for Field 4 (date)
    And I click on "Add field 'Field 4' to the report" "link"
    And I click on "Select an aggregation for the column 'Field 4'" "link"
    And I set the field "New aggregation for the column 'Field 4'" to "Count"
    And I click on "Select an aggregation for the column 'Field 4'" "link"
    And I set the field "New aggregation for the column 'Field 4'" to "Count unique"
    And I click on "Select an aggregation for the column 'Field 4'" "link"
    And I set the field "New aggregation for the column 'Field 4'" to "Unique values"
    # Try different aggregation types for Field 5 (select)
    And I click on "Add field 'Field 5' to the report" "link"
    And I click on "Select an aggregation for the column 'Field 5'" "link"
    And I set the field "New aggregation for the column 'Field 5'" to "Count"
    And I click on "Select an aggregation for the column 'Field 5'" "link"
    And I set the field "New aggregation for the column 'Field 5'" to "Count unique"
    And I click on "Select an aggregation for the column 'Field 5'" "link"
    And I set the field "New aggregation for the column 'Field 5'" to "Comma separate values"
    And I click on "Select an aggregation for the column 'Field 5'" "link"
    And I set the field "New aggregation for the column 'Field 5'" to "Unique values"
    And I log out

  @javascript
  Scenario: Make sure column sorting is not possible for some aggregation types.
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I change window size to "large"
    And I follow "Example report"
    And I click on "Add field 'Full name' to the report" "button"
    And I click on "Add field 'Last access' to the report" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I click on "Expand sorting" "button"
    And I should see "Full name" in the "region-report-sorting" "region"
    And I should see "Last access" in the "region-report-sorting" "region"
    # Change aggregation to each type and observe sorting tab changes.
    When I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count"
    Then I should see "Full name" in the "region-report-sorting" "region"
    And I should see "Last access" in the "region-report-sorting" "region"
    When I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Count unique"
    Then I should see "Full name" in the "region-report-sorting" "region"
    And I should see "Last access" in the "region-report-sorting" "region"
    When I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Comma separate values"
    Then I should not see "Full name" in the "region-report-sorting" "region"
    And I should see "Last access" in the "region-report-sorting" "region"
    When I click on "Select an aggregation for the column 'Full name'" "link"
    And I set the field "New aggregation for the column 'Full name'" to "Unique values"
    Then I should see "Full name" in the "region-report-sorting" "region"
    And I should see "Last access" in the "region-report-sorting" "region"
    And I log out

  @javascript
  Scenario: Confirm report paging is correct when using aggregated columns
    Given "1" tenants exist with "20" users and "0" courses in each
    And the following "tool_reportbuilder > reports" exist:
      | name           | tenant  | source                                   |
      | Example report | Tenant1 | tool_reportbuilder\test\mock_aggregation |
    When I log in as "tenantadmin1"
    And I change window size to "large"
    And I navigate to "Reports > Report builder - outdated version > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Example report" "table_row"
    And I click on "Add field 'Confirmed' to the report" "button"
    Then ".tool_reportbuilder_report ul.pagination" "css_element" should exist
    And I should see "2" in the ".tool_reportbuilder_report ul.pagination" "css_element"
    When I click on "Select an aggregation for the column 'Confirmed'" "link"
    And I set the field "New aggregation for the column 'Confirmed'" to "Comma separate values"
    Then ".tool_reportbuilder_report ul.pagination" "css_element" should not exist
