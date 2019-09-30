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
 * Class tenant_group
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

/**
 * Class tenant_group
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_group extends \core\persistent {

    /** The table name. */
    const TABLE = 'tool_tenant_group';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'courseid' => array(
                'type' => PARAM_INT,
                'description' => 'Course id',
            ),
            'tenantid' => array(
                'type' => PARAM_INT,
                'description' => 'Tenant id',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'groupid' => array(
                'type' => PARAM_INT,
                'description' => 'Group id',
            ),
            'component' => array(
                'type' => PARAM_COMPONENT,
                'description' => 'Component (if known)',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'area' => array(
                'type' => PARAM_COMPONENT,
                'description' => 'Area (if known)',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
            'itemid' => array(
                'type' => PARAM_INT,
                'description' => 'Item id from the component table',
                'default' => null,
                'null' => NULL_ALLOWED,
            ),
        );
    }

    /**
     * Delete all group associations for a given course
     *
     * @param int $courseid
     */
    public static function delete_for_course(int $courseid) {
        $records = self::get_records(['courseid' => $courseid]);
        foreach ($records as $record) {
            $record->delete();
        }
    }

    /**
     * Delete all group associations for a given tenant
     *
     * @param int $tenantid
     */
    public static function delete_for_tenant(int $tenantid) {
        $records = self::get_records(['tenantid' => $tenantid]);
        foreach ($records as $record) {
            $record->delete();
        }
    }

    /**
     * Delete all group association for a given component/area/itemid
     *
     * @param string $component
     * @param string $area
     * @param int $itemid
     */
    public static function delete_for_component(string $component, string $area, int $itemid) {
        $records = self::get_records(['component' => $component, 'area' => $area, 'itemid' => $itemid]);
        foreach ($records as $record) {
            $record->delete();
        }
    }
}
