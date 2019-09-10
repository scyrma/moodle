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
 * Class tab_users
 *
 * @package     tool_tenant
 * @copyright   2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\output;

use tool_tenant\form\edit_css_form;
use tool_tenant\permission;
use tool_wp\output\tab_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_users - tab that lists users in the current tenant
 *
 * @package     tool_tenant
 * @copyright   2019 Adrian Greeve
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_appearance extends tab_form {

    /**
     * Name of the class that contains the form (must extend tool_wp\modal_form)
     *
     * @return string
     */
    public function get_form_class(): string {
        return edit_css_form::class;
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_edit_tenant_theme($this->data['tenantid']);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_tenant/appearance';
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('appearance');
    }

    /**
     * Export for template
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output) {
        $data = parent::export_for_template($output);
        $data['tabheading'] = $heading = get_string('appearance');
        return $data;
    }
}
