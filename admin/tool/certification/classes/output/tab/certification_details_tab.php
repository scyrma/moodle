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
 * File for the class certification_details_tab.
 *
 * This tab should be only available when user has no permission to edit the certification.
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\output\tab;

use core_form\dynamic_form;
use tool_certification\certification;
use tool_certification\edit_certification_details_form;
use tool_certification\permission;
use renderer_base;
use tool_wp\output\tab_form;

/**
 * Class certification_details_tab.
 *
 * @package   tool_certification
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_details_tab extends tab_form {

    /**
     * Name of the class that contains the form (must extend \core_form\dynamic_form)
     *
     * @return string
     */
    public function get_form_class(): string {
        return edit_certification_details_form::class;
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('details');
    }

    /**
     * Check permission of the current user to access this tab
     *
     * @return bool
     */
    public function is_available(): bool {
        $certification = new certification(!empty($this->data['id']) ? $this->data['id'] : 0);
        return permission::can_view_details($certification);
    }

    /**
     * Check permission of the current user to edit certification details
     *
     * @return bool
     */
    protected function can_edit(): bool {
        $certification = new certification($this->data['id'] ?? 0);
        return permission::can_view_details($certification) && permission::can_edit_details($certification);
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_certification/edit_certification_details';
    }

    /**
     * Export data for the template
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $data = parent::export_for_template($output);
        $data['tabheading'] = get_string('details');
        return $data;
    }
}
