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

namespace tool_reportbuilder\output\tabs;

use renderer_base;
use tool_reportbuilder\audience_base;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_wp\output\tab;
use tool_reportbuilder\local\helpers\audience as audience_helper;

/**
 * Audience class tab
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audiences extends tab {

    /** @var string  */
    const TEMPLATE = 'tool_reportbuilder/audiences';

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template() : string {
        return self::TEMPLATE;
    }

    /**
     * Export this for use in a mustache template context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        // Get all the audiences types to populate the left menu.
        $menucardsarray = $this->get_all_audiences_menu_types();

        // Get all current audiences instances for this report.
        $audienceinstances = $this->get_all_report_audiences();

        $data = [
            'tabheading' => get_string('audience', 'tool_reportbuilder'),
            'reportid' => $this->data['reportid'],
            'menucards' => $menucardsarray,
            'instances' => $audienceinstances,
            'noinstances' => empty($audienceinstances),
            'noinstancesurl' => $output->image_url('no-instance', 'tool_reportbuilder')->out(),
        ];

        return $data;
    }

    /**
     * Get all the audiences types the current user can add to, organised by categories.
     *
     * @return array
     */
    private function get_all_audiences_menu_types(): array {
        [$categorisedaudiences, $categorynamemap] = audience_helper::get_all_audience_types_by_category();
        $menucardsarray = [];

        foreach ($categorisedaudiences as $categorykey => $categoryaudiences) {
            $menucards = [
                'categoryid' => $categorykey,
                'categorytitle' => $categorynamemap[$categorykey],
            ];

            foreach ($categoryaudiences as $instance) {
                $menucard['title'] = $instance->get_title();
                $menucard['isavailable'] = $instance->is_available();
                $menucard['notavailablelabel'] = $instance->get_not_available_label();
                $menucard['configclass'] = get_class($instance);
                $menucards['menucarditems'][] = $menucard;
            }

            // Order audience types on each category alphabetically.
            \core_collator::asort_array_of_arrays_by_key($menucards['menucarditems'], 'title');
            $menucards['menucarditems'] = array_values($menucards['menucarditems']);

            $menucardsarray[] = $menucards;
        }

        return $menucardsarray;
    }

    /**
     * Get all current audiences instances for this report.
     *
     * @return array
     */
    private function get_all_report_audiences(): array {
        $audienceinstances = [];
        $reportaudiences = audience_helper::get_base_records($this->data['reportid']);
        $showormessage = false;
        foreach ($reportaudiences as $reportaudience) {
            $persistent = $reportaudience->get_persistent();
            $canedit = $reportaudience->user_can_edit();

            $params = [
                'classname' => $persistent->get('classname'),
                'instanceid' => $persistent->get('id'),
                'description' => $reportaudience->get_description(),
                'uniquestring' => random_string(),
                'title' => $reportaudience->get_title(),
                'canedit' => $canedit,
                'candelete' => $canedit,
                'showormessage' => $showormessage,
            ];
            $audienceinstances[] = $params;
            $showormessage = true;
        }

        return $audienceinstances;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label() : string {
        return get_string('audience', 'tool_reportbuilder');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available() : bool {
        $report = manager::get_report($this->data['reportid']);

        return permission::can_edit($report);
    }
}
