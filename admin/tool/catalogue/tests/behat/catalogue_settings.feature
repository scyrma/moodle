@tool @tool_catalogue @moodleworkplace @javascript
Feature: Configuring the leraning catalogue
  As an admin or manager
  I should be able to configure the learning catalogue

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | tool_catalogue |
    Given the following "roles" exist:
      | name              | shortname  | description      | archetype |
      | Catalogue manager | catmanager | My custom role 1 |           |
    And the following "users" exist:
      | username | firstname | lastname | email             |
      | user1    | User      | 1        | user1@example.com |
    And the following "role assigns" exist:
      | user  | role       | contextlevel | reference |
      | user1 | catmanager | System       |           |
    And the following "permission overrides" exist:
      | capability             | permission | role       | contextlevel | reference |
      | moodle/site:configview | Allow      | catmanager | System       |           |
      | tool/catalogue:config  | Allow      | catmanager | System       |           |
    And the following "custom field categories" exist:
      | name   | component   | area   | itemid |
      | Newcat | core_course | course | 0      |
    And the following "custom fields" exist:
      | name            | category | type     | shortname | description | configdata            |
      | Field shorttext | Newcat   | text     | f1        | d1          |                       |
      | Field textarea  | Newcat   | textarea | f2        | d2          |                       |
      | Field checkbox  | Newcat   | checkbox | f3        | d3          |                       |
      | Field date      | Newcat   | date     | f4        | d4          |                       |
      | Field select    | Newcat   | select   | f5        | d5          | {"options":"a\nb\nc"} |

  Scenario: Configuring the learning catalogue as a manager
    When I log in as "user1"
    And I navigate to "Courses > Learning catalogue configuration" in site administration
    And I should see "Course summary" in the "tool_catalogue-displayfields_tiles" "table"
    And I click on "Show" "link" in the "Tags" "table_row"
    And I click on "Show" "link" in the "Course summary" "table_row"
    And I click on "Move up" "link" in the "Course summary" "table_row"
    And the field "Allow HTML tags" in the "Course summary" "table_row" matches value "Only safe HTML tags"
    And I set the field "Allow HTML tags" in the "Course summary" "table_row" to "Allow any HTML tags"
    # Refresh and check that everything is saved
    And I navigate to "Courses > Learning catalogue configuration" in site administration
    And "Hide" "link" should exist in the "Tags" "table_row"
    And "Hide" "link" should exist in the "Course summary" "table_row"
    And the field "Allow HTML tags" in the "Course summary" "table_row" matches value "Allow any HTML tags"

  Scenario: Admin can search admin tree and find learning catalogue settings
    When I log in as "admin"
    And I navigate to "Courses > Learning catalogue configuration" in site administration
    And I should see "Fields to display in the 'tiles' (compact) view of learning catalogue"
    And I should see "Fields to display in the 'list' (detailed) view of learning catalogue"
    And I set the field "Search" to "Fields to display in the 'list' (detailed) view of learning catalogue"
    And I press "Search"
    And I should not see "Fields to display in the 'tiles' (compact) view of learning catalogue"
    And I should see "Fields to display in the 'list' (detailed) view of learning catalogue"
