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
 * Class tab_positions
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\output;

use renderer_base;
use tool_organisation\helper;
use tool_organisation\permission;
use tool_organisation\position;
use tool_organisation\position_manager;
use tool_tenant\tenancy;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_positions
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_positions extends tab {

    /**
     * Check permission of the current user to access this tab
     *
     * @return mixed
     */
    public function is_available(): bool {
        return permission::can_view_positions();
    }

    /**
     * Get framework id from the data
     * @return int
     */
    protected function get_framework_id(): int {
        return !empty($this->data['frameworkid']) ?
            clean_param($this->data['frameworkid'], PARAM_INT) : 0;
    }

    /**
     * Template to use to display tab contents
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_organisation/positions';
    }

    /**
     * The label to be displayed on the tab
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string($this->get_tab_id(), 'tool_organisation');
    }

    /**
     * Exporter
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = [
            'tabheading' => get_string('positionframeworks', 'tool_organisation'),
            'addbutton' => true,
            'addbuttontitle' => get_string('newframework', 'tool_organisation'),
            'addbuttonicon' => true,
            'frameworks' => [],
        ];
        $manager = new position_manager();
        $frameworks = $manager->get_position_frameworks();
        foreach ($frameworks as $f) {
            $frameworkid = $f->get('id');
            $position = $manager->get_position_structure($frameworkid);
            $formattedname = $f->get_formatted_name();
            $isshared = $f->get('tenantid') != tenancy::get_tenant_id();
            $editablename = $this->get_position_editablename($f, $isshared, $output);
            $canedit = permission::can_edit_position($f);
            $editinsharedspace = '';
            if ($isshared && permission::can_edit_position_in_its_tenant($position)) {
                $editinsharedspace = helper::add_shared_space_button($f->get('tenantid'), $output, 'positions');
            }
            $strmove = get_string('movepositionframework', 'tool_organisation', $formattedname);
            // Disable drag&drop if positions in framework are more than 200.
            $disabledragdrop = !$canedit || $manager->too_many_children_disable_sorting($position);
            $frameworkinfo = [
                'moveicon' => $canedit ? $output->render_from_template('core/drag_handle', ['movetitle' => $strmove]) : '',
                'disabledragdrop' => (int)$disabledragdrop,
                'frameworkname' => $editablename,
                'frameworknameformatted' => $formattedname,
                'frameworkid' => $frameworkid,
                'contextid' => \context_system::instance()->id,
                'expanded' => ($frameworkid == $this->get_framework_id()) ? 1 : 0,
                'candelete' => empty($position->get_jobs_count($frameworkid)->totalwithchildren) && $canedit,
                'editinsharedspace' => $editinsharedspace,
                'canedit' => $canedit,
            ];
            $key = $isshared ? 'sharedframeworks' : 'frameworks';
            $data[$key][] = $frameworkinfo;
        }
        $data['noframeworks'] = empty($data['frameworks']) && empty($data['sharedframeworks']);
        return $data;
    }

    /**
     * Returns rendered position editable name
     *
     * @param position $f
     * @param bool $isshared
     * @param renderer_base $output
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function get_position_editablename(position $f, bool $isshared, renderer_base $output): string {
        if ($isshared) {
            // Add 'Shared space' badge to the name.
            return $f->get_formatted_name() . ' ' . \html_writer::span(get_string('sharedspace', 'tool_tenant'),
                    'badge badge-secondary ml-1');
        }

        // Get the Position inplace editable name.
        $editablename = $f->get_editable_name()->export_for_template($output);
        return $output->render_from_template('core/inplace_editable', $editablename);
    }
}
