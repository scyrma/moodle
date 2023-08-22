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
 * Reportbuilder step definition overrides for the workplace theme.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

// NOTE: No MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../reportbuilder/tests/behat/behat_reportbuilder.php');

/**
 * Step definitions and overrides for reportbuilder in the workplace theme.
 *
 * @package    theme_workplace
 * @category   test
 * @copyright  2023 Moodle Pty Ltd <support@moodle.com>
 * @author     2023 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class behat_theme_workplace_behat_reportbuilder extends behat_reportbuilder {

    /**
     * Press a given action from the action menu in a given report row
     *
     * @param string $action
     * @param string $row
     */
    public function i_press_action_in_the_report_row(string $action, string $row): void {
        $this->execute('behat_action_menu::i_open_the_action_menu_in', [$this->escape($row), 'table_row']);
        $this->execute('behat_action_menu::i_choose_in_the_open_action_menu', [$this->escape($action)]);
    }
}
