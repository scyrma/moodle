<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * tool_wp steps definitions.
 *
 * @package    tool_wp
 * @category   test
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * @Given /^I click on "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
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
     * @Then /^"(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" should exist in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
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
     * @Then /^"(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>[^"]*)" should not exist in the "(?P<tree_node_string>(?:[^"]|\\")*)" table tree node$/
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
     * @Given /^I open the autocomplete suggestions list in the dialog$/
     */
    public function i_open_the_autocomplete_suggestions_list_in_the_dialog(): void {
        $csselement = '.form-autocomplete-downarrow';
        $nodeelement = '.modal-dialog';
        $this->execute('behat_general::i_click_on_in_the', [$csselement, 'css_element', $nodeelement, 'css_element']);
    }

    /**
     * Open the auto-complete suggestions list in a container.
     *
     * @Given /^I open the autocomplete suggestions list in the "(?P<element2_string>(?:[^"]|\\")*)" "(?P<selector2_string>[^"]*)"$/
     * @param string $containerelement
     * @param string $containerselectortype
     */
    public function i_open_the_autocomplete_suggestions_list_in_the($containerelement, $containerselectortype): void {
        $csselement = '.form-autocomplete-downarrow';
        $this->execute('behat_general::i_click_on_in_the', [$csselement, 'css_element', $containerelement, $containerselectortype]);
    }

    /**
     * Sets the field in the specified container
     *
     * @Given /^I set the visible field "(?P<field_string>(?:[^"]|\\")*)" to "(?P<field_value_string>(?:[^"]|\\")*)"$/
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
}
