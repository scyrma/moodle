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
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_reportbuilder\datasources;

use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationrevoke_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_certification\local\helpers\certificationuser_format;
use tool_organisation\local\entities\jobs as jobs_entity;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_format;
use tool_reportbuilder\constants;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\report_column;
use tool_tenant\tenancy;
use lang_string;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_certification_user_allocation
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_certification_user_allocation extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $tenantid = tenancy::get_tenant_id();
        $this->set_main_table('tool_certification_users', 'tcu');
        $this->add_base_join('INNER JOIN {user} u ON tcu.userid = u.id AND u.deleted = 0');
        $this->add_base_join('INNER JOIN {tool_certification} tc ON tc.id = tcu.certificationid');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');
        // Added tcc join here because status in certification user uses completion table.
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid AND tcc.timerevoked = 0');
        $this->add_base_condition_simple('tc.tenantid', $tenantid);

        // Check tenant id on users in case they have been moved to another tenant.
        [$join, $where, $params] = tenancy::get_users_sql('u', $tenantid);
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        $this->add_organisation_condition('u');

        $this->set_downloadable(true);
        $this->set_columns();

        $this->get_column('tool_certification:fullname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);

        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);

        $this->get_column('user:lastaccess')
            ->set_is_default(true, 3)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_users:timecreated')
            ->set_is_default(true, 4)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_users:duedate')
            ->set_is_default(true, 5)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_users:expirydate')
            ->set_is_default(true, 6)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_users:certificationstatus')
            ->set_is_default(true, 7)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_compltion:certifieddate')
            ->set_is_default(true, 8)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_users:actions')
            ->set_is_default(true, 9)
            ->set_is_sortable(true, true);

        // Add default conditions.
        $conditions = $this->get_conditions();
        $conditions['tool_certification:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_certification:fullname']->set_is_default(true);
        $filters['tool_program:programselector']->set_is_default(true);
        $filters['tool_certification_users:filterablestatus']->set_is_default(true);
        $filters['tool_certification_users:timecreated']->set_is_default(true);
        $filters['tool_certification_compltion:certifieddate']->set_is_default(true);
        $filters['tool_certification_compltion:expirydate']->set_is_default(true);
        $filters['user:fullname']->set_is_default(true);
        $filters['tool_organisation_jobs:department']->set_is_default(true);
        $filters['tool_organisation_jobs:position']->set_is_default(true);
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
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new certification_entity('', 'tc'));
        $this->add_entity(new certificationuser_entity('', 'tcu', [], 'tcc'));
        $this->add_entity(new certificationcompletion_entity('', 'tcc'));
        $this->add_entity(new user('', 'u'));
        if (class_exists('tool_organisation\local\entities\jobs')) {
            $this->add_entity(new jobs_entity('LEFT JOIN {tool_organisation_job} toj ON u.id = toj.userid', 'toj'));
        }
        $this->add_entity(new program_entity('', 'tp'));

        // We can have multiple records on completion table when certifying and revoking same user from same certification.
        // We retrieve just the latest record it was revoked.
        $revokedjoin = 'LEFT JOIN
        (SELECT MAX(id) AS id, certificationid, userid
        FROM {tool_certification_compltion}
        WHERE timerevoked > 0
        GROUP BY certificationid, userid) tcrmax
        ON tcrmax.certificationid = tcu.certificationid AND tcrmax.userid = tcu.userid
        LEFT JOIN {tool_certification_compltion} tccr ON tccr.id = tcrmax.id';
        $this->add_entity(new certificationrevoke_entity($revokedjoin, 'tccr'));

        // Column certificationprogress.
        $column = (new report_column(
            'certificationprogress',
            new lang_string('certificationprogress', 'tool_certification'),
            'tool_certification_compltion'
        ))
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field('tc.program', 'programid')
            ->add_field('tcu.userid')
            ->add_callback([programuser_format::class, 'programprogress'])
            ->disable_aggregation('count')
            ->disable_aggregation('countdistinct')
            ->disable_aggregation('groupconcat')
            ->disable_aggregation('groupconcatdistinct');
        $this->add_column($column);

        // Actions column.
        $column = (new report_column(
            'actions',
            new lang_string('actions', 'tool_certification'),
            'tool_certification_users'
        ))
            ->add_field('u.id', 'userid')
            ->add_field('tc.id', 'certificationid')
            ->add_field('tcc.id', 'completionid')
            ->add_callback([certificationuser_format::class, 'actions']);
        columns::disable_column_aggregation($column);
        $this->add_column($column);
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