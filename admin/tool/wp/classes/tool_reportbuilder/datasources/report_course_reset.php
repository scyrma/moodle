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
 * Class report_course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
namespace tool_wp\tool_reportbuilder\datasources;

use tool_reportbuilder\local\entities\user as user_entity;
use tool_reportbuilder\local\entities\course as course_entity;
use tool_reportbuilder\datasource;
use tool_reportbuilder\permission;
use tool_tenant\tenancy;
use tool_wp\local\helpers\course_reset_entity;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_course_reset
 *
 * @package   tool_wp
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_course_reset extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_wp_course_reset', 'twpcr');
        $this->add_base_join('INNER JOIN {user} u ON twpcr.userid = u.id');

        $this->add_base_condition_sql(tenancy::get_users_subquery(false, false, 'u.id', 0, true));

        $this->set_downloadable(true);
        $this->set_columns();

        if ($column = $this->get_column('course:fullname')) {
            $column->set_is_default(true, 1);
            $column->set_is_sortable(true, true, 1);
        }

        if ($column = $this->get_column('user:fullname')) {
            $column->set_is_default(true, 2);
            $column->set_is_sortable(true, true, 2);
        }

        if ($column = $this->get_column('tool_wp_course_reset:timereseted')) {
            $column->set_is_default(true, 3);
            $column->set_is_sortable(true, true, 3);
        }

        $canshowtenantcolumn = permission::can_show_tenant_column(tenancy::get_tenant_id());
        if ($columntenant = $this->get_column('user:tenant')) {
            $columntenant->set_is_default($canshowtenantcolumn, 4)
                ->set_is_sortable($canshowtenantcolumn, true)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($filtertenant = $this->get_filter('user:tenant')) {
            $filtertenant->set_is_default($canshowtenantcolumn)
                ->set_is_available($canshowtenantcolumn);
        }
        if ($conditiontenant = $this->get_condition('user:tenant')) {
            $columntenant->set_is_available($canshowtenantcolumn);
        }
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('coursereset', 'tool_wp');
    }

    /**
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity(new course_reset_entity());

        $allowtenant = permission::can_show_tenant_column(tenancy::get_tenant_id());
        $this->add_entity((new user_entity())->set_allow_tenant_columns($allowtenant));

        $this->add_entity((new course_entity())
            ->add_join('LEFT JOIN {course} c ON c.id=twpcr.courseid'));

        if (class_exists('tool_program\local\helpers\program_entity')) {
            $this->add_entity((new \tool_program\local\helpers\program_entity())
                ->add_join("LEFT JOIN {tool_program} prog ON prog.id = twpcr.programid")
                ->set_table_alias('tool_program', 'prog'));
        }

        if (class_exists('tool_certification\local\helpers\certification_entity')) {
            $this->add_entity((new \tool_certification\local\helpers\certification_entity())
                ->add_join("LEFT JOIN {tool_certification} cert ON cert.id = twpcr.certificationid")
                ->set_table_alias('tool_certification', 'cert'));
        }
    }
}
