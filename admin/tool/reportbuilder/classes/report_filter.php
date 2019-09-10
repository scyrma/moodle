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
 * Class report_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * Class report_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_filter {
    /** @var string $classname */
    protected $classname;
    /** @var string $entity */
    protected $entity;
    /** @var string $fieldsql */
    protected $fieldsql;
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
    /** @var mixed $options */
    protected $options;

    /**
     * report_filter constructor.
     *
     * @param string $classname standard name of the class that extends filter_base
     * @param string $name - name unique inside the entity (for internal indexing only, not used in the UI)
     * @param \lang_string $header - the name and default title of the filter
     * @param string $entity - name of the entity that is used for groupping the filters/conditions.
     *          See also report_base::add_entity()
     * @param string $fieldsql - SQL expression for the field being searched
     */
    public function __construct(string $classname, string $name, \lang_string $header, string $entity, ?string $fieldsql = null) {
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
        $this->joins[] = strtolower(trim($join));
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
     * Set the options for filter in the format that filter allows
     * @param mixed $options
     * @return report_filter
     */
    public function set_options($options) : report_filter {
        $this->options = $options;
        return $this;
    }

    /**
     * Get the options for the select type filter.
     *
     * @return mixed
     */
    public function get_options() {
        return $this->options;
    }
}