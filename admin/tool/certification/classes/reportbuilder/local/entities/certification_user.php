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

declare(strict_types=1);

namespace tool_certification\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use tool_certification\api;
use tool_certification\constants;
use tool_certification\reportbuilder\local\formatters\certification_user as certificationuser_formatter;

/**
 * Certification user entity class implementation
 *
 * This entity uses tool_certification_users as the main table but in order for some columns/filters to work it needs to
 * join also with tool_certification_completion (to check user allocation completions).
 *
 * @package     tool_certification
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 David Matamoros <davidmc@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certification_user extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_certification_users' => 'tcu', 'tool_certification_compltion' => 'tcc'];
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('userallocation', 'tool_certification');
    }

    /**
     * Initialise the entity
     *
     * @return base
     */
    public function initialise(): base {

        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }

        foreach ($this->get_all_filters() as $filter) {
            $this
                ->add_filter($filter)
                ->add_condition($filter);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $certificationuser = $this->get_table_alias('tool_certification_users');
        $completion = $this->get_table_alias('tool_certification_compltion');
        $dateformat = get_string('strftimedatefullshort', 'core_langconfig');

        // Allocationtype column.
        $columns[] = (new column(
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$certificationuser}.allocationtype")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_formatter::class, 'allocationtype']);

        // Expirydate column.
        $columns[] = (new column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$completion}.expirydate")
            ->add_field("{$certificationuser}.userid")
            ->add_field("{$certificationuser}.certificationid")
            ->add_field("{$completion}.id", "completionid")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_formatter::class, 'expirydate']);

        // Suspended column.
        $columns[] = (new column(
            'suspended',
            new lang_string('suspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("CASE WHEN {$certificationuser}.status = " . constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END", 'suspended')
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'boolean_as_text']);

        // Time suspended column.
        $columns[] = (new column(
            'timesuspended',
            new lang_string('timesuspended', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$certificationuser}.timesuspended")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Allocation date (time created) column.
        $columns[] = (new column(
            'timecreated',
            new lang_string('allocationdate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$certificationuser}.timecreated")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Time modified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$certificationuser}.timemodified")
            ->set_is_sortable(true)
            ->add_callback([\core_reportbuilder\local\helpers\format::class, 'userdate'], $dateformat);

        // Certification status column.
        $program = database::generate_alias();
        $programuser = database::generate_alias();
        $columns[] = (new column(
            'certificationstatus',
            new lang_string('certificationstatus', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_program} {$program} ON {$program}.id = {$certificationuser}.currentprogramid")
            ->add_join("
                LEFT JOIN {tool_program_users} {$programuser}
                       ON {$programuser}.userid = {$certificationuser}.userid
                      AND {$programuser}.programid = {$program}.id
                      AND {$programuser}.certificationid = {$certificationuser}.certificationid
            ")
            ->set_type(column::TYPE_TEXT)
            ->add_field(api::get_status_sql_cases(0, $certificationuser, $completion, $programuser), 'status')
            ->add_field("{$programuser}.certificationid")
            ->set_is_sortable(true)
            ->add_callback([certificationuser_formatter::class, 'status']);

        // Days taking certification column.
        $programuser = database::generate_alias();
        $columns[] = (new column(
            'daystakingcertification',
            new lang_string('daystakingcertification', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_program} {$program} ON {$program}.id = {$certificationuser}.currentprogramid")
            ->add_join("
                LEFT JOIN {tool_program_users} {$programuser}
                       ON {$programuser}.userid = {$certificationuser}.userid
                      AND {$programuser}.programid = {$program}.id
                      AND {$programuser}.certificationid = {$certificationuser}.certificationid
            ")
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_daystakingcertification_sql($programuser, $completion), 'daystakingcertification')
            ->set_is_sortable(true)
            ->add_callback([certificationuser_formatter::class, 'dayround']);

        // Days since allocation column.
        $columns[] = (new column(
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field($this->get_dayssinceallocation_sql($completion), 'dayssinceallocation')
            ->set_is_sortable(true)
            ->add_callback([certificationuser_formatter::class, 'dayround']);

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $certificationuser = $this->get_table_alias('tool_certification_users');
        $completion = $this->get_table_alias('tool_certification_compltion');

        // Allocationtype filter.
        $filters[] = (new filter(
            select::class,
            'allocationtype',
            new lang_string('allocationsource', 'tool_certification'),
            $this->get_entity_name(),
            "{$certificationuser}.allocationtype"
        ))
            ->add_joins($this->get_joins())
            ->set_options_callback([certificationuser_formatter::class, 'get_allocation_sources']);

        // Suspended filter.
        $filters[] = (new filter(
            boolean_select::class,
            'suspended',
            new lang_string('suspended', 'tool_certification'),
            $this->get_entity_name(),
            "{$certificationuser}.suspended"
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql("CASE WHEN {$certificationuser}.status = " . constants::STATUS_OVERRIDE_SUSPENDED .
                " THEN 1 ELSE 0 END");

        // Time suspended filter.
        $filters[] = (new filter(
            date::class,
            'timesuspended',
            new lang_string('timesuspended', 'tool_certification'),
            $this->get_entity_name(),
            "{$certificationuser}.timesuspended"
        ))
            ->add_joins($this->get_joins());

        // Allocation date (time created) filter.
        $filters[] = (new filter(
            date::class,
            'timecreated',
            new lang_string('allocationdate', 'tool_certification'),
            $this->get_entity_name(),
            "{$certificationuser}.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'tool_certification'),
            $this->get_entity_name(),
            "{$certificationuser}.timemodified"
        ))
            ->add_joins($this->get_joins());

        // Status filter.
        $program = database::generate_alias();
        $programuser = database::generate_alias();
        $filters[] = (new filter(
            select::class,
            'filterablestatus',
            new lang_string('certificationstatus', 'tool_certification'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $certificationuser, $completion, $programuser, true)
        ))
            ->add_joins($this->get_joins())
            ->add_join("LEFT JOIN {tool_program} {$program} ON {$program}.id = {$certificationuser}.currentprogramid")
            ->add_join("
                LEFT JOIN {tool_program_users} {$programuser}
                       ON {$programuser}.userid = {$certificationuser}.userid
                      AND {$programuser}.programid = {$program}.id
                      AND {$programuser}.certificationid = {$certificationuser}.certificationid
            ")
            ->set_options_callback([api::class, 'get_certification_statuses_fieldset']);

        // Days taking certification filter.
        $programuser = database::generate_alias();
        $certificationcompletion = database::generate_alias();
        $programuserjoin = "
            LEFT JOIN {tool_program_users} {$programuser}
                   ON {$programuser}.userid = {$certificationuser}.userid
                  AND {$programuser}.certificationid = {$certificationuser}.certificationid
        ";
        $completionjoin = $this->get_completion_join($certificationuser, $certificationcompletion);
        $filters[] = (new filter(
            number::class,
            'daystakingcertification',
            new lang_string('daystakingcertification', 'tool_certification'),
            $this->get_entity_name(),
            $this->get_daystakingcertification_sql($programuser, $certificationcompletion)
        ))
            ->add_joins($this->get_joins())
            ->add_joins([$programuserjoin, $completionjoin]);

        // Days since allocation filter.
        $certificationcompletion = database::generate_alias();
        $completionjoin = $this->get_completion_join($certificationuser, $certificationcompletion);
        $filters[] = (new filter(
            number::class,
            'dayssinceallocation',
            new lang_string('dayssinceallocation', 'tool_certification'),
            $this->get_entity_name(),
            $this->get_dayssinceallocation_sql($certificationcompletion)
        ))
            ->add_joins($this->get_joins())
            ->add_join($completionjoin);

        return $filters;
    }

    /**
     * Returns certification completion join
     *
     * @param string $certificationuser
     * @param string $completion
     * @return string
     */
    private function get_completion_join(string $certificationuser, string $completion): string {
        return "
            LEFT JOIN {tool_certification_compltion} {$completion}
                   ON {$completion}.certificationid = {$certificationuser}.certificationid
                  AND {$completion}.userid = {$certificationuser}.userid
                  AND {$completion}.timerevoked = 0
                  AND {$completion}.islast = 1";
    }

    /**
     * Returns daystakingcertification sql
     *
     * This method returns:
     * - Zero if the user is allocated to the certification but it has not started yet.
     * - The number of days between now and the time user was certified (if user is certified).
     * - The number of days in case it has already started and the user is still taking the certification.
     *
     * @param string $programuser
     * @param string $completion
     * @return string
     */
    private function get_daystakingcertification_sql(string $programuser, string $completion): string {
        $certificationuser = $this->get_table_alias('tool_certification_users');
        return "
                CASE WHEN {$completion}.id IS NOT NULL
                      AND {$completion}.timerevoked = 0
                      AND {$completion}.islast = 1
                      AND {$programuser}.startdate > 0
                      AND {$completion}.timecertified > {$programuser}.startdate
                     THEN ({$completion}.timecertified - {$programuser}.startdate) / ".DAYSECS."
                     WHEN {$completion}.id IS NOT NULL
                      AND {$completion}.timerevoked = 0
                      AND {$programuser}.startdate > 0
                      AND {$completion}.timecreated <= {$programuser}.startdate
                     THEN 0
                     WHEN ({$completion}.id IS NOT NULL
                      AND {$completion}.timerevoked = 0
                      AND {$completion}.islast = 1
                      AND {$programuser}.startdate = 0)
                       OR {$completion}.id IS NULL
                     THEN (".time()." - {$certificationuser}.timecreated) / ".DAYSECS."
                     ELSE NULL
                END
                ";
    }

    /**
     * Returns dayssinceallocation sql
     *
     * This method returns:
     * - The number of days since it was allocated and the time user was certified (if user is certified).
     * - The number of days since it was allocated and the user is still taking the certification.
     *
     * @param string $completion
     * @return string
     */
    private function get_dayssinceallocation_sql(string $completion): string {
        $certificationuser = $this->get_table_alias('tool_certification_users');
        return "
                CASE
                WHEN {$completion}.id IS NOT NULL AND {$completion}.timerevoked = 0 AND {$completion}.islast = 1
                AND {$completion}.timecreated > {$certificationuser}.timecreated
                THEN ({$completion}.timecreated - {$certificationuser}.timecreated) / ".DAYSECS."
                ELSE (".time()." - {$certificationuser}.timecreated) / ".DAYSECS."
                END
                ";
    }
}
