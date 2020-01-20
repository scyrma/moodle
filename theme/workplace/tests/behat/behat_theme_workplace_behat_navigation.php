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
 * Navigation step definition overrides for the workplace theme.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: No MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/tests/behat/behat_navigation.php');

use Behat\Mink\Exception\ExpectationException as ExpectationException;
use Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

/**
 * Step definitions and overrides to navigate through the navigation tree nodes in the workplace theme.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_theme_workplace_behat_navigation extends behat_navigation {

    /**
     * Navigate to an item within the site administration menu.
     *
     * @throws ExpectationException
     * @param string $nodetext The navigation node/path to follow, excluding "Site administration" itself, eg "Grades > Scales"
     * @return void
     */
    public function i_navigate_to_in_site_administration($nodetext) {

        try {
            // Try site administration.
            parent::i_navigate_to_in_site_administration($nodetext);
        } catch (ElementNotFoundException $e) {
            // Try workplace menu.
            $nodelist = array_map('trim', explode('>', $nodetext));
            $this->i_navigate_to_in_workplace_launcher(end($nodelist));
        }

    }

    /**
     * Go to site administration item
     *
     * @Given /^I navigate to "(?P<nodetext_string>(?:[^"]|\\")*)" in workplace launcher$/
     *
     * @throws ExpectationException
     * @param string $nodetext navigation node to click
     * @return void
     */
    public function i_navigate_to_in_workplace_launcher($nodetext) {
        if ($nodetext === 'Manage custom reports') {
            $nodetext = 'Report builder';
        }

        $this->execute('behat_general::i_click_on', ['#workplace-menulink', 'css_element']);
        $this->execute('behat_general::i_click_on_in_the', [$nodetext, 'link', '.workplace-menu', 'css_element']);
    }

    /**
     * Click on an entry in the user menu.
     *
     * @param string $nodetext
     */
    public function i_follow_in_the_user_menu($nodetext) {
        try {
            parent::i_follow_in_the_user_menu($nodetext);
        } catch (ElementNotFoundException $ex) {
            // Locate user menu entry based on "original-title" data XPath.
            $xpath = "//a[@data-original-title='" . $this->escape($nodetext) . "']";
            $csspath = '.usermenu .dropdown-menu';

            $this->execute('behat_general::i_click_on_in_the', [$xpath, 'xpath_element', $csspath, 'css_element']);
        }
    }
}
