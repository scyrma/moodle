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

namespace tool_wp\output;

use core\output\notification;
use renderer_base;

/**
 * Tree displayed as a table
 *
 * @package     tool_wp
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class table_tree implements \templatable {

    /**
     * List of columns aliases
     *
     * @return string[]
     */
    abstract protected function get_columns(): array;

    /**
     * Number of columns
     *
     * @return mixed
     */
    protected function get_columns_count() {
        $columns = $this->get_columns();
        return max(1, count($columns));
    }

    /**
     * Returns the width of the column. The total of all widths must be 12
     *
     * @param string $columnname
     * @return int
     */
    protected function get_column_width(string $columnname): int {
        $columns = array_values($this->get_columns());
        $count = $this->get_columns_count();
        $minwidth = floor(12 / $count);
        if ($columnname === $columns[0]) {
            return 12 - $minwidth * ($count - 1);
        } else {
            return $minwidth;
        }
    }

    /**
     * CSS class to add to the cell
     *
     * @param string $columnname name of the column
     * @param mixed|null $node null for the root element or the tree node
     * @return string
     */
    protected function get_cell_class(string $columnname, $node = null): string {
        return 'col-' . $this->get_column_width($columnname) . ' col-' . $columnname;
    }

    /**
     * Attributes to add to the node <div> element (may include data- attributes, id, class)
     *
     * @param mixed|null $node null for the root element or the tree node
     * @return array associative array with attributes
     */
    protected function get_node_attributes($node = null): array {
        return [];
    }

    /**
     * Attributes to add to the tree <div> element (may include data- attributes, id, class)
     *
     * @return array associative array with attributes
     */
    protected function get_tree_attributes(): array {
        return [];
    }

    /**
     * Returns the list of the node children
     *
     * @param mixed|null $node null for the root element or the tree node
     * @return array|false list of children or false if this node can not have children
     */
    abstract protected function get_node_children($node = null);

    /**
     * Returns the contents of the cell
     *
     * When $node===null return the header of the column
     *
     * @param renderer_base $output
     * @param string $columnname
     * @param mixed|null $node null for the root element or the tree node
     * @return string
     */
    abstract protected function export_cell(renderer_base $output, string $columnname, $node = null): string;

    /**
     * Exports one node (called recursively)
     *
     * @param renderer_base $output
     * @param mixed|null $node null for the root element or the tree node
     * @return array
     */
    protected function export_node(renderer_base $output, $node) {
        $value = [
            'columns' => [],
            'canhavechildren' => false,
            'children' => [],
            'nodeattributes' => [],
            'nodeclass' => ''
        ];
        $columns = $this->get_columns();
        foreach ($columns as $columnname) {
            $value['columns'][] = [
                'class' => $this->get_cell_class($columnname, $node),
                'content' => $this->export_cell($output, $columnname, $node)
            ];
        }
        foreach ($this->get_node_attributes($node) as $name => $attribute) {
            if ($name === 'class') {
                $value['nodeclass'] = $attribute;
            } else {
                $value['nodeattributes'][] = ['name' => $name, 'value' => $attribute];
            }
        }
        if (($children = $this->get_node_children($node)) !== false) {
            $value['canhavechildren'] = true;
            foreach ($children as $child) {
                $value['children'][] = $this->export_node($output, $child);
            }
        }
        return $value;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $value = $this->export_node($output, null) + ['treeclass' => '', 'treeattributes' => []];
        foreach ($this->get_tree_attributes() as $name => $attribute) {
            if ($name === 'class') {
                $value['treeclass'] = $attribute;
            } else {
                $value['treeattributes'][] = ['name' => $name, 'value' => $attribute];
            }
        }
        $value['tableisempty'] = false;
        if (empty($value['children'])) {
            $value['tableisempty'] = true;
            $notification = new notification(get_string('nothingtodisplay'), notification::NOTIFY_INFO);
            $notification->set_show_closebutton();
            $value['nothingtodisplay'] = $output->render($notification);
        }
        return $value;
    }
}
