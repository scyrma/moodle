@tool @tool_catalogue @moodleworkplace @javascript
Feature: Course cover in the Learning catalogue plugin
  In order to access a course
  As a student
  I need to see all the course information first

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following "courses" exist:
      | fullname                | shortname | summary                     | enddate      |
      | Program Course 01       | PC01      | This course is awesome      | ##+1 year##  |
      | Program Course 02       | PC02      | This course is even better  | ##+1 month##  |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | user12    | PC01    | editingteacher  |
      | user12    | PC02    | editingteacher  |
    And the following "tool_program > programs" exist:
      | fullname   | tenant  | enddatetype | enddateabsolute |
      | Program 01 | Tenant1 | 1           | ##+1 year##     |
      | Program 02 | Tenant1 | 1           | ##+1 year##     |
    And the following "tool_program > program_courses" exist:
      | program      | course   |
      | Program 01   | PC01     |
      | Program 01   | PC02     |
      | Program 02   | PC01     |
    And the following "tool_program > program_users" exist:
      | program    | user   |
      | Program 01 | user11 |
      | Program 02 | user11 |

  Scenario: User can see course cover when accessing a program course through catalogue
    # 'disablecoursecovermodals' setting is enabled for behat by default, but we need to disable it for this scenario
    # to check the course cover modal.
    Given the following config values are set as admin:
      | config               | value | plugin         |
      | disablecoursemodals  | 0     | tool_catalogue |
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And I click on "Program Course 01" "tool_catalogue > Catalogue item"
    Then I should see "Program Course 01" in the "[data-region='course-cover-modal']" "css_element"
    And I should see "This course is part of some programs" in the "[data-region='course-cover-modal']" "css_element"
    And "See \"Program 01\" details" "link" should exist
    And "See \"Program 02\" details" "link" should exist
    # About this course (only section expanded by default).
    And I should see "This course is awesome" in the "About this course" "tool_catalogue > Catalogue pagesection"
    # Dates.
    And I click on "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##+1 year##End date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    # Trainers.
    And I click on "Trainers" "tool_catalogue > Catalogue pagesection"
    And I should see "User 12" in the "Trainers" "tool_catalogue > Catalogue pagesection"
    # Course files.
    # TODO: WP-3575.
    # Proceed to course content.
    And I click on "Proceed to course content" "button"
    And I select "Information" from secondary navigation
    # Accessing course again won't show course cover modal.
    And I am on "Program Course 01" course homepage
    And "[data-region='course-cover-modal']" "css_element" should not exist
    And I select "Information" from secondary navigation

  Scenario: User can see course cover in the course information tab
    When I am on the "Program Course 01" course page logged in as "user11"
    # Course information (with all sections expanded by default) should be visible in the "Information" tab.
    And I select "Information" from secondary navigation
    Then I should see "This course is awesome" in the "About this course" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##+1 year##End date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "User 12" in the "Trainers" "tool_catalogue > Catalogue pagesection"
    # TODO: WP-3575 Check course files
    And "Proceed to course content" "button" should not exist

  Scenario: User can see course cover in the enrolments page
    When I am on the "Program Course 02" course page logged in as "user11"
    # About this course (only section expanded by default).
    Then I should see "This course is even better" in the "About this course" "tool_catalogue > Catalogue pagesection"
    # Dates.
    And I click on "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##+1 month##End date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    # Trainers.
    And I click on "Trainers" "tool_catalogue > Catalogue pagesection"
    And I should see "User 12" in the "Trainers" "tool_catalogue > Catalogue pagesection"
    # Course files.
    # TODO: WP-3575.
    And "Proceed to course content" "button" should not exist
