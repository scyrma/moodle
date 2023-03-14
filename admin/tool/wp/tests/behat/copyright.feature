@tool @tool_wp @moodleworkplace @javascript
Feature: Moodle workplace copyright
  As an admin
  I should be able to see workplace copyright

  Scenario: Moodle workplace copyright is displayed on the Notifications page
    When I log in as "admin"
    And I navigate to "General > Notifications" in site administration
    Then I should see "This installation contains Moodle Workplace"
    And I should see "Workplace components are exclusively owned and licensed by Moodle"
    And I log out
