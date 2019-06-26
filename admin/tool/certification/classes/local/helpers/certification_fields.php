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
 * Class certification_fields
 *
 * @package   tool_certification
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_certification\local\helpers;

use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Typical fields from the certification table that can be added
 *
 * @package     tool_certification
 * @copyright   2019 David Matamoros <davidmc@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certification_fields extends entity_base {
    /** @var string */
    protected $certificationjoin = '';
    /** @var string */
    protected $certificationtablealias = 'tc';
    /** @var array */
    protected $excludefields = [];

    /**
     * certification_fields constructor.
     *
     * @param string      $certificationjoin
     * @param string      $certificationtablealias
     * @param array       $excludefields
     */
    public function __construct(string $certificationjoin = '', string $certificationtablealias = 'tc',
                                array $excludefields = []) {
        $this->certificationjoin       = $certificationjoin;
        $this->certificationtablealias = $certificationtablealias;
        $this->excludefields     = array_combine($excludefields, $excludefields);
    }

    /**
     * Entity name
     * @return string
     */
    public function get_entity_name(): string {
        return 'tool_certification';
    }

    /**
     * Entity title
     * @return \lang_string
     */
    public function get_entity_title(): \lang_string {
        return new \lang_string('entitycertification', 'tool_certification');
    }

    /**
     * Certification fields.
     *
     * @return array
     */
    protected function get_certification_fields(): array {
        $fields = [
            'idnumber'                    => new \lang_string('idnumber'),
            'fullname'                    => new \lang_string('fullname'),
            'program'                     => new \lang_string('program', 'tool_certification'),
            'allocationstartdateabsolute' => new \lang_string('allocationstartdate', 'tool_certification'),
            'allocationenddateabsolute'   => new \lang_string('allocationenddate', 'tool_certification'),
            'archived'                    => new \lang_string('archived', 'tool_certification'),
            'timearchived'                => new \lang_string('archivedon', 'tool_certification'),
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

        foreach ($this->get_certification_fields() as $key => $field) {
            $newcolumn = (new report_column(
                $key,
                $field,
                'tool_certification'
            ))
                ->add_join($this->certificationjoin)
                ->add_field($this->certificationtablealias . '.' . $key);

            switch ($key) {
                case 'allocationstartdateabsolute':
                    $newcolumn->add_field('tc.allocationstartdatetype');
                    $newcolumn->add_callback([format::class, 'allocationdate'], $key);
                    break;
                case 'allocationenddateabsolute':
                    $newcolumn->add_field('tc.allocationenddatetype');
                    $newcolumn->add_callback([format::class, 'allocationdate'], $key);
                    break;
                default:
                    $newcolumn->add_callback([$this, 'format'], $key);
                    break;
            }
            $columns[] = $newcolumn;
        }

        return $columns;
    }

    /**
     * Fields format function
     *
     * @param mixed     $value
     * @param \stdClass $row
     * @param string    $fieldname
     * @return mixed
     */
    public function format($value, \stdClass $row, string $fieldname) {
        if (in_array(
            $fieldname,
            [
                'startdateabsolute',
                'duedateabsolute',
                'expirydateabsolute',
                'timearchived'
            ]
        )) {
            return \tool_reportbuilder\local\helpers\format::userdate($value, $row);
        }

        if ($fieldname === 'archived') {
            return format::archived($value, $row);
        }

        return clean_param($value, PARAM_NOTAGS);
    }

    /**
     * Returns all available conditions on certification fields
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        $filters = [];

        $fields = $this->get_certification_fields();
        foreach ($fields as $field => $name) {
            $filter    = new report_filter(
                $this->get_filter_class_name($field, false),
                $field,
                $name,
                'tool_certification',
                $this->certificationtablealias . '.' . $field
            );
            $filter->add_join($this->certificationjoin);
            $filters[] = $filter;
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
            case 'allocationstartdatetype':
            case 'allocationstartdateabsolute':
            case 'allocationenddatetype':
            case 'allocationenddateabsolute':
            case 'timearchived':
                return $iscondition ? date_condition::class : date_filter::class;
            default:
                return text::class;
                break;
        }
    }

    /**
     * Returns all available conditions on certification fields
     *
     * @return report_filter[]
     */
    public function get_conditions(): array {
        $filters = [];

        $fields = $this->get_certification_fields();
        foreach ($fields as $field => $name) {
            $filter    = new report_filter(
                $this->get_filter_class_name($field, false),
                $field,
                $name,
                'tool_certification',
                $this->certificationtablealias . '.' . $field
            );
            $filter->add_join($this->certificationjoin);
            $filters[] = $filter;
        }
        return $filters;
    }
}