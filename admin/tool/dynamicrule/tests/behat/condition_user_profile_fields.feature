@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing conditions for user profile fields
  In order to manage rules
  As a manager
  I need to be able to add and edit user profile field conditions

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | manager2 | Manager   | 2        | manager2@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
      | manager2 | Tenant2 |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_dynamicrule_manager | System       |           |
      | manager2 | tool_dynamicrule_manager | System       |           |

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
    # Set field value for user.
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I click on ".icon[title=Edit]" "css_element" in the "manager1@example.com" "table_row"
    And I expand all fieldsets
    And I set the field "Example field" to "somevalue"
    And I set the field "First name" to "moodler"
    And I click on "Update profile" "button"
    And I log out
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
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

  Scenario: Add a rule with user profile field datetime condition
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Date/Time"
    And I set the following fields to these values:
      | Short name                    | datetime_field   |
      | Name                          | Date Time field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    # Set field value for user.
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I click on ".icon[title=Edit]" "css_element" in the "manager1@example.com" "table_row"
    And I expand all fieldsets
    And I click on "profile_field_datetime_field[enabled]" "checkbox"
    And I set the field "profile_field_datetime_field[day]" to "1"
    And I set the field "profile_field_datetime_field[month]" to "January"
    And I set the field "profile_field_datetime_field[year]" to "2020"
    And I click on "Update profile" "button"
    And I log out
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
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
    And I should see "0 total matches"
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
    And I should see "0 total matches"
    # Test for Current month
    And I follow "Edit condition"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op | 7 |
      | datetime_field_op2 | 3 |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is 'Current Month'"
    And I should see "0 total matches"
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
    # Set field value for user.
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I click on ".icon[title=Edit]" "css_element" in the "manager1@example.com" "table_row"
    And I expand all fieldsets
    And I set the field "Menu field" to "b"
    And I click on "Update profile" "button"
    And I log out
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | Menu field |
      | menu_field_op | b |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Menu field' is 'b'"
    And I should see "1 total matches"

  Scenario: Add a rule with user profile field checkbox condition
    Given I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Checkbox"
    And I set the following fields to these values:
      | Short name                    | checkbox_field   |
      | Name                          | Checkbox field   |
      | Display on signup page?       | Yes             |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    # Set field value for user.
    And I navigate to "Users > Accounts > Browse list of users" in site administration
    And I click on ".icon[title=Edit]" "css_element" in the "manager1@example.com" "table_row"
    And I expand all fieldsets
    And I click on "profile_field_checkbox_field" "checkbox"
    And I click on "Update profile" "button"
    And I log out
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save" in the modal form dialogue
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | Checkbox field |
    And I click on "checkbox_field_op" "checkbox"
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Checkbox field' is 'Yes'"
    And I should see "1 total matches"