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
 * File for the class programcompletion_entity
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\helpers;

use lang_string;
use tool_program\api;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the programcompletion_entity and can be reused in any report datasource
 *
 * @package     tool_program
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Mitxel Moriana <mitxel@tresipunt.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class programcompletion_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tpsc';
    /** @var array */
    protected $excludecolumns = [];
    /** @var string Alias for tool_program_users table */
    protected $tablealiastpu = 'tpu';

    /**
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     * @param string $tablealiastpu
     */
    public function __construct(string $join = '', string $tablealias = 'tpsc',
                                array $excludecolumns = [], string $tablealiastpu = 'tpu') {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
        $this->tablealiastpu = $tablealiastpu;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_program_set_completion';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityprogramcompletion', 'tool_program');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        // Column completed.
        $newcolumn = (new report_column(
            'completed',
            new lang_string('completed', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.completeddate")
            ->add_callback([programcompletion_format::class, 'completed']);
        $columns[] = $newcolumn;

        // Column completeddate.
        $newcolumn = (new report_column(
            'completeddate',
            new lang_string('completiondate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.completeddate")
            ->add_callback([programcompletion_format::class, 'completeddate']);
        $columns[] = $newcolumn;

        // Column timemodified.
        $newcolumn = (new report_column(
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timemodified")
            ->add_callback([programcompletion_format::class, 'timemodified']);
        $columns[] = $newcolumn;

        // Column timecreated.
        $newcolumn = (new report_column(
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_type(constants::DB_TYPE_TIMESTAMP)
            ->add_field("$this->tablealias.timecreated")
            ->add_callback([programcompletion_format::class, 'timecreated']);
        $columns[] = $newcolumn;

        return $columns;
    }

    /**
     * Returns all available filters.
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        return $this->get_filters_or_conditions(false);
    }

    /**
     * Returns all available conditions.
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        return $this->get_filters_or_conditions(true);
    }

    /**
     * Filters/conditions for programs.
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Filter completeddate.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'completeddate',
            new lang_string('completiondate', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.completeddate");

        // Filter timemodified.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timemodified',
            new lang_string('timemodified', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timemodified");

        // Filter timecreated.
        $filters[] = (new report_filter(
            $iscondition ? date_condition::class : date_filter::class,
            'timecreated',
            new lang_string('timecreated', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("$this->tablealias.timecreated");

        // Filter Completed.
        $filters[] = (new report_filter(
            checkbox::class,
            'completed',
            new lang_string('completed', 'tool_program'),
            $this->get_entity_name()
        ))
            ->add_join($this->join)
            ->set_field_sql("(CASE WHEN ({$this->tablealias}.completeddate > 0) THEN 1 ELSE 0 END)");

        // Filter program status.
        $filters[] = (new report_filter(
            select::class,
            'programstatus',
            new lang_string('programstatus', 'tool_program'),
            $this->get_entity_name(),
            api::get_status_sql_cases(0, $this->tablealiastpu, $this->tablealias)
        ))
            ->set_options(api::get_program_statuses_fieldset());

        return $filters;
    }
}
