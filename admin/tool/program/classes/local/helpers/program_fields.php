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
 * Class program_fields
 *
 * @package     tool_program
 * @copyright   2019 Toni Barbera <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_program\local\helpers;

use lang_string;
use stdClass;
use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\customfields;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Typical fields from the program table that can be added
 *
 * @package     tool_program
 * @copyright   2019 Toni Barbera <toni@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class program_fields extends entity_base {

    /** @var string */
    protected $programjoin = '';
    /** @var string */
    protected $programtablealias = 'tp';
    /** @var array */
    protected $excludefields = [];
    /** @var customfields */
    protected $customfields;

    /**
     * program_fields constructor.
     *
     * @param string      $programjoin
     * @param string      $programtablealias
     * @param array       $excludefields
     */
    public function __construct(string $programjoin = '', string $programtablealias = 'tp',
                                array $excludefields = []) {
        $this->programjoin       = $programjoin;
        $this->programtablealias = $programtablealias;
        $this->excludefields     = array_combine($excludefields, $excludefields);
        $this->customfields      = new customfields($programtablealias . '.id', $this->get_entity_name(),
                            'tool_program', 'program', 0);
    }

    /**
     * Entity name
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_program';
    }

    /**
     * Entity title
     * @return lang_string
     */
    public function get_entity_title(): lang_string {
        return new lang_string('entityprogram', 'tool_program');
    }

    /**
     * Program fields.
     *
     * @return array
     */
    protected function get_program_fields(): array {
        $fields = [
            'idnumber'                    => new lang_string('idnumber'),
            'fullname'                    => new lang_string('fullname'),
            'description'                 => new lang_string('description'),
            'visible'                     => new lang_string('visible'),
            'archived'                    => new lang_string('archived', 'tool_program'),
            'timearchived'                => new lang_string('archivedon', 'tool_program'),
            'allowdirectallocation'       => new lang_string('allowdirectallocation', 'tool_program'),
            'allocationstartdateabsolute' => new lang_string('reportstartallocation', 'tool_program'),
            'allocationenddateabsolute'   => new lang_string('reportendallocation', 'tool_program'),
        ];
        return array_diff_key($fields, $this->excludefields);
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    public function get_columns() : array {
        $columns = [];
        $defaultcolumns = ['fullname'];
        foreach ($this->get_program_fields() as $programkey => $programfield) {
            $newcolumn = (new report_column(
                $programkey,
                $programfield,
                'tool_program'
            ))
                ->add_join($this->programjoin)
                ->set_is_default(in_array($programkey, $defaultcolumns, true))
                ->add_field($this->programtablealias . '.' . $programkey);

            switch ($programkey) {
                case 'allocationstartdateabsolute':
                    $newcolumn->add_field('tp.allocationstartdatetype');
                    $newcolumn->add_callback([format::class, 'allocationstartdate'], $programkey);
                    break;
                case 'allocationenddateabsolute':
                    $newcolumn->add_field('tp.allocationenddaterelative');
                    $newcolumn->add_field('tp.allocationenddatetype');
                    $newcolumn->add_field('tp.allocationstartdateabsolute');
                    $newcolumn->add_field('tp.allocationstartdatetype');
                    $newcolumn->add_callback([format::class, 'allocationenddate'], $programkey);
                    break;
                case 'description':
                    $newcolumn->set_type(constants::DB_TYPE_LONGTEXT);
                    $newcolumn->add_callback([$this, 'format'], $programkey);
                    break;
                case 'fullname':
                    $newcolumn->set_type(constants::DB_TYPE_TEXT);
                    $newcolumn->add_callback([$this, 'format'], $programkey);
                    break;
                default:
                    $newcolumn->add_callback([$this, 'format'], $programkey);
                    break;
            }
            $columns[] = $newcolumn;
        }

        // Add program custom fields.
        $cfcolumns = $this->customfields->get_columns();
        if (!empty($cfcolumns)) {
            $columns = array_merge($columns, $cfcolumns);
        }

        return $columns;
    }

    /**
     * Fields format function
     *
     * @param mixed     $value
     * @param stdClass $row
     * @param string    $fieldname
     * @return mixed
     */
    public function format($value, stdClass $row, string $fieldname) {
        if (in_array(
            $fieldname,
            [
                'startdateabsolute',
                'duedateabsolute',
                'enddateabsolute',
                'timearchived'
            ]
        )) {
            return \tool_reportbuilder\local\helpers\format::userdate($value, $row);
        }

        if ($fieldname === 'visible') {
            return format::visible($value, $row);
        }

        if ($fieldname === 'archived') {
            return format::archived($value, $row);
        }

        if ($fieldname === 'allowdirectallocation') {
            return format::allowdirectallocation($value, $row);
        }

        return clean_param($value, PARAM_NOTAGS);
    }

    /**
     * Returns all available conditions on program fields
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        $filters = [];

        $fields = $this->get_program_fields();
        foreach ($fields as $field => $name) {
            if ($field !== 'description') {
                $filter    = new report_filter(
                    $this->get_filter_class_name($field, false),
                    $field,
                    $name,
                    'tool_program',
                    $this->programtablealias . '.' . $field
                );
                $filter->add_join($this->programjoin);
                $filters[] = $filter;
            }
        }

        // Add program custom fields filters.
        $cffilters = $this->customfields->get_filters();
        if (!empty($cffilters)) {
            $filters = array_merge($filters, $cffilters);
        }

        return $filters;
    }

    /**
     * Returns the class name to use for the filter (extending filter_base)
     *
     * @param string $field
     * @param bool   $iscondition true if this is condition, false if this is a filter
     * @return string
     */
    protected function get_filter_class_name(string $field, bool $iscondition): ?string {
        switch ($field) {
            case 'visible':
            case 'archived':
            case 'allowdirectallocation':
                return checkbox::class;
            case 'startdatetype':
            case 'startdateabsolute':
            case 'startdaterelative':
            case 'duedatetype':
            case 'duedateabsolute':
            case 'duedaterelative':
            case 'enddatetype':
            case 'enddateabsolute':
            case 'enddaterelative':
            case 'allocationstartdatetype':
            case 'allocationstartdateabsolute':
            case 'allocationenddatetype':
            case 'allocationenddateabsolute':
            case 'allocationenddaterelative':
            case 'timearchived':
                return $iscondition ? date_condition::class : date_filter::class;
            default:
                return text::class;
                break;
        }
    }

    /**
     * Returns all available conditions on program fields
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        $filters = [];

        $fields = $this->get_program_fields();
        foreach ($fields as $field => $name) {
            if ($field !== 'description') {
                $filter = new report_filter(
                    $this->get_filter_class_name($field, true),
                    $field,
                    $name,
                    'tool_program',
                    $this->programtablealias . '.' . $field
                );
                $filter->add_join($this->programjoin);
                $filters[] = $filter;
            }
        }

        // Add program custom fields conditions.
        $cffilters = $this->customfields->get_conditions();
        if (!empty($cffilters)) {
            $filters = array_merge($filters, $cffilters);
        }

        return $filters;
    }
}
