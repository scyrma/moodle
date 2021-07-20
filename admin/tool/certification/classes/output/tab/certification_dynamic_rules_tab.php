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

/**
 * Dynamic rules tab.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\output\tab;

use context_system;
use renderer_base;
use stdClass;
use tool_certification\certification;
use tool_certification\permission;
use tool_reportbuilder\system_report_factory;
use tool_wp\output\content_with_heading;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification_dynamic_rules_tab
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_dynamic_rules_tab extends \tool_wp\output\tab {
    /**
     * Function to export the renderer data in a format that is suitable for a
     * mustache template. This means:
     * 1. No complex types - only stdClass, array, int, string, float, bool
     * 2. Any additional info that is required for the template is pre-calculated (e.g. capability checks).
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(renderer_base $output) {

        // Check if tool_reportbuilder is installed.
        if (!class_exists('\\tool_reportbuilder\\system_report_factory')) {
            $str = get_string('reportbuilderdynamicrules', 'tool_certification');
            $table = \html_writer::tag('div', $str, array('class' => 'alert alert-warning'));
            $content = new content_with_heading($table, get_string('dynamicrules', 'tool_certification'));
            $content->add_content_wrapper(['data-region' => 'ruleslistwrapper']);
            return $content->export_for_template($output);
        }

        // Check if tool_dynamicrule is installed.
        if (!class_exists('\\tool_dynamicrule\\rules_list')) {
            $str = get_string('dynamicrulesplugincheck', 'tool_certification');
            $table = \html_writer::tag('div', $str, array('class' => 'alert alert-warning'));
            $content = new content_with_heading($table, get_string('dynamicrules', 'tool_certification'));
            $content->add_content_wrapper(['data-region' => 'ruleslistwrapper']);
            return $content->export_for_template($output);
        }

        $canmanagedynamicrules = \tool_dynamicrule\permission::can_manage_rules();

        $params = [
            'component'     => 'tool_certification',
            'componentarea' => 'certification',
            'itemid'        => $this->data['id'],
            'readonly'      => !$canmanagedynamicrules
        ];
        $report = system_report_factory::create(\tool_dynamicrule\rules_list::class, $params);
        $content = new content_with_heading($report->output(), get_string('dynamicrules', 'tool_certification'));
        $content->add_content_wrapper(['data-region' => 'ruleslistwrapper']);
        return $content->export_for_template($output);
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('dynamicrules', 'tool_certification');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        if (0 === (int)$this->data['id']) {
            return false;
        }
        $certification = new certification($this->data['id']);
        return permission::can_view_details($certification);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_dynamicrule/rules_list';
    }
}
