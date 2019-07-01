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
 * File for class jobs.
 *
 * @package   tool_organisation
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_organisation\local\entities;

use lang_string;
use tool_organisation\organisation;
use tool_organisation\tool_reportbuilder\filter\job_department;
use tool_organisation\tool_reportbuilder\filter\job_position;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the jobs entity and can be reused in any report datasource.
 *
 * @package   tool_organisation
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jobs extends entity_base {

    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'toj';
    /** @var array */
    protected $excludecolumns = [];

    /**
     * certification_entity constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     */
    public function __construct(string $join = '', string $tablealias = 'toj', array $excludecolumns = []) {
        $this->tablealias = $tablealias;
        $this->join = $join . $this->get_jobs_join();
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
    }

    /**
     * Additional join for jobs
     *
     * @return string
     */
    protected function get_jobs_join(): string {
        return "LEFT JOIN {tool_organisation_position} topos ON {$this->tablealias}.positionid = topos.id " .
            "LEFT JOIN {tool_organisation_department} tod ON {$this->tablealias}.departmentid = tod.id ";
    }

    /**
     * Entity name
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_organisation_jobs';
    }

    /**
     * Entity title
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('jobs', 'tool_organisation');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        foreach ($this->get_included_columns() as $columnid => $columnoptions) {
            $newcolumn = new report_column($columnid, $columnoptions['label'], $this->get_entity_name());
            $newcolumn->add_join($this->join);

            foreach ($columnoptions['fields'] as $otherfield => $otherfieldalias) {
                $newcolumn->add_field($otherfield, $otherfieldalias);
            }

            if (method_exists($columnoptions['callback']['class'], $columnoptions['callback']['method'])) {
                $newcolumn->add_callback([$columnoptions['callback']['class'], $columnoptions['callback']['method']]);
            }

            if (isset($columnoptions['type'])) {
                $newcolumn->set_type($columnoptions['type']);
            }

            $columns[] = $newcolumn;
        }

        return $columns;
    }

    /**
     * Returns all available conditions on jobs entity.
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        return $this->get_filters_or_conditions(false);
    }

    /**
     * Returns all available conditions on jobs entity.
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        return $this->get_filters_or_conditions(true);
    }

    /**
     * Filters/conditions for jobs
     *
     * @param bool $iscondition
     * @return array
     */
    protected function get_filters_or_conditions(bool $iscondition): array {
        $filters = [];

        // Position select filter.
        $filters[] = (new report_filter(
            job_position::class,
            'position',
            new lang_string('position', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('topos')
            ->set_options(organisation::get_all_positions_menu(
                ['' => get_string('anyposition', 'tool_organisation')]));

        // Department select filter.
        $filters[] = (new report_filter(
            job_department::class,
            'department',
            new lang_string('department', 'tool_organisation'),
            'tool_organisation_jobs'
        ))
            ->add_join($this->join)
            ->set_field_sql('tod')
            ->set_options(organisation::get_all_departments_menu(
                ['' => get_string('anydepartment', 'tool_organisation')]));

        return $filters;
    }

    /**
     * Jobs entity columns.
     *
     * @return array
     */
    protected function get_included_columns(): array {
        global $DB;
        $columns = [
            'position' => [
                'type' => constants::DB_TYPE_TEXT,
                'label' => new lang_string('position', 'tool_organisation'),
                'fields' => [
                    'topos.name' => ''
                ],
                'callback' => [
                    'class' => format::class,
                    'method' => 'format_string',
                ]
            ],
            'department' => [
                'type' => constants::DB_TYPE_TEXT,
                'label' => new lang_string('department', 'tool_organisation'),
                'fields' => [
                    'tod.name' => ''
                ],
                'callback' => [
                    'class' => format::class,
                    'method' => 'format_string',
                ]
            ],
            'positiondepartment' => [
                'type' => constants::DB_TYPE_TEXT,
                'label' => new lang_string('jobpositiondepartment', 'tool_organisation'),
                'fields' => [
                    $DB->sql_concat('topos.name', "' '", 'tod.name') => 'positiondepartment'
                ],
                'callback' => [
                    'class' => format::class,
                    'method' => 'format_string',
                ]
            ],
            'startdate' => [
                'type' => constants::DB_TYPE_TIMESTAMP,
                'label' => new lang_string('startdate', 'tool_organisation'),
                'fields' => [
                    "$this->tablealias.startdate" => ''
                ],
                'callback' => [
                    'class' => format::class,
                    'method' => 'userdate',
                ]
            ],
            'enddate' => [
                'type' => constants::DB_TYPE_TIMESTAMP,
                'label' => new lang_string('enddate', 'tool_organisation'),
                'fields' => [
                    "$this->tablealias.enddate" => ''
                ],
                'callback' => [
                    'class' => format::class,
                    'method' => 'userdate',
                ]
            ]
        ];
        return array_diff_key($columns, $this->excludecolumns);
    }
}