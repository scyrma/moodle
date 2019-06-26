@theme @theme_workplace @moodleworkplace @javascript
Feature: Theme workplace overview test
  To navigate in workplace theme I need to make sure standard steps work

  Scenario: Testing basic steps in workplace theme
    And I log in as "admin"
    And I set the following administration settings values:
      | contactdataprotectionofficer | 1 |
    And I navigate to "Appearance > Themes > Theme settings" in site administration
    And I log out

  Scenario: Testing workplace launcher
    And I log in as "admin"
    And I navigate to "Courses" in workplace launcher
    And I should see "Manage courses and categories"
    And I log out
