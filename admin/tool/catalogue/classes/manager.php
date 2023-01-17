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
use context_course;
use context_header;
use html_writer;
use stdClass;
use tool_catalogue\output\course_cover_modal;
use tool_certification\api as certificationapi;
use tool_certification\certification;
use tool_certification\certification_completion;
use tool_program\api;
use tool_program\permission as program_permission;
use tool_program\persistent\program;
use tool_program\persistent\program_user;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

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
     * Returns a list of accessible programs for the given user
     *
     * Uses can_view_program() which does no take into consideration program tenants.
     *
     * @param int $userid
     * @return program[]
     */
    public static function get_user_accessible_programs(int $userid): array {
        global $DB;

        $programs = [];

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
     * Returns program allocations for a given user
     *
     * Note: Does not return allocations where the certification is archived.
     *
     * @param int $userid
     * @param int $programid
     * @return array program_user[]
     */
    public static function get_user_allocations(int $userid, int $programid = 0): array {
        global $DB;

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
        $params = ['userid' => $userid];

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
     * @param int $userid
     * @return stdClass[]
     */
    public static function get_user_accessible_courses(int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $workplaceexcludedcourses = \tool_program\api::get_course_ids_with_only_enrol_program_instance($userid);
        $courses = enrol_get_users_courses($userid, true, '*');
        return array_filter($courses, static function(stdClass $course) use ($workplaceexcludedcourses): bool {
            return !in_array($course->id, $workplaceexcludedcourses, true);
        });
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
            $status[$allocationid] = $statuses[0]['status'] ?? -1;
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
        global $PAGE, $OUTPUT, $USER;

        // Only add images when we are on the /my/courses.php or /enrol/index.php subpages.
        if (!in_array($PAGE->bodyid, ['page-my-index', 'page-enrol-index']) ) {
            return null;
        }

        $imagedata = null;
        if ($PAGE->bodyid == 'page-my-index') {
            $params = router::get_mycourses_url_params();
        } else if ($PAGE->bodyid == 'page-enrol-index') {
            $params = ['course' => required_param('id', PARAM_INT)];
        }

        if (!empty($params['program'])) {
            // Display program or set information.
            $program = new program($params['program']);
            $showprogrampreference = get_user_preferences('tool_catalogue_show_program_content_' . $program->get('id'), false);
            if (permission::can_view_program((int) $USER->id, $program) && !$showprogrampreference) {
                $image = $program->get_image_url();
                if (!$image) {
                    $image = api::get_program_pattern($program->get('id'));
                }
                $imagedata = html_writer::tag('div', '', ['class' => 'border-radius item-heading-image',
                    'style' => 'background-image: url(' . $image . ')']);
            }
        } else if (!empty($params['course'])) {
            $course = get_course($params['course']);
            $image = \core_course\external\course_summary_exporter::get_course_image($course);
            if (!$image) {
                $image = $OUTPUT->get_generated_image_for_id($course->id);
            }
            $imagedata = html_writer::tag('div', '', ['class' => 'border-radius item-heading-image',
                        'style' => 'background-image: url(' . $image . ')']);
        }
        return new context_header($PAGE->heading, 1, $imagedata, null, null);
    }

    /**
     * Callback to show course cover modal if needed.
     *
     * Used using component_class_callback in '/course/view.php' page.
     *
     * @param stdClass $course
     * @return string
     */
    public static function get_course_cover_modal(stdClass $course): string {
        global $PAGE;

        if (self::should_show_course_cover_modal($course)) {
            $output = $PAGE->get_renderer('tool_catalogue');
            $coursemodalview = new course_cover_modal($course);
            return $output->render($coursemodalview);
        }
        return '';
    }

    /**
     * Check if the course cover modal needs to be shown.
     *
     * @param stdClass $course
     * @return bool
     */
    private static function should_show_course_cover_modal(stdClass $course): bool {
        global $USER;

        $displaycoursecovermodals = (int) get_config('tool_catalogue', 'displaycoursecovermodals');
        $showncoursecoverpreference = (bool) get_user_preferences('tool_catalogue_show_course_content_' . $course->id, false);
        if ($showncoursecoverpreference || $displaycoursecovermodals === constants::NEVER_DISPLAY) {
            return false;
        }
        if ($displaycoursecovermodals === constants::DISPLAY_FOR_EVERYBODY) {
            return true;
        }

        $coursecontext = context_course::instance($course->id);
        $userroles = get_user_roles($coursecontext, $USER->id);
        $userroleids = array_column($userroles, 'roleid');
        $studentroles = get_archetype_roles('student');
        $studentroleids = array_column($studentroles, 'id');
        $userstudentroleids = array_intersect($userroleids, $studentroleids);

        if ($displaycoursecovermodals === constants::DISPLAY_ONLY_FOR_STUDENTS_AND_GUESTS &&
                (is_guest($coursecontext) || !empty($userstudentroleids))) {
            return true;
        }
        return false;
    }

    /**
     * Should show program cover
     *
     * @param program $program
     * @return bool
     */
    public static function should_show_program_cover(program $program): bool {
        global $USER;

        // TODO WP-1793 This validation would change since users who are not allocated in the given program should see a
        // program self-allocation page instead of program cover page even if 'displayprogramcoverpage'
        // setting is set to 'Display for everybody'.

        if (get_user_preferences('tool_catalogue_show_program_content_' . $program->get('id'), false)) {
            return false;
        }

        $displayprogramcoverpage = (int) get_config('tool_catalogue', 'displayprogramcoverpage');

        if ($displayprogramcoverpage === constants::DISPLAY_FOR_EVERYBODY) {
            return true;
        }

        if ($displayprogramcoverpage === constants::NEVER_DISPLAY) {
            return false;
        }

        if ($displayprogramcoverpage === constants::DISPLAY_ONLY_FOR_NOT_ADMIN && (is_siteadmin($USER) ||
                \tool_tenant\manager::is_tenant_admin(tenancy::get_tenant_id(), (int) $USER->id))) {
            return false;
        }

        return true;
    }

    /**
     * Gets the due date type and string ready to be exported
     *
     * @param int $duedate duedate timestamp or 0 if no due date
     * @param int $duelimit timestamp of the limit due date
     * @param string $daysremainingidentifier
     * @return array type / formatted due date
     */
    public static function get_duedate_badge(int $duedate, int $duelimit, string $daysremainingidentifier): array {
        $now = time();

        if ($duedate === 0 || $duelimit < 0 || $duedate > ($now + $duelimit * DAYSECS)) {
            return ['', ''];
        }

        $days = floor(($duedate - $now) / DAYSECS);
        if ($days < 0) {
            return ['danger', get_string('overdue', 'tool_catalogue')];
        }
        if ($days <= 1) {
            return ['warning', get_string('duedateinfo', 'tool_catalogue')];
        }

        return ['warning', get_string($daysremainingidentifier, 'tool_catalogue', $days)];
    }
}
