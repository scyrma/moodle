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

namespace tool_reportbuilder\task;

use core\task\adhoc_task;
use tool_reportbuilder\{datasource, manager};
use core_reportbuilder\permission;

/**
 * Ad-hoc task for convertig all possible reports from tool_reportbuilder to core_reportbuilder.
 *
 * @package     tool_reportbuilder
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Roberto Bravo <roberto.bravo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class convert_all_possible_reports extends adhoc_task {

    /**
     * Execute the task
     */
    public function execute(): void {
        global $DB;

        // Get all datasource report ids.
        $datasourcereportids = $DB->get_fieldset_select('tool_reportbuilder', 'id', 'type = ?',
            ['type' => \tool_reportbuilder\constants::TYPE_DATASOURCE]);

        foreach ($datasourcereportids as $reportid) {

            // Verify that user can create new reports and
            // check if $CFG->enablecustomreports is set and the site/tenant limits are reached.
            if (!permission::can_create_report()) {
                break;
            }

            // This task does not need to throw exception or notify about the reports that were not converted.
            try {
                /** @var datasource $report */
                $report = manager::get_report($reportid);
                $report->convert();
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}
