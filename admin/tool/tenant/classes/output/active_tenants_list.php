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
 * Class tenants_list
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant\output;

use tool_tenant\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tenants_list
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class active_tenants_list extends \tool_wp\output\table_tree {

    /** @var array */
    protected $exportedtenant;

    /**
     * Returns the list of the node children
     *
     * @param mixed|null $node null for the root element or the tree node
     * @return array|false list of children or false if this node can not have children
     */
    public function get_node_children($node = null) {
        if ($node !== null) {
            return false;
        }
        return (new manager())->get_tenants();
    }

    /**
     * Returns the contents of the cell
     *
     * When $node===null return the header of the column
     *
     * @param \renderer_base $output
     * @param string $columnname
     * @param mixed|null $node null for the root element or the tree node
     * @return string
     */
    protected function export_cell(\renderer_base $output, string $columnname, $node = null): string {
        if ($node === null) {
            if ($columnname === 'name') {
                return get_string('name', 'tool_tenant');
            } else if ($columnname === 'users') {
                return get_string('userscount', 'tool_tenant');
            } else if ($columnname === 'category') {
                return get_string('category');
            } else if ($columnname === 'actions') {
                return get_string('actions');
            }
            return $columnname;
        }

        // TODO SP-389 this is a little ugly, it's a result of quick refactoring.
        // Probably we need to move around code between \tool_tenant\output\tenant and this class.

        if (!$this->exportedtenant || $this->exportedtenant['id'] != $node->get('id')) {
            $this->exportedtenant = (new \tool_tenant\output\tenant($node))->export_for_template($output);
        }
        if ($columnname === 'name') {
            $name = $this->exportedtenant['editablename'];
            $draghandle = !empty($this->exportedtenant['movetitle']) ?
                $output->render_from_template('core/drag_handle', $this->exportedtenant) :
                $output->spacer(array('class' => 'icon'));
            return $draghandle . $output->render_from_template('core/inplace_editable', $name);
        } else if ($columnname === 'users') {
            return $this->exportedtenant['userscount'];
        } else if ($columnname === 'category') {
            return $this->exportedtenant['category'];
        } else if ($columnname === 'actions') {
            $s = '';
            foreach ($this->exportedtenant['actions'] as $action) {
                $s .= $output->render_from_template('core/action_link', $action);
            }
            return $s;
        }

        return '';

    }

    /**
     * Returns the width of the column. The total of all widths must be 12
     *
     * @param string $columnname
     * @return int
     */
    protected function get_column_width(string $columnname): int {
        if ($columnname === 'name') {
            return 5;
        } else if ($columnname === 'category') {
            return 3;
        }
        return 2;
    }

    /**
     * List of columns aliases
     *
     * @return string[]
     */
    protected function get_columns(): array {
        return ['name', 'users', 'category', 'actions'];
    }

}
