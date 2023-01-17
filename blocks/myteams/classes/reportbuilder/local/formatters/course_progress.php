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

declare(strict_types=1);

namespace block_myteams\reportbuilder\local\formatters;

use completion_info;
use core_user\output\status_field;
use html_writer;
use stdClass;

/**
 * Class course_progress formatter
 *
 * @package   block_myteams
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_progress {

    /**
     * Format of course status column
     *
     * @param bool|null $value
     * @param stdClass $course
     * @return string
     */
    public static function course_status(?bool $value, stdClass $course): string {
        if ($value === null) {
            return '';
        }

        $badgestatus = 'badge-info';
        $time = time();
        $statusstring = get_string('active');

        // If user has completed the course, status should be "Completed".
        if ($course->completed) {
            $badgestatus = 'badge-success';
            $statusstring = get_string('completed');
        }

        // If user course/enrolment start date is in the future, status should be "Future enrolment".
        if ($course->startdate > $time|| $course->timestart > $time) {
            $badgestatus = 'badge-secondary';
            $statusstring = get_string('futurestart',  'block_myteams');
        }

        // If user enrolment has been suspended, status should be "Suspended".
        if ($course->status == status_field::STATUS_SUSPENDED) {
            $badgestatus = 'badge-danger';
            $statusstring = get_string('suspended');
        }

        return html_writer::span($statusstring, 'badge badge-pill '.$badgestatus);
    }

    /**
     * Formats of course activity completion column
     *
     * @param string|null $value
     * @param stdClass $row
     * @return string
     */
    public static function course_activity_completion(?string $value, stdClass $row): string {
        if ($value === null) {
            return '';
        }

        // Set course/user ids and initialize activities completed counter.
        $activitiescompleted = 0;
        $courseid = (int) $row->courseid;
        $userid = (int) $row->userid;

        // Get course completion info.
        $completion = new completion_info(get_course($courseid));
        $courseactivities = $completion->get_activities();

        foreach ($courseactivities as $activity) {
            // If activity completion is enabled and user complete it, we need to increase counter.
            if ($completion->is_enabled($activity) &&
                    $completion->get_data($activity, true, $userid)->completionstate == COMPLETION_COMPLETE) {
                $activitiescompleted++;
            }
        }

        return $activitiescompleted.'/'.count($courseactivities);
    }
}
