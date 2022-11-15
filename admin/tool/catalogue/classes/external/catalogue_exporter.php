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

namespace tool_catalogue\external;

use context_course;
use core\external\exporter;
use renderer_base;
use tool_catalogue\constants;
use tool_catalogue\manager;
use tool_catalogue\router;
use tool_program\persistent\program_user;

/**
 * Catalogue (My courses) exporter class
 *
 * Exports the list of all user programs and courses belonging to the main "My courses" page.
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Mikel Martín <mikel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class catalogue_exporter extends exporter {

    /** @var array $listitems */
    private $listitems;

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'userid' => 'int?',
            'filter' => 'string?',
            'sort' => 'string?',
            'search' => 'string?',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'listitems' => [
                'type' => [
                    'itemid' => ['type' => PARAM_INT],
                    'fullname' => ['type' => PARAM_TEXT],
                    'numcourses' => ['type' => PARAM_INT, 'optional' => true],
                    'image' => ['type' => PARAM_RAW],
                    'progress' => ['type' => PARAM_FLOAT],
                    'duedate' => ['type' => PARAM_INT],
                    'duedatebadgetype' => ['type' => PARAM_TEXT],
                    'duedatebadgestr' => ['type' => PARAM_TEXT],
                    'lastaccess' => ['type' => PARAM_INT],
                    'url' => ['type' => PARAM_URL],
                    'showprogress' => ['type' => PARAM_BOOL],
                    'isprogram' => ['type' => PARAM_BOOL],
                    'islocked' => ['type' => PARAM_BOOL],
                    'categoryname' => [
                        'type' => PARAM_TEXT,
                        'optional' => true
                    ],
                ],
                'multiple' => true,
                'optional' => true
            ],
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        // Fetch all program and course items for the current user.
        $this->listitems = [];

        // Do not fetch programs if is filtering by courses.
        if ($this->related['filter'] !== constants::FILTER_COURSES) {
            $this->listitems = $this->get_program_items($output);
        }

        // Do not fetch courses if is filtering by programs.
        if ($this->related['filter'] !== constants::FILTER_PROGRAMS) {
            $this->listitems = array_merge($this->listitems, $this->get_course_items($output));
        }

        $this->apply_filter();
        $this->apply_sort();
        $this->apply_search();

        return [
            'listitems' => $this->listitems,
        ];
    }

    /**
     * Returns all course items (that do not belong to a program) where the user is allocated
     *
     * @param renderer_base $output
     * @return array
     */
    private function get_course_items(renderer_base $output): array {
        global $USER;

        $listitems = [];
        // Check if userid is set. If not use current user.
        $userid = $this->related['userid'] ?? (int) $USER->id;

        // Get all courses accesible for the given user.
        $courses = manager::get_user_accessible_courses($userid);
        // Get all user courses completions at once.
        $coursescompletion = manager::get_user_courses_completion($courses, $userid);
        // Get all courses lastaccess times at once.
        $lastaccess = manager::get_last_course_access($userid, array_keys($courses));

        $showcataloguecoursecategory = get_config('tool_catalogue', 'showcataloguecoursecategory');

        foreach ($courses as $course) {
            $related = [
                'context' => context_course::instance($course->id),
                'course' => $course,
            ];
            $exporter = new course_exporter(null, $related);
            $coursedata = $exporter->export($output);

            $data = (object)[];
            $data->itemid = $course->id;
            $data->image = $coursedata->course->courseimage;
            $data->fullname = $coursedata->course->fullname;
            // We use MAX_DATE just on catalogue page to filter by duedate.
            $data->duedate = ($coursedata->duedate === 0) ? constants::MAX_DATE : $coursedata->duedate;
            // Do not show badge if course is already completed.
            $timecompleted = $coursescompletion[$course->id]->timecompleted;
            $data->duedatebadgetype = $timecompleted ? '' : $coursedata->duedatebadgetype;
            $data->duedatebadgestr = $timecompleted ? '' : $coursedata->duedatebadgestr;
            $data->categoryname = $showcataloguecoursecategory ? $coursedata->course->coursecategory : '';
            $data->lastaccess = $lastaccess[$course->id]->timeaccess ?? 0;
            $data->progress = $coursedata->course->progress;
            // We only show progress for courses when completion is enabled.
            $data->showprogress = $coursedata->course->hasprogress;
            $data->url = (string)router::build_course_url((int) $course->id);
            $data->isprogram = false;
            $data->islocked = false;
            // Adjust progress to maximum 95% if course is not completed.
            if (is_null($timecompleted)) {
                $data->progress = min($data->progress, 95);
            }

            $listitems[] = (array)$data;
        }

        return $listitems;
    }

    /**
     * Returns all program items where the user is allocated
     *
     * @param renderer_base $output
     * @return array
     */
    private function get_program_items(renderer_base $output): array {
        global $USER;

        $listitems = [];
        // Check if userid is set. If not use current user.
        $userid = $this->related['userid'] ?? (int) $USER->id;

        $allocations = manager::get_user_allocations($userid);
        $programs = manager::get_user_accessible_programs($userid);

        foreach ($programs as $program) {

            // Filter only by this program allocations.
            $programid = $program->get('id');
            $programallocations = array_filter($allocations, static function(program_user $allocation) use ($programid) {
                return $allocation->get('programid') === $programid;
            });

            $related = [
                'userid' => $userid,
                'context' => $program->get_context(),
                'program' => $program,
                'allocations' => $programallocations,
            ];

            $exporter = new program_exporter(null, $related);
            $programdata = $exporter->export($output);

            $data = (object)[];
            $data->itemid = $programid;
            $data->fullname = $programdata->fullname;
            $data->image = $programdata->image;
            $data->progress = $programdata->programstructure->baseset->progress;
            $data->numcourses = $programdata->numcourses;
            $data->lastaccess = $programdata->lastaccess;
            $data->duedate = $programdata->duedate;
            // Do not show badge if program is already completed.
            $iscompleted = $programdata->programstructure->baseset->iscompleted;
            $data->duedatebadgetype = $iscompleted ? '' : $programdata->duedatebadgetype;
            $data->duedatebadgestr = $iscompleted ? '' : $programdata->duedatebadgestr;
            $data->url = (string)router::build_program_url($programid);
            // The program progress should be only displayed when the user has already seen the program cover.
            $data->showprogress = get_user_preferences('tool_catalogue_show_program_content_' . $programid, false);
            $data->isprogram = true;
            $data->islocked = false;

            $listitems[] = (array)$data;
        }

        return $listitems;
    }

    /**
     * Apply filter on the list of items
     */
    private function apply_filter(): void {
        switch ($this->related['filter']) {
            case constants::FILTER_COURSES:
                $this->listitems = array_filter($this->listitems, static function(array $item) {
                    return !$item['isprogram'];
                });
                break;
            case constants::FILTER_PROGRAMS:
                $this->listitems = array_filter($this->listitems, static function(array $item) {
                    return $item['isprogram'];
                });
                break;
            case constants::FILTER_COMPLETE:
                $this->listitems = array_filter($this->listitems, static function(array $item) {
                    return (int)$item['progress'] === 100;
                });
                break;
            case constants::FILTER_INCOMPLETE:
                $this->listitems = array_filter($this->listitems, static function(array $item) {
                    return (int)$item['progress'] !== 100;
                });
                break;
        }
    }

    /**
     * Apply sorting on the list of items
     */
    private function apply_sort(): void {
        switch ($this->related['sort']) {
            case constants::SORT_DUEDATE:
                usort($this->listitems, static function ($a, $b) {
                    return $a['duedate'] <=> $b['duedate'];
                });
                break;
            case constants::SORT_NAME:
                usort($this->listitems, static function ($a, $b) {
                    return strcasecmp($a['fullname'], $b['fullname']);
                });
                break;
            case constants::SORT_LASTACCESS:
                usort($this->listitems, static function ($a, $b) {
                    return ($a['lastaccess'] > $b['lastaccess']) ? -1 : 1;
                });
                break;
        }
    }

    /**
     * Apply search on the list of items
     */
    private function apply_search(): void {
        $search = $this->related['search'];
        if ($search) {
            $this->listitems = array_filter($this->listitems, static function($item) use ($search) {
                return stripos($item['fullname'], $search) !== false;
            });
        }
    }
}
