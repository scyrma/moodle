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
 * Class behat_format_wplist
 *
 * @package     format_wplist
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
     * Gets the current course format.
     *
     * @return string The course format in a frankenstyled name.
     */
    protected function get_course_format() {
        return 'format_wplist';
    }

    /**
     * Returns the DOM node of the activity from <li>.
     *
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $activityname The activity name
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function get_activity_node($activityname) {

        $activityname = behat_context_helper::escape($activityname);
        $xpath = "//div[@data-region='module'][contains(., $activityname)]";

        return $this->find('xpath', $xpath);
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
     * Hides the specified visible section. You need to be in the course page and on editing mode.
     *
     * @Given /^I hide wplist section "(?P<section_number>\d+)"$/
     * @param int $sectionnumber
     */
    public function i_hide_wplist_section($sectionnumber) {
        // Ensures the section exists.
        $xpath = $this->section_exists($sectionnumber);

        // We need to know the course format as the text strings depends on them.
        $courseformat = $this->get_course_format();
        if (get_string_manager()->string_exists('hidefromothers', $courseformat)) {
            $strhide = get_string('hidefromothers', $courseformat);
        } else {
            $strhide = get_string('hidesection');
        }

        // If javascript is on, link is inside a menu.
        if ($this->running_javascript()) {
            $this->i_open_wplist_section_edit_menu($sectionnumber);
        }

        // Click on delete link.
        $xpath .= "/descendant::div[contains(@class, 'section-actions')]";
        $this->execute('behat_general::i_click_on_in_the',
            array($strhide, "link", $this->escape($xpath), "xpath_element")
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

    /**
     * Open the availability popup for the seciton
     *
     * @Given /^I open availability popup for wplist section "(?P<section_number>\d+)"$/
     * @param int $sectionnumber The section number
     */
    public function i_open_availability_popup_for_wplist_section($sectionnumber) {
        // Ensures the section exists.
        $xpath = $this->section_exists($sectionnumber);

        // Click on expand link.
        $this->execute('behat_general::i_click_on_in_the',
            array('.availability a', "css_element", $xpath, "xpath_element")
        );

    }

    /**
     * Checks that the specified activity's action menu is open.
     *
     * @Then /^wplist activity "(?P<activity_name_string>(?:[^"]|\\")*)" actions menu should be open$/
     * @throws DriverException The step is not available when Javascript is disabled
     * @param string $activityname
     */
    public function wplist_activity_actions_menu_should_be_open($activityname) {

        if (!$this->running_javascript()) {
            throw new DriverException('Activities actions menu not available when Javascript is disabled');
        }

        $activitynode = $this->get_activity_node($activityname);
        // Find the menu.
        $menunode = $activitynode->find('css', 'a[data-toggle=dropdown]');
        if (!$menunode) {
            throw new ExpectationException(sprintf('Could not find actions menu for the activity "%s"', $activityname),
                $this->getSession());
        }
        $expanded = $menunode->getAttribute('aria-expanded');
        if ($expanded != 'true') {
            throw new ExpectationException(sprintf("The action menu for '%s' is not open", $activityname), $this->getSession());
        }
    }

    /**
     * Opens an activity actions menu if it is not already opened.
     *
     * @Given /^I open wplist activity "(?P<activity_name_string>(?:[^"]|\\")*)" actions menu$/
     * @throws DriverException The step is not available when Javascript is disabled
     * @param string $activityname
     */
    public function i_open_wplist_activity_actions_menu($activityname) {

        if (!$this->running_javascript()) {
            throw new DriverException('Activities actions menu not available when Javascript is disabled');
        }

        $this->execute('behat_format_wplist::i_click_on_in_the_wplist_activity',
            array("a[data-toggle='dropdown']", "css_element", $this->escape($activityname))
        );

        $this->wplist_activity_actions_menu_should_be_open($activityname);
    }

    /**
     * Checks that the specified activity's action menu contains an item.
     *
     * @codingStandardsIgnoreLine
     * @Then /^wplist activity "(?P<activity_name_string>(?:[^"]|\\")*)" actions menu should have "(?P<menu_item_string>(?:[^"]|\\")*)" item$/
     * @throws DriverException The step is not available when Javascript is disabled
     * @param string $activityname
     * @param string $menuitem
     */
    public function wplist_activity_actions_menu_should_have_item($activityname, $menuitem) {
        $activitynode = $this->get_activity_node($activityname);

        $notfoundexception = new ExpectationException('"' . $activityname . '" doesn\'t have a "' .
            $menuitem . '" item', $this->getSession());
        $this->find('named_partial', array('link', $menuitem), $notfoundexception, $activitynode);
    }

    /**
     * Checks that the specified activity's action menu does not contains an item.
     *
     * @codingStandardsIgnoreLine
     * @Then /^wplist activity "(?P<activity_name_string>(?:[^"]|\\")*)" actions menu should not have "(?P<menu_item_string>(?:[^"]|\\")*)" item$/
     * @throws DriverException The step is not available when Javascript is disabled
     * @param string $activityname
     * @param string $menuitem
     */
    public function wplist_activity_actions_menu_should_not_have_item($activityname, $menuitem) {
        $activitynode = $this->get_activity_node($activityname);

        try {
            $this->find('named_partial', array('link', $menuitem), false, $activitynode);
            throw new ExpectationException('"' . $activityname . '" has a "' . $menuitem .
                '" item when it should not', $this->getSession());
        } catch (ElementNotFoundException $e) {
            // This is good, the menu item should not be there.
            null;
        }
    }

    /**
     * Clicks on the specified element inside the activity container.
     *
     * @throws ElementNotFoundException
     * @param string $element
     * @param string $selectortype
     * @param string $activityname
     * @return \Behat\Mink\Element\NodeElement
     */
    protected function get_activity_element($element, $selectortype, $activityname) {
        $activitynode = $this->get_activity_node($activityname);

        $exception = new ElementNotFoundException($this->getSession(), "'{$element}' '{$selectortype}' in '${activityname}'");
        return $this->find($selectortype, $element, $exception, $activitynode);
    }

    /**
     * Clicks on the specified element of the activity. You should be in the course page with editing mode turned on.
     *
     * @codingStandardsIgnoreLine
     * @Given /^I click on "(?P<element_string>(?:[^"]|\\")*)" "(?P<selector_string>(?:[^"]|\\")*)" in the "(?P<activity_name_string>(?:[^"]|\\")*)" wplist activity$/
     * @param string $element
     * @param string $selectortype
     * @param string $activityname
     */
    public function i_click_on_in_the_wplist_activity($element, $selectortype, $activityname) {
        $element = $this->get_activity_element($element, $selectortype, $activityname);
        $element->click();
    }

    /**
     * Returns whether the user can edit the course contents or not.
     *
     * @return bool
     */
    protected function is_course_editor() {

        // We don't need to behat_base::spin() here as all is already loaded.
        if (!$this->getSession()->getPage()->findButton(get_string('turneditingoff')) &&
            !$this->getSession()->getPage()->findButton(get_string('turneditingon'))) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether the user can edit the course contents and the editing mode is on.
     *
     * @return bool
     */
    protected function is_editing_on() {
        return $this->getSession()->getPage()->findButton(get_string('turneditingoff')) ? true : false;
    }

    /**
     * Checks that the specified activity is hidden. You need to be in the course page.
     *
     * It can be used being logged as a student and as a teacher on editing mode.
     *
     * @Then /^"(?P<activity_or_resource_string>(?:[^"]|\\")*)" wplist activity should be hidden$/
     * @param string $activityname
     * @throws ExpectationException
     */
    public function wplist_activity_should_be_hidden($activityname) {

        if ($this->is_course_editor()) {

            // The activity should exist.
            $activitynode = $this->get_activity_node($activityname);

            // Should be hidden.
            $exception = new ExpectationException('"' . $activityname . '" is not dimmed', $this->getSession());
            $xpath = "/descendant-or-self::a[contains(concat(' ', normalize-space(@class), ' '), ' dimmed ')] | ".
                "/descendant-or-self::div[contains(concat(' ', normalize-space(@class), ' '), ' dimmed_text ')]";
            $this->find('xpath', $xpath, $exception, $activitynode);

            // Additional check if this is a teacher in editing mode.
            if ($this->is_editing_on()) {
                // Also has either 'Show' or 'Make available' edit control.
                $noshowexception = new ExpectationException('"' . $activityname . '" has neither "' . get_string('show') .
                    '" nor "' . get_string('makeavailable') . '" icons', $this->getSession());
                try {
                    $this->find('named_partial', array('link', get_string('show')), false, $activitynode);
                } catch (ElementNotFoundException $e) {
                    $this->find('named_partial', array('link', get_string('makeavailable')), $noshowexception, $activitynode);
                }
            }

        } else {

            // It should not exist at all.
            try {
                $this->get_activity_node($activityname);
                throw new ExpectationException('The "' . $activityname . '" should not appear', $this->getSession());
            } catch (ElementNotFoundException $e) {
                // This is good, the activity should not be there.
                null;
            }
        }

    }

    /**
     * Checks that the specified activity is visible. You need to be in the course page.
     * It can be used being logged as a student and as a teacher on editing mode.
     *
     * @Then /^"(?P<activity_or_resource_string>(?:[^"]|\\")*)" wplist activity should be available but hidden from course page$/
     * @param string $activityname
     * @throws ExpectationException
     */
    public function wplist_activity_should_be_available_but_hidden_from_course_page($activityname) {

        if ($this->is_course_editor()) {

            // The activity must exists and be visible.
            $activitynode = $this->get_activity_node($activityname);

            // The activity should not be dimmed.
            try {
                $xpath = "/descendant-or-self::a[contains(concat(' ', normalize-space(@class), ' '), ' dimmed ')] | " .
                    "/descendant-or-self::div[contains(concat(' ', normalize-space(@class), ' '), ' dimmed_text ')]";
                $this->find('xpath', $xpath, false, $activitynode);
                throw new ExpectationException('"' . $activityname . '" is hidden', $this->getSession());
            } catch (ElementNotFoundException $e) {
                // All ok.
                null;
            }

            // Should has "stealth" class.
            $exception = new ExpectationException('"' . $activityname . '" does not have CSS class "stealth"', $this->getSession());
            $xpath = "/descendant-or-self::a[contains(concat(' ', normalize-space(@class), ' '), ' stealth ')]";
            $this->find('xpath', $xpath, $exception, $activitynode);

            // Additional check if this is a teacher in editing mode.
            if ($this->is_editing_on()) {
                // Also has either 'Hide' or 'Make unavailable' edit control.
                $nohideexception = new ExpectationException('"' . $activityname . '" has neither "' . get_string('hide') .
                    '" nor "' . get_string('makeunavailable') . '" icons', $this->getSession());
                try {
                    $this->find('named_partial', array('link', get_string('hide')), false, $activitynode);
                } catch (ElementNotFoundException $e) {
                    $this->find('named_partial', array('link', get_string('makeunavailable')), $nohideexception, $activitynode);
                }
            }

        } else {

            // Student should not see the activity at all.
            try {
                $this->get_activity_node($activityname);
                throw new ExpectationException('The "' . $activityname . '" should not appear', $this->getSession());
            } catch (ElementNotFoundException $e) {
                // This is good, the activity should not be there.
                null;
            }
        }
    }

    /**
     * Open the availability popup for the seciton
     *
     * @Given /^I open availability popup for wplist activity "(?P<activity_or_resource_string>(?:[^"]|\\")*)"$/
     * @param string $activityname
     */
    public function i_open_availability_popup_for_wplist_activity($activityname) {
        $this->i_click_on_in_the_wplist_activity('.availability a', "css_element", $activityname);
    }

}
