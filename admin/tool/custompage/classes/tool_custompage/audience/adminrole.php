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

namespace tool_custompage\tool_custompage\audience;

use context_system;
use MoodleQuickForm;
use core_reportbuilder\local\helpers\database;
use tool_custompage\local\audience\base;
use tool_custompage\permission;
use tool_tenant\manager;

/**
 * Admin role audience type
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Carlos Castillo <carlos.castillo@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class adminrole extends base {

    /** @var int Value for selecting all admins. */
    private const ALL = 0;
    /** @var int Value for selecting only site admins. */
    private const ADMINS = 1;
    /** @var int Value for selecting only tenant admins. */
    private const TENANTADMIN = 2;

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $roles = $this->get_custompage_roles();
        $mform->addElement('select', 'roles', get_string('selectrole', 'role'), $roles);
    }

    /**
     * Helps to build SQL to retrieve users that matches the current audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        global $CFG, $DB;

        $rol = (int) $this->get_configdata()['roles'];
        $tenantadminrole = manager::get_tenant_admin_role();

        $adminprefix = database::generate_param_name() . '_';
        $contextidparam = database::generate_param_name();
        $tenantadminroleparam = database::generate_param_name() . '_';
        $ra = database::generate_alias();
        $ctx = database::generate_alias();

        $contextid = context_system::instance()->id;
        [$adminsql, $adminparams] = $DB->get_in_or_equal(explode(',', $CFG->siteadmins), SQL_PARAMS_NAMED, $adminprefix);

        $join = " LEFT JOIN {role_assignments} {$ra} ON {$ra}.userid = {$usertablealias}.id
                LEFT JOIN {context} {$ctx} ON {$ctx}.id = {$ra}.contextid";

        if ($rol == self::ALL) {
            $where = "{$ra}.contextid = :{$contextidparam} AND {$ra}.roleid = :{$tenantadminroleparam}
                OR {$usertablealias}.id {$adminsql}";
            $params = [
                $contextidparam => $contextid,
                $tenantadminroleparam => $tenantadminrole
            ] + $adminparams;
        } else if ($rol == self::ADMINS) {
            $join = "";
            $where = "{$usertablealias}.id {$adminsql}";
            $params = $adminparams;
        } else {
            $where = "{$ra}.contextid = :{$contextidparam} AND {$ra}.roleid = :{$tenantadminroleparam}";
            $params = [
                $contextidparam => $contextid,
                $tenantadminroleparam => $tenantadminrole
            ];
        }

        return [$join, $where, $params];
    }

    /**
     * Return user friendly name of this audience type
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('administrators');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        $roles = $this->get_configdata()['roles'];
        return $this->get_custompage_roles()[$roles];
    }

    /**
     * If the current user is able to add this audience.
     *
     * @param bool $global True if current page is global, otherwise false
     * @return bool
     */
    public function user_can_add(bool $global = false): bool {
        return $global && permission::can_create_global_page();
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        return permission::can_create_global_page();
    }

    /**
     * Return all administrator roles for this audience.
     *
     * @return array
     */
    private function get_custompage_roles(): array {
        return [
            self::ALL => get_string('allsiteadmin', 'tool_custompage'),
            self::ADMINS => get_string('siteadministrators', 'role'),
            self::TENANTADMIN => get_string('tenantadmins', 'tool_tenant')
        ];
    }
}
