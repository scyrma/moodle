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

use tool_certification\local\helpers\certification_entity;
use tool_certification\local\helpers\certificationcompletion_entity;
use tool_certification\local\helpers\certificationuser_entity;
use tool_organisation\local\entities\jobs as jobs_entity;
use tool_program\local\helpers\program_entity;
use tool_reportbuilder\local\entities\user;
use tool_tenant\tenancy;

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

        $this->get_column('tool_certification:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);

        $this->get_column('user:fullnamewithlink')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 2);

        // Add default conditions.
        $conditions = $this->get_conditions();
        $conditions['tool_certification:archived']->set_is_default(true, ['archived_op' => 2, 'archived' => 0]);

        // Add default filters.
        $filters = $this->get_filters();
        $filters['tool_certification:fullname']->set_is_default(true);
        $filters['tool_program:programselector']->set_is_default(true);

        // TODO add more filters:
        // Certification status
        // Allocation date
        // Completion date
        // Expiration
        // User
        // Department
        // Position.
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
        $this->add_entity(new certificationuser_entity('', 'tcu'));
        $this->add_entity(new certificationcompletion_entity('LEFT JOIN {tool_certification_compltion} tcc
                ON tcc.certificationid = tcu.certificationid AND tcc.userid = tcu.userid', 'tcc'));
        $this->add_entity(new certification_entity('', 'tc'));
        $this->add_entity(new user('', 'u'));
        if (class_exists('tool_organisation\local\entities\jobs')) {
            $this->add_entity(new jobs_entity('LEFT JOIN {tool_organisation_job} toj ON u.id = toj.userid', 'toj'));
        }
        $this->add_entity(new program_entity('', 'tp'));
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