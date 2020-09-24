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
 * Class for block_myoverview
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_myoverview
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Mikel Martín <mikel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class block_myoverview {

    /**
     * Callback for block_myoverview that returns the block content.
     *
     * @return \stdClass|false contents of block or false to fall back to the default content
     */
    public static function content_hook() {
        // @codingStandardsIgnoreLine
        global $PAGE;

        if (defined('BEHAT_SITE_RUNNING') || (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            // No modifications for the behat/unittests, otherwise existing core tests will fail.
            return false;
        }

        if (!WS_SERVER && get_config('tool_wp', 'myoverviewdisplayonmobileonly')) {
            // @codingStandardsIgnoreLine
            if ($PAGE->user_is_editing() && $PAGE->user_can_edit_blocks()) {
                // Show an alert if not a webservice, displayonmobileonly setting is enabled and user is editing the page.
                $content = new \stdClass();
                $content->footer = '';
                $content->text = \html_writer::div(get_string('blockmyoverviewmobileonlyalert', 'tool_wp'),
                    'alert alert-warning');
                return $content;
            } else {
                // Return empty content if not a WS, displayonmobileonly setting is enabled and user is not editing the page.
                return (object)[];
            }
        }
        return false;
    }
}
