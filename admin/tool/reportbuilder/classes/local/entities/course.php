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
 * Class course
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\entities;

use tool_reportbuilder\constants;
use tool_reportbuilder\entity_base;
use tool_reportbuilder\local\filter\checkbox;
use tool_reportbuilder\local\filter\course_selector;
use tool_reportbuilder\local\filter\date_condition;
use tool_reportbuilder\local\filter\date_filter;
use tool_reportbuilder\local\filter\select;
use tool_reportbuilder\local\filter\text;
use tool_reportbuilder\local\helpers\customfields;
use tool_reportbuilder\local\helpers\format;
use tool_reportbuilder\report_filter;
use tool_reportbuilder\report_column;

defined('MOODLE_INTERNAL') || die();

/**
 * Typical fields from the course table that can be added
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class course extends entity_base {

    /** @var array */
    protected $customfields;

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return ['course' => 'c'];
    }

    /**
     * The default machine-readable name for this entity that will be used in the internal names of the columns/filters
     *
     * @return string
     */
    protected function get_default_entity_name(): string {
        return 'course';
    }

    /**
     * The default title for this entity in the list of columns/conditions/filters in the report builder
     *
     * @return \lang_string
     */
    protected function get_default_entity_title(): \lang_string {
        return new \lang_string('entitycourse', 'tool_reportbuilder');
    }

    /**
     * Executed when entity is added to the datasource or system report
     */
    public function add_to_report() {
        $columns = array_merge($this->get_all_columns(), $this->get_custom_fields()->get_columns());
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $conditions = array_merge($this->get_conditions_or_filters(true), $this->get_custom_fields()->get_conditions());
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        $filters = array_merge($this->get_conditions_or_filters(false), $this->get_custom_fields()->get_conditions());
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }
    }

    /**
     * Get custom fields helper
     *
     * @return customfields
     */
    protected function get_custom_fields(): customfields {
        if (!$this->customfields) {
            $this->customfields = new customfields($this->get_table_alias('course') . '.id', $this->get_entity_name(),
                'core_course', 'course');
            $this->customfields->add_joins($this->get_joins());
        }
        return $this->customfields;
    }

    /**
     * Course fields.
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_course_fields() {
        $fields = array(
            'fullname' => new \lang_string('fullnamecourse'),
            'shortname' => new \lang_string('shortnamecourse'),
            'category' => new \lang_string('coursecategory'),
            'idnumber' => new \lang_string('idnumbercourse'),
            'summary' => new \lang_string('coursesummary'),
            'format' => new \lang_string('format'),
            'startdate' => new \lang_string('startdate'),
            'enddate' => new \lang_string('enddate'),
            'visible' => new \lang_string('coursevisibility'),
            'groupmode' => new \lang_string('groupmode', 'group'),
            'groupmodeforce' => new \lang_string('groupmodeforce', 'group'),
            'lang' => new \lang_string('forcelanguage'),
            'calendartype' => new \lang_string('forcecalendartype', 'calendar'),
            'theme' => new \lang_string('forcetheme'),
            'enablecompletion' => new \lang_string('enablecompletion', 'completion'),
        );

        return array_filter($fields, [$this, 'should_include_field'], ARRAY_FILTER_USE_KEY);
    }

    /**
     * Is field sortable
     *
     * @param string $fieldname
     * @return bool
     */
    protected function is_sortable(string $fieldname) : bool {
        return !in_array($fieldname, ['summary']);
    }

    /**
     * Returns list of all available columns
     *
     * @return report_column[]
     */
    protected function get_all_columns() : array {

        $columns = [];
        $coursefields = $this->get_course_fields();

        foreach ($coursefields as $coursekey => $coursefield) {
            $columns[] = (new report_column(
                $coursekey,
                $coursefield,
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->add_field($this->get_table_alias('course') . '.' . $coursekey)
                ->set_type($this->get_type($coursekey))
                ->add_callback([$this, 'format'], $coursekey)
                ->add_aggregation_callback('groupconcat', [$this, 'format_aggregation'], $coursekey)
                ->add_aggregation_callback('groupconcatdistinct', [$this, 'format_aggregation'], $coursekey)
                ->add_aggregation_callback('unique', [$this, 'format_aggregation'], $coursekey)
                ->set_is_sortable($this->is_sortable($coursekey));
        }

        return $columns;
    }

    /**
     * Get the type of the column.
     *
     * @param string $coursekey
     * @return null
     */
    protected function get_type(string $coursekey) {
        switch ($coursekey) {
            case 'groupmodeforce':
            case 'visible':
            case 'enablecompletion':
                return constants::DB_TYPE_BOOLEAN;
                break;
            case 'startdate':
            case 'enddate':
                return constants::DB_TYPE_DATETIME;
                break;
            case 'summary':
                return constants::DB_TYPE_LONGTEXT;
                break;
            default:
                return null;
                break;
        }
    }

    /**
     * Returns all available conditions on course fields
     *
     * @param bool $iscondition true if this is condition, false if this is a filter
     * @return report_filter[]
     */
    protected function get_conditions_or_filters(bool $iscondition) {
        global $DB;
        $conditions = [];

        $fields = $this->get_course_fields();
        foreach ($fields as $field => $name) {
            if ($field === 'summary' && $DB->get_dbfamily() === 'oracle') {
                // TODO WP-717 Filtering not yet supported for LONGTEXT fields on Oracle.
                continue;
            }

            $options = $this->get_options_for($field);
            if ($options !== null) {
                $classname = select::class;
            } else if ($this->get_type($field) == constants::DB_TYPE_BOOLEAN) {
                $classname = checkbox::class;
            } else if ($this->get_type($field) == constants::DB_TYPE_DATETIME) {
                $classname = $iscondition ? date_condition::class : date_filter::class;
            } else {
                $classname = text::class;
            }

            $filter = (new report_filter(
                $classname,
                $field,
                $name,
                $this->get_entity_name(),
                $this->get_table_alias('course') . '.' . $field
            ))
                ->add_joins($this->get_joins());
            if ($options !== null) {
                $filter->set_options(['' => ''] + $options);
            }
            $conditions[] = $filter;
        }

        // Filter course selector.
        $conditions[] = (new report_filter(
            course_selector::class,
            'courseselector',
            new \lang_string('selectcourses', 'tool_reportbuilder'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_field_sql($this->get_table_alias('course'));

        return $conditions;
    }

    /**
     * Gets list of options if the field filter/condition should be displayed as select
     *
     * @param string $fieldname
     * @return null|array
     */
    protected function get_options_for($fieldname) {
        static $cached = [];
        if (!array_key_exists($fieldname, $cached)) {
            $cached[$fieldname] = $this->get_options_raw($fieldname);
        }
        return $cached[$fieldname];
    }

    /**
     * Gets list of options if the field filter/condition should be displayed as select
     *
     * @param string $fieldname
     * @return null|array
     */
    protected function get_options_raw($fieldname) {
        global $CFG;
        require_once($CFG->dirroot.'/course/lib.php');
        if ($fieldname === 'groupmode') {
            $choices = array();
            $choices[NOGROUPS] = get_string('groupsnone', 'group');
            $choices[SEPARATEGROUPS] = get_string('groupsseparate', 'group');
            $choices[VISIBLEGROUPS] = get_string('groupsvisible', 'group');
            return $choices;
        }

        if ($fieldname === 'category') {
            $displaylist = \core_course_category::make_categories_list();
            return $displaylist;
        }

        if ($fieldname === 'format') {
            $courseformats = get_sorted_course_formats(true);
            $formcourseformats = array();
            foreach ($courseformats as $courseformat) {
                $formcourseformats[$courseformat] = get_string('pluginname', "format_$courseformat");
            }
            return $formcourseformats;
        }

        if ($fieldname === 'theme') {
            $themeobjects = get_list_of_themes();
            $themes = array();
            foreach ($themeobjects as $key => $theme) {
                if (empty($theme->hidefromselector)) {
                    $themes[$key] = get_string('pluginname', 'theme_' . $theme->name);
                }
            }
            return $themes;
        }

        if ($fieldname === 'lang') {
            return get_string_manager()->get_list_of_translations();
        }

        if ($fieldname === 'calendartype') {
            return \core_calendar\type_factory::get_list_of_calendar_types();
        }

        return null;
    }

    /**
     * Formats the course field for display
     *
     * @param mixed $value
     * @param \stdClass $row
     * @param string $fieldname
     * @return string
     */
    public function format($value, \stdClass $row, string $fieldname) {
        if ($this->get_type($fieldname) == constants::DB_TYPE_DATETIME) {
            // TODO include time?
            return format::userdate($value, $row);
        } else if (($options = $this->get_options_for($fieldname)) !== null && array_key_exists($value, $options)) {
            return $options[$value];
        } else if ($this->get_type($fieldname) == constants::DB_TYPE_BOOLEAN) {
            return format::checkbox_as_text($value);
        } else if (in_array($fieldname, ['fullname', 'shortname'])) {
            return format_string($value); // TODO context, etc.
        } else if (in_array($fieldname, ['summary'])) {
            return format_text($value); // TODO files, context, etc.
        } else {
            return s($value);
        }
    }

    /**
     * Formats the course field for display
     *
     * @param string $value Current field value
     * @param \stdClass $row Complete row
     * @param string $fieldname Current fieldname
     * @return string
     * @throws \coding_exception
     */
    public function format_aggregation(?string $value, \stdClass $row, string $fieldname) {
        // TODO make sure that summary does not support 'groupconcat' aggregation.

        if (($options = $this->get_options_for($fieldname)) !== null) {
            $formattedvalues = [];
            $elements = explode(',', $value);
            foreach ($elements as $key) {
                $formattedvalues[] = array_key_exists($key, $options) ? $options[$key] : s($key);
            }
            return implode(', ', array_filter($formattedvalues));
        } else if (in_array($fieldname, ['shortname', 'fullname'])) {
            return format_string($value);
        } else {
            return $value;
        }
    }
}
