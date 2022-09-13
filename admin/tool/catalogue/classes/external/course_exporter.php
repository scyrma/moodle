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
use core_course\external\course_summary_exporter;
use core_course_list_element;
use moodle_url;
use renderer_base;
use stdClass;
use tool_catalogue\constants;
use tool_catalogue\manager;
use tool_catalogue\router;
use tool_program\api;
use tool_program\program_tree_progress;

/**
 * Course exporter class
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course_exporter extends exporter {

    /**
     * Returns a list of objects that are related.
     *
     * @return array
     */
    protected static function define_related(): array {
        return [
            'context' => 'context',
            'course' => 'stdClass',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return [
            'course' => ['type' => course_summary_exporter::read_properties_definition()],
            'duedate' => ['type' => PARAM_INT],
            'duedatebadgetype' => ['type' => PARAM_TEXT],
            'duedatebadgestr' => ['type' => PARAM_TEXT],
            'linkedprograms' => [
                'type' => [
                    'name' => ['type' => PARAM_TEXT],
                    'url' => ['type' => PARAM_URL],
                ],
                'multiple' => true,
                'optional' => true
            ],
            'trainers' => [
                'type' => [
                    'fullname' => ['type' => PARAM_TEXT],
                    'profileurl' => ['type' => PARAM_URL],
                    'userpicture' => ['type' => PARAM_RAW],
                ],
                'multiple' => true,
                'optional' => true
            ],
            'restrictions' => [
                'type' => PARAM_TEXT,
                'multiple' => true,
                'optional' => true
            ],
            'coursefiles' => [
                'type' => [
                    'isimage' => ['type' => PARAM_BOOL],
                    'url' => ['type' => PARAM_URL],
                    'name' => [
                        'type' => PARAM_TEXT,
                        'optional' => true
                    ],
                    'image' => [
                        'type' => PARAM_RAW,
                        'optional' => true
                    ],
                ],
                'multiple' => true,
                'optional' => true
            ]
        ];
    }

    /**
     * Other values
     *
     * @param renderer_base $output
     * @return array
     */
    protected function get_other_values(renderer_base $output): array {
        $exporter = new course_summary_exporter($this->related['course'], ['context' => $this->related['context']]);
        $course = $exporter->export($output);

        $duedate = (int) $course->enddate;
        $duelimit = (int) get_config('tool_catalogue', 'coursedisplayduelimit');
        [$duedatebadgetype, $duedatebadgestr] = manager::get_duedate_badge($duedate, $duelimit, 'daysleft');
        if ($duedate === 0) {
            $duedate = constants::MAX_DATE;
        }

        // Get list of files in course overview.
        $courselist = new core_course_list_element($this->related['course']);
        $allfiles = [];
        foreach ($courselist->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            $url = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                null, $file->get_filepath(), $file->get_filename(), !$isimage);

            $fileitem = [
                'isimage' => $isimage,
                'url' => $url
            ];
            if (!$isimage) {
                $fileitem += [
                    'name' => $file->get_filename(),
                    'image' => $output->pix_icon(file_file_icon($file, 24), $file->get_filename())
                ];
            }
            $allfiles[] = $fileitem;
        }

        // Sort array of files with images first.
        $isimage = array_column($allfiles, 'isimage');
        array_multisort($isimage, SORT_DESC, $allfiles);

        // Get list of programs that contain this course.
        $persistents = api::get_linked_programs_to_a_user_course((int) $course->id);
        $linkedprograms = [];
        foreach ($persistents as $persistent) {
            $linkedprograms[] = [
                'name' => $persistent->get_formatted_name(),
                'url' => (string) router::build_program_url($persistent->get('id')),
            ];
        }

        // Get all restrictions for this course in all previous programs.
        $restrictions = $this->get_restriction_messages($output, $persistents, $course);

        return [
            'course' => $course,
            'duedate' => $duedate,
            'duedatebadgetype' => $duedatebadgetype,
            'duedatebadgestr' => $duedatebadgestr,
            'trainers' => $this->get_course_trainers($output),
            'linkedprograms' => $linkedprograms,
            'restrictions' => $restrictions,
            'coursefiles' => $allfiles,
        ];
    }

    /**
     * Returns course trainers (fullname, profile url and user picture)
     *
     * @param renderer_base $output
     * @return array
     */
    private function get_course_trainers(renderer_base $output): array {
        global $DB;

        $trainers = [];
        $course = new core_course_list_element($this->related['course']);
        $contacts = $course->get_course_contacts();

        foreach ($contacts as $contact) {
            $user = $DB->get_record('user', ['id' => $contact['user']->id]);
            $trainers[] = [
                'fullname' => fullname($user),
                'profileurl' => (string) new moodle_url('/user/profile.php', ['id' => $user->id]),
                'userpicture' => $output->user_picture($user),
            ];
        }

        return $trainers;
    }

    /**
     * Return restriction messages from all programs that contain this course
     *
     * @param renderer_base $output
     * @param array $persistents
     * @param stdClass $course
     * @return array
     */
    private function get_restriction_messages(renderer_base $output, array $persistents, stdClass $course): array {
        global $USER;

        // Course is directly accessible by the user.
        if (is_enrolled(context_course::instance($course->id), null, '', true)) {
            return [];
        }

        // Course does not belong to any program.
        if (empty($persistents)) {
            return [];
        }

        // Get restriction messages. Loop thru all programs that contain this course and check if this course is available in
        // any program or if not collect all restriction messages.
        $restrictions = [];
        foreach ($persistents as $persistent) {
            // Get all course objects at once to avoid retrieving individual courses inside the exporters.
            $courses = $persistent->get_courses();

            // Fetch the program structure with all related data for the current user.
            $treeprogress = new program_tree_progress($persistent, (int) $USER->id);
            $exporter = new program_content_exporter(null, [
                'context' => $persistent->get_context(),
                'treeprogress' => $treeprogress,
                'courses' => $courses,
            ]);
            $programstructure = $exporter->export($output);

            $flatstructure = self::get_flat_structure($programstructure->baseset->items);

            // Get all course items for this course.
            $courses = array_filter($flatstructure, static function($item) use ($course) {
                return !$item->isset && (int) $item->courseid === (int) $course->id;
            });

            foreach ($courses as $courseitem) {
                if ($courseitem->islocked) {
                    $restrictions[$persistent->get('id')] = $courseitem->unlockrequirement;
                } else {
                    // The course in unlocked at least in one program and then is not restricted.
                    return [];
                }
            }
        }

        return $restrictions;
    }

    /**
     * Generates the program tree structure as a flat array
     *
     * @param array $items
     * @return array
     */
    public static function get_flat_structure(array $items): array {
        $flatstructure = [];
        foreach ($items as $item) {
            if ($item->isset) {
                $flatstructure = array_merge($flatstructure, self::get_flat_structure($item->items)); // Recursion.
                unset ($item->items);
            }
            $flatstructure[] = $item;
        }
        return $flatstructure;
    }
}
