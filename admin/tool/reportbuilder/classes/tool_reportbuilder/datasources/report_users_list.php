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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class report_users_list
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barbera <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\tool_reportbuilder\datasources;

use tool_reportbuilder\local\entities\user as user_entity;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_users_list
 *
 * @package   tool_reportbuilder
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 Toni Barbera <toni@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_users_list extends \tool_reportbuilder\datasource {

    /**
     * Initialise report
     */
    protected function initialise() {
        $this->set_main_table('user', 'u');
        list($join, $where, $params) = tenancy::get_users_sql('u');
        $this->add_base_join($join);
        $this->add_base_condition_sql($where, $params);

        $this->set_columns();
        $this->set_filters();
        $this->set_conditions();

        $this->get_column('user:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);

        $this->get_column('user:email')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 2);

        $this->get_column('user:country')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 3);

        $this->get_column('user:city')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 4);

        $this->get_conditions()['user:suspended']
            ->set_is_default(true, ['suspended_op' => 2]);

        $this->get_filters()['user:hascurrentjobs']
            ->set_is_default(true);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function get_name() {
        return get_string('reportuserslist', 'tool_reportbuilder');
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    protected function set_columns() {
        $this->add_entity(new user_entity());

        if (class_exists('tool_organisation\local\entities\jobs')) {
            $entity = new \tool_organisation\local\entities\jobs();
            $alias = $entity->get_table_alias('tool_organisation_job');

            $tenantid = tenancy::get_tenant_id();

            $this->add_entity($entity->add_join(
                "LEFT JOIN {tool_organisation_job} {$alias} ON {$alias}.userid = u.id AND {$alias}.tenantid = {$tenantid}"));
        }
    }

    /**
     * Set the filters of the report.
     */
    protected function set_filters(): void {
    }

    /**
     * Available conditions to be selected in the report.
     */
    protected function set_conditions(): void {
    }
}