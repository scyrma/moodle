<?php
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

namespace theme_school\output\core;

use stdClass;
use html_writer;
use coursecat_helper;

defined('MOODLE_INTERNAL') || die();

/**
 *
 * Overridden Core Course Renderer for the School theme.
 *
 * @package    theme_school
 * @copyright  2019 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

class course_renderer extends \core_course_renderer {

    const NUMBER_OF_IMAGES = 20;
    const MAX_CATEGORY_COUNT = 11;

    public static function serialise_courses($courses) {
        global $DB, $CFG;

        if (empty($courses)) {
            return array();
        }

        $coursedetailsarray = array();
        foreach ($courses as $course) {
            $url = new \moodle_url('/course/view.php', array('id' => $course->id));
            $enrolledusersurl = new \moodle_url('/user/index.php', array('id' => $course->id));
            $summary = format_text($course->summary, $course->summaryformat, array(), $course->id);
            $name = format_string(get_course_display_name_for_list($course), true, array());
            $imageurl = '';

            $courseinfo = array(
                    'url' => $url->out(),
                    'name' => $name,
                    'summary' => $summary,
                    'contacts' => array(),
            );

            if (\has_capability('moodle/course:enrolreview', \context_course::instance($course->id))) {
                $courseinfo['enrolledusersurl'] = $enrolledusersurl->out();
            }

            if ($course->has_course_contacts()) {
                foreach ($course->get_course_contacts() as $userid => $coursecontact) {
                    $profileurl = new \moodle_url('/user/view.php', array('id' => $userid, 'course' => SITEID));
                    $info = array(
                            'name' => $coursecontact['username'],
                            'profileurl' => $profileurl->out(),
                            'rolename' => $coursecontact['rolename'],
                    );

                    $courseinfo['contacts'][] = $info;
                }
            }

            foreach ($course->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                if ($isimage) {
                    $imageurl = file_encode_url("$CFG->wwwroot/pluginfile.php",
                            '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                            $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);

                    break;
                }
            }

            if (empty($imageurl)) {
                $imagenumber = ($course->id % self::NUMBER_OF_IMAGES) + 1;
                $imageurl = sprintf("%s/theme/school/pix/custom/course/%s.jpg", $CFG->wwwroot, $imagenumber);
            }

            $courseinfo['imageurl'] = $imageurl;

            $coursedetailsarray[] = $courseinfo;
        }

        return $coursedetailsarray;
    }

    private function serialise_categories($categories) {
        if (empty($categories)) {
            return array();
        }

        $helper = new \coursecat_helper();

        $serialiser = function($category) use ($helper) {
            $url = new \moodle_url('/course/index.php', array('categoryid' => $category->id));
            return array(
                    'name' => $category->name,
                    'url' => $url->out(),
                    'description' => $helper->get_category_formatted_description($category),
                    'coursecount' => $category->get_courses_count(),
                    'subcategorycount' => $category->get_children_count(),
            );
        };

        return array_map($serialiser, $categories);
    }

    /**
     * Override course categories rendering on /course page.
     *
     * @param  coursecat_helper $chelper   [description]
     * @param  core_course_category $coursecat top category
     *
     * @return nothing.
     */
    protected function coursecat_tree(coursecat_helper $chelper, $coursecat) {
        return $this->coursecategory_index();
    }

    /**
     * Disable rendering HTML to display particular course category - list of it's subcategories and courses
     *
     * Invoked from /course/index.php
     *
     * @param int|stdClass|core_course_category $category
     */
    public function course_category($category) {
        return $this->coursecategory_index();
    }

    /**
     * Get the content for the course category index page.
     *
     * @return string Html for course category index page.
     */
    public function coursecategory_index() {
        $categoryid = optional_param('categoryid', 0, PARAM_INT);
        if (!$categoryid) {
            // If no id is given and we've only got one category just show those courses.
            if (\core_course_category::is_simple_site()) {
                return $this->coursecategory_courses(\core_course_category::get_default());
            } else {
            // Otherwise show a list of the categories.
                return $this->coursecategory_categories();
            }
        } else {
        // If we were given a category id then show that one specifically.
            $coursecategory = \core_course_category::get($categoryid);
            return $this->coursecategory_courses($coursecategory);
        }
    }

    /**
     * Render a list of categories.
     *
     * @return string Html for categories page.
     */
    public function coursecategory_categories() {
        global $CFG;

        $category = \core_course_category::get(0);
        $childcategories = array_values($category->get_children());
        $moodlecontext = get_category_or_system_context($category->id);
        $coursesearchurl = new \moodle_url('/course/search.php');
        $context = array(
            'categories' => $this->serialise_categories($childcategories),
            'showaddcourse' => has_capability('moodle/course:create', $moodlecontext),
            'urls' => array(
                'coursesearch' => $coursesearchurl->out(),
            ),
        );

        $urls = $this->get_course_category_button_urls($category, $moodlecontext);
        $context['urls'] = array_merge($context['urls'], $urls);

        return $this->render_from_template('theme_school/coursecategory_categories', $context);
    }

    private function get_course_category_button_urls($coursecategory, $context) {
        global $CFG, $DB;

        $urls = array();
        $issystemcontext = $context->contextlevel == CONTEXT_SYSTEM;

        if (has_capability('moodle/course:create', $context)) {
            $categoryid = $coursecategory->id;
            if ($issystemcontext) {
                $categoryid = $CFG->defaultrequestcategory;
            }

            $addcourseurl = new \moodle_url('/course/edit.php', array('category' => $categoryid, 'returnto' => 'category'));
            $urls['addcourse'] = $addcourseurl->out();
        }

        if (!empty($CFG->enablecourserequests)) {
            if (!has_capability('moodle/course:create', $context) && has_capability('moodle/course:request', $context)) {
                $requestcourseurl = new moodle_url('/course/request.php');
                $urls['requestcourseurl'] = $requestcourseurl->out();
            }

            if ($issystemcontext &&
                has_capability('moodle/site:approvecourse', $context) &&
                $DB->record_exists('course_request', array())) {

                $pendingcoursesurl = new moodle_url('/course/pending.php');
                $urls['pendingcoursesurl'] = $pendingcoursesurl->out();
            }
        }

        return $urls;
    }

    public function coursecategory_courses($coursecategory) {
        global $CFG, $DB;

        $categorypickers = array();
        if ($coursecategory->id) {
            $html = html_writer::start_tag('div', array('class' => 'categorypicker'));
            $select = new \single_select(new \moodle_url('/course/index.php'), 'categoryid',
                    \core_course_category::make_categories_list(), $coursecategory->id, null, 'all-category-picker');
            $select->set_label(get_string('allcategories').':');
            $html .= $this->render($select);
            $html .= html_writer::end_tag('div');

            $categorypickers['all'] = $html;
        }

        $courses = $coursecategory->get_courses(array('summary' => 1, 'coursecontacts' => 1));
        $hascourses = !empty($courses);
        $coursedetails = array();

        if ($hascourses) {
            $coursedetails = self::serialise_courses($courses);
        }

        $childcategories = array_values($coursecategory->get_children());
        $haschildcategories = !empty($childcategories);

        if ($haschildcategories) {
            $categorylist = array('-1' => '');
            foreach ($childcategories as $category) {
                $categorylist[$category->id] = $category->name;
            }

            $html = html_writer::start_tag('div', array('class' => 'categorypicker'));
            $select = new single_select(new \moodle_url('/course/index.php'), 'categoryid',
                    $categorylist, $coursecategory->id, null, 'subcategory-picker');
            $select->set_label(get_string('subcategories').':');
            $html .= $this->render($select);
            $html .= html_writer::end_tag('div');

            $categorypickers['sub'] = $html;
        }

        $coursesearchurl = new \moodle_url('/course/search.php');
        $moodlecontext = get_category_or_system_context($coursecategory->id);

        $context = array(
            'hascourses' => $hascourses,
            'courses' => $coursedetails,
            'haschildcategories' => $haschildcategories,
            'categorypickers' => $categorypickers,
            'urls' => array(
                'coursesearch' => $coursesearchurl->out(),
            ),
        );

        $urls = $this->get_course_category_button_urls($coursecategory, $moodlecontext);
        $context['urls'] = array_merge($context['urls'], $urls);

        return $this->render_from_template('theme_school/coursecategory_courses', $context);
    }

    public function frontpage() {
        return '';
    }
}
