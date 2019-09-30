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
 * File that contains the class certification_progress
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use pix_icon;
use tool_certification\certification;
use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_certification\permission;
use tool_certification\local\helpers\format;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;

/**
 * This class defines a system report that shows the progress/completion/status of users within one given certification.
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');
        $this->add_base_join('LEFT JOIN {tool_program_users} tpu
        ON tpu.userid = tcu.userid AND tpu.programid = tp.id AND tpu.certificationid = tc.id');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc ON tcc.userid = tcu.userid
        AND tcc.certificationid = tc.id AND tcc.timerevoked = 0');

        $this->add_base_fields('tcu.userid');
        $this->add_base_condition_simple('tc.id', $this->get_certification()->get('id'));
        $this->add_base_condition_simple('tc.tenantid', tenancy::get_tenant_id());
        $this->add_base_condition_simple('u.deleted', 0);

        $this->set_columns();
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);

        // Default columns.
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 1);
            $column->add_fields('tcu.id as certificationuserid,' . implode(',', $this->get_user_columns('u')));
            $column->set_callback([format::class, 'userinfo']);
        }
        if ($column = $this->get_column('tool_certification_users:startdate')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_certification_users:duedate')) {
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
            $column->add_fields('tpu.programid, tpu.certificationid, tpu.userid');
        }
        if ($column = $this->get_column('tool_program_users:programprogress')) {
            $column->set_is_default(true, 10);
            $column->add_fields('tcu.userid, tcu.certificationid, tc.program as programid');
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
        $this->add_entity(new certificationuser_entity('', 'tcu', $this->get_certificationuser_excluded_columns()));
        $this->add_entity(new certification_entity('', 'tc', $this->get_certification_excluded_columns()));
        $this->add_entity(new certificationcompletion_entity('', 'tcc', $this->get_certificationcompletion_excluded_columns()));
        $this->add_entity(new user('', 'u', []));
        $this->add_entity(new program_entity('', 'tp', $this->get_program_excluded_columns()));
        $this->add_entity(new programuser_entity('', 'tpu', $this->get_programuser_excluded_columns()));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        $url = new moodle_url('/message/index.php', ['id' => ':userid']);
        $icon = new pix_icon('t/messages', get_string('sendmessage', 'core_message'), 'core');
        $params = [
            'class' => 'action-icon',
        ];
        $action = new report_action($url, $icon, $params);
        $this->add_action($action);

        $url = new moodle_url('/user/profile.php', ['id' => ':userid']);
        $icon = new pix_icon('i/user', get_string('profile'), 'core');
        $params = [
            'class' => 'action-icon',
        ];
        $action = new report_action($url, $icon, $params);
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

    /**
     * Returns an array with the excluded columns for certificationuser_entity.
     *
     * @return array
     */
    private function get_certificationuser_excluded_columns(): array {
        return ['suspended', 'timesuspended', 'timemodified', 'daystakingcertification', 'dayssinceallocation'];
    }

    /**
     * Returns an array with the excluded columns for certification_entity.
     *
     * @return array
     */
    private function get_certification_excluded_columns(): array {
        return ['fullnamewithlink', 'idnumber', 'timearchived', 'archived', 'startdate', 'duedate', 'expirydate',
            'allocationstartdate', 'allocationenddate', 'timemodified', 'timecreated'];
    }

    /**
     * Returns an array with the excluded columns for certificationcompletion_entity.
     *
     * @return array
     */
    private function get_certificationcompletion_excluded_columns(): array {
        return ['expirydate', 'expired', 'certified', 'certifiedtype'];
    }

    /**
     * Returns an array with the excluded columns for program_entity.
     *
     * @return array
     */
    private function get_program_excluded_columns(): array {
        return ['fullnamewithimage', 'programimage', 'idnumber', 'tags', 'description', 'startdate', 'duedate', 'enddate',
            'archived', 'timearchived', 'allowdirectallocation', 'allocationstartdate', 'allocationenddate', 'visible',
            'timemodified', 'timecreated', 'numbercoursesunique', 'associatedcertifications', 'numbercurrentallocatedusers'];
    }

    /**
     * Returns an array with the excluded columns for programuser_entity.
     *
     * @return array
     */
    private function get_programuser_excluded_columns(): array {
        return ['startdate', 'duedate', 'enddate', 'programprogresswithoverview', 'suspended', 'timesuspended',
            'allocationtype', 'timecreated', 'timemodified', 'associatedcertification'];
    }
}