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
 * Class program_content_table
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\output;

use context;
use core\output\inplace_editable;
use pix_icon;
use renderer_base;
use tool_program\external\program_tree_exporter;
use tool_program\form\edit_program_set_completion_form;
use tool_program\permission;
use tool_program\persistent\program;
use tool_program\program_tree;
use stdClass;
use tool_tenant\tenancy;
use tool_wp\output\table_tree;

defined('MOODLE_INTERNAL') || die();

/**
 * Class program_content_table
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class program_content_table extends table_tree {

    /** @var program_tree_exporter */
    protected $exporter;
    /** @var stdClass */
    protected $exportedparentset;
    /** @var bool Wether the given program content structure is editable by the current user */
    private $editable;

    /**
     * program_content_table constructor.
     *
     * @param program $program
     * @param context $context
     */
    public function __construct(program $program, context $context) {
        $this->editable = permission::can_edit_details($program);
        $programtree = new program_tree($program);
        $this->exporter = new program_tree_exporter(null,
            ['context' => $context, 'programtree' => $programtree]);
    }

    /**
     * List of columns aliases
     *
     * @return string[]
     */
    protected function get_columns(): array {
        if (!$this->editable) {
            return ['name', 'completion'];
        }

        return ['name', 'completion', 'actions'];
    }

    /**
     * Returns the width of the column. The total of all widths must be 12
     *
     * @param string $columnname
     * @return int
     */
    protected function get_column_width(string $columnname): int {
        if ($columnname === 'name') {
            return $this->editable ? 7 : 8;
        }

        if ($columnname === 'completion') {
            return $this->editable ? 3 : 4;
        }

        return 2;
    }

    /**
     * Returns the list of the node children
     *
     * @param mixed|null $node null for the root element or the tree node
     * @return array|false list of children or false if this node can not have children
     */
    protected function get_node_children($node = null) {
        if ($node === null) {
            return $this->exportedparentset->items;
        }

        if (!empty($node->courseid)) {
            return false;
        }

        return $node->items;
    }

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
    protected function export_cell(renderer_base $output, string $columnname, $node = null): string {
        if ($node === null) {
            // Header.
            if ($columnname === 'name') {
                return get_string('name', 'tool_program');
            }

            if ($columnname === 'completion') {
                return get_string('completion', 'tool_program')
                    . $output->help_icon('completion', 'tool_program');
            }

            if ($columnname === 'actions') {
                return get_string('actions', 'tool_program');
            }

            return $columnname;
        }

        // Content.
        if ($columnname === 'completion' && empty($node->courseid)) {
            return $this->export_set_completion_form($output, $node);
        }

        if ($columnname === 'name' && empty($node->courseid)) {
            return $this->export_set_name($output, $node);
        }

        if ($columnname === 'name') {
            return $this->export_course_name($output, $node);
        }

        if ($columnname === 'actions') {
            return $this->export_actions($output, $node);
        }

        return '';
    }

    /**
     * Exports content for the Completion column for the sets
     *
     * @param renderer_base $output
     * @param stdClass $node
     * @return string
     */
    private function export_set_completion_form(renderer_base $output, stdClass $node): string {
        $customdata = [
            'id' => $node->id,
        ];
        $attributes = [
            'id' => 'set_completion_form_' . $node->id,
            'data-setid' => $node->id,
        ];
        $mform = new edit_program_set_completion_form('javascript:', $customdata, 'post', '', $attributes,
            $this->editable);
        $mform->set_data((object) [
            'setid' => $node->id,
            'completioncriteria' => $node->completioncriteria,
            'completionatleast' => $node->completionatleast,
        ]);
        return $mform->render();
    }

    /**
     * Export contents for the name column for sets
     *
     * @param renderer_base $output
     * @param stdClass $node
     * @return string
     */
    protected function export_set_name(renderer_base $output, stdClass $node): string {
        $edithint = get_string('editsetname', 'tool_program');
        $displayvalue = $node->name;
        $editlabel = get_string('newnameforset', 'tool_program', $displayvalue);
        $inlineeditable = new inplace_editable('tool_program', 'setname', $node->id, $this->editable,
            $displayvalue, $node->editablename, $edithint, $editlabel);
        $setname = '';
        if ($this->editable) {
            $setname .= $output->render_from_template('core/drag_handle',
                ['movetitle' => get_string('movecontent', 'moodle', $node->name)]);
        }
        $setname .= $output->pix_icon('a/view_tree_active', '', 'core');
        $setname .= $output->render($inlineeditable);

        return $setname;
    }

    /**
     * Export contents for the name column for courses
     *
     * @param renderer_base $output
     * @param stdClass $node
     * @return string
     */
    protected function export_course_name(renderer_base $output, stdClass $node): string {
        $displayvalue = $node->name;
        $columnname = '';
        if ($this->editable) {
            $columnname .= $output->render_from_template('core/drag_handle',
                ['movetitle' => get_string('movecontent', 'moodle', $displayvalue)]);
        }
        $courseurl = new \moodle_url('/course/view.php', ['id' => $node->courseid]);
        $displayvalue = \html_writer::link($courseurl, $displayvalue);
        $columnname .= $displayvalue . $node->warning;

        return $columnname;
    }

    /**
     * Exports contens for the actions column
     *
     * @param renderer_base $output
     * @param stdClass $node
     * @return string
     */
    protected function export_actions(renderer_base $output, stdClass $node): ?string {
        if (!$this->editable) {
            return '';
        }
        if (empty($node->courseid)) {
            return $output->render_from_template('tool_program/edit_program_content_add_set', $node) .
                ' ' . $output->action_icon('#', new pix_icon('i/trash',
                    get_string('delete'), 'core'), null,
                    ['class' => 'delete-set']);
        }

        return $output->action_icon('#', new pix_icon('i/trash',
            get_string('delete'), 'core'), null,
            ['class' => 'delete-course']);
    }

    /**
     * Attributes to add to the node <div> element (may include data- attributes, id, class)
     *
     * @param mixed|null $node null for the root element or the tree node
     * @return array associative array with attributes
     */
    protected function get_node_attributes($node = null): array {
        $rv = [];
        $rv['class'] = 'program-item';
        if ($node !== null) {
            $rv['data-isset'] = empty($node->courseid) ? 1 : 0;
            $rv['data-id'] = $node->id;
            $rv['data-name'] = $node->name;
        } else {
            $rv['data-isset'] = 1;
            $rv['data-id'] = $this->exportedparentset->id;
            $rv['data-name'] = $this->exportedparentset->name;
        }
        return $rv;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return array|stdClass
     */
    public function export_for_template(renderer_base $output) {
        $this->exportedparentset = $this->exporter->export($output)->parentset;
        $rv = parent::export_for_template($output);
        $rv['mainset'] = [
            'id' => $this->exportedparentset->id,
            'setcompletionform' => $this->export_set_completion_form($output, $this->exportedparentset)
        ];
        $rv['programid'] = $this->exportedparentset->programid;
        return $rv;
    }
}
