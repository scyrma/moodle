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

namespace block_myteams\reportbuilder\local\systemreports;

use block_myteams\api;
use core_reportbuilder\system_report;
use moodle_url;
use stdClass;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\reportbuilder\local\entities\job;
use tool_tenant\permission;
use tool_tenant\tenancy;
use core_reportbuilder\local\entities\user;

/**
 * System report class for showing managed users for the current user
 *
 * @package     block_myteams
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Mikel Martín <mikel@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class managed_users extends system_report {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('user', 'u');

        $this->add_base_condition_simple('u.deleted', 0);

        // If user cannot view suspended or not confirmed users, then don't show they in the report.
        if (!permission::can_view_inactive_users(tenancy::get_tenant_id())) {
            $this->add_base_condition_sql('u.suspended = 0 AND u.confirmed = 1');
        }

        $this->add_base_fields('u.confirmed, u.suspended'); // Necessary for get_row_class.

        $user = organisation::get_user_with_jobs();
        if (!$user || !$user->is_manager()) {
            // Nothing to show for non-manager.
            $this->add_base_condition_sql('1=2');
        } else {
            list($where, $params) = helper::get_managed_users_select($user);
            $this->add_base_condition_sql($where, $params);
        }

        // Add our report entities.
        $this->add_entity((new user())->set_table_alias('user', $this->get_main_table_alias()));
        $this->add_entity(new job());

        $this->add_columns();
        $this->add_filters();

        $this->set_downloadable(false);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return true;
    }

    /**
     * Set the columns for the report.
     */
    protected function add_columns(): void {
        $usertablealias = $this->get_main_table_alias();

        // Column "userinfo".
        $this->add_column_from_entity('user:fullnamewithpicture')
            ->set_title(null)
            ->add_fields("{$usertablealias}.lastaccess, {$usertablealias}.suspended, {$usertablealias}.confirmed,
                {$usertablealias}.deleted")
            ->add_callback([$this, 'userinfo']);

        $this->set_initial_sort_column('user:fullnamewithpicture', SORT_ASC);
    }

    /**
     * Define report filters
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'user:fullname',
            'job:orgstructure',
        ]);
    }

    /**
     * User information callback (uses {@see userinfo_section::get_order} to order the sections)
     *
     * @param mixed $value
     * @param stdClass $row
     * @return string
     */
    public function userinfo($value, stdClass $row): string {
        global $CFG, $USER, $PAGE;
        require_once($CFG->dirroot.'/user/lib.php');

        $lastaccess = $row->lastaccess ? format_time(time() - $row->lastaccess) : get_string('never');
        $lastaccessdate = $row->lastaccess
            ? userdate($row->lastaccess, get_string('strftimedatetimeshort', 'langconfig'))
            : get_string('never');
        $data = [
            'id' => (int) $row->id,
            'fullnamewithpicture' => $value,
            'lastaccess' => $lastaccess,
            'lastaccessdate' => $lastaccessdate,
            'profileurl' => null,
            'messageurl' => null,
            'isoverdue' => false,
            'sections' => []
        ];

        // Ensure user can be messaged.
        if (!empty($CFG->messaging) && \core_message\api::can_send_message($data['id'], (int) $USER->id)) {
            $data['messageurl'] = new moodle_url('/message/index.php', ['id' => $data['id']]);
        }

        // Ensure user can view user row profile.
        if (user_can_view_profile($row)) {
            $data['profileurl'] = new moodle_url('/user/profile.php', ['id' => $data['id']]);
        }

        // Look for plugins adding a section to user information.
        [$data['sections'], $data['isoverdue']] = api::get_all_user_sections((int) $row->id);

        $output = $PAGE->get_renderer('block_myteams');
        return $output->render_from_template('block_myteams/userinfo/main', $data);
    }

    /**
     * CSS class for the row.
     *
     * @param stdClass $row
     * @return string
     */
    public function get_row_class(stdClass $row): string {
        return ($row->suspended || !$row->confirmed) ? 'text-muted' : '';
    }
}
