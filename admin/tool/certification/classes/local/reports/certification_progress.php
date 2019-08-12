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
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use lang_string;
use moodle_url;
use pix_icon;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\local\helpers\certificationuser_format;
use tool_certification\permission;
use \tool_certification\local\helpers\format;
use tool_reportbuilder\report_action;
use tool_reportbuilder\report_column;
use tool_reportbuilder\system_report;
use context_system;
use tool_tenant\tenancy;

/**
 * This class defines a system report that shows the progress/completion/status of users within one given certification.
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        $this->add_base_condition_simple('tc.id', $this->get_certification()->get('id'));
        $this->add_base_condition_simple('tc.tenantid', tenancy::get_tenant_id());
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc ON tcc.userid = tcu.userid
            AND tcc.certificationid = tc.id AND tcc.timerevoked = 0');
        $this->add_base_fields('tcu.userid');
        $this->add_base_condition_simple('u.deleted', 0);

        $this->set_columns();
        $this->add_actions();
        $this->set_show_actions_header(true);
        $this->set_downloadable(false);
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
        $this->annotate_entity(certification_user::TABLE, new lang_string('entitycertificationusers', 'tool_certification'));
        $this->annotate_entity(certification::TABLE, new lang_string('entitycertification', 'tool_certification'));
        $this->annotate_entity('user', new lang_string('entityuser', 'tool_reportbuilder'));

        // Column "userinfo".
        $newcolumn = (new report_column(
            'userinfo',
            new lang_string('fullname', 'tool_certification'),
            'user'
        ))
            ->add_fields('tcu.id as certificationuserid,' . implode(',', $this->get_user_columns('u')))
            ->set_is_default(true, 1);
        $newcolumn->add_callback([format::class, 'userinfo']);
        $this->add_column($newcolumn);

        // Column "startdate".
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.startdate, tcu.startdatelocked')
            ->set_is_default(true, 2)
            ->add_callback([certificationuser_format::class, 'startdate']);
        $this->add_column($newcolumn);

        // Column "duedate".
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.duedate, tcu.duedatelocked')
            ->set_is_default(true, 3)
            ->add_callback([certificationuser_format::class, 'duedate']);
        $this->add_column($newcolumn);

        // Column "expirydate".
        $newcolumn = (new report_column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.expirydate, tcu.expirydatelocked, tcu.userid, tcu.certificationid')
            ->set_is_default(true, 4)
            ->add_callback([certificationuser_format::class, 'expirydate']);
        $this->add_column($newcolumn);

        // Column "allocationdate".
        $newcolumn = (new report_column(
            'allocationdate',
            new lang_string('allocationdate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.timecreated')
            ->set_is_default(true, 5)
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'userdate']);
        $this->add_column($newcolumn);

        // Column "allocationtype".
        $newcolumn = (new report_column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.allocationtype')
            ->set_is_default(true, 6)
            ->add_callback([certificationuser_format::class, 'allocationtype']);
        $this->add_column($newcolumn);

        // Column "program".
        $newcolumn = (new report_column(
            'programuser',
            new lang_string('program', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tc.program, tp.id AS programid, tp.fullname as programfullname, tp.archived as programarchived')
            ->set_is_default(true, 7)
            ->add_callback([format::class, 'program']);
        $this->add_column($newcolumn);

        // Column "certification status".
        $newcolumn = (new report_column(
            'certificationstatus',
            new lang_string('certificationstatus', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field(api::get_status_sql_cases(0, 'tcu', 'tcc'), 'status')
            ->set_is_default(true, 8)
            ->add_callback([certificationuser_format::class, 'status']);
        $this->add_column($newcolumn);

        // Column "program status".
        $newcolumn = (new report_column(
            'programstatus',
            new lang_string('programstatus', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.userid, tcu.certificationid, tc.program')
            ->set_is_default(true, 9)
            ->add_callback([format::class, 'programstatus']);
        $this->add_column($newcolumn);

        // Column "program progress".
        $newcolumn = (new report_column(
            'programprogress',
            new lang_string('programprogress', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_fields('tcu.userid, tcu.certificationid, tc.program as programid')
            ->set_is_default(true, 10)
            ->add_callback([\tool_program\local\helpers\programuser_format::class, 'programprogress']);
        $this->add_column($newcolumn);

        // Column "completion date".
        $newcolumn = (new report_column(
            'completiondate',
            new lang_string('completiondate', 'tool_program'),
            'tool_certification_users'
        ))
            ->add_fields('tcc.timecreated')
            ->set_is_default(true, 11)
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'userdate']);
        $this->add_column($newcolumn);
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
}