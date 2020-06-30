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
 * -
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output\tab;

use context;
use context_system;
use renderer_base;
use stdClass;
use tool_program\output\program_content_table;
use tool_program\permission;
use tool_program\persistent\program;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * -
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana <mitxel@tresipunt.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content_tab extends tab {

    /** @var program current program */
    protected $program = null;

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
        $context = context::instance_by_id($this->data['contextid']);
        $program = $this->get_program();
        $rv = ['tabheading' => get_string('content', 'tool_program')];
        $rv += (new program_content_table($program, $context))->export_for_template($output);
        $rv += ['editmode' => permission::can_edit_details($program)];
        return $rv;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('content', 'tool_program');
    }

    /**
     * Current program
     *
     * @return program
     */
    protected function get_program() : program {
        if (!$this->program) {
            $this->program = new program(!empty($this->data['id']) ? $this->data['id'] : 0);
        }
        return $this->program;
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        return permission::can_view_details($this->get_program());
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_program/edit_program_content';
    }
}
