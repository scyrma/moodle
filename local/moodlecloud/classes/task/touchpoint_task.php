<?php declare(strict_types=1);
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
 * A scheduled task for sending  MoodleCloud touchpoints.
 *
 * @package   local_moodlecloud
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud\task;
defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\functions;
use local_moodlecloud\touchpoints\container;
use local_moodlecloud\touchpoints\touchpoint;

/**
 * MoodleCloud touchpoint task. Cleans up stale touchpoint records from
 * the databases, executes touchpoints as necessary, then queues the next
 * set of touchpoints.
 *
 * @copyright 2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class touchpoint_task extends \core\task\scheduled_task {

    public function get_name() : string {
        return get_string('touchpoint_task', 'local_moodlecloud');
    }

    public function execute() {
        global $CFG, $DB;

        // If the global killswitch is set to false we do nothing.
        if (isset($CFG->moodlecloud_touchpoints_enabled) && $CFG->moodlecloud_touchpoints_enabled == false) {
            return;
        }

        // Cleanup old records
        $sql = "DELETE FROM {moodlecloud_touchpoints} WHERE status = ?";
        $DB->execute($sql, [touchpoint::STATUS_SUCCESS]);

        // ⛈ The thunder.
        $container = new container((include $CFG->dirroot . '/local/moodlecloud/classes/touchpoints/config/DI.php'));
        $results = functions::pipe_forward(
            // Entry point: Get the touchpoint repository.
            $container->get('touchpoints.callables.getRepository'),
            // Get all the touchpoints.
            $container->get('touchpoints.callables.getTouchpoints'),
            // Filter out touchpoints that cannot be run (either it's too early or the criteria isn't met).
            $container->get('touchpoints.callables.filterRunnableTouchpoints'),
            // Execute the touchpoints.
            $container->get('touchpoints.callables.executeTouchpoints')(
                // If any one of the touchpoint actions failed, consider the whole thing a failure.
                function($results) {
                    return functions::failures($results) ? functions::failure($results) : functions::success($results);
                }
            ),
            // By now we have an array where the keys are the touchpoint name, and the values are a computation results
            // representing if the touchpoint succeeded or failed.
            // The wrapped value of these computation results is an array of computation results representing whether an
            // individual action succeeded or failed (and its wrapped value is the action itself).

            // Save the result of the touchpoints.
            $container->get('touchpoints.callables.persistTouchpointsFromComputationResult'),
            // Queue the next appropriate touchpoint.
            $container->get('touchpoints.callables.queueNext')
        );
    }
}
