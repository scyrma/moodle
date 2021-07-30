@tool @tool_reportbuilder @moodleworkplace
Feature: Manage a filter
  In order to manage a report
  As an user with permissions
  I need to be able to add filters, remove filters, view filters in reports, reset filters, reset a filter

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | manager1 | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: Add a new filter to the report
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I set the field "Select a filter" to "Surname"
    And I set the field "Select a filter" to "First name"
    And I set the field "Select a filter" to "ID number"
    And I should see "Surname" in the "ul.js-filters-list" "css_element"
    And I should see "First name" in the "ul.js-filters-list" "css_element"
    And I should see "ID number" in the "ul.js-filters-list" "css_element"
    # Preview mode
    And I click on "Switch to preview view" "button"
    And I should see "Switch to edit view"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Surname"
    And I should see "First name"
    And I should see "ID number"
    And I set the field "Surname field limiter" to "contains"
    And I should not see "Delete filter 'Surname'"
    And I should not see "Delete condition 'Surname'"
    And I should not see "Edit condition name"
    And "Reset 'Surname' field" "button" should exist
    And I log out

  @javascript
  Scenario: Change the order of a filter
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Delete column" "link" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I click on "Delete column" "link" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I set the field "Select a filter" to "Surname"
    And I set the field "Select a filter" to "First name"
    And I set the field "Select a filter" to "ID number"
    And I click on "Move filter 'ID number'" "button"
    And I follow "To the top of the list"
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And "ID number" "text" should appear before "Surname" "text"
    And I click on "Switch to edit view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I click on "Move filter 'ID number'" "button"
    And I follow "After \" First name \""
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And "ID number" "text" should appear after "Surname" "text"
    And I log out

  @javascript
  Scenario: Ensure the formatting is applied correctly to filter names
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I change window size to "large"
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I set the field "Select a filter" to "Surname"
    And I set the field "Select a filter" to "First name"
    And I set the field "Edit filter name" in the "#filterstab [data-key=\"user:lastname\"]" "css_element" to "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"
    And I should see "Test&\"2" in the "#filterstab" "css_element"
    And I should not see "Prueba"
    # Reload, recheck and test moving
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I should see "Test&\"2" in the "#filterstab" "css_element"
    And I should not see "Prueba"
    And I click on "Move filter 'Test&\"2'" "button"
    And I click on "After \" First name \"" "link" in the "Move filter 'Test&\"2'" "dialogue"
    And I click on "Move filter 'First name'" "button"
    And I click on "After \" Test&\"2 \"" "link" in the "Move filter 'First name'" "dialogue"
    # Preview mode
    And I click on "Switch to preview view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Test&\"2"
    And I should not see "Prueba"
    And I set the field "Test&\"2 field limiter" to "contains"
    And I set the field "Test&\"2 value" to "e"
    And I log out

  @javascript
  Scenario: Use filters
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    And the following "users" exist:
      | username | firstname | lastname    | email               | phone1 | department   | institution   | city  | country | lastaccess |
      | user100  | User100   | Lastname100 | user100@example.com | 101    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user200  | User200   | Lastname200 | user200@example.com | 102    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user300  | User300   | Lastname300 | user300@example.com | 103    | Department 2 | Institution 2 | CITY2 | AU      | 1315958041 |
      | user400  | User400   | Lastname400 | user400@example.com | 104    | Department 2 | Institution 2 | CITY3 | AU      | 1425158941 |
      | user500  | User500   | Lastname500 | user500@example.com | 105    | Department 2 | Institution 2 | CITY4 | AU      | 1355958041 |
      | user600  | User600   | Lastname600 | user600@example.com | 106    | Department 2 | Institution 2 | CITY5 | FR      | 1425158941 |
      | user700  | User700   | Lastname700 | user700@example.com | 107    | Department 2 | Institution 2 | CITY6 | FR      | 1425158941 |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user100    | Tenant1 |
      | user200    | Tenant1 |
      | user300    | Tenant1 |
      | user400    | Tenant1 |
      | user500    | Tenant1 |
      | user600    | Tenant1 |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I change window size to "large"
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I set the field "Select a filter" to "Country"
    And I set the field "Select a filter" to "Profile department"
    And I set the field "Select a filter" to "Institution"
    #Check in preview
    And I click on "Switch to preview view" "button"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Country"
    And I should see "Profile department"
    And I should see "Institution"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Australia"
    And I should not see "User100 Lastname100" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should not see "User600 Lastname600" in the "report-table" "table"
    And I click on "Reset 'Country' field" "button"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "France"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User100 Lastname100" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "User400 Lastname400" in the "report-table" "table"
    And I should not see "User500 Lastname500" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    And I press "Reset table"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    # Add some filters, confirm count is correct.
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Australia"
    And I set the field "Profile department value" to "Dep1"
    And I set the field "Institution value" to "Inst1"
    And I press the enter key
    And I should see "3" in the ".js-filters-active" "css_element"
    And I click on "Reset 'Country' field" "button"
    And I should see "2" in the ".js-filters-active" "css_element"
    And I log out

  @javascript
  Scenario: Ensure adding filters to a report makes them available to the user
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    And the following "users" exist:
      | username | firstname | lastname    | email               | phone1 | department   | institution   | city  | country | lastaccess |
      | user100  | User100   | Lastname100 | user100@example.com | 101    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user200  | User200   | Lastname200 | user200@example.com | 102    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user300  | User300   | Lastname300 | user300@example.com | 103    | Department 2 | Institution 2 | CITY2 | AU      | 1315958041 |
      | user400  | User400   | Lastname400 | user400@example.com | 104    | Department 2 | Institution 2 | CITY3 | AU      | 1425158941 |
      | user500  | User500   | Lastname500 | user500@example.com | 105    | Department 2 | Institution 2 | CITY4 | AU      | 1354958041 |
      | user600  | User600   | Lastname600 | user600@example.com | 106    | Department 2 | Institution 2 | CITY5 | FR      | 1423158941 |
      | user700  | User700   | Lastname700 | user700@example.com | 107    | Department 2 | Institution 2 | CITY6 | FR      | 1425118941 |
      | user800  | User800   | Lastname700 | user800@example.com | 107    | Department 3 | Institution 3 | CITY6 | ES      | 1425158941 |
    And the following users allocations to tenants exist:
      | user       | tenant  |
      | user100    | Tenant1 |
      | user200    | Tenant1 |
      | user300    | Tenant1 |
      | user400    | Tenant1 |
      | user500    | Tenant1 |
      | user600    | Tenant1 |
      | user700    | Tenant1 |
      | user800    | Tenant1 |
    # Add permission to manager1 to add manual users audience to the report.
    And the following "roles" exist:
      | shortname             | name                  | archetype |
      | manageuseraudiences   | Manage user audiences |           |
    And the following "role assigns" exist:
      | user        | role                  | contextlevel | reference |
      | manager1    | manageuseraudiences   | System       |           |
    And the following "permission overrides" exist:
      | capability                  | permission | role                 | contextlevel | reference |
      | moodle/user:viewalldetails  | Allow      | manageuseraudiences  | System       |           |
      | tool/tenant:browseusers     | Allow      | manageuseraudiences  | System       |           |
    #As manager add some conditions and filters and then check with the user
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Audience" "link" in the "[role=tablist]" "css_element"
    Then I click on "Manually added users" "link"
    And I set the field "Add users manually" to "User100 Lastname100"
    And I press "Save changes"
    And I should see "User100 Lastname100"
    And I click on "Table" "link" in the "[role=tablist]" "css_element"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I set the field "Select a filter" to "Country"
    And I set the field "Select a filter" to "Profile department"
    And I set the field "Select a filter" to "Institution"
    And I log out
    And I log in as "user100"
    And I navigate to "Custom reports" in workplace launcher
    And I follow "Report1"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Australia"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should not see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I log out

  @javascript
  Scenario: Ensure that default filters are applied when creating the report
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I should see "Email address" in the "//*[@id='filterstab']//*[@data-region='active-filters']" "xpath_element"
    And I should not see "First name" in the "//*[@id='filterstab']//*[@data-region='active-filters']" "xpath_element"
    And I should not see "ID number" in the "//*[@id='filterstab']//*[@data-region='active-filters']" "xpath_element"
    And I should not see "Surname" in the "//*[@id='filterstab']//*[@data-region='active-filters']" "xpath_element"
    And I should not see "Phone" in the "//*[@id='filterstab']//*[@data-region='active-filters']" "xpath_element"
    And I log out

  @javascript
  Scenario: Ensure that filters for user custom profile fields are available
    Given the following "tool_reportbuilder > reports" exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    And the following "custom profile fields" exist:
      | datatype | shortname  | name                       | visible |
      | text     | text       | Custom text field          | 2       |
      | text     | texthidden | Custom text field (hidden) | 0       |
      | checkbox | checkbox   | Custom checkbox field      | 2       |
      | datetime | datetime   | Custom datetime field      | 2       |
      | menu     | menu       | Custom menu field          | 2       |
      | textarea | textarea   | Custom textarea field      | 2       |
    When I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    Then the "Select a filter" select box should contain "Custom text field"
    And the "Select a filter" select box should contain "Custom checkbox field"
    And the "Select a filter" select box should contain "Custom datetime field"
    And the "Select a filter" select box should contain "Custom menu field"
    And the "Select a filter" select box should contain "Custom textarea field"
    And the "Select a filter" select box should not contain "Custom text field (hidden)"
    And I log out
