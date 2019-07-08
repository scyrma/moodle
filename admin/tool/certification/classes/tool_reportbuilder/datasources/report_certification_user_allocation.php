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
 * Class report_certification_user_allocation
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_reportbuilder\datasources;

use tool_certification\local\helpers\certification_fields;
use tool_organisation\helper;
use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\department_select;
use tool_organisation\tool_reportbuilder\filter\job_department;
use tool_organisation\tool_reportbuilder\filter\job_position;
use tool_organisation\tool_reportbuilder\filter\position_select;
use tool_organisation\tool_reportbuilder\filter\showpastjobs;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;
use lang_string;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_certification_user_allocation
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_certification_user_allocation extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_certification_users', 'tcu');
        $this->add_base_join('INNER JOIN {user} u ON tcu.userid = u.id');
        $this->add_base_join('INNER JOIN {tool_certification} tc ON tc.id = tcu.certificationid');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');
        $this->add_base_condition_simple('tc.tenantid', tenancy::get_tenant_id());

        $this->add_organisation_condition('u');

        $this->set_downloadable(true);
        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        $this->get_column('user:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);

        $this->get_column('tool_certification:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('entitycertificationusers', 'tool_certification');
    }

    /**
     * Gets an instance of certification_fields_helper that is used to add typical certification columns, filters and conditions
     *
     * @return certification_fields
     */
    protected function get_certification_fields_helper(): certification_fields {
        return new certification_fields(
            '',
            'tc',
            [
                'program',
                'allocationstartdatetype',
                'allocationenddatetype',
            ]
        );
    }

    /**
     * SQL call helper for columns that have joins
     *
     * @return string
     */
    private function get_certification_completion_joins_helper(): string {
        return 'LEFT JOIN {tool_certification_compltion} tcc
                ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid AND tcc.timerevoked = 0';
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity($this->get_certification_fields_helper());
        $this->annotate_entity('tool_certification_users', new lang_string('userallocation', 'tool_certification'));
        $this->annotate_entity('tool_certification_compltion', new lang_string('usercompletion', 'tool_certification'));
        $this->annotate_entity('tool_organisation_department', new lang_string('entitydepartment', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_position', new lang_string('entityposition', 'tool_organisation'));
        $this->annotate_entity('tool_organisation_jobs', new lang_string('entityjob', 'tool_organisation'));

        // Program name.
        $newcolumn = (new report_column(
            'certificationprogram',
            new lang_string('program', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('tp.fullname');
        $newcolumn->add_callback([\tool_certification\local\helpers\format::class, 'programname']);
        $this->add_column($newcolumn);

        // User due date.
        $newcolumn = (new report_column(
            'userduedate',
            new lang_string('duedate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.duedate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        // User start date.
        $newcolumn = (new report_column(
            'userstartdate',
            new lang_string('startdate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.startdate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        // User expiry date.
        $newcolumn = (new report_column(
            'userexpirydate',
            new lang_string('expirydate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.expirydate');
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        // User status.
        $newcolumn = (new report_column(
            'userstatus',
            new lang_string('status', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('tcu.certificationid')
            ->add_field('tcu.userid')
            ->add_field('tcu.status');
        $newcolumn->add_callback([\tool_certification\local\helpers\format::class, 'status']);
        $this->add_column($newcolumn);

        // User certified date.
        $newcolumn = (new report_column(
            'usercertifieddate',
            new lang_string('certifieddate', 'tool_certification'),
            'tool_certification_compltion'
        ))
            ->add_field('tcc.timecreated')
            ->add_join($this->get_certification_completion_joins_helper());
        $newcolumn->add_callback([format::class, 'userdate']);
        $this->add_column($newcolumn);

        // Add users entity.
        $this->add_entity(new user_entity('', 'u'));
    }

    /**
     * Set the filters of the report
     */
    protected function set_filters(): void {
        $this->add_filters_conditions_helper('add_filter');
    }

    /**
     * Available conditions to be selected in the report.
     */
    protected function set_conditions(): void {
        $this->add_filters_conditions_helper('add_condition');
    }

    /**
     * Helper that add filters or conditions depend how is called
     *
     * @param string $method
     */
    protected function add_filters_conditions_helper(string $method): void {
        if (!in_array($method, ['add_filter', 'add_condition'])) {
            throw new \moodle_exception('helperactionnotallowed', 'tool_certification');
        }

        // Filter for certifieddate.
        $this->$method(
            (new report_filter(
                date_condition::class,
                'usercertifieddate',
                new lang_string('certifieddate', 'tool_certification'),
                'tool_certification_compltion',
                'tcc.timecreated'
            ))
                ->add_join($this->get_certification_completion_joins_helper())
        );

        // Filter for duedate.
        $this->$method(
            new report_filter(
                date_condition::class,
                'userduedate',
                new lang_string('duedate', 'tool_certification'),
                'tool_certification_users',
                'tcu.duedate'
            )
        );

        // Filter for startdate.
        $this->$method(
            new report_filter(
                date_condition::class,
                'userstartdate',
                new lang_string('startdate', 'tool_certification'),
                'tool_certification_users',
                'tcu.startdate'
            )
        );

        // Filter for expirydate.
        $this->$method(
            new report_filter(
                date_condition::class,
                'userexpirydate',
                new lang_string('expirydate', 'tool_certification'),
                'tool_certification_users',
                'tcu.expirydate'
            )
        );

        // Filter for program.
        $this->$method(
            new report_filter(
                text::class,
                'certificationprogram',
                new lang_string('program', 'tool_certification'),
                'tool_certification',
                'tp.fullname'
            )
        );
    }

    /**
     * This report is available to organisation managers with the permission to view reports
     *
     * Only users who are managed by the current user will be displayed
     *
     * @return bool
     */
    public static function supports_organisation_filter(): bool {
        return true;
    }
}