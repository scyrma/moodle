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
 * This file contains the backend class for conditions.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule;

use tool_wp\exporter_base;
use tool_wp\importer_base;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for conditions.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class condition_base {
    /** @var string Element for selecting all entities. */
    const CRITERIA_ALL = 'all';
    /** @var string Element for selecting any entities. */
    const CRITERIA_ANY = 'any';
    /** @var string Element for selecting each entity. */
    const CRITERIA_EACH = 'each';

    /**
     * The persistent object associated with this condition
     * @var condition
     */
    protected $condition;

    /** @var rule */
    private $rule;

    /**
     * Protected constructor, please use the static instance method.
     */
    protected function __construct() {
    }

    /**
     * Get instance of condition
     *
     * @param int $id
     * @param null|\stdClass $record
     * @return condition_base|null
     */
    final public static function instance(int $id = 0, ?\stdClass $record = null) :? condition_base {
        return self::instance_from_persistent(new condition($id, $record));
    }

    /**
     * Helper method to create an instance from persistent
     *
     * @param condition $persistent
     * @return condition_base|null
     */
    final protected static function instance_from_persistent(condition $persistent) :? condition_base {
        if (!$classname = $persistent->get('classname')) {
            // Use the called class name.
            $classname = get_called_class();
            $persistent->set('classname', $classname);
        }

        // Ensure the necessary class exists.
        if (!class_exists($classname) || !is_subclass_of($classname, self::class)) {
            return null;
        }

        $instance = new $classname();
        $instance->condition = $persistent;
        return $instance;
    }

    /**
     * Creates a new condition and saves it to database
     *
     * @param int $ruleid
     * @param array $configdata
     * @return condition_base
     */
    final public static function create(int $ruleid, array $configdata) : condition_base {
        $record = new \stdClass();
        $record->ruleid = $ruleid;
        $record->configdata = json_encode($configdata);
        $instance = self::instance(0, $record);
        $instance->condition->save();
        return $instance;
    }

    /**
     * Check if current instance is dummy condition.
     *
     * @return bool
     */
    final public function is_dummy(): bool {
        return (get_called_class() === 'tool_dynamicrule\\tool_dynamicrule\\condition\\dummy');
    }

    /**
     * Delete condition from database.
     */
    final public function delete() {
        $this->condition->delete();
    }

    /**
     * If the current user is able to add this condition.
     *
     * Condition classes need to define this method to make user capabilities
     * and permissions check for adding this condition. If that is not applicable,
     * use self::is_available() to control condition adding possibility.
     *
     * @see self::is_available()
     *
     * TODO: make abstract when all conditions are fixed
     *
     * @return bool
     */
    abstract public function user_can_add(): bool;

    /**
     * If the current user is able to edit this condition.
     *
     * Condition classes need to define this method to make user capabilities
     * and permissions check for the current configuration of condition.
     * (e.g. user can enrol into currently selected course in enrol condition).
     *
     * TODO: make abstract when all conditions are fixed
     *
     * @param array $configdata
     * @return bool
     */
    abstract public function user_can_edit(array $configdata): bool;

    /**
     * If the current user is able to use this condition.
     *
     * This method need to return true if condition is available to user for
     * other reason than permission check (which is done in user_can_add()).
     * (e.g. user can use cohort condition only if there is at least one cohort
     * in the system). Use get_not_available_label() function to define the reason
     * which will be shown in the interface.
     *
     * @see self::get_not_available_label()
     *
     * @return bool
     */
    public static function is_available(): bool {
        return true;
    }

    /**
     * Condition not available label.
     *
     * Conditions may provide more detailed information why it is not available when
     * is_available returns false.
     *
     * @see self::is_available()
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('conditionisnotavailable', 'tool_dynamicrule');
    }

    /**
     * Returns configdata as assoc
     *
     * @return array decoded configdata
     */
    public function get_configdata() {
        return json_decode($this->condition->get('configdata'), 1);
    }

    /**
     * Update configdata in condition persistent
     *
     * @param array $configdata
     * @param bool $save Whether to save the config change to the database
     */
    public function update_configdata(array $configdata, bool $save = false) {
        $this->condition->set('configdata', json_encode($configdata));
        $this->condition->set('broken', 0);

        if ($save) {
            $this->condition->save();
        }
    }

    /**
     * Returns $configdata from form data suitable for use in DB record.
     *
     * @param \stdClass $data data obtained from $mform->get_data()
     * @return array $configdata
     */
    public static function retrieve_configdata($data) {
        // Ideally we need some sort of define_configdata_properties abstract to
        // ensure configdata properties validity, but in simple case we just cut off
        // what we already know should not be there.
        $configdata = (array) $data;
        $invalidkeys = array_fill_keys(['id', 'ruleid'], '');
        return array_diff_key($configdata, $invalidkeys);
    }

    /**
     * Returns string for outcome category.
     * Classes may override if they need to be organized in category different from pluginname.
     *
     * @return string
     */
    public function get_category(): string {
        $pluginname = explode('\\', get_class($this))[0];
        if ($pluginname === 'tool_dynamicrule') {
            // For Dynamic rule itself we name the category "General".
            return get_string('general', 'tool_dynamicrule');
        }
        return get_string('pluginname', $pluginname);
    }

    /**
     * Returns the title of the condition
     *
     * @return string The title as formated string
     */
    abstract public function get_title(): string;

    /**
     * Adds condition's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    abstract public function get_config_form(\MoodleQuickForm $mform);

    /**
     * Validates the configform of the condition.
     *
     * Only validate form elements data here and return array of ['element' => 'error text']
     * if there are error, as this is called from moodleform::validation().
     * Please use user_can_edit method in the condition to validate user capabilities.
     * Prior to saving form data, it will be passed through user_can_edit, so that any
     * form mangling attempt will be prevented.
     *
     * @see self::user_can_edit()
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    abstract public function validate_config_form(array $data): array;

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Condition broken label.
     *
     * Conditions may provide more detailed information on what is broken when
     * is_configuration_valid returns false.
     *
     * This function is replaced with get_broken_description for more precise naming.
     *
     * @return string
     * @deprecated in WP-1426
     */
    public function get_broken_label(): string {
        debugging('Function get_broken_label() should not be used', DEBUG_DEVELOPER);
        return $this->get_broken_description();
    }

    /**
     * Condition broken description.
     *
     * Conditions may provide more detailed information on what is broken when
     * is_configuration_valid returns false.
     *
     * @see self::is_configuration_valid()
     *
     * @return string
     */
    public function get_broken_description(): string {
        return get_string('conditionisbroken', 'tool_dynamicrule');
    }

    /**
     * Return if the current condition is negated/inverted
     * TODO: Remove once function has been removed in other plugins.
     *
     * @return bool
     * @deprecated in WP-636
     */
    protected function is_negated(): bool {
        debugging('Function is_negated() should not be used', DEBUG_DEVELOPER);
        return $this->condition->get('negated');
    }

    /**
     * Return ID of current condition.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->condition->get('id');
    }

    /**
     * Return rule ID of current condition.
     *
     * @return int
     */
    public function get_ruleid(): int {
        return (int) $this->condition->get('ruleid');
    }

    /**
     * Returns a rule this condition belongs to
     *
     * @return rule
     */
    public function get_rule(): rule {
        if ($this->rule === null) {
            $this->rule = new rule($this->get_ruleid());
        }
        return $this->rule;
    }

    /**
     * Return Tenant ID of the rule the current condition belongs to.
     *
     * @return int
     */
    protected function get_tenantid(): int {
        return $this->get_rule()->get('tenantid');
    }

    /**
     * Configuration validity check.
     *
     * Must return true if the configuration of the condition are still valid.
     * This should not check user permissions (e.g. for course
     * completion condition this will check that the course exists and completion
     * is enabled for this course).
     *
     * If configuration is not valid, use get_broken_description() to provide
     * explanation.
     *
     * @see self::get_broken_description()
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return true;
    }

    /**
     * Mark condition as broken.
     *
     */
    public function mark_as_broken() {
        $this->condition->set('broken', 1);
        $this->condition->save();
    }

    /**
     * Mark condition as not broken.
     *
     */
    public function mark_as_not_broken(): void {
        $this->condition->set('broken', 0);
        $this->condition->save();
    }

    /**
     * Return if the current condition is broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        return $this->condition->get('broken');
    }

    /**
     * Returns a list of values that can be provided for outcomes
     *
     * For example,
     *  condition "course completed" can provide the data - course name, grade, completion date, etc.
     *
     * It will return:
     * [
     *  'courseid' => new lang_string('courseid...'),
     *  'coursename' => new lang_string('....'),
     *  'completiontime' => new lang_string('....'),
     *  'grade' => new lang_string('....'),
     * ]
     *
     * @param outcome_base $calleroutcome usually not used but some conditions may want to provide more specific data
     * @return array
     */
    public function get_available_data_for_outcome(outcome_base $calleroutcome): array {
        return [];
    }

    /**
     * Provide custom data for the specific outcome
     *
     * For example,
     *  condition "course completed" can provide the data - course name, grade, completion date, etc.
     *  condition "program completed" can provide - program name, list of completed courses
     * and so on
     *
     * Each outcome that accepts custom data will call this method on all rule conditions and parse it
     *
     * @param array $keys keys from the self::get_available_data_for_outcomes()
     * @param array $users array of user objects
     * @param outcome_base $calleroutcome usually not used but some conditions may want to provide more specific data
     * @return array array of data indexed by userid, each element is an associative array with keys from $keys
     */
    public function get_data_for_outcome(array $keys, array $users, outcome_base $calleroutcome): array {
        $data = [];
        $keys = array_intersect($keys, array_keys($this->get_available_data_for_outcome($calleroutcome)));
        foreach ($users as $user) {
            $data[$user->id] = array_fill_keys($keys, null); // When overriding specify an actual value here.
        }
        return $data;
    }

    /**
     * Event subscription.
     *
     * By specifying event class in this function, you declare that rule
     * processing will be triggered when event occurs and only for the user
     * this event is affecting. May requires get_event_related_userid to be
     * defined depending on type of event you use.
     *
     * Notice that subscriptions are cached, when adding new one please bump version
     * so application cache is cleared.
     *
     * @return string|array|false event class(es) or false if no subscription.
     */
    public function get_event_subscription() {
        return false;
    }

    /**
     * Fetch affected userid from the event object.
     *
     * Translates event object defined in get_event_subscription to affected userid,
     * which is then used to trigger rule processing for this user. By default
     * it is returning 'relateduserid' value following Event 2 API convention.
     * Override in condition if required.
     *
     * @param  \core\event\base $event
     * @return int userid
     */
    public static function get_event_related_userid(\core\event\base $event): int {
        $data = $event->get_data();
        if (!array_key_exists('relateduserid', $data)) {
            throw new \coding_exception('No relateduserid property supplied in event object');
        }
        return $data['relateduserid'];
    }

    /**
     * Allow conditions to add mapped fields during export. Conditions should implement this method when they contain
     * references to individual entities (e.g. course, cohort, badge), for example:
     *
     * $exporter->add_mapping('cohort', $this->get_cohortid());
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
     * $cohortid = $importer->get_mapping('cohort', $this->get_cohortid(), IGNORE_MISSING) ?? 0;
     *
     * Note that we should use zero if the mapping doesn't exist (which will be handled by Dynamic rules automatically)
     *
     * @param importer_base $importer
     */
    public function get_importer_mapping(importer_base $importer): void {
        return;
    }
}
