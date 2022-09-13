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
 * Class report_certification_user_allocation
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\tool_reportbuilder\datasources;

use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationrevoke_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_certification\local\helpers\certificationuser_format;
use tool_program\local\helpers\program_entity;
use tool_program\local\helpers\programuser_entity;
use tool_program\local\helpers\programuser_format;
use tool_reportbuilder\constants;
use tool_reportbuilder\convert_not_implemented;
use tool_reportbuilder\convert_not_possible;
use tool_reportbuilder\db;
use tool_reportbuilder\local\entities\user;
use tool_reportbuilder\local\helpers\columns;
use tool_reportbuilder\permission;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;
use lang_string;

/**
 * Class report_certification_user_allocation
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_certification_user_allocation extends \tool_reportbuilder\datasource {

    /**
     * When converting custom report to core reportbuilder which class corresponds to this datasource
     *
     * @return string name of the class extending {@see \core_reportbuilder\datasource}
     */
    public function convert_get_datasource_class(): string {
        return \tool_certification\reportbuilder\datasource\certification_user_allocation::class;
    }

    /**
     * Get the entity name that corresponds to the given entity in the converted datasource
     *
     * @param string $oldentityname entity name in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string entity name in the converted datasource
     */
    public function convert_get_entity_name(string $oldentityname, \core_reportbuilder\datasource $newsource): string {
        if ($oldentityname === 'tool_certification_revokedby') {
            return 'tool_certification_revokedby';
        } else if ($oldentityname === 'tool_certification_compltion') {
            return 'certification_completion';
        } else if ($oldentityname === 'tool_certification_certifiedby') {
            return 'tool_certification_certifiedby';
        }
        return parent::convert_get_entity_name($oldentityname, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which column corresponds to the given column
     *
     * @param report_column $oldcolumn the column in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding column in the converted datasource
     * @throws convert_not_possible
     */
    public function convert_get_column_unique_identifier(report_column $oldcolumn,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldcolumn->get_unique_identifier() === 'tool_certification_compltion:revoked') {
            return 'certification_revoke:revoked';
        } else if ($oldcolumn->get_unique_identifier() === 'tool_certification_compltion:timerevoked') {
            return 'certification_revoke:timerevoked';
        } else if ($oldcolumn->get_unique_identifier() === 'tool_certification:tenant') {
            return 'tenant:name';
        } else if ($oldcolumn->get_unique_identifier() === 'tool_program:tenant') {
            // TODO WP-3702 implement program tenant column.
            throw new convert_not_possible('Program tenant column can not be converted');
        }
        return parent::convert_get_column_unique_identifier($oldcolumn, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which filter corresponds to the given filter
     *
     * @param report_filter $oldfilter the filter in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding filter in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_filter_unique_identifier(report_filter $oldfilter,
                                                         \core_reportbuilder\datasource $newsource): string {
        if ($oldfilter->get_unique_identifier() === 'tool_certification_compltion:revoked') {
            return 'certification_revoke:revoked';
        } else if ($oldfilter->get_unique_identifier() === 'tool_certification:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_filter_unique_identifier($oldfilter, $newsource);
    }

    /**
     * When converting this report to core_reportbuilder which condition corresponds to the given condition
     *
     * @param report_filter $oldcondition the condition in this datasource
     * @param \core_reportbuilder\datasource $newsource
     * @return string the full name (unique identifier) of the corresponding condition in the converted datasource
     * @throws convert_not_implemented
     */
    public function convert_get_condition_unique_identifier(report_filter $oldcondition,
                                                            \core_reportbuilder\datasource $newsource): string {
        if ($oldcondition->get_unique_identifier() === 'tool_certification_compltion:revoked') {
            return 'certification_revoke:revoked';
        } else if ($oldcondition->get_unique_identifier() === 'tool_certification:tenant') {
            return 'tenant:name';
        }
        return parent::convert_get_condition_unique_identifier($oldcondition, $newsource);
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_certification_users', 'tcu', false);
        $this->add_base_join('INNER JOIN {user} u ON tcu.userid = u.id AND u.deleted = 0');
        $this->add_base_join('INNER JOIN {tool_certification} tc ON tc.id = tcu.certificationid');
        $this->add_base_join('LEFT JOIN {tool_program} tp ON tp.id = tcu.currentprogramid');
        // Added tcc join here because status in certification user uses completion table.
        $this->add_base_join('LEFT JOIN {tool_certification_compltion} tcc
        ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid AND tcc.timerevoked = 0 AND tcc.islast = 1');
        // Added tpu join here because date for certification users are stored in program users table.
        $join = 'LEFT JOIN {tool_program_users} tpu ON tpu.userid = tcu.userid AND tpu.certificationid = tcu.certificationid
        AND tpu.programid = tp.id';
        $this->add_base_join($join);
        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id'));

        // Certification tenant condition.
        [$sql, $params] = hierarchy::filter_own_or_sub_or_parent_shared_entities_sql('tc.tenantid', 'tc.shared=1');
        $this->add_base_condition_sql($sql, $params);

        $this->set_downloadable(true);
        $this->set_columns();

        $this->get_column('tool_certification:fullname')
            ->set_is_default(true, 1)
            ->set_is_sortable(true, true);

        $this->get_column('user:fullnamewithpicturelink')
            ->set_is_default(true, 2)
            ->set_is_sortable(true, true);

        $this->get_column('user:lastaccess')
            ->set_is_default(true, 3)
            ->set_is_sortable(true, true);

        $this->get_column('tool_certification_users:timecreated')
            ->set_is_default(true, 4)
            ->set_is_sortable(true, true);

        $this->get_column('tool_program_users:duedate')
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

        $canshowtenantcolumn = hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($columntenant = $this->get_column('tool_certification:tenant')) {
            $columntenant->set_is_default($canshowtenantcolumn, 10)
                ->set_is_sortable($canshowtenantcolumn, true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($conditiontenant = $conditions['tool_certification:tenant']) {
            $conditiontenant->set_is_available($canshowtenantcolumn);
        }
        if ($filtertenant = $filters['tool_certification:tenant']) {
            $filtertenant->set_is_default($canshowtenantcolumn)
                ->set_is_available($canshowtenantcolumn);
        }
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
        // Certification entity.
        $this->add_entity(new certification_entity());

        // User allocation to certification entity.
        $this->add_entity(new certificationuser_entity());

        // User completion in the certification entity.
        $this->add_entity(new certificationcompletion_entity());

        // User entity to represent "Certified by".
        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user())
            ->set_allow_tenant_columns($allowtenant)
            ->add_join('left join {user} tccuc on tccuc.id = tcc.certifiedby')
            ->set_table_alias('user', 'tccuc')
            ->set_table_alias('tool_tenant_user', \tool_wp\db::generate_alias())
            ->set_table_alias('tool_tenant', \tool_wp\db::generate_alias())
            ->set_include_only_fields(['fullname*', 'firstname', 'lastname', 'email', 'tenant'])
            ->set_entity_name('tool_certification_certifiedby')
            ->set_entity_title(new lang_string('certifiedby', 'tool_certification')));

        // User entity to represent "Revoked by".
        $revokedby = (new user())
            ->set_allow_tenant_columns($allowtenant)
            ->add_join('left join {user} tccur on tccur.id = tcc.revokedby')
            ->set_table_alias('user', 'tccur')
            ->set_table_alias('tool_tenant_user', \tool_wp\db::generate_alias())
            ->set_table_alias('tool_tenant', \tool_wp\db::generate_alias())
            ->set_include_only_fields(['fullname*', 'firstname', 'lastname', 'email', 'tenant'])
            ->set_entity_name('tool_certification_revokedby')
            ->set_entity_title(new lang_string('revokedby', 'tool_certification'));
        $this->add_entity($revokedby);

        // User entity for the actual certified user.
        $this->add_entity((new user())
            ->set_allow_tenant_columns($allowtenant));

        // Jobs of certified user.
        if (class_exists('tool_organisation\local\entities\jobs')) {
            $entity = new \tool_organisation\local\entities\jobs();
            $alias = $entity->get_table_alias('tool_organisation_job');

            $this->add_entity($entity->add_join(
                "LEFT JOIN {tool_organisation_job} {$alias} ON {$alias}.userid = u.id AND " .
                    \tool_organisation\local\entities\jobs::get_job_tenant_join($alias))
            );
        }

        // User's current program.
        $this->add_entity((new program_entity())
            ->set_entity_title(new lang_string('currentprogram', 'tool_certification')));

        // User allocation and completion in his current program.
        $joins = ['LEFT JOIN {tool_program_sets} tps ON tps.parent = 0 AND tps.programid = tpu.programid',
                'LEFT JOIN {tool_program_set_completion} tpsc ON tpsc.userid = tcu.userid AND tpsc.setid = tps.id'];
        $this->add_entity((new programuser_entity())
            ->add_joins($joins)
            ->set_table_alias('tool_program_users', 'tpu')
            ->set_entity_title(new lang_string('programuserallocation', 'tool_certification')));

        // Certification revoke.
        // We can have multiple records on completion table when certifying and revoking same user from same certification.
        // We retrieve just the latest record it was revoked.
        $revokedjoin = 'LEFT JOIN
        (SELECT MAX(id) AS id, certificationid, userid
        FROM {tool_certification_compltion}
        WHERE timerevoked > 0
        GROUP BY certificationid, userid) tcrmax
        ON tcrmax.certificationid = tcu.certificationid AND tcrmax.userid = tcu.userid
        LEFT JOIN {tool_certification_compltion} tccr ON tccr.id = tcrmax.id';
        $this->add_entity((new certificationrevoke_entity())
            ->add_join($revokedjoin));
        // TODO do we ever need to show the revoke if it is not the last completion record?

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
}
