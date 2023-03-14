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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * tool_wp steps definitions.
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for tool_wp.
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_wp extends behat_base {

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
            new behat_component_named_selector('Secondary tab', [
                <<<XPATH
    .//div[@aria-label=%locator% and @role='tabpanel']
XPATH
            ], true),
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
                    $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $stepoptions);
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

        $this->execute('behat_general::i_click_on_in_the',
            [get_string('close', 'core_form'), 'link_or_button', 'region-main', 'region']);
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

        $this->execute('behat_general::i_click_on_in_the',
            [get_string('close', 'core_form'), 'link_or_button', 'region-main', 'region']);
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

    /**
     * Check that plugin is installed.
     *
     * @Given /^the plugin "(?P<component_string>(?:[^"]|\\")*)" is installed$/
     * @param string $component the Moodle component.
     * @throws \Moodle\BehatExtension\Exception\SkippedException
     */
    public function the_plugin_is_installed($component) {
        $manager = \core_plugin_manager::instance();
        if ($manager->get_plugin_info($component) === null) {
            throw new \Moodle\BehatExtension\Exception\SkippedException();
        }
    }
}
