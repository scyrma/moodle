@tool @tool_catalogue @moodleworkplace @javascript
Feature: Program cover in the Learning catalogue plugin
  In order to access a program
  As a student
  I need to see all the program information

  Background:
    Given "2" tenants exist with "3" users and "2" courses in each
    And the following "tool_program > programs" exist:
      | fullname   | tenant  | program_tags | enddatetype | enddateabsolute |
      | Program 01 | Tenant1 | black        | 1           | ##+1 year##     |
    And the following "tool_program > program_sets" exist:
      | name      | program    | set     |
      | Set       | Program 01 |         |
      | SubSet    | Program 01 | Set     |
    And the following "tool_program > program_courses" exist:
      | program      | course  |
      | Program 01   | C11     |
    And the following "tool_certification > certifications" exist:
      | fullname         | tenant  | program     | certification_tags | startdatetype         | startdaterelative  | duedatetype      | duedaterelative | expirydatetype | expirydaterelative |
      | Certification 01 | Tenant1 | Program 01  | white              | user_allocation_date  | 0 week             | after_start_date | -1 day          | after_due_date | 0 week             |
      | Certification 02 | Tenant1 | Program 01  | red                | user_allocation_date  | +1 week            | after_start_date | +1 day          | after_due_date | 0 week             |
    And the following "tool_program > program_users" exist:
      | program    | user   |
      | Program 01 | user11 |
    And the following "tool_certification > certification_users" exist:
      | certification    | user    |
      | Certification 01 | user11  |
      | Certification 02 | user11  |

  Scenario: User can hide the program help box
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I should see "What is a program?" in the "tool_catalogue_hide_program_cover_help" "region"
    And I click on "Don't show this message again" "button" in the "tool_catalogue_hide_program_cover_help" "region"
    And I reload the page
    And I should not see "What is a program?"

  Scenario: User should see the program information
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    Then I should see "Program 01" in the ".breadcrumb" "css_element"
    And I should see "Program 01" in the "page-header" "region"
    # About this program (only section expanded by default).
    And I should see "A program description" in the "About this program" "tool_catalogue > Catalogue pagesection"
    And I should see "black" in the "About this program" "tool_catalogue > Catalogue pagesection"
    And I should see "white" in the "About this program" "tool_catalogue > Catalogue pagesection"
    And I should see "red" in the "About this program" "tool_catalogue > Catalogue pagesection"
    # Dates.
    And I click on "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##yesterday##Due date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "Overdue" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##+1 year##End date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    # Certifications.
    And I click on "Certifications" "tool_catalogue > Catalogue pagesection"
    And I should see "##yesterday##The certification 'Certification 01' was due on '%d/%m/%y'##" in the "Certifications" "tool_catalogue > Catalogue pagesection"
    And I should see "##tomorrow##Complete this program by '%d/%m/%y' to get the certification 'Certification 02'##" in the "Certifications" "tool_catalogue > Catalogue pagesection"
    # Program structure.
    And I click on "Program structure" "tool_catalogue > Catalogue pagesection"
    And I should see "Course11" in the "Program structure" "tool_catalogue > Catalogue pagesection"
    And I should not see "SubSet" in the "Program structure" "tool_catalogue > Catalogue pagesection"
    And I click on "Set" "link" in the "Program structure" "tool_catalogue > Catalogue pagesection"
    And I should see "SubSet" in the "Program structure" "tool_catalogue > Catalogue pagesection"
    # Proceed to program.
    And I click on "Not now" "link"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    And "program-container" "region" should exist

  Scenario: User should not see the program cover if already accessed the program
    When I am on the "My courses" page logged in as "user11"
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    And I click on "Proceed to program content" "button"
    # Go to the already accessed "Program 01".
    And I am on the "My courses" page
    And I click on "Program 01" "tool_catalogue > Catalogue item"
    Then I should not see "Proceed to program content"
    And "program-container" "region" should exist
    # Program information (with all section expanded by default) should be visible in the "Information" tab.
    And I click on "Information" "link" in the "nav-tab" "region"
    And I should see "A program description" in the "About this program" "tool_catalogue > Catalogue pagesection"
    And I should see "##today##Start date: %d/%m/%y##" in the "Dates" "tool_catalogue > Catalogue pagesection"
    And I should see "##yesterday##The certification 'Certification 01' was due on '%d/%m/%y'##" in the "Certifications" "tool_catalogue > Catalogue pagesection"
    And I should see "Course11" in the "Program structure" "tool_catalogue > Catalogue pagesection"
