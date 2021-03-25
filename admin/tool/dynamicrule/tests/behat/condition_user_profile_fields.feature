@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing conditions for user profile fields
  In order to manage rules
  As a tenant admin
  I need to be able to add and edit user profile field conditions

  Background:
    Given "2" tenants exist with "0" users and "0" courses in each

  Scenario: Add a rule with user profile field text condition
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Text input"
    And I set the following fields to these values:
      | Short name                    | example_field   |
      | Name                          | Example field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_example_field |
      | Tenant1 | usertest | moodler   | Test     | usertest@example.com | somevalue                   |
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    # Test for Is equal to
    And I set the following fields to these values:
      | Field | First name |
      | firstname_op | 2 |
      | firstname_value | moodler |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'First name' is equal to moodler"
    And I should see "1 total matches"
    # Test for Contains
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 0 |
      | example_field_value | some |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' contains some"
    And I should see "1 total matches"
    # Test for doesn't contain
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 1 |
      | example_field_value | some |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' doesn't contain some"
    And I should see "0 total matches"
    # Test for starts with
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 3 |
      | example_field_value | some |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' starts with some"
    And I should see "1 total matches"
    # Test for Ends with
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 4 |
      | example_field_value | some |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' ends with some"
    And I should see "0 total matches"
     # Test for Is empty
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 5 |
    And "[name=example_field_value]" "css_element" should not be visible
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' is empty"
    And I should see "0 total matches"
     # Test for Is not empty
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 6 |
    And "[name=example_field_value]" "css_element" should not be visible
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' Is not empty"
    And I should see "1 total matches"
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Example field |
      | example_field_op | 3 |
      | example_field_value | hello |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Example field' starts with hello"
    And I should see "0 total matches"

  Scenario: Add a rule with user authentication method condition
    Given the following "users" exist:
      | username | auth   | firstname | lastname | email               |
      | userone  | ldap   | User      | One      | userone@example.com |
      | usertwo  | manual | User      | Two      | usertwo@example.com |
    When I log in as "admin"
    And I navigate to "Dynamic rules" in workplace launcher
    And I follow "New rule"
    And I set the field "Name" to "Users based on authentication method"
    And I press "Save"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field                 | Authentication method |
      | Authentication method | LDAP server           |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Authentication method' is 'LDAP server'"
    And I should see "1 total matches"
    And I follow "View matching users"
    And I should see "userone@example.com" in the "View matching users" "dialogue"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"

  Scenario: Add a rule with user profile field datetime condition
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Date/Time"
    And I set the following fields to these values:
      | Short name                    | datetime_field   |
      | Name                          | Date Time field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_datetime_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | ##today##%s##                |
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    # Test for Any value
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 0 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Any value'"
    And I should see "1 total matches"
    And "[name=datetime_field_value]" "css_element" should not be visible
    # Test for Is not empty
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 1 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Is not empty'"
    And I should see "1 total matches"
    And "[name=datetime_field_value]" "css_element" should not be visible
    # Test for Is empty
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 2 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Is empty'"
    And I should see "0 total matches"
    And "[name=datetime_field_value]" "css_element" should not be visible
    # Test for In the past
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 3 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'In the past'"
    And I should see "1 total matches"
    And "[name=datetime_field_value]" "css_element" should not be visible
    # Test for In the future
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 4 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'In the future'"
    And I should see "0 total matches"
    And "[name=datetime_field_value]" "css_element" should not be visible
    # Test for Last .. days
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 5 |
      | datetime_field_value | 2 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Last ... days'"
    And I should see "1 total matches"
    # Test for Next .. days
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 6 |
      | datetime_field_value | 5 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Next ... days'"
    And I should see "0 total matches"
    # Test for Current day
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 7 |
      | datetime_field_op2 | 1 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Current day'"
    And I should see "1 total matches"
    # Test for Current month
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 7 |
      | datetime_field_op2 | 3 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Current Month'"
    And I should see "1 total matches"
    # Test for Previous week
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 8 |
      | datetime_field_op2 | 2 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Previous Week'"
    And I should see "0 total matches"
    # Test for Upcoming year
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 9 |
      | datetime_field_op2 | 5 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Upcoming year'"
    And I should see "0 total matches"

  Scenario: Add a rule with user profile field menu condition
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Drop-down menu"
    And I set the following fields to these values:
      | Short name                    | menu_field   |
      | Name                          | Menu field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to everyone |
    And I change window size to "large"
    And I set the field "Menu options (one per line)" to multiline:
      """
      a
      b
      """
    And I click on "Save changes" "button"
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_menu_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | b                        |
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | Menu field |
      | menu_field_op | b |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Menu field' is 'b'"
    And I should see "1 total matches"
    And I follow "View matching users"
    And I should see "usertest@example.com" in the "View matching users" "dialogue"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"

  Scenario: Add a rule with user profile field checkbox condition
    Given I log in as "admin"
    And I change window size to "large"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Checkbox"
    And I set the following fields to these values:
      | Short name                    | checkbox_field   |
      | Name                          | Checkbox field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_checkbox_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | 1                            |
    And I log out
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | Checkbox field |
    And I click on "checkbox_field_op" "checkbox"
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Checkbox field' is 'Yes'"
    And I should see "1 total matches"
    And I follow "View matching users"
    And I should see "usertest@example.com" in the "View matching users" "dialogue"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"
