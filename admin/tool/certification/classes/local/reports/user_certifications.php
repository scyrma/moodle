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

use lang_string;
use tool_certification\api;
use tool_certification\certification;
use tool_certification\certification_user;
use tool_certification\local\helpers\certificationuser_format;
use tool_certification\local\helpers\format;
use tool_certification\permission;
use tool_program\persistent\program;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
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

        $u = 'u'; // User table alias.
        $c = 'c'; // Certifications table alias.
        $cu = 'cu'; // Certification users table alias.
        $cc = 'cc'; // Certification user completions table alias.
        $pr = 'pr'; // Programs table alias.
        $this->set_columns($c, $cu, $pr, $cc);
        $type = $this->get_parameter('type', -1, PARAM_INT);
        $this->set_filters($type, $cu, $cc);

        $this->set_main_table('user', $u);
        $this->add_base_condition_simple("{$u}.id", $this->userid);
        $this->add_base_condition_simple("{$u}.deleted", 0);
        $this->add_base_join(api::get_status_sql_join($u, $c, $cu, $cc, $pr));

        // Base condition is a visibility check (non archived, within correct tenant).
        $usertenantid = tenancy::get_tenant_id($this->userid);
        $tenant = \tool_wp\db::generate_param_name();
        $this->add_base_condition_sql("
                {$c}.archived = 0
                AND {$c}.tenantid = :{$tenant} ", [$tenant => $usertenantid]);

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
     *
     * @param string $c Certifications table alias.
     * @param string $cu Certification users table alias.
     * @param string $pr Programs table alias.
     * @param string $cc Certification completion table alias.
     */
    protected function set_columns($c = 'c', $cu = 'cu', $pr = 'pr', $cc = 'cc'): void {
        $this->annotate_entity(certification_user::TABLE, new lang_string('entitycertificationusers', 'tool_certification'));
        $this->annotate_entity(certification::TABLE, new lang_string('entitycertification', 'tool_certification'));
        $this->annotate_entity(program::TABLE, new lang_string('entityprogram', 'tool_program'));

        // Column "certificationname".
        $newcolumn = (new report_column(
            'fullname',
            new lang_string('certificationname', 'tool_certification'),
            'tool_certification'
        ))
            ->add_fields("{$c}.fullname, {$c}.id, {$c}.tenantid, {$c}.archived")
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true)
            ->add_callback([format::class, 'usercertificationname'], ['userid' => $this->userid]);
        $this->add_column($newcolumn);

        // Column "programname".
        $newcolumn = (new report_column(
            'programname',
            new lang_string('programname', 'tool_certification'),
            'tool_program'
        ))
            ->add_fields("{$pr}.fullname, {$pr}.id, {$pr}.tenantid, {$pr}.archived, {$pr}.visible")
            ->set_is_default(true, 3)
            ->add_callback([format::class, 'userprogramname'], ['userid' => $this->userid]);
        $this->add_column($newcolumn);

        // Column "duedate".
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field("{$cu}.duedate")
            ->add_field("{$cu}.duedatelocked")
            ->set_is_default(true, 4)
            ->add_callback([certificationuser_format::class, 'duedate']);
        $this->add_column($newcolumn);

        // Column "status".
        $newcolumn = (new report_column(
            'userid',
            new lang_string('status', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field(api::get_status_sql_cases(0, $cu, $cc), 'status')
            ->set_is_default(true, 5)
            ->add_callback([certificationuser_format::class, 'status']);
        $this->add_column($newcolumn);
    }

    /**
     * Set filters.
     *
     * @param int $statusid
     * @param string $cu certification users table alias
     * @param string $cc certification completions table alias
     */
    protected function set_filters(int $statusid, $cu = 'cu', $cc = 'cc'): void {
        // Filter by status.
        $filter = (new report_filter(
            select::class,
            'filterablestatus',
            new lang_string('status', 'tool_certification'),
            'tool_certification_users',
            api::get_status_sql_cases($statusid, $cu, $cc, true)
        ))
            ->set_is_default(true)
            ->set_options(api::get_certification_statuses_fieldset());
        $this->add_filter($filter);
    }

    /**
     * Set the actions icons of the report.
     */
    private function add_actions(): void {
        // No actions defined.
    }
}
