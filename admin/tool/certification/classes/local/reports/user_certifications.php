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
 * Class user_certifications
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
use tool_reportbuilder\system_report;
use tool_tenant\tenancy;

/**
 * Class user_certifications
 *
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package tool_certification
 */
class user_certifications extends system_report {

    /**
     * @var
     */
    private $userid;

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->userid = $this->get_parameter('userid', 0, PARAM_INT);
        $type = $this->get_parameter('type', -1, PARAM_INT);

        $this->set_columns();
        $this->set_main_table('user', 'u');
        $this->add_base_condition_simple('u.id', $this->userid);
        $this->add_base_condition_simple('u.deleted', 0);
        $this->add_base_join(api::get_status_sql_join('u', 'tc', 'tcu', 'tcc', 'tp'));
        $this->add_base_join('LEFT JOIN {tool_program_users} tpu
            ON tpu.userid = tcu.userid AND tpu.certificationid = tc.id AND tpu.certificationid = tc.id');

        // Base condition is a visibility check (non archived, within correct tenant).
        $usertenantid = tenancy::get_tenant_id($this->userid);
        $tenant = \tool_wp\db::generate_param_name();
        $this->add_base_condition_sql("
                tc.archived = 0
                AND tc.tenantid = :{$tenant} ", [$tenant => $usertenantid]);

        $this->add_actions();
        $this->set_show_actions_header(true);
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
        if ($column = $this->get_column('tool_program_users:programprogresswithoverview')) {
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
        $this->add_entity(new certificationuser_entity('', 'tcu', [], 'tcc'));
        $this->add_entity(new certification_entity('', 'tc'));
        $this->add_entity(new certificationcompletion_entity('', 'tcc'));
        $this->add_entity(new program_entity('', 'tp'));
        $this->add_entity(new programuser_entity('', 'tpu'));
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        // No actions defined.
    }
}
