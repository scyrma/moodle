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
 * Class report_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class report_filter {
    /** @var string $classname */
    protected $classname;
    /** @var string $entity */
    protected $entity;
    /** @var string $fieldsql */
    protected $fieldsql;
    /** @var array $fieldparams */
    protected $fieldparams = [];
    /** @var array $joins */
    protected $joins = array();
    /** @var string $header */
    protected $header;
    /** @var string $name */
    protected $name;
    /** @var int $default */
    protected $default;
    /** @var array $defaultvalues */
    protected $defaultvalues;
    /** @var  bool $available */
    protected $available = true;
    /** @var callable|array $options */
    protected $options;
    /** @var array $operators */
    protected $operators;

    /**
     * report_filter constructor.
     *
     * @param string $classname standard name of the class that extends filter_base
     * @param string $name - name unique inside the entity (for internal indexing only, not used in the UI)
     * @param \lang_string $header - the name and default title of the filter
     * @param string $entity - name of the entity that is used for groupping the filters/conditions.
     *          See also report_base::add_entity()
     * @param string $fieldsql - SQL expression for the field being searched
     * @param array $fieldparams - SQL params for the field being searched
     */
    public function __construct(string $classname, string $name, \lang_string $header, string $entity, ?string $fieldsql = null,
            array $fieldparams = []) {

        if (!class_exists($classname) || !is_subclass_of($classname, filter_base::class)) {
            throw new \moodle_exception('filternotvalid', 'tool_reportbuilder');
        }
        $this->classname = $classname;
        $this->entity = $entity;
        $this->header = $header;
        $this->name = $name;
        if ($fieldsql !== null) {
            $this->set_field_sql($fieldsql);
        }
        if (!empty($fieldparams)) {
            $this->set_field_params($fieldparams);
        }
    }

    /**
     * Get the identifier for this filter that is unique for the datasource
     *
     * @return string
     */
    public function get_unique_identifier() : string {
        return $this->get_entity() . ':' . $this->get_name();
    }

    /**
     * Get entity.
     *
     * @return mixed
     */
    public function get_entity() {
        return $this->entity;
    }

    /**
     * Get class name.
     *
     * @return string
     */
    public function get_classname() : string {
        return $this->classname;
    }

    /**
     * Return field name (for internal reference)
     *
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }

    /**
     * Set field name
     *
     * @param string $name
     * @return report_filter
     */
    public function set_name(string $name) : report_filter {
        $this->name = $name;
        return $this;
    }

    /**
     * Get joins.
     *
     * @return array
     */
    public function get_joins() {
        return $this->joins;
    }

    /**
     * Add join
     *
     * @param string $join
     * @return report_filter
     */
    public function add_join(string $join) : report_filter {
        $this->joins[] = trim($join);
        return $this;
    }

    /**
     * Add multiple joins
     *
     * @param array $joins
     * @return report_filter
     */
    public function add_joins(array $joins) : report_filter {
        foreach ($joins as $join) {
            $this->add_join($join);
        }
        return $this;
    }

    /**
     * Get the header.
     *
     * @return string
     */
    public function get_header() : string {
        return (string)$this->header;
    }

    /**
     * Set the header.
     *
     * @param string $header
     * @return report_filter
     */
    public function set_header(string $header) : report_filter {
        $this->header = $header;
        return $this;
    }

    /**
     * SQL expression for the field
     *
     * @return string
     */
    public function get_field_sql() {
        return $this->fieldsql;
    }

    /**
     * Set the SQL expression for the field that is being filtered. It will be passed to the filter class
     *
     * @param string $sql
     * @return report_filter
     */
    public function set_field_sql(string $sql) : report_filter {
        $this->fieldsql = $sql;
        return $this;
    }

    /**
     * Get the SQL params for the field being filtered
     *
     * @return array
     */
    public function get_field_params() : array {
        return $this->fieldparams;
    }

    /**
     * Set the SQL params for the field being filtered. They will be passed to the filter class
     *
     * @param array $params
     * @return report_filter
     */
    public function set_field_params(array $params) : report_filter {
        $this->fieldparams = $params;
        return $this;
    }

    /**
     * Set the filter as default.
     *
     * @param bool $isdefault
     * @param array $values
     * @return report_filter
     */
    public function set_is_default(bool $isdefault, array $values = []) : report_filter {
        $this->default = $isdefault;
        $this->defaultvalues = $values;
        return $this;
    }

    /**
     * Get if the filter is default.
     *
     * @return bool
     */
    public function is_default() : bool {
        return isset($this->default) && $this->default === true;
    }

    /**
     * Returns filter default values.
     *
     * @return array
     */
    public function get_default_values(): array {
        return $this->defaultvalues;
    }

    /**
     * Conditionally set whether the filter is available
     *
     * @param bool $available
     * @return report_filter
     */
    public function set_is_available(bool $available): report_filter {
        $this->available = $available;
        return $this;
    }

    /**
     * Return available state of the filter
     *
     * @return bool
     */
    public function get_is_available(): bool {
        return $this->available;
    }

    /**
     * Set the options for filter in the format that filter allows
     *
     * Only use if the list of options does not require calculations/queries, otherwise use set_options_callback()
     * Do not use get_string() in options values, rather "new lang_string()"
     *
     * @param array $options
     * @return report_filter
     */
    public function set_options($options) : report_filter {
        $this->options = $options;
        return $this;
    }

    /**
     * Set the callback returning options for filter in the format that filter allows
     *
     * Callback should take no arguments and return value should be compatible with what the
     * filter expects.
     *
     * 'select' filter expects an associative array with the name-value pairs for
     * the <select> form element (or two-dimensional array for the select element with
     * optgroups).
     *
     * @param callable $callback
     * @return report_filter
     */
    public function set_options_callback(callable $callback) : report_filter {
        $this->options = $callback;
        return $this;
    }

    /**
     * Get the options for the select type filter.
     *
     * @return array
     */
    public function get_options() {
        if (is_callable($this->options)) {
            $callable = $this->options;
            $this->options = $callable();
        }
        return $this->options;
    }

    /**
     * Set the operators for filter in the format that filter allows
     *
     * @param array $operators
     * @return report_filter
     */
    public function set_operators(array $operators) : report_filter {
        $this->operators = $operators;
        return $this;
    }

    /**
     * Get the operators for the filter.
     *
     * @return null|array
     */
    public function get_operators() : ?array {
        return $this->operators;
    }
}
