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

declare(strict_types=1);

namespace tool_organisation\reportbuilder\local\formatters;

use html_writer;
use stdClass;
use tool_organisation\organisation;
use tool_organisation\position;

/**
 * Formatters for the job entity
 *
 * @package   tool_organisation
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class job {

    /**
     * Renders global/department management icons.
     *
     * @param string|null $value
     * @param stdClass|null $row
     * @param string|null $permissiontype
     * @return string
     */
    public static function managementicons(?string $value, ?stdClass $row, ?string $permissiontype): string {
        global $OUTPUT;
        $output = '';

        if ($value === null) {
            return $output;
        }

        if (isset($permissiontype) && $permissiontype === 'departmentpermissions') {
            $permissions = organisation::get_department_manager_permissions();
        } else {
            $permissions = organisation::get_global_manager_permissions();
        }
        $perms = (int)$value;

        // Allocate programs = 1.
        if ($perms & organisation::PERM_ALLOCATE_PROGRAMS) {
            $output .= html_writer::span($OUTPUT->render($permissions[organisation::PERM_ALLOCATE_PROGRAMS]['icon']),
                '', ['title' => $permissions[organisation::PERM_ALLOCATE_PROGRAMS]['title']]);
        }

        // View reports = 2.
        if ($perms & organisation::PERM_VIEW_REPORTS) {
            $output .= html_writer::span($OUTPUT->render($permissions[organisation::PERM_VIEW_REPORTS]['icon']),
                '', ['title' => $permissions[organisation::PERM_VIEW_REPORTS]['title']]);
        }

        // Receive notifications = 4.
        if ($perms & organisation::PERM_RECEIVE_NOTIFICATIONS) {
            $output .= html_writer::span($OUTPUT->render($permissions[organisation::PERM_RECEIVE_NOTIFICATIONS]['icon']),
                '', ['title' => $permissions[organisation::PERM_RECEIVE_NOTIFICATIONS]['title']]);
        }

        return $output;
    }

    /**
     * Render permission icons as well as value
     *
     * @param string|null $value A string that could be name of department|position etc.
     * @param stdClass $row
     * @param string $permissiontype It could be globalmanager|departmentmanager etc.
     * @return string
     */
    public static function entityname_and_permissions(?string $value, stdClass $row, string $permissiontype = ''): string {
        global $OUTPUT;
        $pout = '';
        if (empty($permissiontype) || empty($row->id)) {
            return format_string($value);
        }
        unset($row->entityname);
        $position = new position(0, $row);
        $permissions = array_filter($position->get_node_roles_permissions(), static function ($el) use ($permissiontype) {
            return $el['permissiontype'] === $permissiontype;
        });
        foreach ($permissions as $permission) {
            $permission['showpermissionsonly'] = true;
            $pout .= $OUTPUT->render_from_template('tool_organisation/rolespermissions', $permission);
        }
        return format_string($value) . $pout;
    }
}
