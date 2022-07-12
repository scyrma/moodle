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
 * File that contains the class certification_progress
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\reports;

use moodle_url;
use pix_icon;
use tool_certification\certification;
use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_certification\permission;
use tool_certification\local\helpers\format;
use tool_organisation\organisation;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

/**
 * This class defines a system report that shows the progress/completion/status of users within one given certification.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_progress extends system_report {
    /** @var certification */
    protected $certification;

    /**
     * Get current certiication
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $certificationid = $this->get_parameter('certificationid', 0, PARAM_INT);
            $this->certification = new certification($certificationid);
        }
        return $this->certification;
    }
    /**
     * Initialise report
     */
    protected function initialise(): void {

        $this->set_main_table('tool_certification_users', 'tcu');
        $this->add_base_join('INNER JOIN {user} u ON tcu.userid = u.id');
        $this->add_base_join('INNER JOIN {tool_certification} tc ON tc.id = tcu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_program} tp ON tp.id = tcu.currentprogramid');
        $this->add_base_join('LEFT JOIN {tool_program_users} tpu ON tpu.userid = tcu.userid AND tpu.programid = tp.id
            AND tpu.certificationid = tcu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc ON tcc.userid = tcu.userid
        AND tcc.certificationid = tc.id AND tcc.timerevoked = 0 AND tcc.islast = 1');

        // Tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_parent_shared_entities_sql('tc.tenantid', 'tc.shared=1');
        $this->add_base_condition_sql($sql, $params);

        // Managers with no system capability are only allowed to see the users they manage.
        if (!permission::has_allocateuser_capability($this->get_certification()->get_context()) &&
            $manager = organisation::get_user_with_jobs()) {
            [$where, $params] = $manager->get_managed_users_select('u', organisation::PERM_VIEW_REPORTS);
            $this->add_base_condition_sql($where, $params);
        }

        // Show only users from the current tenant and/or its subtenants.
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'tcu.userid'));

        $this->add_base_fields('tcu.userid');
        $this->add_base_condition_simple('tc.id', $this->get_certification()->get('id'));
        $this->add_base_condition_simple('u.deleted', 0);

        $this->set_columns();
        $this->add_actions();
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 1);
            $column->add_fields('tcu.id as certificationuserid,' . implode(',', $this->get_user_columns('u')));
            $column->set_callback([format::class, 'userinfo']);
        }
        if ($column = $this->get_column('tool_program_users:startdate')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_program_users:duedate')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_certification_users:expirydate')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_certification_users:timecreated')) {
            $column->set_is_default(true, 5);
        }
        if ($column = $this->get_column('tool_certification_users:allocationtype')) {
            $column->set_is_default(true, 6);
        }
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 7);
            $column->add_fields('tc.program, tp.id AS programid, tp.fullname as programfullname, tp.archived as programarchived');
            $column->set_callback([format::class, 'program']);
        }
        if ($column = $this->get_column('tool_certification_users:certificationstatus')) {
            $column->set_is_default(true, 8);
        }
        if ($column = $this->get_column('tool_program_users:programstatus')) {
            $column->set_is_default(true, 9);
        }
        if ($column = $this->get_column('tool_program_users:programprogress')) {
            $column->set_is_default(true, 10);
        }
        if ($column = $this->get_column('tool_certification_compltion:certifieddate')) {
            $column->set_is_default(true, 11);
        }
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_users_progress($this->get_certification());
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportcertificationprogress', 'tool_certification');
    }

    /**
     * Set the columns for the report.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new certificationuser_entity());
        $this->add_entity(new certification_entity());
        $this->add_entity(new certificationcompletion_entity());
        $this->add_entity(new user());
        $this->add_entity(new program_entity());
        $this->add_entity(new programuser_entity());
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $url = new moodle_url('/message/index.php', ['id' => ':userid']);
        $icon = new pix_icon('t/messages', get_string('sendmessage', 'core_message'), 'core');
        $action = new report_action($url, $icon);
        $this->add_action($action);

        $url = new moodle_url('/user/profile.php', ['id' => ':userid']);
        $icon = new pix_icon('i/user', get_string('profile'), 'core');
        $action = new report_action($url, $icon);
        $this->add_action($action);
    }

    /**
     * Returns an array of user column names prefixed with the given user table alias.
     * TODO we don't need all fields.
     *
     * @param string $u
     * @return array
     */
    private function get_user_columns(string $u): array {
        global $DB;
        return array_map(static function($column) use ($u) {
            return "$u.$column";
        }, array_keys($DB->get_columns('user')));
    }
}
