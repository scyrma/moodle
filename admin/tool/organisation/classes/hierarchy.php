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
 * Class hierarchy
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation;

use core\output\inplace_editable;

defined('MOODLE_INTERNAL') || die();

/**
 * Class hierarchy
 *
 * @package     tool_organisation
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class hierarchy extends \core\persistent {

    /**
     * @var hierarchy[]
     */
    protected $children = [];

    /**
     * @var hierarchy
     */
    protected $parent = null;

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'name' => array(
                'type' => PARAM_TEXT,
                'description' => 'The entity name.',
            ),
            'archived' => array(
                'type' => PARAM_INT,
                'description' => 'Is archived.',
                'default' => 0,
            ),
            'sortorder' => array(
                'type' => PARAM_INT,
                'description' => 'Sort order',
                'default' => 0,
            ),
            'tenantid' => array(
                'type' => PARAM_INT,
                'description' => 'Tenant',
            ),
            'parentid' => array(
                'type' => PARAM_INT,
                'description' => 'Parent',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'pathlevel' => array(
                'type' => PARAM_INT,
                'description' => 'Level in hierarchy',
            ),
            'path' => array(
                'type' => PARAM_PATH,
                'description' => 'Path',
            ),
            'idnumber' => array(
                'type' => PARAM_RAW,
                'description' => 'ID number',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'description' => array(
                'type' => PARAM_RAW,
                'description' => 'Description',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'descriptionformat' => array(
                'type' => PARAM_INT,
                'description' => 'Description format',
                'default' => 0
            ),
            'timearchived' => array(
                'type' => PARAM_INT,
                'description' => 'When it was archived',
                'null' => NULL_ALLOWED,
                'default' => null
            ),
        );
    }

    /**
     * Adds a child hierarchy
     *
     * @param hierarchy $hierarchy
     * @return hierarchy
     */
    public function add_child(hierarchy $hierarchy) : hierarchy {
        $this->children[$hierarchy->get('id')] = $hierarchy;
        $hierarchy->set_parent($this);
        return $hierarchy;
    }

    /**
     * Sets the parent hierarchy
     *
     * @param hierarchy $hierarchy
     * @return hierarchy
     */
    protected function set_parent(hierarchy $hierarchy) : hierarchy {
        $this->parent = $hierarchy;
        return $hierarchy;
    }

    /**
     * Returns formatted name
     *
     * @return string
     */
    public function get_formatted_name() : string {
        return format_string($this->get('name'), true, ['context' => \context_system::instance()]);
    }

    /**
     * Generates inplace_editable object for the name
     *
     * @return inplace_editable
     */
    abstract public function get_editable_name() : inplace_editable;

    /**
     * Returns the top-most parent id or 0 if it is a framework
     *
     * @return int
     */
    public function get_framework_id() : int {
        $path = preg_split('|/|', $this->get('path'), -1, PREG_SPLIT_NO_EMPTY);
        $frameworkid = (int)reset($path);
        return $frameworkid;
    }

    /**
     * Checks if this is a top-most element in the hierarchy
     *
     * @return bool
     */
    public function is_framework() : bool {
        return !$this->get('parentid');
    }

    /**
     * Returns the children
     *
     * @return hierarchy[]
     */
    public function get_children() : array {
        return $this->children;
    }

    /**
     * Archive an instance (recursively for all children)
     *
     * @param bool $main
     */
    public function archive(bool $main = true) {
        if ($this->get('archived')) {
            return;
        }
        $this->set('archived', true);
        if ($main) {
            $this->set('timearchived', time());
        }
        $this->save();
        foreach ($this->children as $child) {
            $child->archive(false);
        }
    }

    /**
     * Restore archived instance (recursively for all children)
     *
     * @param bool $main
     */
    public function unarchive(bool $main = true) {
        if (!$this->get('archived')) {
            return;
        }
        if (!$main && $this->get('timearchived') !== null) {
            // This was already archived in another action.
            return;
        }

        $this->set('archived', false);
        $this->set('timearchived', null);
        $this->save();
        foreach ($this->children as $child) {
            $child->unarchive(false);
        }
    }
}
