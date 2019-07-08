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
 * Class customfields
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder\local\helpers;

use coding_exception;
use core_customfield\data_controller;
use core_customfield\field_controller;
use core_customfield\handler;
use tool_reportbuilder\aggregation_base;
use tool_reportbuilder\constants;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\report_column;
use tool_reportbuilder\report_filter;
use tool_wp\db;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Class customfields
 *
 * @package   tool_reportbuilder
 * @copyright 2019 David Matamoros <davidmc@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class customfields {

    /** @var string Name of the entity */
    private $entityname;

    /**
     * The component this handler handles
     *
     * @var string $component
     */
    private $component;

    /**
     * The area within the component
     *
     * @var string $area
     */
    private $area;

    /**
     * The id of the item within the area and component

     * @var int $itemid
     */
    private $itemid;

    /**
     * The prefix for the report columns

     * @var int $prefix
     */
    private $prefix;

    /**
     * The handler for the customfields

     * @var int $handler
     */
    private $handler;

    /**
     * The table alias and the field name (table.field) that matches the customfield instanceid.

     * @var int $tablefieldalias
     */
    private $tablefieldalias;

    /** @var array additional joins */
    private $joins = [];

    /**
     * Class customfields constructor.
     *
     * @param string $tablefieldalias table alias and the field name (table.field) that matches the customfield instanceid.
     * @param string $entityname name of the entity in the datasource where we add custom fields
     * @param string $component component name of full frankenstyle plugin name
     * @param string $area name of the area (each component/plugin may define handlers for multiple areas)
     * @param int $itemid item id if the area uses them (usually not used)
     */
    public function __construct(string $tablefieldalias, string $entityname, string $component, string $area, int $itemid = 0) {
        $this->tablefieldalias = $tablefieldalias;
        $this->component = $component;
        $this->entityname = $entityname;
        $this->area = $area;
        $this->itemid = $itemid;
        $this->handler = handler::get_handler($this->component, $this->area, $this->itemid);
        $this->prefix = clean_param($tablefieldalias, PARAM_ALPHANUMEXT);
    }

    /**
     * Additional joins that are needed
     *
     * @param string $join
     * @return customfields
     */
    public function add_join($join): customfields {
        if (strlen($join)) {
            $this->joins[] = $join;
        }
        return $this;
    }

    /**
     * Gets the custom fields columns for the report.
     *
     * @return array report columns
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function get_columns() {
        $columns = [];
        $categorieswithfields = $this->handler->get_categories_with_fields();
        if (!empty($categorieswithfields)) {
            foreach ($categorieswithfields as $fieldcategory) {
                $categoryfields = $fieldcategory->get_fields();
                foreach ($categoryfields as $field) {
                    $d = db::generate_alias();
                    $dc = data_controller::create(0, null, $field);
                    $datafield = $dc->datafield();

                    $selectfields = "$d.id,$d.".$datafield;
                    if ($datafield === 'value') {
                        $selectfields .= ",$d.valueformat";
                    }

                    $columnname = format_string($field->get('name'), true, ['escape' => false]);

                    $newcolumn = (new report_column(
                        $this->prefix.$field->get('id'),
                        new \lang_string('customfieldcolumn', 'tool_reportbuilder', $columnname),
                        $this->entityname
                    ));

                    if ($this->joins) {
                        foreach ($this->joins as $join) {
                            $newcolumn->add_join($join);
                        }
                    }

                    $newcolumn
                        ->add_join("LEFT JOIN {customfield_data} $d
                            ON $d.fieldid = ".$field->get('id')." AND $d.instanceid = $this->tablefieldalias")
                        ->add_fields($selectfields)
                        ->add_callback([$this, 'customfield_value'], $field)
                        // Important. If the handler implements can_view() function, it will be called with parameter $instanceid=0.
                        // This means that per-instance access validation will be ignored.
                        // It is impossible to call it inside aggregation methods.
                        ->set_is_available($this->handler->can_view($field, 0));

                    // Aggregation for groupconcat.
                    $newcolumn->add_aggregation_fields('groupconcat', "$d.$datafield")
                        ->add_aggregation_callback('groupconcat', [$this, 'customfield_aggregation_group'], $field);

                    // Aggregation for groupconcatdistinct.
                    $newcolumn->add_aggregation_fields('groupconcatdistinct', "$d.$datafield")
                        ->add_aggregation_callback('groupconcatdistinct', [$this, 'customfield_aggregation_group'], $field);

                    // Aggregation for count.
                    $newcolumn->add_aggregation_fields('count', "$d.$datafield");

                    // Aggregation for countdtistinct.
                    $newcolumn->add_aggregation_fields('countdistinct', "$d.$datafield");

                    // Aggregation for sum.
                    $newcolumn->add_aggregation_fields('sum', "$d.$datafield");

                    // Aggregation for avg.
                    $newcolumn->add_aggregation_fields('avg', "$d.$datafield")
                        ->add_aggregation_callback('avg', [$this, 'customfield_aggregation'], $field);

                    // Aggregation for max.
                    $newcolumn->add_aggregation_fields('max', "$d.$datafield")
                        ->add_aggregation_callback('max', [$this, 'customfield_aggregation'], $field);

                    // Aggregation for min.
                    $newcolumn->add_aggregation_fields('min', "$d.$datafield")
                        ->add_aggregation_callback('min', [$this, 'customfield_aggregation'], $field);

                    // Aggregation for percent.
                    $newcolumn->add_aggregation_fields('percent', "$d.$datafield");

                    // Aggregation for unique.
                    $newcolumn->add_aggregation_fields('unique', "$d.$datafield");

                    // Type of columns.
                    if ($field->get('type') === 'checkbox') {
                        $newcolumn->set_type(constants::DB_TYPE_BOOLEAN);
                    } else if ($field->get('type') === 'date') {
                        $newcolumn->set_type(constants::DB_TYPE_DATETIME);
                    } else if ($datafield === 'intvalue' || $datafield === 'decvalue') {
                        $newcolumn->set_type(constants::DB_TYPE_NUMBER);
                    } else if ($datafield === 'value') {
                        $newcolumn->set_type(constants::DB_TYPE_LONGTEXT);
                    } else {
                        $newcolumn->set_type(constants::DB_TYPE_TEXT);
                    }

                    $columns[] = $newcolumn;
                }
            }
        }
        return $columns;
    }

    /**
     * Returns all available filters on custom fields
     *
     * @param bool $iscondition true if this is condition, false if this is a filter
     * @return report_filter[]
     */
    protected function get_conditions_or_filters(bool $iscondition): array {
        $filters = [];

        $categorieswithfields = $this->handler->get_categories_with_fields();
        if (!empty($categorieswithfields)) {
            foreach ($categorieswithfields as $fieldcategory) {
                $categoryfields = $fieldcategory->get_fields();
                foreach ($categoryfields as $field) {
                    $d = db::generate_alias();
                    $dc = data_controller::create(0, null, $field);
                    $datafield = $dc->datafield();
                    $typeclass = $this->get_class_type($dc, $iscondition);

                    $filter = new report_filter(
                        $typeclass,
                        $this->prefix.$field->get('id'),
                        new \lang_string('customfieldcolumn', 'tool_reportbuilder', $field->get('name')),
                        $this->entityname,
                        "$d.$datafield"
                    );

                    if ($field->get('type') === 'select') {
                        // Method set_options starts using array at index 1. we shift one position on this array.
                        $options = explode("\r\n", $field->get_configdata_property('options'));
                        array_unshift($options, " ");
                        unset($options[0]);

                        $filter->set_options($options);
                    }

                    $filter->add_join("LEFT JOIN {customfield_data} $d
                        ON $d.fieldid = ".$field->get('id')." AND $d.instanceid = $this->tablefieldalias");
                    $filters[] = $filter;
                }
            }
        }
        return $filters;
    }

    /**
     * Returns all available filters on custom fields
     *
     * @return report_filter[]
     */
    public function get_filters(): array {
        return $this->get_conditions_or_filters(false);
    }

    /**
     * Returns class for the filter/condition element that should be used for the field
     *
     * In some situation we can assume what kind of data is stored in the customfield plugin and we can
     * display appropriate condition/filter form element. For all others assume text filter.
     *
     * @param data_controller $dc
     * @param bool $iscondition
     * @return string
     */
    private function get_class_type(data_controller $dc, bool $iscondition) {
        $type = $dc->get_field()->get('type');
        $datafield = $dc->datafield();
        if ($type === 'checkbox') {
            return checkbox::class;
        } else if ($type === 'date') {
            return $iscondition ? date_condition::class : date_filter::class;
        } else if ($type === 'select') {
            return select::class;
        } else if ($datafield === 'intvalue' || $datafield === 'decvalue') {
            // TODO replace with numeric filter when we have it.
            return text::class;
        } else {
            return text::class;
        }
    }

    /**
     * Returns all available conditions on custom fields
     *
     * @return array|report_filter[]
     */
    public function get_conditions() {
        return $this->get_conditions_or_filters(true);
    }

    /**
     * Format for custom fields value. We get the correct custom field value using export_value method.
     *
     * @param null $value
     * @param stdClass $row
     * @param field_controller $field
     * @return mixed|null
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function customfield_value($value, stdClass $row, field_controller $field) {
        $data = data_controller::create(0, $row, $field);
        return $data->export_value();
    }

    /**
     *  Format for custom fields values with aggregation.
     *
     * @param null $value
     * @param stdClass $row
     * @param field_controller $field
     * @return mixed|null
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function customfield_aggregation($value, stdClass $row, field_controller $field) {
        $params = (object) [
            'id'        => '-1',
            'intvalue'  => $value,
            'decvalue'  => $value,
            'value'     => $value,
            'fieldid'   => $field->get('id')
        ];
        return $this->customfield_value(0, $params, $field);
    }

    /**
     * Format for custom fields values with aggregation.
     *
     * @param null $value
     * @param stdClass $row
     * @param field_controller $field
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function customfield_aggregation_group($value, stdClass $row, field_controller $field) {
        if (!empty($row)) {
            $separator = aggregation_base::get_list_separator();
            $data = [];
            $values = array_filter(explode($separator, current($row)));
            foreach ($values as $val) {
                $params = (object) [
                    'id'             => '-1',
                    'shortcharvalue' => $val,
                    'charvalue'      => $val,
                    'intvalue'       => $val,
                    'decvalue'       => $val,
                    'value'          => $val,
                    'fieldid'        => $field->get('id')
                ];
                $data[] = $this->customfield_value(0, $params, $field);
            }
            return implode($separator, $data);
        }
        return '';
    }
}