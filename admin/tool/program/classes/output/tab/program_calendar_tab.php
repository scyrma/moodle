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
 * File for the class program_calendar_tab.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output\tab;

use context_system;
use tool_program\form\edit_program_calendar_form;
use tool_program\permission;
use tool_program\persistent\program;
use tool_wp\output\tab_form;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_calendar_tab.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_calendar_tab extends tab_form {

    /**
     * Name of the class that contains the form (must extend tool_wp\modal_form)
     *
     * @return string
     */
    public function get_form_class(): string {
        return edit_program_calendar_form::class;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('calendar', 'tool_program');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        $program = new program(!empty($this->data['id']) ? $this->data['id'] : 0);
        return permission::can_view_details($program);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_program/edit_program_calendar';
    }

    /**
     * Export data for the template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $rv = parent::export_for_template($output);
        $rv['tabheading'] = get_string('calendar', 'tool_program');

        return $rv;
    }
}
