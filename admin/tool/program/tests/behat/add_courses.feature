@tool @tool_program @moodleworkplace @theme_workplace
Feature: Add courses to programs
  In order to add courses to programs
  As a manager
  I need to add courses from the Content tab in Edit program settings

  Background:
    Given "2" tenants exist with "2" users and "3" courses in each
    And the following "role assigns" exist:
      | user   | role                 | contextlevel | reference |
      | user11 | tool_program_manager | System       |           |
    Given the following tool program data "programs" exist:
      | fullname | tenant  |
      | Program1 | Tenant1 |
      | Program2 | Tenant1 |
    And the following "courses" exist:
      | fullname        | shortname | category |
      | Course hi & bye | C4        | CAT1 |

  @javascript
  Scenario: Add courses to an empty program
    When I log in as "user11"
    Then I navigate to "Courses > Programs" in site administration
    And I should see "Programs"
    Then I click on "Active" "link"
    And I should see "Program1"
    And I should see "Program2"
    Then I click on ".edit_program" "css_element" in the "Program1" "table_row"
    Then I should see "Program1"
    And I should see "Edit details"
    Then I click on "Content" "link"
    And I should see "Content" in the "#program_content_tab" "css_element"
    Then I press "Add a set or a course"
    Then I click on "Course" "link" in the ".dropdown-menu.show" "css_element"
    Then I should see "Add courses"
    Then I should see "Select courses"
    Then I open the autocomplete suggestions list in the dialog
    And I should see "Course11" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Course12" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Course13" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should see "Course hi & bye" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I should not see "Course2"
    Then I click on "Course11" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Course12" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I click on "Course hi & bye" "text" in the ".modal-dialog .form-autocomplete-suggestions" "css_element"
    And I press key "27" in the field "Select courses"
    Then I press "Save changes" in the modal form dialogue
    Then I should see "Course11" in the "#program_content_tab" "css_element"
    And I should see "Course12" in the "#program_content_tab" "css_element"
    And I should see "Course hi & bye" in the "#program_content_tab" "css_element"
    And I log out
