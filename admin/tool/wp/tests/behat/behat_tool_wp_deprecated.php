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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

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
     * Check that plugin is installed.
     *
     * @deprecated since Moodle Workplace 4.2
     *
     * @Given /^the plugin "(?P<component_string>(?:[^"]|\\")*)" is installed$/
     * @param string $component the Moodle component.
     * @throws \Moodle\BehatExtension\Exception\SkippedException
     */
    public function the_plugin_is_installed($component) {
        $this->deprecated_message([
            'behat_general::plugin_is_installed',
        ]);
        $this->execute('behat_general::plugin_is_installed', [$component]);
    }
}
