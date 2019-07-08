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
 * Class report_certifications
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\tool_reportbuilder\datasources;

use lang_string;
use moodle_exception;
use tool_certification\local\helpers\certification_fields;
use tool_certification\local\helpers\format;
use tool_reportbuilder\datasource;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_certifications
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_certifications extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_certification', 'tc');
        $this->add_base_join('INNER JOIN {tool_program} tp ON tp.id = tc.program');

        $this->set_downloadable(false);
        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

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
        return get_string('certifications', 'tool_certification');
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
     * Set the columns available for the report and the definition of each.
     *
     */
    protected function set_columns(): void {
        $this->add_entity($this->get_certification_fields_helper());

        // Program name.
        $newcolumn = (new report_column(
            'certificationprogram',
            new lang_string('program', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('tp.fullname');
        $newcolumn->add_callback([format::class, 'programname']);
        $this->add_column($newcolumn);

        // Certification start date.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('tc.startdatetype')
            ->add_field('tc.startdateabsolute')
            ->add_field('tc.startdaterelative');
        $newcolumn->add_callback([format::class, 'certificationstartdate']);
        $this->add_column($newcolumn);

        // Certification due date.
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('tc.startdatetype')
            ->add_field('tc.startdateabsolute')
            ->add_field('tc.duedaterelative');
        $newcolumn->add_callback([format::class, 'certificationduedate']);
        $this->add_column($newcolumn);

        // Certification expiry date.
        $newcolumn = (new report_column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            'tool_certification'
        ))
            ->add_field('tc.expirydatetype')
            ->add_field('tc.expirydateabsolute')
            ->add_field('tc.expirydaterelative')
            ->add_field('tc.startdatetype')
            ->add_field('tc.startdateabsolute')
            ->add_field('tc.duedaterelative');
        $newcolumn->add_callback([format::class, 'certificationexpirydate']);
        $this->add_column($newcolumn);
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
            throw new moodle_exception('Helper action not allowed.');
        }

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
}
