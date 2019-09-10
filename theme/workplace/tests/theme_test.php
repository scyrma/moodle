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
 * Tests for theme_workplace.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for theme_workplace
 *
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_workplace_theme_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test for theme_workplace_page_init().
     */
    public function test_page_init() {
        $this->resetAfterTest();

        // We need a $USER->id for the programs overview.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $wp = new \theme_workplace\workplace();
        $this->assertTrue(is_object($wp->dashboard()));
    }

    /**
     * Make sure that there are exactly the same overrides as in the boost theme
     *
     * All exceptions have to be added to this test
     */
    public function test_behat_overrides() {
        global $CFG;
        $extrawpfiles = ['navigation'];
        $extraboostfiles = [];

        $wpdir = $CFG->dirroot.'/theme/workplace/tests/behat';
        $boostdir = $CFG->dirroot.'/theme/boost/tests/behat';

        $wpfiles = get_directory_list($wpdir, '', false);
        $boostfiles = get_directory_list($boostdir, '', false);
        foreach ($wpfiles as $file) {
            if (preg_match('/^behat_theme_workplace_behat_(.*)\\.php$/', $file, $matches)
                    && !in_array($matches[1], $extrawpfiles)) {
                $this->assertTrue(in_array("behat_theme_boost_behat_{$matches[1]}.php", $boostfiles),
                    "Override file for {$matches[1]} is present in workplace but abscent in boost, ' .
                    'maybe it needs to be added to \$extrawpfiles ?");
                require_once($wpdir . '/' . $file);
                $classname = pathinfo($file, PATHINFO_FILENAME);
                new $classname();
                $this->assertTrue(is_subclass_of($classname, "behat_theme_boost_behat_{$matches[1]}"));
            }
        }

        foreach ($boostfiles as $file) {
            if (preg_match('/^behat_theme_boost_behat_(.*)\\.php$/', $file, $matches)
                    && !in_array($matches[1], $extraboostfiles)) {
                $this->assertTrue(in_array("behat_theme_workplace_behat_{$matches[1]}.php", $wpfiles),
                    "Override file for {$matches[1]} is present in boost but abscent in workplace");
            }
        }
    }
}
