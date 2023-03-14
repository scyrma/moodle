@tool @tool_tenant @moodleworkplace @javascript
Feature: Allowing to WP to create accounts with the same email address
  In order to manage user accounts
  As a global admin or tenant admin
  I need to be able to set whether to allow multiple accounts with the same email or not.

  Scenario Outline: Create a user with existing equivalent (case-sensitive) email
    Given the following config values are set as admin:
      | allowaccountssameemail | <allowsameemail> |
    And the following "users" exist:
      | username  | firstname | lastname | email           |
      | s1        | John      | Doe      | s1@example.com  |
    When I log in as "admin"
    And I navigate to "Users > Organisation > User management" in site administration
    And I follow "New user"
    And I set the following fields in the "New user" "dialogue" to these values:
      | Username      | s2         |
      | Email address | <email>    |
    And I set the following fields to these values:
      | First name    | Jane       |
      | Last name       | Doe        |
      | New password  | Moodle.123 |
    And I press "Save"
    Then I should <expect> "This email address is already registered."

    Examples:
      | allowsameemail | email          | expect  |
      | 0              | s1@example.com | see     |
      | 0              | S1@example.com | see     |
      | 0              | S1@EXAMPLE.COM | see     |
      | 1              | s1@example.com | not see |
      | 1              | S1@EXAMPLE.COM | not see |

  Scenario Outline: Update a user with existing equivalent (case-sensitive) email
    Given the following config values are set as admin:
      | allowaccountssameemail | <allowsameemail> |
    And the following "users" exist:
      | username  | firstname | lastname | email           |
      | s1        | John      | Doe      | s1@example.com  |
      | s2        | Jane      | Doe      | s2@example.com  |
    When I log in as "admin"
    And I navigate to "Users > Organisation > User management" in site administration
    And I press "Edit user account" action in the "Jane Doe" report row
    And I set the following fields in the "Edit user 'Jane Doe'" "dialogue" to these values:
      | Email address | <email> |
    And I press "Save"
    Then I should <expect> "This email address is already registered."

    Examples:
      | allowsameemail | email          | expect  |
      | 0              | s1@example.com | see     |
      | 0              | S1@example.com | see     |
      | 0              | S1@EXAMPLE.COM | see     |
      | 1              | s1@example.com | not see |
      | 1              | S1@EXAMPLE.COM | not see |
      | 0              | S2@EXAMPLE.COM | not see |
      | 1              | S2@EXAMPLE.COM | not see |
