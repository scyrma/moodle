@tool @tool_catalogue @moodleworkplace @javascript
Feature: My courses page with the Learning catalogue plugin
  In order to have a clear and consistent view on the my courses page
  As a student
  I need to see the programs and courses in the expected placement

  Background:
    Given "2" tenants exist with "3" users and "0" courses in each
    And the following config values are set as admin:
      | config                        | value | plugin         |
      | showcataloguecoursecategory   | 1     | tool_catalogue |
    And the following "categories" exist:
      | name          | category | idnumber  |
      | Category 01   | 0        | cat01     |
    And the following "courses" exist:
      | fullname                | shortname | enablecompletion | enddate              | category |
      | Non-program Course 01   | NPC001    | 1                | ##+2 days 5 hours##  | cat01    |
      | Non-program Course 02   | NPC002    | 1                |                      |          |
      | Program Course 01       | PC001     | 1                |                      |          |
      | Program Course 02       | PC002     | 1                |                      |          |
      | Program Course 03       | PC003     | 1                |                      |          |
      | Program Course 04       | PC004     | 1                |                      |          |
    And the following "activities" exist:
      | activity | name      | intro     | course   | idnumber | completion | completionview |
      | page     | PageName1 | PageDesc1 | PC002    | PAGE1    | 1          | 1              |
      | page     | PageName2 | PageDesc2 | NPC001   | PAGE2    | 1          | 1              |
      | page     | PageName3 | PageDesc3 | NPC002   | PAGE3    | 1          | 1              |
      | page     | PageName4 | PageDesc4 | NPC002   | PAGE4    | 1          | 1              |
    And the following "course enrolments" exist:
      | user   | course | role    |
      | user11 | NPC001 | student |
      | user11 | NPC002 | student |
    And the following "tool_program > programs" exist:
      | fullname    | tenant  | duedatetype           | duedaterelative |
      | Program 01  | Tenant1 | after_user_allocation | 1 weeks         |
      | Program 02  | Tenant1 | none                  | 1 weeks         |
      | Program 03  | Tenant1 | after_user_allocation | -1 weeks        |
    And the following "tool_program > program_courses" exist:
      | program      | course  |
      | Program 02   | PC001   |
      | Program 01   | PC002   |
      | Program 01   | PC003   |
      | Program 01   | PC004   |
    And the following "tool_program > program_users" exist:
      | user   | program     |
      | user11 | Program 01  |
      | user11 | Program 02  |
      | user11 | Program 03  |
    And the following "tool_program > program_completions" exist:
      | program    | user   |
      | Program 02 | user11 |

  Scenario: User can see and filter programs and courses in the catalogue
    # Set completion criteria for courses.
    And I log in as "admin"
    And I am on "Program Course 02" course homepage
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | PageName1 | 1 |
    And I press "Save changes"
    And I am on "Non-program Course 01" course homepage
    And I navigate to "Course completion" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | PageName2 | 1 |
    And I press "Save changes"
    And I log in as "user11"
    # Complete some course activities first as user11.
    And I am on "Program Course 02" course homepage
    And I toggle the manual completion state of "PageName1"
    And I am on "Non-program Course 01" course homepage
    And I toggle the manual completion state of "PageName2"
    And I am on "Non-program Course 02" course homepage
    And I toggle the manual completion state of "PageName3"
    # Access the programs for the first time.
    And I am on the "My courses" page
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And I am on the "My courses" page
    And I click on "Program 02" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"

    # Check basic information is shown.
    And I am on the "My courses" page
    And I should see "3 Courses" in the "Program 01" "tool_catalogue > Catalogue item"
    And I should see "33% completed" in the "Program 01" "tool_catalogue > Catalogue item"
    And I should see "Due in 6 days" in the "Program 01" "tool_catalogue > Catalogue item"
    Then I should see "Program" in the "Program 02" "tool_catalogue > Catalogue item"
    And I should see "1 Courses" in the "Program 02" "tool_catalogue > Catalogue item"
    And I should see "100% completed" in the "Program 02" "tool_catalogue > Catalogue item"
    And I should not see "Due in" in the "Program 02" "tool_catalogue > Catalogue item"
    And I should see "Overdue" in the "Program 03" "tool_catalogue > Catalogue item"
    And I should not see "0% completed" in the "Program 03" "tool_catalogue > Catalogue item"
    And I should see "2 days left" in the "Non-program Course 01" "tool_catalogue > Catalogue item"
    And I should see "100% completed" in the "Non-program Course 01" "tool_catalogue > Catalogue item"
    And I should see "Category 01" in the "Non-program Course 01" "tool_catalogue > Catalogue item"
    And I should see "50% completed" in the "Non-program Course 02" "tool_catalogue > Catalogue item"

    # Filter programs.
    And I click on "Filter" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Programs" "link" in the "[data-region='mycourses-filter']" "css_element"
    And I should see "Program 02" in the "[data-region='catalogue']" "css_element"
    And I should see "Program 01" in the "[data-region='catalogue']" "css_element"
    And I should not see "Non-program Course 01" in the "[data-region='catalogue']" "css_element"
    And I should not see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"
    # Filter courses.
    And I click on "Filter" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Courses" "link" in the "[data-region='mycourses-filter']" "css_element"
    And I should not see "Program 02" in the "[data-region='catalogue']" "css_element"
    And I should not see "Program 01" in the "[data-region='catalogue']" "css_element"
    And I should see "Non-program Course 01" in the "[data-region='catalogue']" "css_element"
    And I should see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"
    # Filter completed.
    And I click on "Filter" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Completed" "link" in the "[data-region='mycourses-filter']" "css_element"
    And I should see "Program 02" in the "[data-region='catalogue']" "css_element"
    And I should not see "Program 01" in the "[data-region='catalogue']" "css_element"
    And I should see "Non-program Course 01" in the "[data-region='catalogue']" "css_element"
    And I should not see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"
    # Filter not completed.
    And I click on "Filter" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Not completed" "link" in the "[data-region='mycourses-filter']" "css_element"
    And I should not see "Program 02" in the "[data-region='catalogue']" "css_element"
    And I should see "Program 01" in the "[data-region='catalogue']" "css_element"
    And I should not see "Non-program Course 01" in the "[data-region='catalogue']" "css_element"
    And I should see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"

  Scenario: User can sort learning elements
    When I log in as "user11"
    # Access to a course in Program 1 first, and then to Non-program Course 01.
    And I am on "Program Course 02" course homepage
    And I am on "Non-program Course 01" course homepage
    And I am on the "My courses" page
    # Sort by name.
    And I click on "Sort by" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Sort by name" "link" in the "[data-region='mycourses-sorting']" "css_element"
    Then "Non-program Course 01" "tool_catalogue > Catalogue item" should appear before "Non-program Course 02" "tool_catalogue > Catalogue item"
    # Sort by conclussion date.
    And I click on "Sort by" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Sort by conclusion date" "link" in the "[data-region='mycourses-sorting']" "css_element"
    And "Non-program Course 01" "tool_catalogue > Catalogue item" should appear before "Program 02" "tool_catalogue > Catalogue item"
    # Sort by last accessed.
    And I click on "Sort by" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Sort by last accessed" "link" in the "[data-region='mycourses-sorting']" "css_element"
    And "Non-program Course 01" "tool_catalogue > Catalogue item" should appear before "Program 01" "tool_catalogue > Catalogue item"

  Scenario: User can search for learning elements
    When I am on the "My courses" page logged in as "user11"
    And I set the field "Search courses or programs" in the "[data-region='catalogue-controls']" "css_element" to "01"
    And I click on "Search courses or programs" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I should see "Program 01" in the "[data-region='catalogue']" "css_element"
    And I should not see "Program 02" in the "[data-region='catalogue']" "css_element"
    And I should see "Non-program Course 01" in the "[data-region='catalogue']" "css_element"
    And I should not see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"
    And I set the field "Search courses or programs" in the "[data-region='catalogue-controls']" "css_element" to ""
    And I click on "Search courses or programs" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I should see "Program 02" in the "[data-region='catalogue']" "css_element"
    And I should see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"

  Scenario: User can use pagination for learning elements
    Given the following "tool_program > programs" exist:
      | fullname    | tenant  | duedatetype | duedaterelative |
      | Program 04  | Tenant1 | none        | 1 weeks         |
      | Program 05  | Tenant1 | none        | 1 weeks         |
      | Program 06  | Tenant1 | none        | 1 weeks         |
      | Program 07  | Tenant1 | none        | 1 weeks         |
      | Program 08  | Tenant1 | none        | 1 weeks         |
      | Program 09  | Tenant1 | none        | 1 weeks         |
    And the following "tool_program > program_users" exist:
      | user   | program     |
      | user11 | Program 04  |
      | user11 | Program 05  |
      | user11 | Program 06  |
      | user11 | Program 07  |
      | user11 | Program 08  |
      | user11 | Program 09  |
    When I am on the "My courses" page logged in as "user11"
    # Sort by name first.
    And I click on "Sort by" "button" in the "[data-region='catalogue-controls']" "css_element"
    And I click on "Sort by name" "link" in the "[data-region='mycourses-sorting']" "css_element"
    Then I should see "Non-program Course 02" in the "[data-region='catalogue']" "css_element"
    And I should not see "Program 09" in the "[data-region='catalogue']" "css_element"
    # Use pagination.
    And I click on "Next page" "link" in the ".pagination" "css_element"
    And I should see "Program 09" in the "[data-region='catalogue']" "css_element"

  Scenario: User can see shared programs
    Given shared space is enabled
    And the following "tool_program > programs" exist:
      | fullname        | tenant  |
      | Shared program  | -       |
    And the following "tool_program > program_courses" exist:
      | program         | course  |
      | Shared program  | PC001   |
      | Shared program  | PC002   |
    And the following "tool_program > program_users" exist:
      | user     | program          |
      | user11   | Shared program   |
    When I am on the "My courses" page logged in as "user11"
    Then I should see "2 Courses" in the "Shared program" "tool_catalogue > Catalogue item"
    And I should see "Program" in the "Shared program" "tool_catalogue > Catalogue item"
    And I should see "Program 01" in the "[data-region='catalogue']" "css_element"

  Scenario: User is not allocated to any program or course
    When I am on the "My courses" page logged in as "user12"
    Then I should see "Nothing to display" in the "[data-region='catalogue']" "css_element"
