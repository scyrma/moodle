@theme @theme_workplace @moodleworkplace @javascript
Feature: Theme workplace overview test
  To navigate in workplace theme I need to make sure standard steps work

  Scenario: Testing basic steps in workplace theme
    And I log in as "admin"
    And I set the following administration settings values:
      | contactdataprotectionofficer | 1 |
    And I navigate to "Appearance > Themes > Theme settings" in site administration
    And I log out

  Scenario: Testing workplace launcher in list format
    And I log in as "admin"
    And I set the following administration settings values:
      | wpmenumodal | 0 |
    And I navigate to "Courses" in workplace launcher
    And I should see "Manage courses and categories"
    And I log out

  Scenario: Testing workplace launcher in modal format
    And I log in as "admin"
    And I set the following administration settings values:
      | wpmenumodal | 1 |
    And I navigate to "Courses" in workplace launcher
    And I should see "Manage courses and categories"
    And I log out

  Scenario: Testing workplace user menu course list
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
      | Course 3 | C3        | 0        |
    And the following "course enrolments" exist:
      | user      | course  | role    |
      | student1  | C1      | student |
      | student1  | C2      | student |
      | student1  | C3      | student |
    When I log in as "student1"
    And I am on "Course 2" course homepage
    And I wait "1" seconds
    And I am on "Course 3" course homepage
    And I press "usermenulink"
    Then "Course 3" "text" should appear before "Course 2" "text" in the ".workplace-usermenu" "css_element"
    And "Course 1" "text" should not exist in the ".workplace-usermenu" "css_element"
