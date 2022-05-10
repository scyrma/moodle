<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * tool_wp steps definitions.
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException,
    Behat\Mink\Exception\ExpectationException as ExpectationException;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_wp.
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_wp extends behat_base {

    /**
     * Click on the form button in the modal form.
     *
     * @When /^I press "(?P<button_string>(?:[^"]|\\")*)" in the modal form dialogue$/
     * @param string $buttontext
     */
    public function i_press_in_the_modal_form_dialogue($buttontext) {

        // I click on "Save changes" "button" in the ".modal.show .modal-footer" "css_element" .
        $this->execute('behat_general::i_click_on_in_the', [$this->escape($buttontext), 'button',
            '.modal.show .modal-footer', 'css_element']);
    }

    /**
     * Click on a link in a table tree
     *
     * phpcs:ignore
     * @Given /^I click on "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
     *
     * @deprecated since 3.10.2
     *
     * Deprecated, use:
     * And I click on "ELEMENT "SELECTOR" in the "LOCATOR" "tool_wp > Table tree node"
     *
     * @param string $element
     * @param string $selector
     * @param string $treenode
     */
    public function i_click_on_in_the_table_tree_node($element, $selector, $treenode) {
        $xpathtarget = "//div[contains(concat(' ', @class, ' '), ' tool-wp-node-name ') and contains(.,'" .
            $this->escape($treenode) . "')]";

        $this->execute('behat_general::i_click_on_in_the',
            [$this->escape($element), $selector, $xpathtarget, 'xpath_element']);
    }

    /**
     * Check that something exist
     *
     * phpcs:ignore
     * @Then /^"(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" should exist in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
     *
     * @deprecated since 3.10.2
     *
     * Deprecated, use:
     * And "ELEMENT "SELECTOR" should exist in the "LOCATOR" "tool_wp > Table tree node"
     *
     * @param string $element
     * @param string $selector
     * @param string $treenode
     */
    public function should_exist_in_the_table_tree_node($element, $selector, $treenode) {
        $xpathtarget = "//div[contains(concat(' ', @class, ' '), ' tool-wp-node-name ') and contains(.,'" .
            $this->escape($treenode) . "')]";

        $this->execute('behat_general::should_exist_in_the',
            [$this->escape($element), $selector, $xpathtarget, 'xpath_element']);
    }

    /**
     * Check that something exist
     *
     * phpcs:ignore
     * @Then /^"(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" should not exist in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
     *
     * @deprecated since 3.10.2
     *
     * Deprecated, use:
     * And "ELEMENT "SELECTOR" should not exist in the "LOCATOR" "tool_wp > Table tree node"
     *
     * @param string $element
     * @param string $selector
     * @param string $treenode
     */
    public function should_not_exist_in_the_table_tree_node($element, $selector, $treenode) {
        $xpathtarget = "//div[contains(concat(' ', @class, ' '), ' tool-wp-node-name ') and contains(.,'" .
            $this->escape($treenode) . "')]";

        $this->execute('behat_general::should_not_exist_in_the',
            [$this->escape($element), $selector, $xpathtarget, 'xpath_element']);
    }

    /**
     * Open the auto-complete suggestions list (Assuming there is only one on the page.).
     *
     * @deprecated since 3.10.3, 3.11
     *
     * Deprecated, use:
     * And I open the autocomplete suggestions list in the "TITLE" "dialogue"
     *
     * @Given /^I open the autocomplete suggestions list in the dialog$/
     */
    public function i_open_the_autocomplete_suggestions_list_in_the_dialog(): void {
        $csselement = '.form-autocomplete-downarrow';
        $nodeelement = '.modal-dialog';
        $this->execute('behat_general::i_click_on_in_the', [$csselement, 'css_element', $nodeelement, 'css_element']);
    }

    /**
     * Sets the field in the specified container
     *
     * @Given /^I set the visible field "(?P<field_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)"$/
     *
     * @deprecated since 3.10.3, 3.11
     *
     * Deprecated, use:
     * And I set the field "FIELD" in the "ELEMENT" "SELECTOR" to "VALUE"
     *
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $field
     * @param string $value
     */
    public function i_set_the_visible_field_to($field, $value): void {
        list($selector, $locator) = $this->transform_selector('field', $field);

        // Specific exception giving info about where can't we find the element.
        $locatorexceptionmsg = $field . '"';
        $exception = new ElementNotFoundException($this->getSession(), 'field', null, $locatorexceptionmsg);

        // Looks for the requested node inside the container node.
        $element = $this->find_visible($selector, $locator, $exception);

        // Set the field value.
        $field = behat_field_manager::get_form_field($element, $this->getSession());
        $field->set_value($value);
    }

    /**
     * Fills a form with field/value data.
     *
     * @deprecated since 3.10.3, 3.11
     *
     * Deprecated, use:
     * And I set the following fields in the "ELEMENT" "SELECTOR" to these values:
     *
     * @Given /^I set the following visible fields to these values:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param TableNode $data
     */
    public function i_set_the_following_visible_fields_to_these_values(TableNode $data) {
        $this->execute('behat_forms::i_expand_all_fieldsets');

        $datahash = $data->getRowsHash();

        // The action depends on the field type.
        foreach ($datahash as $fieldname => $value) {
            $this->i_set_the_visible_field_to($fieldname, $value);
        }
    }

    /**
     * Returns first matching element that is visible
     *
     * @param string $selector The selector type (css, xpath, named...)
     * @param mixed $locator It depends on the $selector, can be the xpath, a name, a css locator...
     * @param Exception $exception Otherwise we throw exception with generic info
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function find_visible($selector, $locator, $exception) {

        $nodes = $this->find_all($selector, $locator, $exception);

        // We spin as we don't have enough checking that the element is there, we
        // should also ensure that the element is visible. Using microsleep as this
        // is a repeated step and global performance is important.
        $node = $this->spin(
            function($context, $args) {

                foreach ($args['nodes'] as $node) {
                    if ($node->isVisible()) {
                        return $node;
                    }
                }

                // If non of the nodes is visible we loop again.
                throw $args['exception'];
            },
            array('nodes' => $nodes, 'exception' => $exception),
            false,
            false,
            true
        );

        if (!$node) {
            throw $exception;
        }

        return $node;
    }

    /**
     * Search for a string using the list group search.
     *
     * @Given /^I search for "(?P<string>(?:[^"]|\\")*)" in aside$/
     * @param string $string the search string.
     */
    public function i_search_for_string_in_aside($string) {
        $this->execute('behat_forms::i_set_the_field_with_xpath_to',
            [
                "//input[@data-region='aside-search-input']",
                $this->escape($string)
            ]
        );
        $this->execute('behat_general::wait_until_the_page_is_ready');
    }

    /**
     * Return the list of partial named selectors.
     *
     * Those selectors can be used to capture tabs and tab content. Examples:
     *    When I click on "Schedule" "tool_wp > Tab"
     *    And I should see "Rule1" in the "Archived" "tool_wp > Tab content"
     *    And "Users" "tool_wp > Active tab" should exist
     * Other selectors examples:
     *    And I click on "Disable" "link" in the "Pluginname" "tool_wp > Row"
     *    And I click on "Tenant2" "link" in the "Tenant2" "tool_wp > Table tree node"
     *
     * @return array
     */
    public static function get_partial_named_selectors(): array {
        return [
            new behat_component_named_selector('Tab', [
                <<<XPATH
    .//ul[@role='tablist']//a[@role='tab' and contains(string(), %locator%)]
XPATH
            ], false),
            new behat_component_named_selector('Tab content', [
                <<<XPATH
    .//div[@aria-labelledby=//ul[@role='tablist']
        //a[@role='tab' and contains(concat(' ', normalize-space(@class), ' '), ' active ' ) and contains(string(), %locator%)]/@id]
XPATH
            ], false),
            new behat_component_named_selector('Active tab', [
                <<<XPATH
    .//ul[@role='tablist']
        //a[@role='tab' and contains(concat(' ', normalize-space(@class), ' '), ' active ') and contains(string(), %locator%)]
XPATH
            ], false),
            new behat_component_named_selector('Row',
                [".//div[contains(concat(' ', normalize-space(@class), ' '), ' row ') and contains(., %locator%)]"],
                true),
            new behat_component_named_selector('Table tree node',
                ["//div[contains(concat(' ', normalize-space(@class), ' '), ' tool-wp-node-name ') and contains(., %locator%)]"],
                true),
        ];
    }

    /**
     * Export given entities
     *
     * @Given /^I perform a new export with these options:$/
     * @param TableNode $options Export options in the format: | Step | Option name | Option value |
     */
    public function i_perform_a_new_export_using_this_options($options) {
        $this->execute('behat_theme_workplace_behat_navigation::i_navigate_to_in_workplace_launcher',
            get_string('exportimport', 'tool_wp'));
        $this->execute("behat_general::wait_until_the_page_is_ready");

        $this->execute("behat_forms::press_button", get_string('doexport', 'tool_wp'));
        $this->execute("behat_general::wait_until_the_page_is_ready");

        do {
            $header = $this->find('xpath', "//h2[contains(., 'Step')]")->getText();
            if (preg_match('/Step \d/', $header, $matches)) {
                if ($stepoptions = $this->get_step_options($options, $matches[0])) {
                    $this->execute('behat_tool_wp::i_set_the_following_visible_fields_to_these_values', $stepoptions);
                }

                $laststep = $matches[0] === 'Step 3';
                $this->execute("behat_forms::press_button",
                    $laststep ? get_string('doexport', 'tool_wp') : get_string('next'));
                $this->execute("behat_general::wait_until_the_page_is_ready");
                if ($laststep) {
                    $this->execute("behat_forms::press_button", get_string('proceed', 'tool_wp'));
                    $this->execute("behat_general::wait_until_the_page_is_ready");
                }
            } else {
                throw new \Behat\Mink\Exception\ExpectationException('Header does not contain step number',
                    $this->getSession());
            }
        } while (!$laststep);

        $this->execute('behat_general::i_run_all_adhoc_tasks');

        $this->execute('behat_general::i_click_on', [get_string('close', 'core_form'), 'link_or_button']);
    }

    /**
     * Import given entities
     *
     * @Given /^I perform a new import with these options:$/
     * @param TableNode $options Import options in the format: | Step | Option name | Option value |
     */
    public function i_perform_a_new_import_with_this_options($options) {
        $this->execute('behat_theme_workplace_behat_navigation::i_navigate_to_in_workplace_launcher',
            get_string('exportimport', 'tool_wp'));
        $this->execute("behat_general::wait_until_the_page_is_ready");

        // Switch to "Imports" tab.
        $this->execute("behat_general::click_link", get_string('imports', 'tool_wp'));
        $this->execute("behat_general::wait_until_the_page_is_ready");

        // Start new import.
        $this->execute("behat_forms::press_button", get_string('doimport', 'tool_wp'));
        $this->execute("behat_general::wait_until_the_page_is_ready");

        do {
            $header = $this->find('xpath', "//h2[contains(., 'Step')]")->getText();
            if (preg_match('/Step \d/', $header, $matches)) {
                if ($stepoptions = $this->get_step_options($options, $matches[0])) {
                    $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $stepoptions);
                }

                $laststep = $matches[0] === 'Step 5';
                $this->execute("behat_forms::press_button",
                    $laststep ? get_string('doimport', 'tool_wp') : get_string('next'));
                $this->execute("behat_general::wait_until_the_page_is_ready");
                if ($laststep) {
                    $this->execute("behat_forms::press_button", get_string('proceed', 'tool_wp'));
                    $this->execute("behat_general::wait_until_the_page_is_ready");
                }
            } else {
                throw new \Behat\Mink\Exception\ExpectationException('Header does not contain step number',
                    $this->getSession());
            }
        } while (!$laststep);

        // Import.
        $this->execute('behat_general::i_run_all_adhoc_tasks');

        $this->execute('behat_general::i_click_on', [get_string('close', 'core_form'), 'link_or_button']);
    }


    /**
     * Get the options specific to this step of the backup/restore process.
     *
     * @param TableNode $options The options table to filter
     * @param string $step The name of the step
     * @return TableNode The filtered options table
     * @throws ExpectationException
     */
    protected function get_step_options($options, $step): ?TableNode {
        // Nothing to fill if no options are provided.
        if (!$options) {
            return null;
        }

        $rows = $options->getRows();
        $newrows = array();
        foreach ($rows as $k => $data) {
            if (count($data) !== 3) {
                // Not enough information to guess the page.
                throw new ExpectationException("The export/import step must be specified for all options",
                    $this->getSession());
            } else if ($data[0] == $step) {
                $newrows[] = [$data[1], $data[2]];
            }
        }
        $pageoptions = new TableNode($newrows);

        return $pageoptions;
    }

    /**
     * Simulate site registration in the database.
     *
     * @Given /^the site is registered$/
     */
    public function the_site_is_registered() {
        global $DB;
        // Site is registered.
        $hub = new stdClass();
        $hub->token = get_site_identifier();
        $hub->secret = $hub->token;
        $hub->huburl = HUB_MOODLEORGHUBURL;
        $hub->hubname = 'moodle';
        $hub->confirmed = 1;
        $hub->timemodified = time();
        $DB->insert_record('registration_hubs', $hub);
    }
}
