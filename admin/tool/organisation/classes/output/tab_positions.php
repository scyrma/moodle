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
 * Class tab_positions
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\output;

use renderer_base;
use tool_organisation\permission;
use tool_organisation\position;
use tool_organisation\position_manager;
use tool_wp\output\tab;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tab_positions
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
            'addbuttontitle' => get_string('addpositionframework', 'tool_organisation'),
            'frameworks' => [],
        ];
        $manager = new position_manager();
        $frameworks = $manager->get_position_frameworks();
        foreach ($frameworks as $f) {
            $frameworkid = $f->get('id');
            $position = $manager->get_position_structure($frameworkid);
            $tree = new positions_tree($frameworkid, $position->get_children());
            $editablename = $f->get_editable_name()->export_for_template($output);
            $formattedname = $f->get_formatted_name();
            $strmove = get_string('movepositionframework', 'tool_organisation', $formattedname);
            $frameworkinfo = ['moveicon' => $output->render_from_template('core/drag_handle', ['movetitle' => $strmove]),
                              'frameworkname' => $output->render_from_template('core/inplace_editable', $editablename),
                              'frameworknameformatted' => $formattedname,
                              'frameworkid' => $frameworkid,
                              'expanded' => ($frameworkid == $this->get_framework_id()) ? 1 : 0];
            $data['frameworks'][] = array_merge($frameworkinfo, $tree->export_for_template($output));
        }
        $data['noframeworks'] = empty($data['frameworks']);
        return $data;
    }
}
