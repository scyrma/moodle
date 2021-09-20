@tool @tool_dynamicrule @javascript @moodleworkplace
Feature: Creating and editing conditions for user profile fields
  In order to manage rules
  As a tenant admin
  I need to be able to add and edit user profile field conditions

  Background:
    Given "2" tenants exist with "0" users and "0" courses in each

  Scenario: Add a rule with user profile field text condition
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible |
      | text     | example_field     | Example field     |  2       |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_example_field |
      | Tenant1 | usertest | moodler   | Test     | usertest@example.com | somevalue                   |
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

  Scenario Outline: Add a rule with user profile field datetime condition (empty, past future)
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible | param2        |
      | datetime | datetime_field    | Date Time field   |  2       | ##+1 year##%Y## |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_datetime_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | <date>                       |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | Date Time field |
      | datetime_field_op    | <operator> |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is '<operatordesc>'"
    And I should see "<matches> total matches"
    Examples:
      | operator | operatordesc  | date           | matches |
      | 0        | Any value     | ##today##%s##  | 1       |
      | 1        | Is not empty  | ##today##%s##  | 1       |
      | 2        | Is empty      | ##today##%s##  | 0       |
      | 3        | In the past   | ##-1 day##%s## | 1       |
      | 3        | In the past   | ##+1 day##%s## | 0       |
      | 4        | In the future | ##-1 day##%s## | 0       |
      | 4        | In the future | ##+1 day##%s## | 1       |

  Scenario Outline: Add a rule with user profile field datetime condition (last, next)
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible | param2        |
      | datetime | datetime_field    | Date Time field   |  2       | ##+1 year##%Y## |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_datetime_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | <date>                       |
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
      | datetime_field_op    | <operator> |
      | datetime_field_value | <fieldvalue> |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is '<operatordesc>'"
    And I should see "<matches> total matches"
    Examples:
      | operator | operatordesc  | date           | matches | fieldvalue |
      | 5        | Last ... days | ##-1 day##%s## | 1       | 2          |
      | 5        | Last ... days | ##+1 day##%s## | 0       | 2          |
      | 6        | Next ... days | ##-1 day##%s## | 0       | 5          |
      | 6        | Next ... days | ##+1 day##%s## | 1       | 5          |

  Scenario Outline: Add a rule with user profile field datetime condition (current, previous, upcoming)
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible | param2        |
      | datetime | datetime_field    | Date Time field   |  2       | ##+1 year##%Y## |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_datetime_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | <date>                       |
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
      | datetime_field_op    | <operator> |
      | datetime_field_op2   | <operator2> |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Date Time field' is '<operatordesc>'"
    And I should see "<matches> total matches"
    Examples:
      | operator | operatordesc  | date               | matches | operator2  |
      | 7        | Current day   | ##today##%s##      | 1       | 1          |
      | 7        | Current day   | ##+1 day##%s##     | 0       | 1          |
      | 7        | Current Month | ##-2 month##%s##   | 0       | 3          |
      | 7        | Current Month | ##today##%s##      | 1       | 3          |
      | 8        | Previous Week | ##-1 week##%s##    | 1       | 2          |
      | 8        | Previous Week | ##+1 week##%s##    | 0       | 2          |
      | 9        | Upcoming year | ##-1 year##%s##    | 0       | 5          |
      | 9        | Upcoming year | ##+1 year##%s##    | 1       | 5          |

  Scenario: Add a rule with user profile field menu condition
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible |
      | menu     | menu_field        | Menu field        |  2       |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_menu_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | No                        |
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    And I follow "New rule"
    And I set the following fields to these values:
      | Name | Rule1 |
    And I press "Save"
    And I follow "User profile field"
    And I set the following fields to these values:
      | Field | Menu field |
      | menu_field_op | No |
    And I press "Save changes"
    Then I should see "Users whose value for profile field 'Menu field' is 'No'"
    And I should see "1 total matches"
    And I follow "View matching users"
    And I should see "usertest@example.com" in the "View matching users" "dialogue"
    And I click on "Cancel" "button" in the "View matching users" "dialogue"

  Scenario: Add a rule with user profile field checkbox condition
    # Visibility values: 0 = PROFILE_VISIBLE_NONE; 2 = PROFILE_VISIBLE_ALL
    Given the following "custom profile fields" exist:
      | datatype | shortname         | name              |  visible |
      | checkbox | checkbox_field    | Checkbox field    |  2       |
    And the following "tool_tenant > users" exist:
      | tenant  | username | firstname | lastname | email                | profile_field_checkbox_field |
      | Tenant1 | usertest | User      | Test     | usertest@example.com | 1                            |
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
