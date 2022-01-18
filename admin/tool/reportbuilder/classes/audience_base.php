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
 * Audience base class
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

use MoodleQuickForm;
use stdClass;
use tool_reportbuilder\local\models\audiences;
use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * Audience base class
 *
 * @package    tool_reportbuilder
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class audience_base {

    /** @var int Maximim number of multi-select elements to show in description, before appending "plus X more" */
    private const MULTI_SELECT_LIMIT = 5;

    /** @var audiences The persistent object associated with this audience */
    protected $audience;

    /**
     * Protected constructor, please use the static instance method.
     */
    protected function __construct() {
    }

    /**
     * Loads an existing instance of audience with persistent
     *
     * @param int $id
     * @param null|stdClass $record
     * @return self|null
     */
    final public static function instance(int $id = 0, ?stdClass $record = null):? self {
        $persistent = new audiences($id, $record);
        // Needed for get_all_audience_types() method.
        if (!$classname = $persistent->get('classname')) {
            // Use the called class name.
            $classname = get_called_class();
            $persistent->set('classname', $classname);
        }

        // Check if audience type class still exists in the system.
        if (!class_exists($classname)) {
            return null;
        }

        $instance = new $classname();
        $instance->audience = $persistent;
        return $instance;
    }

    /**
     * Creates a new audience and saves it to database
     *
     * @param int $reportid
     * @param array $configdata
     * @return self
     */
    final public static function create(int $reportid, array $configdata): self {
        $record = new stdClass();
        $record->reportid = $reportid;
        $record->classname = get_called_class();
        $record->configdata = json_encode($configdata);
        $instance = self::instance(0, $record);
        $instance->audience->save();
        return $instance;
    }

    /**
     * Helps to build SQL to retrieve users that matches the current audience
     *
     * Implementations must use api::generate_alias() for table/column aliases
     * and api::generate_param_name() for named parameters
     *
     * @param string $usertablealias
     * @return array array of three elements [$join, $where, $params]
     */
    abstract public function get_sql(string $usertablealias): array;

    /**
     * Returns string for audience category.
     * Classes may override if they need to be organized in category different from pluginname.
     *
     * @return string
     */
    public function get_category(): string {
        $pluginname = explode('\\', get_class($this))[0];
        if ($pluginname === 'tool_reportbuilder') {
            return get_string('general');
        }
        return get_string('pluginname', $pluginname);
    }

    /**
     * If the current user is able to add this audience type
     *
     * @return bool
     */
    abstract public function user_can_add(): bool;

    /**
     * If the current user is able to edit this audience type
     *
     * @return bool
     */
    abstract public function user_can_edit(): bool;

    /**
     * If the current user is able to use this audience type
     *
     * This method needs to return true if audience type is available to user for
     * reasons other than permission check, which is done in {@see user_can_add}.
     * (e.g. user can add cohort audience type only if there is at least one cohort
     * they can access).
     *
     * Use the {@see get_not_available_label} method to define a string defining the
     * label shown when the audience is not available to the user.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Audience type not available label.
     *
     * Label used in the UI to define why the audience type is not available to the user when
     * {@see is_available} method returns false.
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('notavailable', 'moodle');
    }

    /**
     * Returns the title of this audience type
     *
     * @return string The title as formated string
     */
    abstract public function get_title(): string;

    /**
     * Return the description of this audience type
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Helper to format descriptions for audience types that may contain many selected elements, limiting number show according
     * to {@see MULTI_SELECT_LIMIT} constant value
     *
     * @param array $elements
     * @return string
     */
    protected function format_description_for_multiselect(array $elements): string {
        $elementcount = count($elements);
        if ($elementcount > self::MULTI_SELECT_LIMIT) {
            $elements = array_slice($elements, 0, self::MULTI_SELECT_LIMIT);

            // Append overflow element.
            $elementoverflow = $elementcount - self::MULTI_SELECT_LIMIT;
            $elements[] = get_string('audiencemultiselectpostix', 'tool_reportbuilder', $elementoverflow);
        }

        return $this->get_title() . ' ' . implode(', ', $elements);
    }

    /**
     * Adds condition's elements to the given mform
     *
     * @param MoodleQuickForm $mform The form to add elements to
     */
    abstract public function get_config_form(MoodleQuickForm $mform): void;

    /**
     * Validates the configform of the condition.
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    public function validate_config_form(array $data): array {
        return [];
    }

    /**
     * Returns configdata as an associative array
     *
     * @return array decoded configdata
     */
    public function get_configdata(): array {
        return json_decode($this->audience->get('configdata'), true);
    }

    /**
     * Update configdata in audience persistent
     *
     * @param array $configdata
     */
    public function update_configdata(array $configdata): void {
        $this->audience->set('configdata', json_encode($configdata));
        $this->audience->save();
    }

    /**
     * Returns $configdata from form data suitable for use in DB record.
     *
     * @param stdClass $data data obtained from $mform->get_data()
     * @return array $configdata
     */
    public static function retrieve_configdata(stdClass $data): array {
        $configdata = (array) $data;
        $invalidkeys = array_fill_keys(['id', 'reportid', 'classname'], '');
        return array_diff_key($configdata, $invalidkeys);
    }

    /**
     * Return ID of current audience
     *
     * @return int
     */
    public function get_id(): int {
        return $this->audience->get('id');
    }

    /**
     * Return report ID of current condition.
     *
     * @return int
     */
    public function get_reportid(): int {
        return (int) $this->audience->get('reportid');
    }

    /**
     * Return audience persistent.
     *
     * @return audiences
     */
    public function get_persistent(): audiences {
        return $this->audience;
    }

    /**
     * Allow audiences to add mapped fields during export. This method should be implemented if the type contains
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
     * Allow audiences to retrieve mapped fields during import. This method should be implemented if the type contain
     * references to individual entities (e.g. course, cohort, badge), for example:
     *
     * $mappedcourseid = $importer->get_mapping('course', $courseid, IGNORE_MISSING) ?? 0;
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        return;
    }
}
