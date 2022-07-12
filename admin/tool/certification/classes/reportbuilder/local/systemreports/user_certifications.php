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

namespace tool_certification\reportbuilder\local\systemreports;

use context_system;
use lang_string;
use moodle_url;
use pix_icon;
use core_reportbuilder\system_report;
use core_reportbuilder\local\report\action;
use tool_certification\api;
use tool_certification\permission;
use tool_certification\reportbuilder\local\entities\certification;
use tool_certification\reportbuilder\local\entities\certification_completion;
use tool_certification\reportbuilder\local\entities\certification_user;
use tool_program\reportbuilder\local\entities\program;
use tool_program\reportbuilder\local\entities\program_user;

/**
 * System report defining certifications for a given user
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @author     2022 Paul Holden <paulh@moodle.com>
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

        $this->set_main_table('user', 'u');
        $this->add_base_condition_simple('u.id', $this->userid);
        $this->add_base_condition_simple('u.deleted', 0);
        $this->add_base_condition_simple('tc.archived', 0);

        // This whopper adds all the JOINs used by subsequent entities.
        $this->add_join(api::get_status_sql_join('u', 'tc', 'tcu', 'tcc', 'tp', 'tpu'));

        $this->add_base_fields('tc.id AS certificationid, tp.id AS programid, tp.fullname AS programname, tpu.id AS allocationid');

        // Add our entitites, ensuring table aliases match those given above.
        $this->add_entity((new certification())->set_table_alias('tool_certification', 'tc'));
        $this->add_entity((new certification_user())->set_table_alias('tool_certification_users', 'tcu'));
        $this->add_entity((new certification_completion())->set_table_alias('tool_certification_compltion', 'tcc'));
        $this->add_entity((new program())->set_table_alias('tool_program', 'tp'));
        $this->add_entity((new program_user())->set_table_alias('tool_program_users', 'tpu'));

        // Now populate our report elements.
        $this->add_columns();
        $this->add_filters();
        $this->add_actions();

        $this->set_downloadable(false);
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
     * Set the columns for the report.
     */
    protected function add_columns(): void {
        $this->add_columns_from_entities([
            'certification:fullname',
            'program:fullname',
            'program_user:duedate',
            'certification_user:expirydate',
            'certification_user:certificationstatus',
            'program_user:programprogress',
            'certification_completion:certifieddate',
        ]);

        $this->set_initial_sort_column('certification:fullname', SORT_ASC);
    }

    /**
     * Set the filters for the report.
     */
    protected function add_filters(): void {
        $this->add_filters_from_entities([
            'certification_user:filterablestatus',
        ]);
    }

    /**
     * Set the actions icons of the report.
     */
    protected function add_actions(): void {

        // Certification user log icon.
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('check-circle-o', '', 'tool_wp'),
            [
                'data-action' => 'view_certification_user_log',
                'data-userid' => $this->userid,
                'data-id' => ':certificationid',
            ],
            false,
            new lang_string('viewcertificationuserlog', 'tool_certification')
        ))
            ->add_callback(function(): bool {
                return permission::can_view_user_progress($this->userid);
            })
        );

        // Progress report icon.
        $this->add_action((new action(
            new moodle_url('/admin/tool/program/programprogress.php', ['programid' => ':programid', 'userid' => $this->userid]),
            new pix_icon('bar-chart', '', 'tool_wp'),
            [],
            false,
            new lang_string('progressreport', 'tool_certification')
        ))
            ->add_callback(function(): bool {
                global $USER;
                return $USER->id != $this->userid && permission::can_view_user_progress($this->userid);
            })
        );

        // Progress overview icon.
        $context = context_system::instance();
        $this->add_action((new action(
            new moodle_url('#'),
            new pix_icon('i/dashboard', '', 'core'),
            [
                'data-action' => 'program_progress_overview',
                'data-allocationid' => ':allocationid',
                'data-title' => get_string('progressoverview', 'tool_program'),
                'data-contextid' => $context->id,
            ],
            false,
            new lang_string('progressoverview', 'tool_program')
        ))
            ->add_callback(function(): bool {
                global $USER;
                return $USER->id != $this->userid && permission::can_view_user_progress($this->userid);
            })
        );
    }
}
