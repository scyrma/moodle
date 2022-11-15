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
 * This file contains the backend class for Has system role audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\audiences;

use context_system;
use MoodleQuickForm;
use tool_reportbuilder\audience_base;
use tool_wp\db;

/**
 * The backend class for Has system role audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class systemrole extends audience_base {

    /**
     * Which audience class from core reportbuilder should this audience be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\audiences\base}
     */
    public function convert_get_audience_class(): string {
        return \core_reportbuilder\reportbuilder\audience\systemrole::class;
    }

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);

        $mform->addElement('autocomplete', 'roles', get_string('selectrole', 'role'), $roles, ['multiple' => true]);
        $mform->addRule('roles', null, 'required', null, 'client');
        $mform->addHelpButton('roles', 'addusers', 'tool_reportbuilder'); // TODO.
    }

    /**
     * Helps to build SQL to retrieve users that matches the current audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        global $DB;

        $roles = $this->get_configdata()['roles'];
        [$insql, $inparams] = $DB->get_in_or_equal($roles, SQL_PARAMS_NAMED);

        $contextid = db::generate_param_name();
        $ra = db::generate_alias();
        $ctx = db::generate_alias();

        $join = "
            JOIN {role_assignments} {$ra} ON {$ra}.userid = {$usertablealias}.id
            JOIN {context} {$ctx} ON {$ctx}.id = {$ra}.contextid";

        $where = "{$ra}.contextid = :{$contextid} AND {$ra}.roleid {$insql}";

        return [$join, $where, $inparams + [$contextid => context_system::instance()->id]];
    }

    /**
     * Returns the title of the audience
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('hassystemrole', 'tool_reportbuilder');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;
        $rolesids = $this->get_configdata()['roles'];
        $roles = $DB->get_records_list('role', 'id', $rolesids, 'sortorder');
        $rolesfixed = role_fix_names($roles, context_system::instance(), ROLENAME_ALIAS, true);
        return $this->format_description_for_multiselect($rolesfixed);
    }

    /**
     * If the current user is able to add this audience.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        // Check if user is able to assign any role from the system context.
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        if (empty($roles)) {
            return false;
        }

        return true;
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        // Check if user can assign all saved role types on this audicence instance.
        $roleids = $this->get_configdata()['roles'];
        $roles = get_assignable_roles(context_system::instance(), ROLENAME_ALIAS);
        if (!empty(array_diff($roleids, array_keys($roles)))) {
            return false;
        }

        return true;
    }
}
