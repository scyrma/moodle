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

namespace tool_wp\task;

use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;

/**
 * Class cleanup_exports_imports_scheduled_task
 *
 * @package   tool_wp
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cleanup_exports_imports_scheduled_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanupexpiredimportsexports', 'tool_wp');
    }

    /**
     * Do the job.
     */
    public function execute() {
        // Clean up abandoned imports after 24h with the status CREATED.
        helper::cleanup_abandoned_imports();

        // Clean up imports and exports if max number of days has been reached.
        helper::cleanup_expired_exports_imports();
    }
}
