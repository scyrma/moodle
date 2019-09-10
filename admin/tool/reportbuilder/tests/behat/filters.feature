@tool @tool_reportbuilder @moodleworkplace
Feature: Manage a filter
  In order to manage a report
  As an user with permissions
  I need to be able to add filters, remove filters, view filters in reports, reset filters, reset a filter

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "users" exist:
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
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I click on "#addfilterselect" "css_element"
    And I click on "Surname" "text" in the "#addfilterselect" "css_element"
    And I click on "First name" "text" in the "#addfilterselect" "css_element"
    And I click on "ID number" "text" in the "#addfilterselect" "css_element"
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
    And I should see "Reset all"
    And I should see "Reset 'Surname' field"
    And I log out

  @javascript
  Scenario: Change the order of a filter
    When I log in as "manager1"
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Delete column" "link" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I click on "Delete column" "link" in the "//table[contains(@class,'report-table')]//th[1]" "xpath_element"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I click on "#addfilterselect" "css_element"
    And I click on "Surname" "text" in the "#addfilterselect" "css_element"
    And I click on "First name" "text" in the "#addfilterselect" "css_element"
    And I click on "ID number" "text" in the "#addfilterselect" "css_element"
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
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    When I log in as "manager1"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I click on "#addfilterselect" "css_element"
    And I click on "Surname" "text" in the "#addfilterselect" "css_element"
    And I click on "First name" "text" in the "#addfilterselect" "css_element"
    And I click on "Edit filter name" "link" in the "//div[contains(@id,'filterstab')]//li[contains(.,'Surname')]" "xpath_element"
    And I set the field "New value for 'Surname'" to "<span lang=\"en\" class=\"multilang\">Test&\"2</span><span lang=\"es\" class=\"multilang\">Prueba&\"2</span>"
    And I press key "13" in the field "New value for 'Surname'"
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
    And I click on "After \" First name \"" "link" in the "Move filter 'Test&\"1'" "dialogue"
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
    Given the following custom reports exist:
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
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I change window size to "large"
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I click on "#addfilterselect" "css_element"
    And I click on "Country" "text" in the "#addfilterselect" "css_element"
    And I click on "Profile department" "text" in the "#addfilterselect" "css_element"
    And I click on "Institution" "text" in the "#addfilterselect" "css_element"
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
    And I follow "Reset all"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    And I set the field "Country field limiter" to "is equal to"
    And I set the field "Country value" to "Australia"
    And I set the field "Profile department value" to "Dep1"
    And I set the field "Institution value" to "Inst1"
    And I should see "3" in the ".filters-count" "css_element"
    And I click on "Reset 'Country' field" "button"
    And I should see "2" in the ".filters-count" "css_element"
    And I log out

  @javascript
  Scenario: Use filters in the dashboard
    Given the following custom reports exist:
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
      | user900  | User900   | Lastname700 | user900@example.com | 107    | Department 4 | Institution 3 | CITY6 | AU      | 1425158941 |
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
    And the following departments exist in organisation structure:
      | tenant     | name             | parent         |
      | Tenant1    | Framework_t1     |                |
      | Tenant1    | Department_t1_d1  | Framework_t1   |
      | Tenant1    | Department_t1_d2  | Framework_t1   |
    And the following positions exist in organisation structure:
      | tenant     | name              | parent           | globalmanager | globalpermissions | departmentmanager | departmentpermissions |
      | Tenant1    | Framework_t1      |                  |      0        |       0           |       0           |        0              |
      | Tenant1    | Position_t1_f1    | Framework_t1     |      1        |       0           |       2          |        2              |
      | Tenant1    | Position_t1_f2    | Framework_t1     |      0        |       0           |        0         |        0              |
    And the following job assignments exist in organisation structure:
      | user        | department      | position       |
      | user100     | Department_t1_d1 | Position_t1_f1 |
      | user100     | Department_t1_d2 | Position_t1_f1 |
      | user200     | Department_t1_d1 | Position_t1_f1 |
      | user300     | Department_t1_d1 | Position_t1_f1 |
      | user400     | Department_t1_d1 | Position_t1_f2 |
      | user500     | Department_t1_d1 | Position_t1_f2 |
      | user600     | Department_t1_d2 | Position_t1_f2 |
      | user700     | Department_t1_d2 | Position_t1_f2 |
    And I log in as "user100"
    # Check filters in the dashboard
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Position" to "Position_t1_f2"
    And I should see "1" in the ".filters-count" "css_element"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I click on "Reset 'Position' field" "button"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I set the field "Position" to "Position_t1_f2"
    And I should see "1" in the ".filters-count" "css_element"
    And I set the field "Department" to "Department_t1_d2"
    And I should see "2" in the ".filters-count" "css_element"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should see "User700 Lastname700" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "User400 Lastname400" in the "report-table" "table"
    And I should not see "User500 Lastname500" in the "report-table" "table"
    And I follow "Reset all"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should see "User700 Lastname700" in the "report-table" "table"
    And I set the field "Full name value" to "Lastname400"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should not see "User500 Lastname500" in the "report-table" "table"
    And I should not see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    And I navigate to "Custom reports" in workplace launcher
    And I follow "Report1"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should see "User700 Lastname700" in the "report-table" "table"
    And I log out
    #As manager add some conditions and filters and then check with the user
    And I log in as "manager1"
    And I navigate to "Report builder" in workplace launcher
    And I follow "Report1"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    And I click on "#addfilterselect" "css_element"
    And I click on "Country" "text" in the "#addfilterselect" "css_element"
    And I click on "Profile department" "text" in the "#addfilterselect" "css_element"
    And I click on "Institution" "text" in the "#addfilterselect" "css_element"
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
    Given the following custom reports exist:
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
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    When I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Text input"
    And I set the following fields to these values:
      | Short name                    | custom_textinput_field |
      | Name                          | custom_textinput_field |
      | Display on signup page?       | Yes                    |
      | Who is this field visible to? | Visible to everyone    |
    And I click on "Save changes" "button"
    And I set the field "datatype" to "Text input"
    And I set the following fields to these values:
      | Short name                    | custom_textinputhidden_field |
      | Name                          | custom_textinputhidden_field |
      | Display on signup page?       | Yes                          |
      | Who is this field visible to? | Not visible                  |
    And I click on "Save changes" "button"
    And I set the field "datatype" to "Checkbox"
    And I set the following fields to these values:
      | Short name                    | custom_checkbox_field |
      | Name                          | custom_checkbox_field |
      | Display on signup page?       | Yes                   |
      | Who is this field visible to? | Visible to everyone   |
    And I click on "Save changes" "button"
    And I set the field "datatype" to "Date/Time"
    And I set the following fields to these values:
      | Short name                    | custom_datetime_field |
      | Name                          | custom_datetime_field |
      | Display on signup page?       | Yes                   |
      | Who is this field visible to? | Visible to everyone   |
    And I click on "Save changes" "button"
    And I set the field "datatype" to "Drop-down menu"
    And I set the following fields to these values:
      | Short name                    | custom_dropdown_field |
      | Name                          | custom_dropdown_field |
      | Display on signup page?       | Yes                   |
      | Who is this field visible to? | Visible to everyone   |
    And I set the field "Menu options (one per line)" to multiline:
    """
    a
    b
    """
    And I click on "Save changes" "button"
    And I set the field "datatype" to "Text area"
    And I set the following fields to these values:
      | Short name                    | custom_textarea_field |
      | Name                          | custom_textarea_field |
      | Display on signup page?       | Yes                   |
      | Who is this field visible to? | Visible to everyone   |
    And I click on "Save changes" "button"
    And I log out
    When I log in as "manager1"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I follow "Report1"
    And I click on "Add field 'Other fields: custom_textinput_field' to the report" "link"
    And I click on "Add field 'Other fields: custom_checkbox_field' to the report" "link"
    And I click on "Add field 'Other fields: custom_datetime_field' to the report" "link"
    And I click on "Add field 'Other fields: custom_dropdown_field' to the report" "link"
    And I click on "Add field 'Other fields: custom_textarea_field' to the report" "link"
    And "Add field 'Other fields: custom_textinputhidden_field' to the report" "link" should not exist
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Filters"
    Then "//select[@id='addfilterselect']//option[starts-with(@value,'user:profilefield_custom_textinput')]" "xpath_element" should exist
    Then "//select[@id='addfilterselect']//option[starts-with(@value,'user:profilefield_custom_checkbox')]" "xpath_element" should exist
    Then "//select[@id='addfilterselect']//option[starts-with(@value,'user:profilefield_custom_datetime')]" "xpath_element" should exist
    Then "//select[@id='addfilterselect']//option[starts-with(@value,'user:profilefield_custom_dropdown')]" "xpath_element" should exist
    Then "//select[@id='addfilterselect']//option[starts-with(@value,'user:profilefield_custom_textarea')]" "xpath_element" should exist
    Then "//select[@id='addfilterselect']//option[starts-with(@value,'user:profilefield_custom_textinputhidden')]" "xpath_element" should not exist
    And I log out