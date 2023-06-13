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
 * Class containing helper methods for format columns data as callbacks.
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Suraj Kumar
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_organisation\local\helpers;

use tool_organisation;
use stdClass;
use tool_organisation\organisation;
use tool_organisation\position;
use tool_reportbuilder\helper;

/**
 * Class format
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Suraj Kumar
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class format {

    /**
     * Render permission icons as well as value
     *
     * @param string $value A string that could be name of department|position etc.
     * @param stdClass $row
     * @param string $permissiontype It could be globalmanager|departmentmanager etc.
     * @return string
     */
    public static function entityname_and_permissions(?string $value, stdClass $row, $permissiontype = ''): string {
        global $OUTPUT;
        $pout = '';
        if (empty($permissiontype) || empty($row->id)) {
            return format_string($value);
        }
        unset($row->entityname);
        $position = new position(0, $row);
        $permissions = array_filter($position->get_node_roles_permissions(), function ($el) use ($permissiontype) {
            return $el['permissiontype'] === $permissiontype;
        });
        foreach ($permissions as $permission) {
            $permission['showpermissionsonly'] = true;
            $pout .= $OUTPUT->render_from_template('tool_organisation/rolespermissions', $permission);
        }
        return format_string($value) . $pout;
    }

    /**
     * Render roles with permission icons
     *
     * @param string $value
     * @param stdClass $row
     * @return string
     */
    public static function permissions_with_icons(?string $value, stdClass $row): string {
        global $OUTPUT;
        $pout = '';
        $position = new position(0, $row);
        $permissions = $position->get_node_roles_permissions();
        foreach ($permissions as $permission) {
            $pout .= $OUTPUT->render_from_template('tool_organisation/rolespermissions', $permission);
        }
        return $pout;
    }

    /**
     * Renders global/department management icons.
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string $permissiontype
     * @return string
     */
    public static function managementicons($value, ?stdClass $row, $permissiontype): string {
        global $OUTPUT;
        $output = '';

        if ($value === null) {
            return $output;
        }

        if ($permissiontype === 'departmentpermissions') {
            $permissions = organisation::get_department_manager_permissions();
        } else {
            $permissions = organisation::get_global_manager_permissions();
        }
        $perms = (int)$value;

        // Allocate programs = 1.
        if ($perms & organisation::PERM_ALLOCATE_PROGRAMS) {
            $output .= $OUTPUT->render($permissions[organisation::PERM_ALLOCATE_PROGRAMS]['icon']);
        }

        // View reports = 2.
        if ($perms & organisation::PERM_VIEW_REPORTS) {
            $output .= $OUTPUT->render($permissions[organisation::PERM_VIEW_REPORTS]['icon']);
        }

        // Receive notifications = 4.
        if ($perms & organisation::PERM_RECEIVE_NOTIFICATIONS) {
            $output .= $OUTPUT->render($permissions[organisation::PERM_RECEIVE_NOTIFICATIONS]['icon']);
        }

        return $output;
    }

    /**
     * Renders global/department management icons.
     *
     * @param string|null $value
     * @param stdClass $row
     * @param string $permissiontype
     * @return string
     */
    public static function managementicons_group($value, stdClass $row, $permissiontype): string {
        $separator = helper::get_list_separator();
        $values = preg_split('/' . preg_quote($separator, '/') . '/',
            $value, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($values as $idx => $value) {
            $values[$idx] = self::managementicons($value, null, $permissiontype);
        }
        return join($separator, $values);
    }

    /**
     * Formatter for job start/end dates - show only date and always in the server timezone
     *
     * We want to make sure that jobs dates are always shown the same regardless of user
     * timezone.
     *
     * @param mixed $value
     * @param stdClass $row
     */
    public static function jobdate($value, ?stdClass $row = null) {
        global $CFG;
        return $value ? userdate($value, get_string('strftimedatefullshort'), $CFG->timezone) : '';
    }
}
