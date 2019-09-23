@tool @tool_reportbuilder @javascript @moodleworkplace
Feature: Manage conditions in report builder
  In order to manage a report
  As an manager
  I need to be able to add, remove, change header of conditions

  Background:
    Given "1" tenants exist with "3" users and "0" courses in each
    And the following "role assigns" exist:
      | user     | role                       | contextlevel | reference |
      | user11   | tool_reportbuilder_manager | System       |           |

  @javascript
  Scenario: Ensure the formatting is applied correctly to conditions names
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    And the "multilang" filter is "on"
    And the "multilang" filter applies to "content and headings"
    When I log in as "user11"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "First name"
    And I set the field "Select a condition" to "Surname"
    And I click on "Edit condition name" "link" in the "//div[contains(@id,'conditionstab')]//div[contains(@class,'card-header') and contains(.,'Surname')]" "xpath_element"
    And I set the field "New value for 'Surname'" to "<span lang=\"en\" class=\"multilang\">Test&\"3</span><span lang=\"es\" class=\"multilang\">Prueba&\"3</span>"
    And I press key "13" in the field "New value for 'Surname'"
    And I should see "Test&\"3" in the "#conditionstab" "css_element"
    And I should not see "Prueba"
    # Reload, recheck and test deleting
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Test&\"3" in the "#conditionstab" "css_element"
    And I should not see "Prueba"
    And I click on "Delete condition" "link" in the "//div[contains(@id,'conditionstab')]//div[contains(@class,'card-header') and contains(.,'Test&\"3')]" "xpath_element"
    And I should see "Are you sure you want to delete condition 'Test&\"3'?" in the "Confirm" "dialogue"
    And I should not see "Prueba"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "The condition 'Test&\"3' has been deleted."
    And I should not see "Prueba"
    And I log out

  @javascript
  Scenario: Delete a condition
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    When I log in as "user11"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "First name"
    And I set the field "First name value" to "This test should be deleted"
    And I set the field "First name field limiter" to "is equal to"
    And I click on "Delete condition 'First name'" "link"
    And I should see "Are you sure you want to delete condition 'First name'?" in the "Confirm" "dialogue"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    # Check can be added again and the condition is new
    And I set the field "Select a condition" to "First name"
    And the field "First name value" matches value ""
    And the field "First name field limiter" matches value "contains"
    And I log out

  @javascript
  Scenario: Apply a condition
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
    When I log in as "user11"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Country"
    And I set the field "Country field limiter" to "is equal to"
    And I wait "1" seconds
    And I set the field "Country value" to "Spain"
    And I wait "1" seconds
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "Australia" in the "report-table" "table"
    #Check reload the tab
    #Check in preview
    And I click on "Switch to preview view" "button"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "Australia" in the "report-table" "table"
    And I click on "Switch to edit view" "button"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Country field limiter" to "isn't equal to"
    And I should not see "User100 Lastname100" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I log out

  @javascript
  Scenario: Reset all conditions
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
    When I log in as "user11"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Country"
    And I set the field "Country field limiter" to "is equal to"
    And I wait "1" seconds
    And I set the field "Country value" to "Spain"
    And I wait "1" seconds
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "Australia" in the "report-table" "table"
    And I click on "Reset all conditions" "link"
    And I should see "Are you sure you want to reset all conditions?" in the "Confirm" "dialogue"
    And I click on "Reset all" "button" in the "Confirm" "dialogue"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should see "User300 Lastname300" in the "report-table" "table"
    And I should see "User400 Lastname400" in the "report-table" "table"
    And I should see "User500 Lastname500" in the "report-table" "table"
    And I should see "User600 Lastname600" in the "report-table" "table"
    And I should not see "User700 Lastname700" in the "report-table" "table"
    And I log out

  @javascript
  Scenario: Reset a condition
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
    When I log in as "user11"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Country"
    And I set the field "Country field limiter" to "is equal to"
    And I wait "1" seconds
    And I set the field "Country value" to "Spain"
    And I wait "1" seconds
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "Australia" in the "report-table" "table"
    And I set the field "Select a condition" to "First name"
    And I set the field "First name field limiter" to "is equal to"
    And I wait "1" seconds
    And I set the field "First name value" to "User100"
    And I wait "1" seconds
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should not see "User200 Lastname200" in the "report-table" "table"
    And I click on "Reset 'First name' field" "link"
    And I should see "Are you sure you want to reset 'First name' condition?" in the "Confirm" "dialogue"
    And I click on "Reset condition" "button" in the "Confirm" "dialogue"
    And I should see "User100 Lastname100" in the "report-table" "table"
    And I should see "User200 Lastname200" in the "report-table" "table"
    And I should not see "User300 Lastname300" in the "report-table" "table"
    And I should not see "Australia" in the "report-table" "table"
    And I log out

  Scenario: Ensure that default conditions are applied when creating the report
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\test\mock_report3 |
    And the following "users" exist:
      | username | firstname | lastname    | email               | phone1 | department   | institution   | city  | country | lastaccess |
      | user100  | User100   | Lastname100 | user100@example.com | 101    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
      | user200  | User200   | Lastname200 | myemail@test.com    | 102    | Department 1 | Institution 1 | CITY1 | ES      | 1315958041 |
    When I log in as "user11"
    And I change window size to "large"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I should see "Email address" in the "#conditionstab .tool_reportbuilder_report_active_conditions" "css_element"
    And I should not see "First name" in the "#conditionstab .tool_reportbuilder_report_active_conditions" "css_element"
    And I should not see "ID number" in the "#conditionstab .tool_reportbuilder_report_active_conditions" "css_element"
    And I should not see "Surname" in the "#conditionstab .tool_reportbuilder_report_active_conditions" "css_element"
    And I should not see "Phone" in the "#conditionstab .tool_reportbuilder_report_active_conditions" "css_element"
    And I should see "is equal to" in the "#conditionstab .tool_reportbuilder_report_active_conditions" "css_element"
    And the field with xpath "//input[@id[starts-with(.,'id_useremail_')]]" matches value "myemail@test.com"
    And I should see "User200" in the "report-table" "table"
    And I should not see "User100" in the "report-table" "table"
    And I log out

  @javascript
  Scenario: Ensure that conditions for user custom profile fields are available
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
      | Who is this field visible to? | Visible to everyone            |
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
    When I log in as "user11"
    And I navigate to "Report builder" in workplace launcher
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I follow "Conditions"
    Then the "Select a condition" select box should contain "custom_textinput_field"
    And the "Select a condition" select box should contain "custom_checkbox_field"
    And the "Select a condition" select box should contain "custom_datetime_field"
    And the "Select a condition" select box should contain "custom_dropdown_field"
    And the "Select a condition" select box should contain "custom_textarea_field"
    And the "Select a condition" select box should not contain "custom_textinputhidden_field"
    And I log out

  @javascript
  Scenario: Ensure that last access condition works when added without refresh page
    Given the following custom reports exist:
      | name    | tenant  | source |
      | Report1 | Tenant1 | tool_reportbuilder\tool_reportbuilder\datasources\report_users_list |
    When I log in as "user11"
    Then I navigate to "Reports > Report builder > Manage custom reports" in site administration
    And I click on "Edit content" "link" in the "Report1" "table_row"
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Last access"
    Then I should see "Any value"
    And "[name=\"user:lastaccess_op2\"]" "css_element" should not be visible
    And "[name=\"user:lastaccess\"]" "css_element" should not be visible
    And I set the field "user:lastaccess_op" to "Previous"
    And "[name=\"user:lastaccess_op2\"]" "css_element" should be visible
    And I set the field "user:lastaccess_op" to "Last ... days"
    And "[name=\"user:lastaccess\"]" "css_element" should be visible
    And "[name=\"user:lastaccess_op2\"]" "css_element" should not be visible

  @javascript
  Scenario: Ensure that course selector condition works when added without refresh page
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | CAT1     |
      | Course 2 | C2        | CAT1     |
    And the following "course enrolments" exist:
      | user   | course | role           |
      | user12 | C1     | student        |
    When I log in as "user11"
    And I navigate to "Report builder" in workplace launcher
    And I follow "New report"
    And I set the following fields to these values:
      | Report name | Report1 |
      | Report source | Course completion from datastore |
    And I press "Save" in the modal form dialogue
    And I click on "Show/hide filters sidebar" "button"
    And I set the field "Select a condition" to "Select courses"
    Then I should see "No selection"
    And I open the autocomplete suggestions list
    And I should see "Course 1" in the ".form-autocomplete-suggestions" "css_element"
    And I should see "Course 2" in the ".form-autocomplete-suggestions" "css_element"
    And I click on "Course 1" "text" in the ".form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select courses"
