@tool @tool_dynamicrule @moodleworkplace
Feature: Rule aside
  In order to search a condition or action
  As an manager
  I need to be able to for elements in the aside list

  Background:
    Given the following tenants exist:
      | name    |
      | Tenant1 |
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following users allocations to tenants exist:
      | user     | tenant  |
      | manager1 | Tenant1 |
    And the following "role assigns" exist:
      | user     | role                     | contextlevel | reference |
      | manager1 | tool_dynamicrule_manager | System       |           |
    And the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |

  @javascript
  Scenario: Allow searching in the conditions aside
    When I log in as "manager1"
    And I navigate to "Dynamic rules" in site administration
    Then I follow "Edit rule 'Rule1'"
    When I search for "completed" in aside
    Then I should see "Course completed" in the "//div[@data-region='aside-search-area']" "xpath_element"
    And I should not see "User enrolled" in the "//div[@data-region='aside-search-area']" "xpath_element"
    And I follow "Actions"
    When I search for "badge" in aside
    Then I should see "Award badge" in the "//div[@data-region='aside-search-area']" "xpath_element"
    And I should not see "Notification" in the "//div[@data-region='aside-search-area']" "xpath_element"