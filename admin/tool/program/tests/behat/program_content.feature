@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Edit program content with the drag and drop editor
  In order to edit the content of a program
  As a manager
  I need to go to the content tab from program and add sets and courses

  Background:
    Given "2" tenants exist with "2" users and "0" courses in each
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                 | contextlevel | reference |
      | manager1 | tool_program_manager | System       |           |
    Given the following "tool_program > programs" exist:
      | fullname | archived | tenant  |
      | Program1 | 0        | Tenant1 |
    And the following "courses" exist:
      | fullname | shortname | format | category |
      | Course 1 | C1        | topics | CAT1     |
      | Course 2 | C2        | topics | CAT1     |
      | Course 3 | C3        | topics | CAT1     |
      | Course 4 | C4        | topics | CAT1     |
      | Course 5 | C5        | topics | CAT1     |
      | Course 6 | C6        | topics | CAT1     |
    And the following "tool_program > program_users" exist:
      | user     | program  |
      | user11   | Program1 |

  Scenario: Add one course to a program
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I should see "Content"
    And I should see "Nothing to display"
    # Add a single course to program
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    And I set the field "Select courses" to "Course 3"
    And I should see "Re-calculate program completion" in the ".modal-dialog .modal-body" "css_element"
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    # Set completion criteria for program to All in order
    And I select "All in order" from the "completioncriteria" singleselect
    And I should not see "Nothing to display"

  Scenario: Add one set to a program with two courses and delete one course
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    # Change completion criteria for program to All in any order
    And I select "All in any order" from the "completioncriteria" singleselect
    # Create an All in order set with 3 courses
    And I click on "Add a set or a course" "button"
    And I follow "Set of courses"
    And I should see "Add set" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Select courses" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Completion" in the ".modal-dialog .modal-body" "css_element"
    And I set the field "Name" to "Set 1"
    Then I open the autocomplete suggestions list in the "Add set" "dialogue"
    And "Course 3" "autocomplete_suggestions" should exist
    And I click on "Course 1" item in the autocomplete list
    And I click on "Course 2" item in the autocomplete list
    And "Course 3" "autocomplete_suggestions" should exist
    And I select "All in order" from the "completioncriteriaset" singleselect
    And I should see "Re-calculate program completion" in the ".modal-dialog .modal-body" "css_element"
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And I should not see "Nothing to display"
    And I should see "Name"
    And I should see "Completion"
    And I should see "Actions"
    And "Set 1" "text" should appear before "Course 1" "text"
    And I should see "All in order" in the "Set 1" "tool_wp > Table tree node"
    And I click on "Delete" "link" in the "Course 1" "tool_wp > Table tree node"
    And I click on "Remove" "button" in the "Confirm" "dialogue"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And I should see "Course 2"
    And I should not see "Course 1"

  Scenario: Add one set with courses to a program and reorder courses
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    And I select "All in order" from the "completioncriteria" singleselect
    # Create an All in order set with 3 courses
    And I click on "Add a set or a course" "button"
    And I follow "Set of courses"
    And I set the following fields to these values:
      | Name                  | Set 1                      |
      | Select courses        | Course 4,Course 2,Course 1 |
      | completioncriteriaset | All in order               |
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And I click on "Move Course 1" "button"
    And I follow "To the top of the list"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And "Course 1" "text" should appear before "Course 2" "text"
    And "Course 1" "text" should appear before "Course 4" "text"
    And I click on "Move Course 2" "button"
    And I follow "After \"Set 1\""
    And I should see "Changes saved" in the ".toast-message" "css_element"
    # Check everything is in the correct order.
    And "Course 1" "text" should appear before "Set 1" "text"
    And "Set 1" "text" should appear before "Course 4" "text"
    And "Course 4" "text" should appear before "Course 2" "text"
    # Delete one set and its courses
    And I click on "Delete" "link" in the "Set 1" "tool_wp > Table tree node"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And "Course 1" "text" should appear before "Course 2" "text"
    And I should not see "Course 4"
    And I should not see "Set 1"

  Scenario: Add one sub-set with courses and remove the sub-set
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    # Create an All in order set with 1 course
    And I click on "Add a set or a course" "button"
    And I follow "Set of courses"
    And I set the following fields to these values:
      | Name                  | Set 1         |
      | Select courses        | Course 1      |
      | completioncriteriaset | All in order  |
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And I click on "Add a set or a course" "link" in the "Set 1" "tool_wp > Table tree node"
    And I click on "Set of courses" "link" in the "Set 1" "tool_wp > Table tree node"
    And I set the following fields to these values:
      | Name                  | Set 2         |
      | Select courses        | Course 2      |
      | completioncriteriaset | All in order  |
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And "Set 1" "text" should appear before "Set 2" "text"
    And "Course 1" "text" should appear before "Course 2" "text"
    And I click on "Delete" "link" in the "Set 2" "tool_wp > Table tree node"
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And I should see "Set 1"
    And I should see "Course 1"
    And I should not see "Set 2"
    And I should not see "Course 2"

  Scenario: Add different completion criteria sets
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
    # Change completion criteria for program to All in any order
    And I select "All in order" from the "completioncriteria" singleselect
    # Create an All in any order set with 2 courses
    And I click on "Add a set or a course" "button"
    And I follow "Set of courses"
    And I set the following fields to these values:
      | Name                  | Set 1             |
      | Select courses        | Course 2,Course 1 |
      | completioncriteriaset | All in any order  |
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    # Create an All in order set with 2 courses
    And I click on "Add a set or a course" "link" in the "Set 1" "tool_wp > Table tree node"
    And I click on "Set of courses" "link" in the ".program-item .dropdown .dropdown-menu" "css_element"
    And I set the following fields to these values:
      | Name                  | Set 2             |
      | Select courses        | Course 4,Course 3 |
      | completioncriteriaset | All in order      |
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    # Create an At least 1 set with 2 courses
    And I click on "Add a set or a course" "link" in the "Set 1" "tool_wp > Table tree node"
    And I click on "Set of courses" "link" in the ".program-item .dropdown .dropdown-menu" "css_element"
    And I set the following fields to these values:
      | Name                  | Set 3             |
      | Select courses        | Course 6,Course 5 |
      | completioncriteriaset | At least          |
      | completionatleastset  | 2                 |
    Then I press "Save changes"
    And I should see "Changes saved" in the ".toast-message" "css_element"
    And "Set 1" "text" should appear before "Course 1" "text"
    # Check completion criteria have been saved correctly.
    And I should see "All in any order" in the "Set 1" "tool_wp > Table tree node"
    And I should see "All in order" in the "Set 2" "tool_wp > Table tree node"
    And I should see "At least" in the "Set 3" "tool_wp > Table tree node"

  Scenario: Add a hidden course into a program
    Given the following "courses" exist:
      | fullname      | shortname | format | category | visible |
      | Course hidden | C7        | topics | CAT1     | 0       |
    And the following "tool_program > program_courses" exist:
      | program  | course |
      | Program1 | C1    |
      | Program1 | C7    |
      | Program1 | C2    |
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Program1" "link" in the "Program1" "table_row"
      # Change completion criteria for program to All in order
    And I select "All in order" from the "completioncriteria" singleselect
    And I follow "Course hidden"
    And I should see "This course is currently unavailable to students"
    And I log out
      # Check dashboard
  # TODO when hidden courses is fixed.
#    And I am on the "My courses" page logged in as "user11"
#  And I click on "Program1" "tool_catalogue > Catalogue item"
#  And I click on "Proceed to program content" "button"
#    And I should see "Course 1"
#    And I should not see "Course hidden"
#    And I should see "Course not available"
#    And I should see "Course 2"
#    And I log out
