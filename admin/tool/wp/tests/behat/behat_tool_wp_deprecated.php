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
 * Steps definitions for deprecated plugin Behat steps
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

require_once(__DIR__ . '/../../../../../lib/behat/behat_deprecated_base.php');

/**
 * Steps definitions for deprecated plugin Behat steps
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_tool_wp_deprecated extends behat_deprecated_base {

    /**
     * Click on the form button in the modal form.
     *
     * @When /^I press "(?P<button_string>(?:[^"]|\\")*)" in the modal form dialogue$/
     * @param string $buttontext
     *
     * @deprecated Since 3.11. Please use {@see behat_general::i_click_on_in_the}
     */
    public function i_press_in_the_modal_form_dialogue($buttontext) {
        $this->deprecated_message(
            'Use instead: And I click on "BUTTONNAME "button" in the "DIALOGUENAME" "dialogue"');

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
     * @param string $element
     * @param string $selector
     * @param string $treenode
     *
     * @deprecated since 3.10.2. Please use {@see behat_general::i_click_on_in_the}
     */
    public function i_click_on_in_the_table_tree_node($element, $selector, $treenode) {
        $this->deprecated_message(
            'Use instead: And I click on "ELEMENT "SELECTOR" in the "LOCATOR" "tool_wp > Table tree node"');

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
     * @param string $element
     * @param string $selector
     * @param string $treenode
     *
     * @deprecated since 3.10.2. Please use {@see behat_general::should_exist_in_the}
     */
    public function should_exist_in_the_table_tree_node($element, $selector, $treenode) {
        $this->deprecated_message(
            'Use instead: And "ELEMENT "SELECTOR" should exist in the "LOCATOR" "tool_wp > Table tree node"');

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
     * @param string $element
     * @param string $selector
     * @param string $treenode
     *
     * @deprecated since 3.10.2. Please use {@see behat_general::should_not_exist_in_the}
     */
    public function should_not_exist_in_the_table_tree_node($element, $selector, $treenode) {
        $this->deprecated_message(
            'Use instead: And "ELEMENT "SELECTOR" should not exist in the "LOCATOR" "tool_wp > Table tree node"');

        $xpathtarget = "//div[contains(concat(' ', @class, ' '), ' tool-wp-node-name ') and contains(.,'" .
            $this->escape($treenode) . "')]";

        $this->execute('behat_general::should_not_exist_in_the',
            [$this->escape($element), $selector, $xpathtarget, 'xpath_element']);
    }

    /**
     * Open the auto-complete suggestions list (Assuming there is only one on the page.).
     *
     * @Given /^I open the autocomplete suggestions list in the dialog$/
     *
     * @deprecated since 3.10.3, 3.11. Please use {@see behat_general::i_click_on_in_the}
     */
    public function i_open_the_autocomplete_suggestions_list_in_the_dialog(): void {
        $this->deprecated_message(
            'Use instead: And I open the autocomplete suggestions list in the "TITLE" "dialogue"');

        $csselement = '.form-autocomplete-downarrow';
        $nodeelement = '.modal-dialog';
        $this->execute('behat_general::i_click_on_in_the', [$csselement, 'css_element', $nodeelement, 'css_element']);
    }

    /**
     * Sets the field in the specified container
     *
     * @Given /^I set the visible field "(?P<field_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)"$/
     *
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $field
     * @param string $value
     *
     * @deprecated since 3.10.3, 3.11. Please use {@see behat_forms::i_set_the_field_in_container_to}
     */
    public function i_set_the_visible_field_to($field, $value): void {
        $this->deprecated_message(
            'Use instead: And I set the field "FIELD" in the "ELEMENT" "SELECTOR" to "VALUE"');

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
     * @Given /^I set the following visible fields to these values:$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param TableNode $data
     *
     * @deprecated since 3.10.3, 3.11. Please use {@see behat_forms::i_set_the_following_fields_in_container_to_these_values}
     */
    public function i_set_the_following_visible_fields_to_these_values(TableNode $data) {
        $this->deprecated_message(
            'Use instead: And I set the following fields in the "ELEMENT" "SELECTOR" to these values:');

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
}
