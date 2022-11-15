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

use moodle_url;
use tool_catalogue\output\my_courses;
use tool_catalogue\output\renderer;
use tool_program\persistent\program;
use tool_program\persistent\program_set;

/**
 * Router for tool_catalogue
 *
 * @package    tool_catalogue
 * @copyright  2022 Moodle Pty Ltd <support@moodle.com>
 * @author     2022 Marina Glancy
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class router {

    /** @var string Name for the query string parameter if slasharguments are not enabled */
    public const MYCOURSES_PATH_PARAM = 'path';

    /**
     * Called from /my/courses.php page to override the core course listing and/or programs and courses overview pages
     *
     * @param array $coursemanagemenu context menu for the header, can be set to [] if context menu is not needed
     * @return string|null
     */
    public static function mycourses_page(array &$coursemanagemenu): ?string {
        global $USER;
        $params = self::get_mycourses_url_params();

        if (!empty($params['program'])) {
            // Display program or set information.
            $coursemanagemenu = [];
            $program = new program($params['program']);
            if ($program && $program->get('id') && permission::can_view_program((int) $USER->id, $program)) {
                if (!empty($params['set'])) {
                    foreach ($program->get_sets() as $set) {
                        if ($set->get('id') == $params['set']) {
                            return self::mycourses_page_program_set($program, $set);
                        }
                    }
                    // Set not found in the given program - redirect to the program page.
                    redirect(self::build_program_url($params['program']));
                }
                return self::mycourses_page_program($program);
            }
            // Program not found or user does not have access to it - redirect to my courses page.
            redirect(new moodle_url('/my/courses.php'));
        }

        return self::mycourses_page_list();
    }

    /**
     * Displays the list of the user programs and courses on the /my/courses.php page
     *
     * @return string
     */
    protected static function mycourses_page_list(): string {
        global $PAGE;

        /** @var renderer $output */
        $output = $PAGE->get_renderer('tool_catalogue');

        // Get the stored filter and sort options for the user.
        $defaultfilter = get_user_preferences('tool_catalogue_my_courses_filter') ?? constants::FILTER_ALL;
        $defaultsort = get_user_preferences('tool_catalogue_my_courses_sort') ?? get_config('tool_catalogue', 'defaultsortorder');

        $page = optional_param('page', 0, PARAM_INT);
        $sort = optional_param('sort', $defaultsort, PARAM_ALPHA);
        $filter = optional_param('filter', $defaultfilter, PARAM_ALPHA);
        $search = optional_param('search', '', PARAM_NOTAGS);
        $view = new my_courses($page, $sort, $filter, $search);

        // Save the new filter and sort options.
        set_user_preference('tool_catalogue_my_courses_filter', $filter);
        set_user_preference('tool_catalogue_my_courses_sort', $sort);

        return $output->render($view);
    }


    /**
     * Sets up the page and displays information about a single program
     *
     * @param program $program
     * @return string
     */
    protected static function mycourses_page_program(program $program): string {
        global $PAGE;

        /** @var renderer $output */
        $output = $PAGE->get_renderer('tool_catalogue');

        $programname = $program->get_formatted_name();
        $PAGE->set_url(self::build_program_url($program->get('id')));
        $PAGE->navbar->add(get_string('mycourses') . ' ', new moodle_url('/my/courses.php'));
        $PAGE->navbar->add($programname);
        $PAGE->set_heading($programname);
        $PAGE->set_title(get_string('mycourses') . ' - ' . $programname);
        $coverview = new \tool_catalogue\output\program_cover($program);
        $coverhtml = $output->render($coverview);

        // Check if it needs to show tabs (program content and program information) or just the program content.
        if (get_user_preferences('tool_catalogue_show_program_content_' . $program->get('id'), false)) {

            $contentview = new \tool_catalogue\output\program_content($program);
            $contenthtml = $output->render($contentview);

            // TODO WP-3457 It just needs to get certification alerts.
            $data = $coverview->export_for_template($output);
            $tabs = new \tool_catalogue\output\program_tabs($coverhtml, $contenthtml, $data->certifications);
            return $output->render($tabs);
        }

        return $coverhtml;
    }

    /**
     * Sets up the page and displays information about a single program set
     *
     * @param program $program
     * @param program_set $set
     * @return string
     */
    protected static function mycourses_page_program_set(program $program, program_set $set): string {
        global $PAGE;

        /** @var renderer $output */
        $output = $PAGE->get_renderer('tool_catalogue');

        $programname = $program->get_formatted_name();
        $PAGE->set_url(self::build_program_set_url($program->get('id'), $set->get('id')));
        $PAGE->navbar->add(get_string('mycourses') . ' ', new moodle_url('/my/courses.php'));
        $PAGE->navbar->add(format_string($programname), self::build_program_url($program->get('id')));
        // TODO add all parent sets to navbar.
        $PAGE->navbar->add(format_string($set->get('name')));
        $PAGE->set_heading($set->get('name'));
        $PAGE->set_title(get_string('mycourses') . ' - '. $programname . ' - ' . $set->get('name'));

        $contentview = new \tool_catalogue\output\program_set_content($program, $set);
        return $output->render($contentview);
    }

    /**
     * Creates URL for viewing the single program page
     *
     * @param int $programid
     * @return moodle_url
     */
    public static function build_program_url(int $programid): moodle_url {
        $url = new moodle_url('/my/courses.php');
        $url->set_slashargument('/program/' . $programid, self::MYCOURSES_PATH_PARAM);
        return $url;
    }

    /**
     * Creates URL for viewing the single program set page
     *
     * @param int $programid
     * @param int $setid
     * @return moodle_url
     */
    public static function build_program_set_url(int $programid, int $setid): moodle_url {
        $url = new moodle_url('/my/courses.php');
        $url->set_slashargument('/program/' . $programid . '/set/' . $setid, self::MYCOURSES_PATH_PARAM);
        return $url;
    }

    /**
     * Creates URL for viewing the single course page
     *
     * @param int $courseid
     * @return moodle_url
     */
    public static function build_course_url(int $courseid): moodle_url {
        global $CFG;
        require_once("{$CFG->dirroot}/course/lib.php");
        return course_get_url($courseid);
    }

    /**
     * Returns parameters from the current URL for /my/courses.php page (program/set/course)
     *
     * @return array array ['program' => ?, 'set' => ?, 'course' => ?]
     */
    public static function get_mycourses_url_params(): array {
        $args = explode('/', ltrim(self::get_mycourses_url_path()));
        if (count($args) > 4 && $args[1] === 'program' && is_numeric($args[2]) && $args[3] === 'set' && is_numeric($args[4])) {
            return ['program' => (int)$args[2], 'set' => (int)$args[4]];
        }
        if (count($args) > 2 && $args[1] === 'program' && is_numeric($args[2])) {
            return ['program' => (int)$args[2]];
        }
        return [];
    }

    /**
     * Returns parameters from the current URL for /my/courses.php page as a string
     *
     * Mostly copied from {@see get_file_argument()}
     *
     * @return string for example '/program/12' or '/course/77' or '/program/12/set/1'
     */
    protected static function get_mycourses_url_path(): string {
        global $SCRIPT;

        $relativepath = false;
        $hasforcedslashargs = false;

        if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
            if ((strpos($_SERVER['REQUEST_URI'], '/my/courses.php/') !== false)
                && isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO'])) {
                // Exclude edge cases like '/my/courses.php/?path='.
                $args = explode('/', ltrim($_SERVER['PATH_INFO']));
                $hasforcedslashargs = (count($args) > 2); // Always at least: context, component and filearea.
            }
        }
        if (!$hasforcedslashargs) {
            $relativepath = optional_param(self::MYCOURSES_PATH_PARAM, false, PARAM_PATH);
        }

        if ($relativepath !== false && $relativepath !== '') {
            return $relativepath;
        }

        $relativepath = '';

        // Then try extract file from the slasharguments.
        if (stripos($_SERVER['SERVER_SOFTWARE'], 'iis') !== false) {
            // NOTE: IIS tends to convert all file paths to single byte DOS encoding.
            if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '') {
                // Check that PATH_INFO works == must not contain the script name.
                if (strpos($_SERVER['PATH_INFO'], $SCRIPT) === false) {
                    $relativepath = clean_param(urldecode($_SERVER['PATH_INFO']), PARAM_PATH);
                }
            }
        } else {
            // All other apache-like servers depend on PATH_INFO.
            if (isset($_SERVER['PATH_INFO'])) {
                if (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['PATH_INFO'], $_SERVER['SCRIPT_NAME']) === 0) {
                    $relativepath = substr($_SERVER['PATH_INFO'], strlen($_SERVER['SCRIPT_NAME']));
                } else {
                    $relativepath = $_SERVER['PATH_INFO'];
                }
                $relativepath = clean_param($relativepath, PARAM_PATH);
            }
        }

        return $relativepath;
    }
}
