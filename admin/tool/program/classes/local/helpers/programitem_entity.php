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
 * File for class programitem_entity
 *
 * @package    tool_program
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\local\helpers;

use lang_string;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the programitem_entity and can be reused in any report datasource
 *
 * @package    tool_program
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programitem_entity extends entity_base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['program_item' => 'tpitem', 'tool_program' => 'tp', 'user' => 'u'];
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
        return 'tool_program_item';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entityprogramitem', 'tool_program');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns(): array {
        $columns = [];
        $tablealias = $this->get_table_alias('program_item');
        $tableprogramalias = $this->get_table_alias('tool_program');
        $tableuseralias = $this->get_table_alias('user');

        // Column program item type.
        $newcolumn = (new report_column(
            'type',
            new lang_string('type', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.isset")
            ->add_callback([programitem_format::class, 'type']);
        $columns[] = $newcolumn;

        // Column program item name.
        $newcolumn = (new report_column(
            'name',
            new lang_string('name', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tableprogramalias.fullname")
            ->add_field("$tablealias.name")
            ->add_field("$tablealias.isset")
            ->add_callback([programitem_format::class, 'name']);
        $columns[] = $newcolumn;

        // Column completion criteria.
        $newcolumn = (new report_column(
            'completioncriteria',
            new lang_string('completioncriteria', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.completioncriteria")
            ->add_field("$tablealias.completionatleast")
            ->add_field("$tablealias.isset")
            ->add_callback([programitem_format::class, 'completioncriteria']);
        $columns[] = $newcolumn;

        // Column parent name.
        $newcolumn = (new report_column(
            'parentname',
            new lang_string('parentname', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.parent")
            ->add_field("$tableuseralias.id", 'userid')
            ->add_field("$tableprogramalias.id", 'programid')
            ->add_callback([programitem_format::class, 'parentname']);
        $columns[] = $newcolumn;

        // Column progress percentage.
        $newcolumn = (new report_column(
            'progresspercent',
            new lang_string('progresspercent', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(constants::DB_TYPE_TEXT)
            ->add_field("$tablealias.isset")
            ->add_field("$tablealias.id")
            ->add_field("$tablealias.courseid")
            ->add_field("$tableuseralias.id", 'userid')
            ->add_field("$tableprogramalias.id", 'programid')
            ->add_callback([programitem_format::class, 'progress']);
        $columns[] = $newcolumn;

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
        return $filters;
    }
}
