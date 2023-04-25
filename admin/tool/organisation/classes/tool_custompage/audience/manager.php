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

namespace tool_organisation\tool_custompage\audience;

use MoodleQuickForm;
use tool_organisation\helper;
use tool_organisation\permission;
use tool_custompage\local\audience\base;

/**
 * Custom page audience type based on a users with manager permission within the organisation structure
 *
 * @package   tool_organisation
 * @copyright 2022 Moodle Pty Ltd <support@moodle.com>
 * @author    2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager extends base {

    /**
     * Add form elements to set which kind of managers is added to audience
     *
     * @param MoodleQuickForm $mform
     */
    public function get_config_form(MoodleQuickForm $mform): void {

        // Options to users managers.
        $manageroptions = get_strings([
            'anymanager',
            'departmentmanager',
            'globalmanager'
        ], 'tool_organisation');

        $mform->addElement('select', 'permissions', get_string('positionpermissions',
            'tool_organisation'), (array) $manageroptions);
    }

    /**
     * Helps to build SQL to retrieve users with the settled audience
     *
     * @param string $usertablealias
     * @return array [$join, $where, [$params]]
     */
    public function get_sql(string $usertablealias): array {

        // Get configdata related to this audience.
        $configdata = $this->get_configdata()['permissions'];
        $managerparams = ['globalmanager' => 0, 'departmentmanager' => 0];

        if ($configdata === 'anymanager') {
            $managerparams['globalmanager'] = 1;
            $managerparams['departmentmanager'] = 1;
        } else {
            $managerparams[$configdata] = 1;
        }

        // Set conditions/params to help sql to retrieve users with managers permission.
        list($sql, $params) = helper::users_with_managerpermission_sql($usertablealias, $managerparams);

        return ['', $sql, $params];
    }

    /**
     * Return user friendly name of this audience type
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('audiencemanager', 'tool_organisation');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {

        // Get configdata related to this audience.
        $configdata = $this->get_configdata()['permissions'];

        // Strings uses to create description.
        $managerstrings = get_strings([
            'anymanager',
            'departmentmanager',
            'globalmanager'
        ], 'tool_organisation');

        return get_string('audiencemanagerdescription', 'tool_organisation', [
            'permissions' => $managerstrings->$configdata
        ]);
    }

    /**
     * If the current user is able to add this audience type
     *
     * @param bool $global True if current page is global, otherwise false
     * @return bool
     */
    public function user_can_add(bool $global = false): bool {
        return permission::has_assign_jobs_capability() || permission::user_is_manager();
    }

    /**
     * If the current user is able to edit this audience type. We don't differentiate between adding/editing this audience
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        return self::user_can_add();
    }

}
