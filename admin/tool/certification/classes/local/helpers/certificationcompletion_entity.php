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
 * File for the class certificationcompletion_entity
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification\local\helpers;

use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the certificationcompletion_entity and can be reused in any report datasource
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certificationcompletion_entity extends entity_base {
    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['tool_certification_compltion' => 'tcc'];
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = $this->get_filters_or_conditions(true);
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = $this->get_filters_or_conditions(false);
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Entity name
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'tool_certification_compltion';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('usercompletion', 'tool_certification');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        $columns = [];
        $tablealias = $this->get_table_alias('tool_certification_compltion');

        // User certified date.
        $columns[] = (new report_column(
            'certifieddate',
            new lang_string('certifieddate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("$tablealias.timecertified")
            ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'userdate']);

        // Column Expiry date.
        $columns[] = (new report_column(
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$tablealias.expirydate")
            ->set_is_sortable(true)
            ->add_callback([certificationcompletion_format::class, 'expirydate']);

        // Column Expired.
        $columns[] = (new report_column(
            'expired',
            new lang_string('expired', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("
            CASE
            WHEN ($tablealias.expirydate < " . time() . " AND $tablealias.expirydate > 0
                AND $tablealias.id IS NOT NULL AND $tablealias.timerevoked = 0)
            THEN 1
            WHEN $tablealias.id IS NULL
            THEN NULL
            ELSE 0
            END", 'expired')
            ->set_is_sortable(true)
            ->add_callback([certificationcompletion_format::class, 'expired']);

        // Column Certified/completed.
        $columns[] = (new report_column(
            'certified',
            new lang_string('certified', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_BOOLEAN)
            ->add_field("CASE WHEN $tablealias.id IS NOT NULL THEN 1 ELSE 0 END", 'certified')
            ->set_is_sortable(true)
            ->add_callback([format::class, 'checkbox_as_text']);

        // Column Certified as: Manually/Upon completion.
        $columns[] = (new report_column(
            'certifiedtype',
            new lang_string('certifiedtype', 'tool_certification'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field("
                CASE
                WHEN $tablealias.certifiedby IS NOT NULL
                THEN 2
                ELSE 1
                END
            ", 'certifiedtype')
            ->add_callback([certificationcompletion_format::class, 'certifiedtype'])
            ->add_aggregation_callback('groupconcat', [certificationcompletion_format::class, 'certifiedtype'])
            ->add_aggregation_callback('groupconcatdistinct', [certificationcompletion_format::class, 'certifiedtype']);

        return $columns;
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];
        $tablealias = $this->get_table_alias('tool_certification_compltion');

        // Filter for certifieddate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'certifieddate',
            new lang_string('certifieddate', 'tool_certification'),
            $this->get_entity_name(),
            "$tablealias.timecreated"
        ))
            ->add_joins($this->get_joins());

        // Filter for certified.
        $filters[] = (new report_filter(
            checkbox::class,
            'certified',
            new lang_string('certified', 'tool_certification'),
            $this->get_entity_name(),
            "CASE WHEN {$tablealias}.id IS NOT NULL THEN 1 ELSE 0 END"
        ))
            ->add_joins($this->get_joins());

        // Filter for expired.
        $filters[] = (new report_filter(
            checkbox::class,
            'expired',
            new lang_string('expired', 'tool_certification'),
            $this->get_entity_name(),
            "CASE WHEN ($tablealias.id IS NOT NULL AND $tablealias.expirydate < " . time() .
            " AND $tablealias.timerevoked = 0) THEN 1 ELSE 0 END"
        ))
            ->add_joins($this->get_joins());

        // Filter for expirydate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'expirydate',
            new lang_string('expirydate', 'tool_certification'),
            $this->get_entity_name(),
            "CASE WHEN ($tablealias.id IS NOT NULL AND $tablealias.expirydate > 0 AND
                $tablealias.timerevoked = 0) THEN $tablealias.expirydate ELSE NULL END"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
