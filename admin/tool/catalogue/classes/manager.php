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

namespace tool_catalogue;

use completion_info;
use context_header;
use html_writer;
use stdClass;
use tool_certification\api as certificationapi;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_program\api;
use tool_program\permission as program_permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_tenant\hierarchy;

/**
 * Manager class for tool_catalogue
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {

    /**
     * Returns a list of accessible programs for the current user.
     *
     * Uses can_view_program() which does no take into consideration program tenants.
     *
     * @return program[]
     */
    public static function get_user_accessible_programs(): array {
        global $DB, $USER;

        $programs = [];
        $userid = (int) $USER->id;

        [$tenantsql, $tenantparams] = hierarchy::filter_own_or_parent_shared_entities_sql("tp.tenantid",
            "tp.shared=1");

        $sql = "
            SELECT tp.*
              FROM {tool_program} tp
             WHERE id IN (
                SELECT tpu.programid
                  FROM {tool_program_users} tpu
                 WHERE tpu.userid = :userid AND $tenantsql
            )
        ";

        $records = $DB->get_records_sql($sql, ['userid' => $userid] + $tenantparams);
        foreach ($records as $record) {
            $program = new program(0, $record);
            if (program_permission::can_view_program($program, $userid)) {
                $programs[$record->id] = $program;
            }
        }

        return $programs;
    }

    /**
     * Returns current user program allocations
     *
     * Note: Does not return allocations where the certification is archived.
     *
     * @param int $programid
     * @return array program_user[]
     */
    public static function get_user_allocations(int $programid = 0): array {
        global $USER, $DB;
        $allocations = [];

        $sql = "
            SELECT tpu.*
              FROM {tool_program_users} tpu
             WHERE (tpu.certificationid = 0 OR tpu.certificationid IN (
                SELECT tc.id
                  FROM {tool_certification} tc
                 WHERE tc.archived = 0
            ))
            AND tpu.userid = :userid
        ";
        $params = ['userid' => $USER->id];

        // Filter by program id.
        if ($programid > 0) {
            $sql .= ' AND tpu.programid = :programid';
            $params += ['programid' => $programid];
        }

        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            $allocations[] = new program_user(0, $record);
        }
        return $allocations;
    }

    /**
     * Returns a list of accessible courses for the given user excluding courses enrolled only with the enrol program plugin.
     *
     * @return stdClass[]
     */
    public static function get_user_accessible_courses(): array {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/course/lib.php');

        $userid = (int) $USER->id;
        $hiddencourses = get_hidden_courses_on_timeline();
        $workplaceexcludedcourses = \tool_program\api::get_course_ids_with_only_enrol_program_instance($userid);
        $hiddencourses = array_unique(array_merge($hiddencourses, $workplaceexcludedcourses));
        return enrol_get_my_courses('summary, summaryformat, enddate', null, 0, [], false, 0, $hiddencourses);
    }

    /**
     * Get courses completions for a given user
     *
     * @param stdClass[] $courses
     * @param int $userid
     * @return array
     */
    public static function get_user_courses_completion(array $courses, int $userid): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $courseids = array_map(static function($course) {
            return $course->id;
        }, $courses);

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid', true, 0);

        $sql = "
            SELECT course, timecompleted
              FROM {course_completions}
             WHERE course $insql AND userid = :userid
        ";
        $params = $inparams + ['userid' => $userid];
        $completions = $DB->get_records_sql($sql, $params);
        $coursecompletions = [];
        foreach ($courses as $course) {
            $completion = new completion_info($course);
            $coursecompletions[$course->id] = (object) [
                'timecompleted' => $completions[$course->id]->timecompleted ?? null,
                'completionenabled' => $completion->is_enabled(),
            ];
        }
        return $coursecompletions;
    }

    /**
     * Gets certifications by user id
     *
     * @param int $userid
     * @return certification[]
     */
    public static function get_certifications_by_userid(int $userid): array {
        global $DB;

        $certifications = [];
        $sql = "
            SELECT tc.*
              FROM {tool_certification} tc
             WHERE tc.id IN (
                SELECT tcu.certificationid
                  FROM {tool_certification_users} tcu
                 WHERE tcu.userid = :userid
            ) AND tc.archived = 0
        ";
        $records = $DB->get_records_sql($sql, ['userid' => $userid]);
        foreach ($records as $record) {
            $certifications[$record->id] = new certification(0, $record);
        }

        return $certifications;
    }

    /**
     * Get last access timestamp for the user for multiple courses at once
     *
     * @param int $userid
     * @param array $courseids
     * @return array
     */
    public static function get_last_course_access(int $userid, array $courseids): array {
        global $DB;

        if (!$courseids) {
            return [];
        }

        $courseids = array_unique($courseids);
        [$sql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
        return $DB->get_records_select('user_lastaccess', 'userid = :user AND courseid ' . $sql,
            $params + ['user' => $userid], '', 'courseid, timeaccess');
    }

    /**
     * Get certification allocation status.
     *
     * @param array $allocations
     * @param int $userid
     * @return array
     */
    public static function get_certification_allocations_status(array $allocations, int $userid): array {
        $status = [];

        foreach ($allocations as $allocation) {
            $allocationid = $allocation->get('id');
            $certificationid = $allocation->get('certificationid');
            if ($certificationid === 0) {
                continue;
            }
            $statuses = certificationapi::get_user_allocation_status($certificationid, $userid);
            $status[$allocationid] = $statuses[0]['statusint'] ?? -1;
        }

        return $status;
    }

    /**
     * Get certifications completion.
     *
     * @param certification[] $certifications
     * @param int $userid
     * @return array
     */
    public static function get_certifications_completion(array $certifications, int $userid): array {
        $completions = [];
        foreach ($certifications as $certification) {
            $certificationid = $certification->get('id');
            $params = ['userid' => $userid, 'certificationid' => $certificationid, 'timerevoked' => 0, 'islast' => 1];
            $completion = certification_completion::get_record($params);
            if ($completion) {
                $completions[$certificationid] = $completion;
            }
        }
        return $completions;
    }

    /**
     * Gets the context header and adds program and course images
     *
     * Used using component_class_callback in {@see core_renderer::context_header()}.
     *
     * @return context_header The context header.
     */
    public static function get_context_header(): ?context_header {
        global $PAGE, $OUTPUT;

        // Only add images when we are on the /my/courses.php subpages.
        if ($PAGE->bodyid != 'page-my-index') {
            return null;
        }

        $imagedata = null;
        $params = router::get_mycourses_url_params();
        if (!empty($params['program'])) {
            // Display program or set information.
            $program = new program($params['program']);
            $showprogrampreference = get_user_preferences('tool_catalogue_show_program_content_' . $program->get('id'), false);
            if (permission::can_view_program_cover($program) && !$showprogrampreference) {
                $image = $program->get_image_url();
                if (!$image) {
                    $image = api::get_program_pattern($program->get('id'));
                }
                $imagedata = html_writer::tag('div', '', ['class' => 'rounded infoprogramimage',
                    'style' => 'background-image: url(' . $image . ')']);
            }
        } else if (!empty($params['course'])) {
            $course = get_course($params['course']);
            $image = \core_course\external\course_summary_exporter::get_course_image($course);
            if (!$image) {
                $image = $OUTPUT->get_generated_image_for_id($course->id);
            }
            $imagedata = html_writer::tag('div', '', ['class' => 'rounded infoprogramimage',
                        'style' => 'background-image: url(' . $image . ')']);
        }
        return new context_header($PAGE->heading, 1, $imagedata, null, null);
    }
}
