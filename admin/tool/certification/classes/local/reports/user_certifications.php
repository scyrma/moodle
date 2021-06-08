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
 * Class user_certifications
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\reports;

defined('MOODLE_INTERNAL') || die();

use tool_certification\api;
use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_certification\permission;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_reportbuilder\report_action;
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;

/**
 * Class user_certifications
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class user_certifications extends system_report {

    /** @var int */
    private $userid;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);
        $type = $this->get_parameter('type', -1, PARAM_INT);

        $this->set_columns();
        $this->set_main_table('user', 'u', false);
        $this->add_base_condition_simple('u.id', $this->userid);
        $this->add_base_condition_simple('u.deleted', 0);
        $this->add_base_condition_simple('tc.archived', 0);
        $this->add_base_join(api::get_status_sql_join('u', 'tc', 'tcu', 'tcc', 'tp', 'tpu'));

        $this->add_base_fields('tc.id AS certificationid, tp.id AS programid, tp.fullname AS programname, tpu.id AS allocationid');

        $this->add_actions();
        $this->set_downloadable(false);

        // Add default columns.
        if ($column = $this->get_column('tool_certification:fullname')) {
            $column->set_is_default(true, 1);
        }
        if ($column = $this->get_column('tool_program:fullname')) {
            $column->set_is_default(true, 2);
        }
        if ($column = $this->get_column('tool_certification:duedate')) {
            $column->set_is_default(true, 3);
        }
        if ($column = $this->get_column('tool_certification:expirydate')) {
            $column->set_is_default(true, 4);
        }
        if ($column = $this->get_column('tool_certification_users:certificationstatus')) {
            $column->set_is_default(true, 5);
        }
        if ($column = $this->get_column('tool_program_users:programprogress')) {
            $column->set_is_default(true, 6);
        }
        if ($column = $this->get_column('tool_certification_compltion:certifieddate')) {
            $column->set_is_default(true, 7);
        }

        // Add default filter.
        $filters = $this->get_filters();
        $statusparams = ['filterablestatus_op' => 2, 'filterablestatus' => $type];
        $filters['tool_certification_users:filterablestatus']->set_is_default(true, $statusparams);
    }

    /**
     * Validates access to view this report with the given parameters
     *
     * @return bool
     */
    protected function can_view(): bool {
        return permission::can_view_user_progress($this->get_parameter('userid', 0, PARAM_INT));
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('reportusercerts', 'tool_certification');
    }

    /**
     * Set the columns for the report.
     */
    protected function set_columns(): void {
        $this->add_entity(new certificationuser_entity());
        $this->add_entity(new certification_entity());
        $this->add_entity(new certificationcompletion_entity());
        $this->add_entity(new program_entity());
        $this->add_entity(new programuser_entity());
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        global $CFG;

        // Certification user log icon.
        $logurl = new \moodle_url('#');
        $logicon = new \pix_icon('check-circle-o',
            get_string('viewcertificationuserlog', 'tool_certification'),
            'tool_wp');
        $action = new report_action($logurl, $logicon, [
            'class' => 'view_certification_user_log',
            'data-userid' => $this->userid,
            'data-id' => ':certificationid'
        ]);
        $action->add_callback(function() {
            return permission::can_view_user_progress($this->userid);
        });
        $this->add_action($action);

        // Progress report icon.
        $urlparams = ['programid' => ':programid', 'userid' => $this->userid];
        $progressreporturl = new \moodle_url("/$CFG->admin/tool/program/programprogress.php", $urlparams);
        $progressreporticon = new \pix_icon('bar-chart',
            get_string('progressreport', 'tool_program'),
            'tool_wp');
        $action = new report_action($progressreporturl, $progressreporticon);
        $action->add_callback(function() {
            global $USER;
            return $USER->id != $this->userid && permission::can_view_user_progress($this->userid);
        });
        $this->add_action($action);

        // Progress overview icon.
        $progressoverviewurl = new \moodle_url("#");
        $progressoverviewicon = new \pix_icon('i/dashboard',
            get_string('progressoverview', 'tool_program'),
            'core');
        $action = new report_action($progressoverviewurl, $progressoverviewicon, [
            'class' => 'program-progress-overview-trigger',
            'data-allocationid' => ':allocationid',
            'data-title' => get_string('progressoverview', 'tool_program'),
            'data-contextid' => \context_system::instance()->id
        ]);
        $action->add_callback(function() {
            global $USER;
            return $USER->id != $this->userid && permission::can_view_user_progress($this->userid);
        });
        $this->add_action($action);
    }
}
