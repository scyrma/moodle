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
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\tool_reportbuilder\datasources;

use lang_string;
use moodle_exception;
use tool_program\local\helpers\format;
use tool_program\local\helpers\program_fields;
use tool_reportbuilder\datasource;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Class report_programs
 *
 * @package   tool_program
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_programs extends datasource {

    /**
     * Initialise report
     */
    protected function initialise(): void {
        $this->set_main_table('tool_program', 'tp');
        $this->add_base_join('left join {context} ctx on ctx.instanceid = tp.id and ctx.contextlevel = 10');

        $this->set_downloadable(false);
        $this->set_columns();
        $this->set_conditions();
        $this->set_filters();

        $this->get_column('tool_program:fullname')
            ->set_is_default(true)
            ->set_is_sortable(true, true, 1);
    }

    /**
     * Get the visible name of the report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('programs', 'tool_program');
    }

    /**
     * Gets an instance of program_fields_helper that is used to add typical program columns, filters and conditions
     *
     * @return program_fields
     */
    protected function get_program_fields_helper(): program_fields {
        return new program_fields(
            '',
            'tp',
            [
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
        $this->add_entity($this->get_program_fields_helper());

        // Program start date.
        $newcolumn = (new report_column(
            'startdate',
            new lang_string('startdate', 'tool_program'),
            'tool_program'
        ))
            ->add_field('tp.startdatetype')
            ->add_field('tp.startdateabsolute')
            ->add_field('tp.startdaterelative');
        $newcolumn->add_callback([format::class, 'programstartdate']);
        $this->add_column($newcolumn);

        // Program due date.
        $newcolumn = (new report_column(
            'duedate',
            new lang_string('duedate', 'tool_program'),
            'tool_program'
        ))
            ->add_field('tp.startdatetype')
            ->add_field('tp.startdateabsolute')
            ->add_field('tp.duedatetype')
            ->add_field('tp.duedateabsolute')
            ->add_field('tp.duedaterelative')
            ->add_field('tp.enddatetype')
            ->add_field('tp.enddateabsolute');
        $newcolumn->add_callback([format::class, 'programduedate']);
        $this->add_column($newcolumn);

        // Program expiry date.
        $newcolumn = (new report_column(
            'enddate',
            new lang_string('enddate', 'tool_program'),
            'tool_program'
        ))
            ->add_field('tp.enddatetype')
            ->add_field('tp.enddateabsolute')
            ->add_field('tp.enddaterelative')
            ->add_field('tp.startdatetype')
            ->add_field('tp.startdateabsolute')
            ->add_field('tp.duedatetype')
            ->add_field('tp.duedateabsolute')
            ->add_field('tp.duedaterelative');
        $newcolumn->add_callback([format::class, 'programenddate']);
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
    }
}
