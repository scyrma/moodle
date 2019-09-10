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
 * Class behat_format_wplist
 *
 * @package     format_wplist
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException,
    Behat\Mink\Exception\DriverException as DriverException,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

/**
 * Class behat_format_wplist
 *
 * @package     format_wplist
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_wplist extends behat_base {

    /**
     * Get the section CSS selector
     * @param  int $sectionnumber Section number.
     * @return string The section css selector.
     */
    protected function get_section_css_selector(int $sectionnumber) {
        return 'div[data-region=sectionnumber][data-section=' . $sectionnumber . ']';
    }

    /**
     * Checks if the course section exists.
     *
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param int $sectionnumber
     * @return string The xpath of the section.
     */
    protected function section_exists($sectionnumber) {

        // Just to give more info in case it does not exist.
        $xpath = "//div[@data-region='section' and @data-sectionnumber='" . $sectionnumber . "']";
        $exception = new ElementNotFoundException($this->getSession(), "Section $sectionnumber with xpath $xpath ");
        $this->find('xpath', $xpath, $exception);

        return $xpath;
    }

    /**
     * Waits until the section is available to interact with it.
     * Useful when the section is performing an action and the section
     * is overlayed with a loading layout.
     *
     * Using the protected method as this method will be usually
     * called by other methods which are not returning a set of
     * steps and performs the actions directly, so it would not
     * be executed if it returns another step.
     *
     * Hopefully we would not require test writers to use this step
     * and we will manage it from other step definitions.
     *
     * @Given /^I wait until wplist section "(?P<section_number>\d+)" is available$/
     * @param int $sectionnumber
     * @return void
     */
    public function i_wait_until_wplist_section_is_available($sectionnumber) {

        // Looks for a hidden lightbox or a non-existent lightbox in that section.
        $sectionxpath = $this->section_exists($sectionnumber);
        $hiddenlightboxxpath = $sectionxpath . "/descendant::div[contains(concat(' ', @class, ' ')," .
            " ' lightbox ')][contains(@style, 'display: none')] | " .
            $sectionxpath . "[count(child::div[contains(@class, 'lightbox')]) = 0]";

        $this->ensure_element_exists($hiddenlightboxxpath, 'xpath_element');
    }

    /**
     * Opens a section edit menu if it is not already opened.
     *
     * @Given /^I open wplist section "(?P<section_number>\d+)" edit menu$/
     * @throws DriverException The step is not available when Javascript is disabled
     * @param string $sectionnumber
     */
    public function i_open_wplist_section_edit_menu($sectionnumber) {
        if (!$this->running_javascript()) {
            throw new DriverException('Section edit menu not available when Javascript is disabled');
        }

        // Wait for section to be available, before clicking on the menu.
        $this->i_wait_until_wplist_section_is_available($sectionnumber);

        // If it is already opened we do nothing.
        $xpath = $this->section_exists($sectionnumber);
        $xpath .= "/descendant::div[contains(@class, 'section-actions')]/descendant::a[contains(@class, 'dropdown-toggle')]";

        $exception = new ExpectationException('Section actions menu for section "' .
            $sectionnumber . '" was not found', $this->getSession());
        $menu = $this->find('xpath', $xpath, $exception);
        $menu->click();
        $this->i_wait_until_wplist_section_is_available($sectionnumber);
    }

    /**
     * Go to editing section page for specified section number. You need to be in the course page and on editing mode.
     *
     * @Given /^I edit the wplist section "(?P<section_number>\d+)"$/
     * @param int $sectionnumber
     */
    public function i_edit_the_wplist_section($sectionnumber) {
        // If javascript is on, link is inside a menu.
        if ($this->running_javascript()) {
            $this->i_open_wplist_section_edit_menu($sectionnumber);
        }

        // We need to know the course format as the text strings depends on them.
        $courseformat = 'format_wplist';
        if ($sectionnumber > 0 && get_string_manager()->string_exists('editsection', $courseformat)) {
            $stredit = get_string('editsection', $courseformat);
        } else {
            $stredit = get_string('editsection');
        }

        // Click on un-highlight topic link.
        $xpath = $this->section_exists($sectionnumber) . "/descendant::div[contains(@class, 'section-actions')]";
        $this->execute('behat_general::i_click_on_in_the',
            array($stredit, "link", $xpath, "xpath_element")
        );

    }

    /**
     * Edit specified section and fill the form data with the specified field/value pairs.
     *
     * @When /^I edit the wplist section "(?P<section_number>\d+)" and I fill the form with:$/
     * @param int $sectionnumber The section number
     * @param TableNode $data The activity field/value data
     */
    public function i_edit_the_wplist_section_and_i_fill_the_form_with($sectionnumber, TableNode $data) {

        // Edit given section.
        $this->execute("behat_format_wplist::i_edit_the_wplist_section", $sectionnumber);

        // Set form fields.
        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $data);

        // Save section settings.
        $this->execute("behat_forms::press_button", get_string('savechanges'));
    }

    /**
     * Deletes course section.
     *
     * @Given /^I delete wplist section "(?P<section_number>\d+)"$/
     * @param int $sectionnumber The section number
     */
    public function i_delete_wplist_section($sectionnumber) {
        // Ensures the section exists.
        $xpath = $this->section_exists($sectionnumber);

        // We need to know the course format as the text strings depends on them.
        $courseformat = "format_wplist";
        if (get_string_manager()->string_exists('deletesection', $courseformat)) {
            $strdelete = get_string('deletesection', $courseformat);
        } else {
            $strdelete = get_string('deletesection');
        }

        // If javascript is on, link is inside a menu.
        if ($this->running_javascript()) {
            $this->i_open_wplist_section_edit_menu($sectionnumber);
        }

        // Click on delete link.
        $xpath .= "/descendant::div[contains(@class, 'section-actions')]";
        $this->execute('behat_general::i_click_on_in_the',
            array($strdelete, "link", $xpath, "xpath_element")
        );
    }

    /**
     * Expands course section.
     *
     * @Given /^I expand wplist section "(?P<section_number>\d+)"$/
     * @param int $sectionnumber The section number
     */
    public function i_expand_wplist_section($sectionnumber) {
        // Ensures the section exists.
        $xpath = $this->section_exists($sectionnumber);

        // Click on expand link.
        $this->execute('behat_general::i_click_on_in_the',
            array('button.course-section-toggle', "css_element", $xpath, "xpath_element")
        );
    }

}
