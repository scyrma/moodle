@tool @tool_program @moodleworkplace @theme_workplace @javascript
Feature: Edit program content with the drag and drop editor and check overview
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
    Given the following tool program data "programs" exist:
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
    And the following users allocations to programs exist:
      | user     | program  |
      | user11   | Program1 |

  Scenario: Add one course to a program
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    And I should see "Programs"
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I should see "Content"
    And I should see "Nothing to display"
    # Add a single course to program
    And I click on "Add a set or a course" "button"
    And I click on "Course" "link" in the ".program-items .dropdown .dropdown-menu" "css_element"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course 3" item in the autocomplete list
    Then I press "Save changes" in the modal form dialogue
    # Set completion criteria for program to All in order
    And I select "All in order" from the "completioncriteria" singleselect
    And I should not see "Nothing to display"
    And I log out
    # Check dashboard
    And I log in as "user11"
    And I should see "Programs"
    And I should see "Program1"
    And "Complete all in order" "text" should appear before "Course 3" "text"
    And I should see "Complete all in order (0/1)" in the ".programinfo" "css_element"
    And I should see "Start" in the ".programinfo" "css_element"
    And I should see "0%" in the ".programinfo .progress-circle" "css_element"
    And I should see "Course 3"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 3']/../.." "xpath_element"
    And I log out

  Scenario: Add one set to a program with two courses and delete one course
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    # Change completion criteria for program to All in any order
    And I select "All in any order" from the "completioncriteria" singleselect
    # Create an All in order set with 3 courses
    And I click on "Add a set or a course" "button"
    And I follow "Set"
    And I should see "Add set" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Name" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Select courses" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Completion" in the ".modal-dialog .modal-body" "css_element"
    And I set the field "Name" to "Set 1"
    Then I open the autocomplete suggestions list in the dialog
    And "Course 3" "autocomplete_suggestions" should exist
    And I click on "Course 1" item in the autocomplete list
    And I click on "Course 2" item in the autocomplete list
    And I select "All in order" from the "completioncriteriaset" singleselect
    Then I press "Save changes" in the modal form dialogue
    And I log out
    # Check overview
    And I log in as "user11"
    And I should see "Programs"
    And I should see "Program1"
    And I should see "Complete all in any order (0/1)" in the ".programinfo" "css_element"
    And I should not see "Course 1"
    And I should not see "Course 2"
    And I press "Set 1"
    And I should see "Course 1"
    And I should see "Course 2"
    And "Complete all in order (0/2)" "text" should exist in the "//*[normalize-space(text()) = 'Set 1']" "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And I log out
    # Delete one course from set
    And I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Delete" "link" in the "Course 1" table tree node
    And I click on "Remove" "button" in the "Confirm" "dialogue"
    And I log out
    # Check dashboard
    And I log in as "user11"
    And I should see "Program1"
    And "Complete all in order (0/1)" "text" should exist in the "//*[normalize-space(text()) = 'Set 1']" "xpath_element"
    And I press "Set 1"
    And I should not see "Course 1"
    And I should see "Course 2"
    And I log out

  Scenario: Add one set with courses to a program and reorder courses
    When I log in as "manager1"
    And I change window size to "large"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I select "All in order" from the "completioncriteria" singleselect
    # Create an All in order set with 3 courses
    And I click on "Add a set or a course" "button"
    And I follow "Set"
    And I should see "Add set" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Name" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Select courses" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Completion" in the ".modal-dialog .modal-body" "css_element"
    And I set the field "Name" to "Set 1"
    Then I open the autocomplete suggestions list in the dialog
    And "Course 3" "autocomplete_suggestions" should exist
    And I click on "Course 1" item in the autocomplete list
    And I click on "Course 2" item in the autocomplete list
    And I click on "Course 4" item in the autocomplete list
    And I select "All in order" from the "completioncriteriaset" singleselect
    Then I press "Save changes" in the modal form dialogue
    And I should not see "Nothing to display"
    And I should see "Name"
    And I should see "Completion"
    And I should see "Actions"
    And I click on "Move Course 1" "button"
    And I follow "To the top of the list"
    And "Course 1" "text" should appear before "Course 2" "text"
    And "Course 1" "text" should appear before "Course 4" "text"
    And I click on "Move Course 2" "button"
    And I follow "After \"Set 1\""
    And I log out
    # Check reorder changes on overview
    And I log in as "user11"
    And I should see "Programs"
    And I should see "Program1"
    And I should see "Complete all in order (0/3)" in the ".programinfo" "css_element"
    And I should not see "Course 4"
    And I press "Set 1"
    And I should see "Course 4"
    And "Course 1" "text" should appear before "Set 1" "text"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And "Set 1" "text" should appear before "Course 4" "text"
    And "Course 4" "text" should appear before "Course 2" "text"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 4']/../.." "xpath_element"
    And "Complete all in order (Locked)" "text" should exist in the "//*[normalize-space(text()) = 'Set 1']" "xpath_element"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And I log out
    And I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I should see "Content"
    And I click on "Move Set 1" "button"
    And I follow "To the top of the list"
    And I log out
    # Check dashboard
    And I log in as "user11"
    And I press "Set 1"
    And "Complete all in order (0/1)" "text" should exist in the "//*[normalize-space(text()) = 'Set 1']" "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 4']/../.." "xpath_element"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And I log out
    # Delete one set and its courses
    And I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Delete" "link" in the "Set 1" table tree node
    And I click on "Delete" "button" in the "Confirm" "dialogue"
    And I log out
    # Check only 2 courses exist now and only first is available
    And I log in as "user11"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And I should not see "Course 4"
    And I should not see "Set 1"
    And I log out
    And I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I select "All in any order" from the "completioncriteria" singleselect
    And I log out
    # Check only 2 courses exist now and both are available
    And I log in as "user11"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And I log out
    # Add a new set with completion criteria At least 2
    And I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    And I click on "Add a set or a course" "button"
    And I follow "Set"
    And I set the field "Name" to "Set 2"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course 3" item in the autocomplete list
    And I click on "Course 4" item in the autocomplete list
    And I select "At least" from the "completioncriteriaset" singleselect
    And I set the field "completionatleastset" to "2"
    Then I press "Save changes" in the modal form dialogue
    And I log out
    # Check that now program is set to Complete at least 2
    And I log in as "user11"
    And I should see "Programs"
    And "Complete at least 2 (0/2)" "text" should exist in the "//*[normalize-space(text()) = 'Set 2']/../.." "xpath_element"
    And I press "Set 2"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 4']/../.." "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 3']/../.." "xpath_element"
    And I should see "Complete all in any order (0/3)" in the ".programinfo" "css_element"
    And I log out

  Scenario: Add different completion criteria sets into another set
    When I log in as "manager1"
    And I navigate to "Programs" in workplace launcher
    Then I click on "Edit content" "link" in the "Program1" "table_row"
    # Change completion criteria for program to All in any order
    And I select "All in order" from the "completioncriteria" singleselect
    # Create an All in order set with 2 courses
    And I click on "Add a set or a course" "button"
    And I follow "Set"
    And I should see "Add set" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Name" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Select courses" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Completion" in the ".modal-dialog .modal-body" "css_element"
    And I set the field "Name" to "Set 1"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course 1" item in the autocomplete list
    And I click on "Course 2" item in the autocomplete list
    And I select "All in any order" from the "completioncriteriaset" singleselect
    Then I press "Save changes" in the modal form dialogue
    # Create an All in any order set with 2 courses
    And I click on "Add a set or a course" "link" in the "Set 1" table tree node
    And I click on "Set" "link" in the ".program-item .dropdown .dropdown-menu" "css_element"
    And I should see "Add set" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Name" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Select courses" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Completion" in the ".modal-dialog .modal-body" "css_element"
    And I set the field "Name" to "Set 2"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course 3" item in the autocomplete list
    And I click on "Course 4" item in the autocomplete list
    And I select "All in order" from the "completioncriteriaset" singleselect
    Then I press "Save changes" in the modal form dialogue
    # Create an At least 1 set with 2 courses
    And I click on "Add a set or a course" "link" in the "Set 1" table tree node
    And I click on "Set" "link" in the ".program-item .dropdown .dropdown-menu" "css_element"
    And I should see "Add set" in the ".modal-dialog .modal-title" "css_element"
    And I should see "Name" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Select courses" in the ".modal-dialog .modal-body" "css_element"
    And I should see "Completion" in the ".modal-dialog .modal-body" "css_element"
    And I set the field "Name" to "Set 3"
    Then I open the autocomplete suggestions list in the dialog
    And I click on "Course 5" item in the autocomplete list
    And I click on "Course 6" item in the autocomplete list
    And I select "At least" from the "completioncriteriaset" singleselect
    And I set the field "completionatleastset" to "1"
    Then I press "Save changes" in the modal form dialogue
    And I log out
    # Check overview
    And I log in as "user11"
    And I should see "Programs"
    And I should see "Program1"
    And I should see "Complete all in order (0/1)" in the ".programinfo" "css_element"
    And I press "Set 1"
    And I should see "Course 1"
    And I should see "Course 2"
    And I should see "Set 2"
    And I should see "Set 3"
    And I press "Set 2"
    And I press "Set 3"
    And "Complete all in any order (0/4)" "text" should exist in the "//*[normalize-space(text()) = 'Set 1']" "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 2']/../.." "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 1']/../.." "xpath_element"
    And "Complete all in order (0/2)" "text" should exist in the "//*[normalize-space(text()) = 'Set 2']" "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 4']/../.." "xpath_element"
    And "Locked" "text" should exist in the "//*[normalize-space(text()) = 'Course 3']/../.." "xpath_element"
    And "Complete at least 1 (0/2)" "text" should exist in the "//*[normalize-space(text()) = 'Set 3']" "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 5']/../.." "xpath_element"
    And "Available" "text" should exist in the "//*[normalize-space(text()) = 'Course 6']/../.." "xpath_element"
    And I log out