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
 * This file defines an adhoc task to process a single rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Define adhoc task to process a single rule.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Ruslan Kabalin
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class process_rule extends \core\task\adhoc_task {

    /**
     * Process single rule.
     */
    public function execute() {
        $ruleid = $this->get_custom_data();
        try {
            $rule = \tool_dynamicrule\api::get_rule($ruleid, true);
        } catch (\moodle_exception $e) {
            // Rule not found. Probably was already deleted before cron ran.
            return;
        }
        mtrace("Processing dynamic rule with id " . $rule->get('id'));
        \tool_dynamicrule\api::process_rule($rule);
    }
}
