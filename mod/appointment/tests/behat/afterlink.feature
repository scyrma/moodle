@mod @mod_appointment @moodleworkplace @javascript
Feature: Display info on course page

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | format |
      | Course 1 | C1        | 0        | wplist |
    And the following "activities" exist:
      | activity    | name             | intro            | course | idnumber     |
      | appointment | Test appointment | Appointment desc | C1     | appointment1 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | First | teacher1@example.com |
      | student1 | Student | First | student1@example.com |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: Afterlink content should display date when date is known
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I press "Add session"
    And I set the following fields in the "Adding appointment" "dialogue" to these values:
      | startdate[0][day]    | ##tomorrow##%d## |
      | startdate[0][month]  | ##tomorrow##%B## |
      | startdate[0][year]   | ##tomorrow##%Y## |
      | starttime[0][hour]   | 01 |
      | starttime[0][minute] | 00 |
      | endtime[0][hour]     | 02 |
      | endtime[0][minute]   | 00 |
      | startdate[1][day]    | ##tomorrow##%d## |
      | startdate[1][month]  | ##tomorrow##%B## |
      | startdate[1][year]   | ##tomorrow##%Y## |
      | starttime[1][hour]   | 03 |
      | starttime[1][minute] | 00 |
      | endtime[1][hour]     | 04 |
      | endtime[1][minute]   | 00 |
    And I press "Save"
    Then I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "1:00"
    And I should see "2:00"
    And I should see "##tomorrow##%A, %d %B %Y##"
    And I should see "3:00"
    And I should see "4:00"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "10 seats available"
    And I follow "Test appointment"
    And I press "Book"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I am on "Course 1" course homepage
    And I should see "##tomorrow##%A, %d %B %Y##" in the ".afterlink" "css_element"
    And I should see "1:00" in the ".afterlink" "css_element"
    And I should see "2:00" in the ".afterlink" "css_element"
    And I follow "Test appointment"
    And I press "Cancel"
    And I click on "Confirm cancellation" "button" in the "Cancel booking" "dialogue"
    And I am on "Course 1" course homepage
    And I should see "10 seats available"

  Scenario: Afterlink content should display status when date is unknown
    When I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test appointment"
    And I follow "Add"
    And I choose "Appointment" in the open action menu
    And I press "Delete session"
    And I click on "Save" "button" in the "Adding appointment" "dialogue"
    Then I should see "Not set"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "10 seats available"
    And I follow "Test appointment"
    And I press "Book"
    And I click on "Book" "button" in the "Details" "dialogue"
    And I am on "Course 1" course homepage
    And I should see "Booked"
    And I follow "Test appointment"
    And I press "Cancel"
    And I click on "Confirm cancellation" "button" in the "Cancel booking" "dialogue"
    And I am on "Course 1" course homepage
    And I should see "10 seats available"
