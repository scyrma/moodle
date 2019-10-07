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
 * File for class programitem_entity
 *
 * @package    tool_program
 * @author     2019 David Matamoros <davidmc@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 */
class programitem_entity extends entity_base {
    /** @var string */
    protected $join = '';
    /** @var string */
    protected $tablealias = 'tpitem';
    /** @var array */
    protected $excludecolumns = [];
    /** @var string */
    protected $tableprogramalias = 'tp';
    /** @var string */
    protected $tableuseralias = 'u';

    /**
     * program_fields constructor.
     *
     * @param string $join
     * @param string $tablealias
     * @param array $excludecolumns
     * @param string $tableprogramalias
     * @param string $tableuseralias
     */
    public function __construct(string $join = '', string $tablealias = 'tpitem', array $excludecolumns = [],
                                string $tableprogramalias = 'tp', string $tableuseralias = 'u') {
        $this->join = $join;
        $this->tablealias = $tablealias;
        $this->excludecolumns = array_combine($excludecolumns, $excludecolumns);
        $this->tableprogramalias = $tableprogramalias;
        $this->tableuseralias = $tableuseralias;
    }

    /**
     * Entity name
     *
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_program_item';
    }

    /**
     * Entity title
     *
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityprogramitem', 'tool_program');
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns(): array {
        $columns = [];

        if (!isset($this->excludecolumns['type'])) {
            // Column program item type.
            $newcolumn = (new report_column(
                'type',
                new lang_string('type', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.isset")
                ->add_callback([programitem_format::class, 'type']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['name'])) {
            // Column program item name.
            $newcolumn = (new report_column(
                'name',
                new lang_string('name', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tableprogramalias.fullname")
                ->add_field("$this->tablealias.name")
                ->add_field("$this->tablealias.isset")
                ->add_callback([programitem_format::class, 'name']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['completioncriteria'])) {
            // Column completion criteria.
            $newcolumn = (new report_column(
                'completioncriteria',
                new lang_string('completioncriteria', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.completioncriteria")
                ->add_field("$this->tablealias.completionatleast")
                ->add_field("$this->tablealias.isset")
                ->add_callback([programitem_format::class, 'completioncriteria']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['parentname'])) {
            // Column parent name.
            $newcolumn = (new report_column(
                'parentname',
                new lang_string('parentname', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.parent")
                ->add_field("$this->tableuseralias.id", 'userid')
                ->add_field("$this->tableprogramalias.id", 'programid')
                ->add_callback([programitem_format::class, 'parentname']);
            $columns[] = $newcolumn;
        }

        if (!isset($this->excludecolumns['progresspercent'])) {
            // Column progress percentage.
            $newcolumn = (new report_column(
                'progresspercent',
                new lang_string('progresspercent', 'tool_program'),
                $this->get_entity_name()
            ))
                ->add_join($this->join)
                ->set_type(constants::DB_TYPE_TEXT)
                ->add_field("$this->tablealias.isset")
                ->add_field("$this->tablealias.id")
                ->add_field("$this->tablealias.courseid")
                ->add_field("$this->tableuseralias.id", 'userid')
                ->add_field("$this->tableprogramalias.id", 'programid')
                ->add_callback([programitem_format::class, 'progress']);
            $columns[] = $newcolumn;
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
        return $filters;
    }
}