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

namespace tool_catalogue\external\program;

use core\external\exporter;
use core_course\external\course_summary_exporter;
use renderer_base;
use tool_catalogue\router;
use tool_program\program_item;

/**
 * Program course exporter class
 *
 * Exports all data related to a program course.
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
            'programitemcourse' => program_item::class,
            'course' => 'stdClass',
        ];
    }

    /**
     * Return the list of additional, generated dynamically from the given properties.
     *
     * @return array
     */
    protected static function define_other_properties(): array {
        return  [
            'isset' => [
                'type' => PARAM_BOOL,
            ],
            'setid' => [
                'type' => PARAM_INT,
            ],
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
            ],
            'fullname' => [
                'type' => PARAM_TEXT,
            ],
            'iscompleted' => [
                'type' => PARAM_BOOL,
            ],
            'progress' => [
                'type' => PARAM_INT,
            ],
            'islocked' => [
                'type' => PARAM_BOOL,
            ],
            'ishidden' => [
                'type' => PARAM_BOOL,
            ],
            'image' => [
                'type' => PARAM_RAW,
            ],
            'url' => [
                'type' => PARAM_URL,
            ],
            'categoryname' => [
                'type' => PARAM_TEXT,
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
        global $DB;

        /** @var program_item $programitemcourse */
        $programitemcourse = $this->related['programitemcourse'];

        $categoryname = '';
        if (get_config('tool_catalogue', 'showcataloguecoursecategory')) {
            $categoryname = $DB->get_field('course_categories', 'name', ['id' => $this->related['course']->category]);
        }

        $data = [];
        $data['courseid'] = $programitemcourse->get_courseid();
        $data['isset'] = false;
        $data['categoryname'] = $categoryname;
        $data['setid'] = $programitemcourse->get_persistent()->get('setid');
        $data['sortorder'] = $programitemcourse->get_persistent()->get('sortorder');
        $data['setcriteria'] = null;
        $data['iscompleted'] = $programitemcourse->iscompleted;
        $data['progress'] = $programitemcourse->progresspercentage;
        $data['islocked'] = !$programitemcourse->isunlocked;
        $data['image'] = $this->get_course_image();
        // Check if course is hidden for this user.
        $canviewhiddencourses = has_capability('moodle/course:viewhiddencourses', $this->related['context']);
        $data['ishidden'] = !$programitemcourse->get_course()->visible && !$canviewhiddencourses;
        if ($data['ishidden']) {
            $data['fullname'] = get_string('coursenotavailable', 'tool_catalogue');
            $data['url'] = '';
        } else {
            $data['fullname'] = $programitemcourse->get_name();
            $data['url'] = (string) router::build_course_url((int) $this->related['course']->id);
        }

        return $data;
    }

    /**
     * Get course image
     *
     * @return string
     */
    private function get_course_image(): string {
        global $OUTPUT;

        $course = $this->related['course'];
        $image = course_summary_exporter::get_course_image($course);
        if (!$image) {
            $image = $OUTPUT->get_generated_image_for_id($course->id);
        }

        return $image;
    }
}
