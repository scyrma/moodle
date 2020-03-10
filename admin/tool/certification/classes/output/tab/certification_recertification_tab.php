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
 * Recertification tab
 *
 * @package   tool_certification
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\output\tab;

use context_system;
use tool_certification\certification;
use tool_certification\permission;
use renderer_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class certification_recertification_tab
 *
 * @package   tool_certification
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_recertification_tab extends \tool_wp\output\tab_form {

    /**
     * Name of the class that contains the form (must extend tool_wp\modal_form)
     *
     * @return string
     */
    public function get_form_class(): string {
        return \tool_certification\edit_certification_recertification_form::class;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('recertification', 'tool_certification');
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
        return 'tool_certification/edit_certification_calendar';
    }

    /**
     * Export data for the template
     *
     * @param renderer_base $output
     * @return array
     * @throws \coding_exception
     */
    public function export_for_template(renderer_base $output) {
        $rv = parent::export_for_template($output);
        $rv['tabheading'] = get_string('recertification', 'tool_certification');
        return $rv;
    }
}
