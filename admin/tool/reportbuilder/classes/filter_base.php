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
// Moodle Workplace™ Code is the collection of software scripts
// (plugins and modifications, and any derivations thereof) that are
// exclusively owned and licensed by Moodle under the terms of this
// proprietary Moodle Workplace License ("MWL") alongside Moodle's open
// software package offering which itself is freely downloadable at
// "download.moodle.org" and which is provided by Moodle under a single
// GNU General Public License version 3.0, dated 29 June 2007 ("GPL").
// MWL is strictly controlled by Moodle Pty Ltd and its certified
// premium partners. Wherever conflicting terms exist, the terms of the
// MWL are binding and shall prevail.

/**
 * Abstract class for filters.
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use tool_reportbuilder\local\helpers\conditions as conditions_helper;
use tool_reportbuilder\local\helpers\filters as filters_helper;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Class filter_base
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class filter_base {
    /**
     * @var int
     */
    protected $id;
    /**
     * Suggested form element name - the unique identifier of the filter instance
     * @var string
     */
    protected $name;

    /**
     * The heading of this filter instance as entered by user (empty string means "use default")
     * @var string
     */
    private $heading;

    /**
     * If the editing is enabled.
     * @var int
     */
    protected $editing;

    /** @var mixed|null */
    protected $default;

    /** @var \tool_reportbuilder\report_filter */
    protected $reportfilter;

    /** @var array $value Current values */
    protected $values = [];

    /**
     * filter_base constructor can not be overridden
     */
    final private function __construct() {
    }

    /**
     * Creates an instance of a filter
     *
     * @param string $classname name of the clas extending filter_base
     * @param \tool_reportbuilder\report_filter $reportfilter
     * @param int $id
     * @param string $heading
     * @param mixed $default
     * @param bool $editing editing mode
     * @return filter_base
     * @throws \coding_exception
     */
    final public static function create(string $classname, \tool_reportbuilder\report_filter $reportfilter,
                                  int $id, string $heading, $default = null, bool $editing = false) : self {
        if (!class_exists($classname) || !is_subclass_of($classname, static::class)) {
            throw new \coding_exception('Class does not exist');
        }
        /** @var filter_base $filter */
        $filter = new $classname();
        $filter->reportfilter = $reportfilter;
        $filter->id      = $id;
        $filter->name    = $reportfilter->get_unique_identifier();
        $filter->heading = $heading;
        $filter->default = $default;
        $filter->editing = $editing;
        return $filter;
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param array|null $currentvalues
     * @return array array of two elements - SQL query and named parameters
     */
    abstract public function get_sql_filter(?array $currentvalues): array;

    /**
     * Returns the join condition to be used with SQL wheregs
     * @return array the join string
     */
    final public function get_joins(): array {
        return $this->reportfilter->get_joins();
    }

    /**
     * Get the form element name
     *
     * @return string
     */
    public function get_name() : string {
        return $this->name;
    }
    /**
     * Adds controls specific to this filter in the form.
     * @param \MoodleQuickForm $mform a MoodleForm object to setup
     */
    abstract public function setup_form(\MoodleQuickForm $mform);

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    abstract public function get_label(array $data): string;

    /**
     * Header of the filter/condition as it is displayed to the user
     * @return string
     */
    final public function get_formatted_header(): string {
        return filters_helper::get_formatted_header($this->reportfilter, $this->heading);
    }

    /**
     * Header of the filters/conditions cards.
     *
     * @param \MoodleQuickForm $mform
     * @throws \coding_exception
     */
    protected function common_header(\MoodleQuickForm $mform) {
        global $OUTPUT;
        $id = $this->id;
        $label = $this->get_formatted_header();
        $template = new \stdClass();
        $template->id = $id;
        $template->label = $label;
        $template->active = $this->is_active($this->get_values());
        $template->filtertype = $this->filter_type();
        $template->editing = $this->editing;
        $template->movetitle = get_string('movecontent', 'moodle', $label);
        $tmpl = conditions_helper::get_header_inplace_editable($this->reportfilter, $this->heading, $this->id);
        $template->inplaceeditable = $OUTPUT->render($tmpl);
        $template->start = true;
        $mform->addElement('html', $OUTPUT->render_from_template('tool_reportbuilder/filter_card', $template));
    }

    /**
     * Footer for the card container.
     * @param \MoodleQuickForm $mform
     */
    protected function common_footer(\MoodleQuickForm $mform) {
        global $OUTPUT;
        $template = new \stdClass();
        $template->end = true;
        $mform->addElement('html', $OUTPUT->render_from_template('tool_reportbuilder/filter_card', $template));
    }

    /**
     * Return the filter type, used for rendered template. Can be overridden in child classes
     *
     * @return string
     */
    protected function filter_type() : string {
        $parts = explode('\\', get_class($this));

        // The type of Report builder filters is just their classname, otherwise it's full namespace path.
        if (strcasecmp($parts[0], 'tool_reportbuilder') === 0) {
            $parts = array_slice($parts, -1);
        }

        return 'filter-' . implode('_', $parts);
    }

    /**
     * Check if the current filter is active in order to show the reset button. It is normally considered active if it returns
     * SQL filter data. Child classes should override this method if they use different logic
     *
     * @param array|null $currentvalues
     * @return bool
     */
    protected function is_active(?array $currentvalues): bool {
        $filtersql = $this->get_sql_filter($currentvalues)[0];

        return !empty($filtersql);
    }

    /**
     * Set the filter values
     *
     * @param null|array $values
     */
    public function set_values(?array $values): void {
        $this->values = $values;
    }

    /**
     * Get the filter values
     *
     */
    public function get_values(): array {
        return $this->values;
    }

    /**
     * Allow conditions to add mapped fields during export. Conditions should implement this method when they contain
     * references to individual entities (e.g. course, cohort, badge), for example:
     *
     * $exporter->add_mapping('course', $courseid);
     *
     * @param exporter_base $exporter
     */
    public function add_exporter_mapping(exporter_base $exporter): void {
        return;
    }

    /**
     * Allow conditions to retrieve mapped fields during import. Conditions should implement this method when they contain
     * references to individual entities (e.g. course, cohort, badge), for example:
     *
     * $mappedcourseid = $importer->get_mapping('course', $courseid, IGNORE_MISSING) ?? 0;
     *
     * @param importer_base $importer
     * @return array Keyed on condition operator, values should contain mapped fields
     */
    public function get_importer_mapping(importer_base $importer): array {
        return [];
    }

    /**
     * Returns sample values that can be used in the tests
     *
     * If $extended is not set, return 1-2 sets of values, they will be massively used in tests for all filters in all datasources
     * If $extended is set, return as many sets of values as possible, for extended test of this specific filter
     *
     * @param bool $extended
     * @return array
     */
    public function get_test_values(bool $extended = false) {
        return [];
    }
}
