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
    And I should see "You have no courses in progress" in the "In progress courses" "block"

  Scenario: Hide course cards in the block as a logged in user
    When I log in as "user1"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "In progress courses" block
    And I configure the "In progress courses" block
    And I set the following fields to these values:
      | Region | content |
      | Weight | -10     |
    And I press "Save changes"
    And I should see "(6)" in the ".block_myinprogress .card-title" "css_element"
    And I should not see "Show hidden" in the "In progress courses" "block"
    # Check hide card.
    And I hide "Course1" course card
    And "Course1" "block_myinprogress > Course card" should not be visible
    And I should see "Show hidden from my view (1)" in the "In progress courses" "block"
    And I hide "Course2" course card
    And "Course1" "block_myinprogress > Course card" should not be visible
    And I should see "Show hidden from my view (2)" in the "In progress courses" "block"
    # Check carousel pagination still works.
    And I click on "Next page" "button" in the "In progress courses" "block"
    And I should see "Course7" in the "In progress courses" "block"
    And I click on "Previous page" "button" in the "In progress courses" "block"
    And I hide "Course3" course card
    And "Next page" "button" should not be visible
    # Check empty state.
    And I hide "Course4" course card
    And I hide "Course5" course card
    And I hide "Course7" course card
    And I should see "You have no courses in progress" in the "In progress courses" "block"
    # Show hidden cards.
    And I click on "Show hidden from my view (6)" "checkbox"
    And "Course1" "block_myinprogress > Course card" should be visible
    And I should see "Hidden" in the "Course1" "block_myinprogress > Course card"
    And I show "Course1" course card
    And I should not see "Hidden" in the "Course1" "block_myinprogress > Course card"
    # Check carousel pagination still works.
    And I click on "Next page" "button" in the "In progress courses" "block"
    And I should see "Course4" in the "In progress courses" "block"
    And I should see "Hidden" in the "Course4" "block_myinprogress > Course card"
    And I click on "Previous page" "button" in the "In progress courses" "block"
    # Check 'Show hidden from my view' checkbox is still checked after page reload
    And I reload the page
    And I should see "(6)" in the ".block_myinprogress .card-title" "css_element"
    And I should see "Show hidden from my view (5)" in the "In progress courses" "block"
    And the "checked" attribute of "input[data-action='show-hidden-cards']" "css_element" should be set
    And I should see "Hidden" in the "Course2" "block_myinprogress > Course card"
    # Hide hidden cards.
    And I click on "Show hidden from my view (5)" "checkbox"
    And "Course1" "block_myinprogress > Course card" should be visible
    And "Course2" "block_myinprogress > Course card" should not be visible
    And "Next page" "button" should not be visible
    # Check block title still shows "(6)" course count.
    And I should see "(6)" in the ".block_myinprogress .card-title" "css_element"
    # Check 'Show hidden from my view' checkbox is still unchecked after page reload
    And I reload the page
    And the "checked" attribute of "input[data-action='show-hidden-cards']" "css_element" should not be set
    And "Course2" "block_myinprogress > Course card" should not be visible
    And "Course3" "block_myinprogress > Course card" should not be visible

  Scenario: Show hidden courses only to users with permission to view them
    When the following "courses" exist:
      | fullname      | shortname     | enablecompletion | visible  |
      | Hidden course | hiddencourse  | 1                | 0        |
    And the following "course enrolments" exist:
      | user   | course       | role    |
      | admin  | hiddencourse | student |
      | user2  | hiddencourse | student |
      | user2  | course1      | student |
    And the following "last access times" exist:
      | user     | course       | lastaccess      |
      | admin    | hiddencourse | ##yesterday##   |
      | user2    | hiddencourse | ##yesterday##   |
      | user2    | course1      | ##yesterday##   |
    And I log in as "admin"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "In progress courses" block
    Then I should see "In progress courses (1)" in the "In progress courses" "block"
    And I should see "Hidden from learners" in the "Hidden course" "block_myinprogress > Course card"
    And I log out
    And I log in as "user2"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "In progress courses" block
    Then I should see "In progress courses (1)" in the "In progress courses" "block"
    And I should not see "Hidden course" in the "In progress courses" "block"
    And I should see "Course1" in the "In progress courses" "block"

  Scenario: User preferences use their own permission checks
    When the following "permission overrides" exist:
      | capability                   | permission | role  | contextlevel | reference |
      | moodle/user:editownprofile   | Prohibit   | user  | System       |           |
    And I log in as "user1"
    And I follow "Dashboard"
    And I switch editing mode on
    And I add the "In progress courses" block
    And I configure the "In progress courses" block
    And I set the following fields to these values:
      | Region | content |
      | Weight | -10     |
    And I press "Save changes"
    And I should not see "Show hidden" in the "In progress courses" "block"
    # Check hide card.
    And I hide "Course1" course card
    Then "Course1" "block_myinprogress > Course card" should not be visible
     # Show hidden cards.
    And I click on "Show hidden from my view (1)" "checkbox"
    And "Course1" "block_myinprogress > Course card" should be visible
    And I should see "Hidden" in the "Course1" "block_myinprogress > Course card"
    And I show "Course1" course card
    And I should not see "Hidden" in the "Course1" "block_myinprogress > Course card"
