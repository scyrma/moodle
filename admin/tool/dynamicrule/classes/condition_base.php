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
 * This file contains the backend class for conditions.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for conditions.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class condition_base {

    /**
     * The persistent object associated with this condition
     * @var condition
     */
    protected $condition;

    /**
     * Class constructor
     *
     * @param int $id
     * @param \stdClass $record
     */
    public function __construct($id = 0, \stdClass $record = null) {
        if ($id && $record) {
            debugging('Either id or record need to be specified in the persistent constructor but not both',
                DEBUG_DEVELOPER);
        }
        $this->condition = new condition($id, $record);
        if ($this->condition->get('id') && $this->condition->get('classname') !== get_called_class()) {
            throw new \coding_exception('Mismatching condition class');
        }
    }

    /**
     * Creates a new condition and saves it to database
     *
     * @param int $ruleid
     * @param array $configdata
     * @return condition_base
     */
    public static function create(int $ruleid, array $configdata): condition_base {
        $record = new \stdClass();
        $record->ruleid = $ruleid;
        $record->classname = get_called_class();
        $record->configdata = json_encode($configdata);
        $condition = new condition(0, $record);
        $condition->save();
        $record->id = $condition->get('id');
        return new $record->classname(0, $record);
    }

    /**
     * If the current user is able to use this condition (for any purpose).
     * Condition classes may override this method to make permissions check or
     * avoid access to information the user does not have access to.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return true;
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
     * Update configdata on database
     *
     * @param array $configdata
     */
    public function update_configdata(array $configdata) {
        $this->condition->set('configdata', json_encode($configdata));
        $this->condition->set('broken', 0);
        $this->condition->save();
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
     * Validates the configform of the condition
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
     * Return Tenant ID of the rule the current condition belongs to.
     *
     * @return int
     */
    protected function get_tenantid(): int {
        return (new \tool_dynamicrule\rule($this->get_ruleid()))->get('tenantid');
    }

    /**
     * Must return true if the configuration of the condition are still valid.
     * Conditions must check if needed records exist in database and return false if any of them do not exist.
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
     * Delete condition from database.
     */
    public function delete() {
        $this->condition->delete();
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
     * @return string|false eventname or false if no subscription.
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
}
