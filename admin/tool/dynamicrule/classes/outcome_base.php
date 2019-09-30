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
 * This file contains the backend class for outcomes.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die;

/**
 * The backend class for outcomes.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class outcome_base {

    /**
     * Class constructor
     *
     * @param int $id
     * @param \stdclass $record
     */
    public function __construct($id = 0, $record = null) {
        $this->outcome = new outcome($id, $record);
        if ($this->outcome->get('id') && $this->outcome->get('classname') !== get_called_class()) {
            throw new \coding_exception('Mismatching outcome class');
        }
    }

    /**
     * Creates a new outcome and saves it to database
     *
     * @param int $ruleid
     * @param array $configdata
     * @return outcome_base
     */
    public static function create(int $ruleid, array $configdata) {
        $record = new \stdClass();
        $record->ruleid = $ruleid;
        $record->classname = get_called_class();
        $record->configdata = json_encode($configdata);
        $outcome = new outcome(0, $record);
        $outcome->save();
        $record->id = $outcome->get('id');
        return new $record->classname(0, $record);
    }

    /**
     * If the current user is able to use this outcome (for any purpose).
     * Outcome classes may override this method to make permissions check or
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
        return json_decode($this->outcome->get('configdata'), 1);
    }

    /**
     * Update configdata on database
     *
     * @param array $configdata
     */
    public function update_configdata(array $configdata) {
        $this->outcome->set('configdata', json_encode($configdata));
        $this->outcome->set('broken', 0);
        $this->outcome->save();
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
     * Classes may override if they need to be organized in category different from plugin name.
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
     * Returns the title of the outcome
     *
     * @return string The title as formated string
     */
    abstract public function get_title(): string;

    /**
     * Adds outcome's elements to the given mform
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    abstract public function get_config_form(\MoodleQuickForm $mform);

    /**
     * Validates the configform of the outcome
     *
     * @param array $data Data from the form
     * @return array Array with errors for each element
     */
    abstract public function validate_config_form(array $data): array;

    /**
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     */
    abstract public function apply_to_users(array $users);

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Return ID of current condition.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->outcome->get('id');
    }

    /**
     * Return rule ID of current condition.
     *
     * @return int
     */
    public function get_ruleid(): int {
        return (int) $this->outcome->get('ruleid');
    }

    /**
     * Must return true if the configuration of the outcome are still valid.
     * Outcome must check if needed records exist in database and return false if any of them do not exist.
     *
     * @return bool
     */
    public function is_configuration_valid(): bool {
        return true;
    }

    /**
     * Mark outcome as broken.
     *
     */
    public function mark_as_broken() {
        $this->outcome->set('broken', 1);
        $this->outcome->save();
    }

    /**
     * Return if the current outcome is broken.
     *
     * @return bool
     */
    public function is_broken(): bool {
        return $this->outcome->get('broken');
    }

    /**
     * Collects actual data from conditions
     *
     * @param array $keys list of keys to collect, see also self::get_available_data_from_conditions() for the
     *    list of available data
     * @param array $users array of user objects
     * @return array array of data indexed by userid, each element is an associative array with keys from $keys
     *    All userids are present. Only keys with available data are present
     */
    public function get_data_from_conditions(array $keys, array $users): array {
        $conditionsdata = array_fill_keys(array_column($users, 'id'), []);
        if (!$keys) {
            return $conditionsdata;
        }
        $conditions = api::get_rule_conditions($this->get_ruleid());
        foreach ($conditions as $condition) {
            $thiskeys = array_intersect($keys, array_keys($condition->get_available_data_for_outcome($this)));
            if ($thiskeys) {
                $data = $condition->get_data_for_outcome($thiskeys, $users, $this);
                foreach ($data as $userid => $userdata) {
                    $conditionsdata[$userid] += $userdata;
                }
            }
        }
        return $conditionsdata;
    }
}
