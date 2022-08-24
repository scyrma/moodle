@tool @tool_catalogue @moodleworkplace @javascript
Feature: Program content in the Learning catalogue plugin
  In order to access the program content
  As a student
  I need to see all the program sets and courses and be able to navigate between them

  Background:
    Given "1" tenants exist with "2" users and "7" courses in each
    And the following config values are set as admin:
      | config                        | value | plugin         |
      | showcataloguecoursecategory   | 1     | tool_catalogue |
    And the following "courses" exist:
      | fullname      | shortname | format | category | visible |
      | Mathematics   | CHIDDEN   | topics | CAT1     | 0       |
    And the following "tool_program > programs" exist:
      | fullname   | tenant  |
      | Program 01 | Tenant1 |
    And the following "tool_program > program_sets" exist:
      | name    | program    | set   |
      | Set1    | Program 01 |       |
      | Set1-A  | Program 01 | Set1  |
      | Set2    | Program 01 |       |
      | Set2-A  | Program 01 | Set2  |
    And the following "tool_program > program_courses" exist:
      | program      | course  | set    | sortorder |
      | Program 01   | C11     | Set1   |      2    |
      | Program 01   | C12     | Set1-A |      3    |
      | Program 01   | C13     | Set2   |      1    |
      | Program 01   | C14     | Set2-A |      1    |
      | Program 01   | C15     | Set2-A |      2    |
      | Program 01   | C16     |        |      3    |
      | Program 01   | CHIDDEN |        |      4    |
      | Program 01   | C17     |        |      5    |
    And the following "tool_program > program_users" exist:
      | program    | user   |
      | Program 01 | user11 |
    And the following "tool_certification > certifications" exist:
      | fullname         | tenant  | program     | startdatetype         | startdaterelative  | duedatetype      | duedaterelative | expirydatetype | expirydaterelative |
      | Certification 01 | Tenant1 | Program 01  | user_allocation_date  | 0 week             | after_start_date | +1 year         | after_due_date | 0 week             |
    And the following "tool_certification > certification_users" exist:
      | certification    | user    |
      | Certification 01 | user11  |

  Scenario: User can navigate between sets and courses within the program
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And "Information" "link" should exist in the "nav-tab" "region"
    And "Program" "link" should exist in the "nav-tab" "region"
    # Check content of base set.
    And I should see "##+1 year##Complete this program by '%d/%m/%y' to get the certification 'Certification 01'##" in the "[data-region='alert-dismissible']" "css_element"
    And I should see "Complete all in order" in the "[data-region='program-heading']" "css_element"
    And I should see "0 of 5 completed" in the "[data-region='program-heading']" "css_element"
    And I should see "0% completed" in the "Set1" "tool_catalogue > Catalogue item"
    And I should see "2 Courses" in the "Set1" "tool_catalogue > Catalogue item"
    And I should see "Not available until 'Set1' is completed" in the "Set2" "tool_catalogue > Catalogue item"
    And I should see "3 Courses" in the "Set2" "tool_catalogue > Catalogue item"
    And I should see "Not available until 'Set2' is completed" in the "Course16" "tool_catalogue > Catalogue item"
    And I should see "Course not available" in the "[data-region='program-container']" "css_element"
    And I should see "Not available until 'Course not available' is completed" in the "Course17" "tool_catalogue > Catalogue item"
    # Check content of Set1.
    And I click on "Set1" "tool_catalogue > Catalogue item"
    And I should see "Set1" in the ".breadcrumb" "css_element"
    And I should see "Set1" in the "page-header" "region"
    # We need XPATH here for safety reasons, because same 'data-region' elements exist hidden in the DOM.
    And I should see "0 of 2 completed" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "0% completed" in the "Set1-A" "tool_catalogue > Catalogue item"
    And I should see "1 Courses" in the "Set1-A" "tool_catalogue > Catalogue item"
    And I should see "Not available until 'Set1-A' is completed" in the "Course11" "tool_catalogue > Catalogue item"
    # Check content of Set1-A.
    And I click on "Set1-A" "tool_catalogue > Catalogue item"
    And I should see "Set1-A" in the ".breadcrumb" "css_element"
    And I should see "Set1-A" in the "page-header" "region"
    And I should see "0 of 1 completed" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "0% completed" in the "Course12" "tool_catalogue > Catalogue item"
    # Check content of Set2.
    And I click on "Program 01" "link" in the ".breadcrumb" "css_element"
    And I click on "Set2" "tool_catalogue > Catalogue item"
    And I should see "Set2" in the ".breadcrumb" "css_element"
    And I should see "Set2" in the "page-header" "region"
    And I should see "Not available until 'Set1' is completed" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "Complete all in order" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "0 of 2 completed" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "Not available unless 'Set2' is available" in the "Set2-A" "tool_catalogue > Catalogue item"
    And I should see "2 Courses" in the "Set2-A" "tool_catalogue > Catalogue item"
    And I should see "Not available unless 'Set2' is available" in the "Course13" "tool_catalogue > Catalogue item"
    And I should not see "Course16"
    # Check content of Set2-A.
    And I click on "Set2-A" "tool_catalogue > Catalogue item"
    And I should see "Set2-A" in the ".breadcrumb" "css_element"
    And I should see "Set2-A" in the "page-header" "region"
    And I should see "Not available unless 'Set2' is available" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "Complete all in order" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "0 of 2 completed" in the ".//*[@data-region='set-heading' and not(contains(@class, 'd-none'))]" "xpath_element"
    And I should see "Not available unless 'Set2-A' is available" in the "Course14" "tool_catalogue > Catalogue item"
    And I should see "Not available unless 'Set2-A' is available" in the "Course15" "tool_catalogue > Catalogue item"
    And I should not see "Course13"

  Scenario: User can see the recent accessed courses section
    Given the following "course enrolments" exist:
      | user    | course | role     |
      | user11  | C11    | student  |
      | user11  | C12    | student  |
      | user11  | C13    | student  |
      | user11  | C14    | student  |
    And the following "activities" exist:
      | activity | name              | intro                    | course | idnumber | section | completion |
      | assign   | Activity sample 1 | Assignment description 1 | C11    | sample1  | 1       | 1          |
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    # User has not accessed any courses yet.
    Then "Recently accessed courses" "tool_catalogue > Catalogue pagesection" should not exist
    And I am on "Course11" course homepage
    And I toggle the manual completion state of "Activity sample 1"
    And I toggle the manual completion state of "URL1"
    And I am on "Course12" course homepage
    And I am on "Course14" course homepage
    And I am on the "My courses" page
    And I should see "66% completed" in the "Course11" "tool_catalogue > Catalogue item"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I should see "Course11" in the "Recently accessed courses" "tool_catalogue > Catalogue pagesection"
    And I should see "Category1" in the "Course11" "tool_catalogue > Recently accessed course card"
    And I should see "66% completed" in the "Course11" "tool_catalogue > Recently accessed course card"
    And I should see "Course12" in the "Recently accessed courses" "tool_catalogue > Catalogue pagesection"
    And I should not see "Course13" in the "Recently accessed courses" "tool_catalogue > Catalogue pagesection"
    And I should see "Course14" in the "Recently accessed courses" "tool_catalogue > Catalogue pagesection"
