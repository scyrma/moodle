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

/**
 * Class to define report audience tab
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\output\tabs;

use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\form\audience as audience_form;
use tool_wp\output\tab_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Audience class tab
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience extends tab_form {

    /** @var string  */
    const TEMPLATE = 'tool_reportbuilder/tab_audience';

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
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $data = parent::export_for_template($output);
        $data['tabheading'] = $this->get_tab_label();

        return $data;
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
     * Name of the class that contains the tab form
     *
     * @return string
     */
    public function get_form_class() : string {
        return audience_form::class;
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