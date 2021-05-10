@tool @tool_dynamicrule @moodleworkplace
Feature: Rule aside
  In order to search a condition or action
  As an tenant admin
  I need to be able to for elements in the aside list

  Background:
    Given "2" tenants exist with "2" users and "2" courses in each
    And the following dynamic rules exist:
      | name  | tenant  |
      | Rule1 | Tenant1 |

  @javascript
  Scenario: Allow searching in the conditions aside
    When I log in as "tenantadmin1"
    And I navigate to "Dynamic rules" in site administration
    Then I follow "Edit rule 'Rule1'"
    When I search for "completed" in aside
    Then I should see "Course completed" in the "//div[@data-region='aside-search-area']" "xpath_element"
    And I should not see "User enrolled" in the "//div[@data-region='aside-search-area']" "xpath_element"
    And I click on "Actions" "tool_wp > Tab"
    When I search for "badge" in aside
    Then I should see "Award badge" in the "//div[@data-region='aside-search-area']" "xpath_element"
    And I should not see "Notification" in the "//div[@data-region='aside-search-area']" "xpath_element"
