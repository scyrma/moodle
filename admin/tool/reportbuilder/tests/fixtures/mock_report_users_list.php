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
 * Class mock_report_users_list
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class mock_report_users_list
 *
 * Adds tenant columns/filters to the users datasource
 *
 * @package     tool_reportbuilder
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class mock_report_users_list extends \tool_reportbuilder\tool_reportbuilder\datasources\report_users_list {
    /**
     * Set the columns available for the report and the definition of each.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns() {
        // This is the line we want to modify in the parent class: we want to add user entity with tenant columns/filters.
        $this->add_entity((new \tool_reportbuilder\local\entities\user())->set_allow_tenant_columns(true));

        // This is copied from the parent class without modifications.
        if (class_exists('tool_organisation\local\entities\jobs')) {
            $entity = new \tool_organisation\local\entities\jobs();
            $alias = $entity->get_table_alias('tool_organisation_job');

            $tenantid = \tool_tenant\tenancy::get_tenant_id();

            $this->add_entity($entity->add_join(
                "LEFT JOIN {tool_organisation_job} {$alias} ON {$alias}.userid = u.id AND {$alias}.tenantid = {$tenantid}"));
        }
    }
}
