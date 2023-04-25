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
 * This file contains the backend class for Manually added users audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\audiences;

use context_system;
use core_user;
use MoodleQuickForm;
use tool_reportbuilder\audience_base;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The backend class for Manually added users audience type
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manual extends audience_base {

    /**
     * Which audience class from core reportbuilder should this audience be migrated to
     *
     * @return string name of the class extending {@see \core_reportbuilder\local\audiences\base}
     */
    public function convert_get_audience_class(): string {
        return \core_reportbuilder\reportbuilder\audience\manual::class;
    }

    /**
     * Adds audience's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(MoodleQuickForm $mform): void {
        // Users selector.
        $options = [
            'ajax' => 'tool_wp/form-potential-user-selector',
            'data-component' => 'tool_reportbuilder',
            'data-area' => 'usersmanually',
            'data-itemid' => 0,
            'multiple' => true,
            'valuehtmlcallback' => function($userid) {
                $userinfo = core_user::get_user($userid);
                return '<span>' . fullname($userinfo) . '</span>';
            }
        ];

        $mform->addElement('autocomplete', 'users', get_string('addusers', 'tool_reportbuilder'), [], $options);
        $mform->addRule('users', null, 'required', null, 'client');
        $mform->addHelpButton('users', 'addusers', 'tool_reportbuilder');
    }

    /**
     * Helps to build SQL to retrieve users that matches the current report audience
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(string $usertablealias): array {
        global $DB;

        $users = $this->get_configdata()['users'];
        [$insql, $inparams] = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);

        return ['', "{$usertablealias}.id $insql", $inparams];
    }

    /**
     * Returns the title of the audience
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('manuallyaddedusers', 'tool_reportbuilder');
    }

    /**
     * Return the description for the audience.
     *
     * @return string
     */
    public function get_description(): string {
        global $DB;

        $canviewfullnames = has_capability('moodle/site:viewfullnames', context_system::instance());

        $userslist = [];

        $userids = $this->get_configdata()['users'];
        $users = $DB->get_records_list('user', 'id', $userids, 'lastname');
        foreach ($users as $user) {
            $userslist[] = fullname($user, $canviewfullnames);
        }

        return $this->format_description_for_multiselect($userslist);
    }

    /**
     * If the current user is able to add this audience.
     *
     * @return bool
     */
    public function user_can_add(): bool {
        return \tool_tenant\permission::can_browse_users() &&
            has_capability('moodle/user:viewalldetails', context_system::instance());
    }

    /**
     * If the current user is able to edit this audience.
     *
     * @return bool
     */
    public function user_can_edit(): bool {
        return \tool_tenant\permission::can_browse_users() &&
            has_capability('moodle/user:viewalldetails', context_system::instance());
    }

    /**
     * Add user field mapping during export
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        $userids = $this->get_configdata()['users'];
        foreach ($userids as $userid) {
            $exporter->add_mapping('user', $userid);
        }
    }

    /**
     * Get user field mapping during import
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        $mappeduserids = array_map(static function(int $userid) use ($importer): int {
            return $importer->get_mapping('user', $userid, IGNORE_MISSING) ?? -1;
        }, $this->get_configdata()['users']);

        $this->update_configdata(['users' => $mappeduserids]);
    }
}
