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
 * File for the class certificationcompletion_entity
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
use \tool_wp\db;

defined('MOODLE_INTERNAL') || die();

/**
 * Columns, filters and conditions that defines the certificationcompletion_entity and can be reused in any report datasource
 *
 * @package    tool_certification
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificationcompletion_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tcc';
    /** @var array */
    protected $excludecolumns = [];

    /**
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     */
    public function __construct(string $join = '', string $tablealias = 'tcc', array $excludecolumns = []) {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_certification_compltion';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('usercompletion', 'tool_certification');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        // User certified date.
        if (!isset($this->excludecolumns['certifieddate'])) {
            $columns[] = (new report_column(
                'certifieddate',
                new lang_string('certifieddate', 'tool_certification'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->set_is_sortable(true)
                ->add_field("$this->tablealias.timecreated")
                ->add_callback([\tool_reportbuilder\local\helpers\format::class, 'userdate']);
        }

        // Column Expiry date.
        if (!isset($this->excludecolumns['expirydate'])) {
            $columns[] = (new report_column(
                'expirydate',
                new lang_string('expirydate', 'tool_certification'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TIMESTAMP)
                ->add_field("$this->tablealias.expirydate")
                ->set_is_sortable(true)
                ->add_callback([certificationcompletion_format::class, 'expirydate']);
        }

        // Column Expired.
        if (!isset($this->excludecolumns['expired'])) {
            $columns[] = (new report_column(
                'expired',
                new lang_string('expired', 'tool_certification'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_BOOLEAN)
                ->add_field("
                CASE
                WHEN ($this->tablealias.expirydate < " . time() . " AND $this->tablealias.expirydate > 0
                    AND $this->tablealias.id IS NOT NULL AND $this->tablealias.timerevoked = 0)
                THEN 1
                WHEN $this->tablealias.id IS NULL
                THEN NULL
                ELSE 0
                END", 'expired')
                ->set_is_sortable(true)
                ->add_callback([certificationcompletion_format::class, 'expired']);
        }

        // Column Certified/completed.
        if (!isset($this->excludecolumns['certified'])) {
            $columns[] = (new report_column(
                'certified',
                new lang_string('certified', 'tool_certification'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_BOOLEAN)
                ->add_field("CASE WHEN $this->tablealias.id IS NOT NULL THEN 1 ELSE 0 END", 'certified')
                ->set_is_sortable(true)
                ->add_callback([format::class, 'checkbox_as_text']);
        }

        // Column Certified as: Manually/Upon completion.
        if (!isset($this->excludecolumns['certifiedtype'])) {
            $tpu = db::generate_alias();
            $tps = db::generate_alias();
            $tpsc = db::generate_alias();
            $joinprograms = "
            LEFT JOIN {tool_program_users} $tpu
            ON $tpu.certificationid = $this->tablealias.certificationid
            AND $tpu.userid = $this->tablealias.userid
            LEFT JOIN {tool_program_sets} $tps
            ON $tps.programid = $tpu.programid AND $tps.parent = 0
            LEFT JOIN {tool_program_set_completion} $tpsc
            ON $tpsc.setid = $tps.id AND $tpsc.userid = $this->tablealias.userid";

            $columns[] = (new report_column(
                'certifiedtype',
                new lang_string('certifiedtype', 'tool_certification'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->add_join($joinprograms)
                ->set_type(constants::DB_TYPE_TEXT)
                ->set_is_sortable(true)
                ->add_field("
                    CASE
                    WHEN $this->tablealias.id IS NOT NULL
                    AND $this->tablealias.timerevoked = 0
                    AND $tpsc.id IS NOT NULL
                    AND $tpsc.completeddate > 0
                    THEN 1
                    WHEN $this->tablealias.id IS NOT NULL
                    AND $this->tablealias.timerevoked = 0
                    AND $tpsc.id IS NULL
                    THEN 2
                    ELSE 0
                    END
                ", 'certifiedtype')
                ->add_callback([certificationcompletion_format::class, 'certifiedtype'])
                ->add_aggregation_callback('groupconcat', [certificationcompletion_format::class, 'certifiedtype'])
                ->add_aggregation_callback('groupconcatdistinct', [certificationcompletion_format::class, 'certifiedtype']);
        }

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

        if (!isset($this->excludecolumns['certifieddate'])) {
            // Filter for certifieddate.
            $filters[] = (new report_filter(
                $iscondition ? date_condition::class : date_filter::class,
                'certifieddate',
                new lang_string('certifieddate', 'tool_certification'),
                $this->get_entity_name(),
                "$this->tablealias.timecreated"
            ))
                ->add_join($this->join);
        }

        if (!isset($this->excludecolumns['certified'])) {
            // Filter for certified.
            $filters[] = (new report_filter(
                checkbox::class,
                'certified',
                new lang_string('certified', 'tool_certification'),
                $this->get_entity_name(),
                "CASE WHEN {$this->tablealias}.id IS NOT NULL THEN 1 ELSE 0 END"
            ))
                ->add_join($this->join);
        }

        if (!isset($this->excludecolumns['expired'])) {
            // Filter for expired.
            $filters[] = (new report_filter(
                checkbox::class,
                'expired',
                new lang_string('expired', 'tool_certification'),
                $this->get_entity_name(),
                "CASE WHEN ($this->tablealias.id IS NOT NULL AND $this->tablealias.expirydate < " . time() .
                " AND $this->tablealias.timerevoked = 0) THEN 1 ELSE 0 END"
            ))
                ->add_join($this->join);
        }

        if (!isset($this->excludecolumns['expirydate'])) {
            // Filter for expirydate.
            $filters[] = (new report_filter(
                $iscondition ? date_condition::class : date_filter::class,
                'expirydate',
                new lang_string('expirydate', 'tool_certification'),
                $this->get_entity_name(),
                "CASE WHEN ($this->tablealias.id IS NOT NULL AND $this->tablealias.expirydate > 0 AND
                    $this->tablealias.timerevoked = 0) THEN $this->tablealias.expirydate ELSE NULL END"
            ))
                ->add_join($this->join);
        }

        return $filters;
    }
}