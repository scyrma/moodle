@block @block_myinprogress @moodleworkplace @javascript
Feature: The 'In progress courses' block allows users to view their current courses
  In order to view information about my courses in progress
  As a user
  I can add the 'In progress courses' block to my dashboard

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
      | user2    | User      | 1        | user1@example.com |
    And the following "courses" exist:
      | fullname              | shortname | enablecompletion |
      | Course1               | course1   | 1                |
      | Course2               | course2   | 1                |
      | Course3               | course3   | 1                |
      | Course4               | course4   | 1                |
      | Course5               | course5   | 1                |
      | Course6               | course6   | 0                |
      | Course7               | course7   | 1                |
    And the following "activities" exist:
      | activity | name       | intro     | course     | idnumber | completion | completionview |
      | page     | Page1      | PageDesc1 | course1    | PAGE1    | 1          | 1              |
      | page     | Page2      | PageDesc2 | course1    | PAGE2    | 1          | 1              |
      | page     | Page3      | PageDesc3 | course7    | PAGE3    | 1          | 1              |
    And the following "course enrolments" exist:
      | user   | course  | role    |
      | user1  | course1 | student |
      | user1  | course2 | student |
      | user1  | course3 | student |
      | user1  | course4 | student |
      | user1  | course5 | student |
      | user1  | course6 | student |
      | user1  | course7 | student |
    And the following "last access times" exist:
      | user     | course  | lastaccess      |
      | user1    | course1 | ##yesterday##   |
      | user1    | course2 | ##-2 days##   |
      | user1    | course3 | ##-3 days##   |
      | user1    | course4 | ##-4 days##   |
      | user1    | course5 | ##-5 days##   |
      | user1    | course6 | ##-6 days##   |
      | user1    | course7 | ##-7 days##   |

  Scenario: View the block by a logged in user
    # Set completion criteria for courses as admin.
    When I log in as "admin"
    And I am on "Course1" course homepage
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Page1 | 1 |
      | Page2 | 1 |
    And I press "Save changes"
    And I am on "Course7" course homepage
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Page3 | 1 |
    And I press "Save changes"

    # Complete some course activities first.
    And I log in as "user1"
    And I am on "Course1" course homepage
    And I toggle the manual completion state of "Page1"
    And I am on "Course7" course homepage
    And I toggle the manual completion state of "Page3"

    # Add the block and test contents.
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "In progress courses" block
    And I configure the "In progress courses" block
    And I set the following fields to these values:
      | Region | content |
      | Weight | -10     |
    And I press "Save changes"
    Then I should see "In progress courses (5)" in the "In progress courses" "block"
    And I should see "Course1" in the "In progress courses" "block"
    And I should see "50% completed" in the "Course1" "block_myinprogress > Course card"
    And I should see "Course2" in the "In progress courses" "block"
    And I should see "Course3" in the "In progress courses" "block"
    And I should not see "Course4" in the "In progress courses" "block"
    And I should not see "Course5" in the "In progress courses" "block"
    And I should not see "Course6" in the "In progress courses" "block"
    And the ".block_myinprogress button[data-action='previous']" "css_element" should be disabled
    And I click on "Next page" "button" in the "In progress courses" "block"
    And I should not see "Course1" in the "In progress courses" "block"
    And I should not see "Course2" in the "In progress courses" "block"
    And I should see "Course3" in the "In progress courses" "block"
    And I should see "Course4" in the "In progress courses" "block"
    And I should see "Course5" in the "In progress courses" "block"
    # Completion is disabled for Course6, it should not be displayed.
    And I should not see "Course6" in the "In progress courses" "block"
    # Course7 is completed by the user, it should not be displayed.
    And I should not see "Course7" in the "In progress courses" "block"
    And the ".block_myinprogress button[data-action='next']" "css_element" should be disabled
    And I click on "Previous page" "button" in the "In progress courses" "block"
    And I should not see "Course4" in the "In progress courses" "block"
    And I should not see "Course5" in the "In progress courses" "block"
    And I should see "Course1" in the "In progress courses" "block"
    And I should see "Course2" in the "In progress courses" "block"
    And I should see "Course3" in the "In progress courses" "block"
    And the ".block_myinprogress button[data-action='previous']" "css_element" should be disabled

  Scenario: View the block empty by a logged in user
    When I log in as "user2"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "In progress courses" block
    And I configure the "In progress courses" block
    And I set the following fields to these values:
      | Region | content |
      | Weight | -10     |
    And I press "Save changes"
    Then I should see "In progress courses" in the "In progress courses" "block"
    And I should not see "(0)" in the "In progress courses" "block"
    And I should see "Nothing to display" in the "In progress courses" "block"
