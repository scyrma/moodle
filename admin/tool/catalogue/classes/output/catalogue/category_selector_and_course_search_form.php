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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace tool_catalogue\output\catalogue;

use tool_catalogue\external\category_selector_exporter;

/**
 * Category selector and course search form.
 *
 * @package     tool_catalogue
 * @copyright   2023 Moodle Pty Ltd <support@moodle.com>
 * @author      2023 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class category_selector_and_course_search_form implements \renderable, \core\output\named_templatable {

    /**
     * Constructor.
     *
     * @param string $query search query from the previous step
     */
    public function __construct(protected string $query = '') {
    }

    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param \renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return \stdClass|array
     */
    public function export_for_template(\renderer_base $output) {
        // Search form data.
        $data = [
            'action' => (new \moodle_url('/course/search.php'))->out(false),
            'btnclass' => 'btn-primary',
            'inputname' => 'q',
            'searchstring' => get_string('searchcourses'),
            'query' => $this->query,
        ];

        // Format categories in correct way.
        $exporter = new category_selector_exporter([]);
        $categories = $exporter->export($output);

        return array_merge([
                'categories' => $categories->categories,
                'hascategories' => !empty($categories->categories),
                'maxdepth' => \tool_catalogue\configuration::get_categories_depth_limit(),
            ], $data);
    }

    /**
     * Get the name of the template to use for this templatable.
     *
     * @param \renderer_base $renderer The renderer requesting the template name
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'tool_catalogue/categoryselector/selector_and_search';
    }
}
