@tool @tool_catalogue @moodleworkplace @javascript
Feature: Course cover in the Learning catalogue plugin
  In order to access a course
  As a student
  I need to see all the course information first

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following "courses" exist:
      | fullname                | shortname | summary                     | enddate      | enablecompletion |
      | Program Course 01       | PC01      | This course is awesome      | ##+1 year##  | 1                |
      | Program Course 02       | PC02      | This course is even better  | ##+1 month## | 1                |
      | Program Course 03       | PC03      | This course has no enddate  | 0            | 1                |
    And the following "course enrolments" exist:
      | user      | course  | role            |
      | user12    | PC01    | editingteacher  |
      | user12    | PC02    | editingteacher  |
      | user11    | PC03    | student         |
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

  @_file_upload
  Scenario: User can see course cover when accessing a program course through catalogue
    # 'displaycoursecovermodals' setting is disabled for behat by default, but we need to enable it for this scenario
    # to check the course cover modal.
    Given the following config values are set as admin:
      | config                   | value | plugin         |
      | displaycoursecovermodals | 2     | tool_catalogue |
    # Upload a file.
    And I am on the "PC01" "Course" page logged in as "admin"
    And I click on "Proceed to course content" "button"
    And I navigate to "Settings" in current page administration
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Course image" filemanager
    And I press "Save and display"
    And I log out
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
    # Course files. Looking at if the filename is the same as the file we added before as course image.
    And I click on "Course files" "tool_catalogue > Catalogue pagesection"
    And "//img[contains(@src,'pluginfile.php') and contains(@src, '/gd-logo.png')]" "xpath_element" should exist in the "Course files" "tool_catalogue > Catalogue pagesection"
    # Proceed to course content.
    And I click on "Proceed to course content" "button"
    And I select "Information" from secondary navigation
    # Accessing course again won't show course cover modal.
    And I am on "Program Course 01" course homepage
    And "[data-region='course-cover-modal']" "css_element" should not exist
    And I select "Information" from secondary navigation

  @_file_upload
  Scenario: User can see course cover in the course information tab
    # Upload a file.
    When I am on the "PC01" "Course" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Course image" filemanager
    And I press "Save and display"
    And I log out
    When I am on the "Program Course 01" course page logged in as "user11"
    # Course information (with all sections expanded by default) should be visible in the "Information" tab.
    And I select "Information" from secondary navigation
    Then I should see "This course is awesome" in the "About this course" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##+1 year##End date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "User 12" in the "Trainers" "tool_catalogue > Catalogue pagesection"
    # Course files. Looking at if the filename is the same as the file we added before as course image.
    And I click on "Course files" "tool_catalogue > Catalogue pagesection"
    And "//img[contains(@src,'pluginfile.php') and contains(@src, '/gd-logo.png')]" "xpath_element" should exist in the "Course files" "tool_catalogue > Catalogue pagesection"
    And "Proceed to course content" "button" should not exist

  @_file_upload
  Scenario: User can see course cover in the enrolments page
    Given the following config values are set as admin:
      | config          | value | plugin         |
      | showcoursedates | 1     | tool_catalogue |
    # Upload a file.
    And I am on the "PC02" "Course" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I upload "lib/tests/fixtures/gd-logo.png" file to "Course image" filemanager
    And I press "Save and display"
    And I log out
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
    # Course files. Looking at if the filename is the same as the file we added before as course image.
    And I click on "Course files" "tool_catalogue > Catalogue pagesection"
    And "//img[contains(@src,'pluginfile.php') and contains(@src, '/gd-logo.png')]" "xpath_element" should exist in the "Course files" "tool_catalogue > Catalogue pagesection"
    And "Proceed to course content" "button" should not exist
    And  the following config values are set as admin:
      | config          | value | plugin         |
      | showcoursedates | 0     | tool_catalogue |
    Then I am on the "Program Course 02" course page logged in as "user11"
    And "Dates" "tool_catalogue > Catalogue pagesection" should not exist

  Scenario: Course cover does not show end date if is not set
    When I am on the "Program Course 03" course page logged in as "user11"
    # Course information (with all sections expanded by default) should be visible in the "Information" tab.
    And I select "Information" from secondary navigation
    Then I should see "This course has no enddate" in the "About this course" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should not see "End date:" in the "Dates" "tool_catalogue > Catalogue pagesection"

  Scenario: User should not see course cover modal after seeing it in the enrolments page
    # 'displaycoursecovermodals' setting is disabled for behat by default, but we need to enable it for this scenario
    # to check the course cover modal.
    Given the following config values are set as admin:
      | config                   | value | plugin         |
      | displaycoursecovermodals | 2     | tool_catalogue |
    And the following "activities" exist:
      | activity | name              | intro                    | course | idnumber | section | completion |
      | assign   | Activity sample 1 | Assignment description 1 | PC01   | sample1  | 1       | 1          |
    # Set completion criteria for courses.
    When I log in as "admin"
    And I am on "Program Course 01" course homepage
    And I click on "Proceed to course content" "button"
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | Activity sample 1 | 1 |
    And I press "Save changes"
    Then I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And I click on "Program Course 02" "tool_catalogue > Catalogue item"
    # I see the course cover for Course 2 in the enrolments page.
    And I should see "This course is even better" in the "About this course" "tool_catalogue > Catalogue pagesection"
    And I am on "Program Course 01" course homepage
    And I click on "Proceed to course content" "button"
    And I toggle the manual completion state of "Activity sample 1"
    Then I am on the "My courses" page
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Program Course 02" "tool_catalogue > Catalogue item"
    # I should not see the course cover modal for Course 2.
    And "[data-region='course-cover-modal']" "css_element" should not exist
