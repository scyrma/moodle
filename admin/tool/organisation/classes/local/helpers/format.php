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
 * Class containing helper methods for format columns data as callbacks.
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Suraj Kumar
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\local\helpers;

use tool_organisation;
use stdClass;
use tool_organisation\organisation;
use tool_reportbuilder\aggregation_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Class format
 *
 * @package     tool_organisation
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Suraj Kumar
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format {

    /** Render permission icons as well as value
     *
     * @param string $value A string that could be name of department|position etc.
     * @param stdClass $row
     * @param string $permissiontype It could be globalmanager|departmentmanager etc.
     * @return string
     */
    public static function entityname_and_permissions(string $value, stdClass $row, $permissiontype = ''): string {
        global $OUTPUT;
        $pout = '';
        if (empty($permissiontype)) {
            return format_string($value);
        }
        $position = new tool_organisation\position($row->positionid);
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
        $separator = aggregation_base::get_list_separator();
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