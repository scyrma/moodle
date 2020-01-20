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
 * Class positions_tree
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\output;

use renderer_base;
use tool_organisation\position;
use tool_organisation\helper;
use tool_wp\output\table_tree;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot.'/admin/tool/organisation/lib.php');

/**
 * Class positions_tree
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class positions_tree extends table_tree {

    /** @var array */
    protected $columns;

    /** @var position[] */
    protected $toplevelnodes;

    /** @var int */
    protected $frameworkid;

    /**
     * positions_tree constructor.
     *
     * @param int $frameworkid
     * @param position[] $toplevelnodes
     */
    public function __construct(int $frameworkid, array $toplevelnodes) {
        $this->frameworkid = $frameworkid;
        $this->toplevelnodes = $toplevelnodes;
        $this->columns = [
            'name' => get_string('positionname', 'tool_organisation'),
        ];
        if ($this->frameworkid) {
            $this->columns['jobs'] = helper::get_string_with_help_icon('jobsnumber', 'tool_organisation');
            $this->columns['roles'] = get_string('roleslist', 'tool_organisation');
        }
        $this->columns['actions'] = get_string('actions', 'tool_organisation');
    }

    /**
     * Returns the list of the node children
     *
     * @param mixed|null $node null for the positions root element or the tree node
     * @return array|false list of children or false if this node can not have children
     */
    protected function get_node_children($node = null) {
        if (!$node) {
            return $this->toplevelnodes;
        } else if ($this->frameworkid && ($node instanceof position)) {
            return $node->get_children();
        }
        return false;
    }

    /**
     * List of columns aliases
     *
     * @return string[]
     */
    protected function get_columns(): array {
        return array_keys($this->columns);
    }

    /**
     * Returns the width of the column. The total of all widths must be 12
     *
     * @param string $columnname
     * @return int
     */
    protected function get_column_width(string $columnname): int {
        if ($columnname === 'roles') {
            return 3;
        }
        if ($columnname === 'name') {
            return $this->frameworkid ? 5 : 10;
        }
        return 2;
    }

    /**
     * Attributes to add to the node <div> element (may include data- attributes, id, class)
     *
     * @param position|null $node null for the root positions element or the tree node
     * @return array associative array with attributes
     */
    protected function get_node_attributes($node = null): array {
        $attributes = [];
        $attributes['data-position-id'] = $node ? $node->get('id') : $this->frameworkid;
        return $attributes;
    }

    /**
     * Returns the contents of the cell
     *
     * When $node === null return the header of the column
     *
     * @param renderer_base $output
     * @param string $columnname
     * @param position|null $node null for the root element or the tree node
     * @return string
     */
    protected function export_cell(renderer_base $output, string $columnname, $node = null): string {
        if (!$node) {
            return $this->columns[$columnname];
        }

        $s = '';
        if ($columnname === 'name') {
            $s .= $output->render_from_template('core/drag_handle',
                    ['movetitle' => get_string('move')]) .
                $output->render_from_template('core/inplace_editable',
                    $node->get_editable_name()->export_for_template($output));
        } else if ($columnname === 'actions') {
            foreach ($this->get_actions($node) as $action) {
                $s .= $output->render_from_template('core/action_link',
                    $action->export_for_template($output));
            }
        } else if ($columnname === 'jobs') {
            // TODO SP-394 improve performance by not calling it in a loop.
            if ($jobscount = \tool_organisation\job::get_postion_jobs_count($node->get('id')) ) {
                $s = ($jobscount->active || $jobscount->expired) ? $jobscount->active . "&nbsp;(" . $jobscount->expired . ")" : "0";
            } else {
                $s = '0';
            }
        } else if ($columnname === 'roles') {
            // Get roles permissions of position node.
            foreach ($this->get_node_permissions($node) as $permission) {
                $s .= $output->render_from_template('tool_organisation/rolespermissions',
                    $permission);
            }
        }
        return $s;
    }

    /**
     * Attributes to add to the tree <div> element (may include data- attributes, id, class)
     *
     * @return array associative array with attributes
     */
    protected function get_tree_attributes(): array {
        return ['data-framework-id' => $this->frameworkid, 'class' => 'tool-organisation-positions'];
    }

    /**
     * Actions for a position
     *
     * @param position $node
     * @return \action_link[]
     */
    protected function get_actions($node): array {
        $url = new \moodle_url('#');
        $formattedname = $node->get_formatted_name();
        if ($node->is_framework()) {
            $straddposition = get_string('addposition', 'tool_organisation', $formattedname);
            $streditposition = get_string('editpositionframework', 'tool_organisation', $formattedname);
            $strdeleteposition = get_string('deletepositionframework', 'tool_organisation', $formattedname);
        } else {
            $straddposition = get_string('addchildposition', 'tool_organisation', $formattedname);
            $streditposition = get_string('editposition', 'tool_organisation', $formattedname);
            $strdeleteposition = get_string('deleteposition', 'tool_organisation', $formattedname);
        }
        $return = [];
        if (!$node->is_framework()) {
            $return[] = new \action_link($url, '', null,
                ['data-action' => 'addchild', 'data-positionid' => $node->get('id'), 'title' => $straddposition],
                new \pix_icon('t/add', $straddposition, 'core'));
        }
        $return[] = new \action_link($url, '', null,
            ['data-action' => 'edit', 'data-positionid' => $node->get('id'), 'title' => $streditposition],
            new \pix_icon('i/settings', $streditposition, 'core'));
        $return[] = new \action_link($url, '', null,
            ['data-action' => 'delete', 'data-positionid' => $node->get('id'), 'data-name' => $formattedname],
            new \pix_icon('i/trash', $strdeleteposition, 'core'));
        return $return;
    }

    /**
     * Hardcoded roles permissions of node.
     * @param position|null $node null for the root element or the tree node
     * @return array|null
     */
    protected function get_node_permissions(position $node = null):array {
        $permissions = [];
        if ($node) {
            $permissions = $node->get_node_roles_permissions();
        }
        return $permissions;
    }
}
