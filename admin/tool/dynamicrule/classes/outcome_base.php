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
 * This file contains the backend class for outcomes.
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
 * The backend class for outcomes.
 *
 * @package    tool_dynamicrule
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class outcome_base {

    /**
     * The persistent object associated with this outcome
     * @var outcome
     */
    protected $outcome;

    /** @var rule */
    private $rule;

    /**
     * Protected constructor, please use the static instance method.
     */
    protected function __construct() {
    }

    /**
     * Get instance of outcome
     *
     * @param int $id
     * @param null|\stdClass $record
     * @return outcome_base|null
     */
    final public static function instance(int $id = 0, ?\stdClass $record = null) :? outcome_base {
        return self::instance_from_persistent(new outcome($id, $record));
    }

    /**
     * Helper method to create an instance from persistent
     *
     * @param outcome $persistent
     * @return outcome_base|null
     */
    final protected static function instance_from_persistent(outcome $persistent) :? outcome_base {
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
        $instance->outcome = $persistent;
        return $instance;
    }

    /**
     * Creates a new outcome and saves it to database
     *
     * @param int $ruleid
     * @param array $configdata
     * @return outcome_base
     */
    final public static function create(int $ruleid, array $configdata) : outcome_base {
        $record = new \stdClass();
        $record->ruleid = $ruleid;
        $record->configdata = json_encode($configdata);
        $instance = self::instance(0, $record);
        $instance->outcome->save();
        return $instance;
    }

    /**
     * Check if current instance is dummy outcome.
     *
     * @return bool
     */
    final public function is_dummy(): bool {
        return (get_called_class() === 'tool_dynamicrule\\tool_dynamicrule\\outcome\\dummy');
    }

    /**
     * Delete outcome from database.
     */
    public function delete() {
        $this->outcome->delete();
    }

    /**
     * If the current user is able to add this outcome.
     *
     * Outcome classes need to define this method to make user capabilities
     * and permissions check for adding this outcome. If that is not applicable,
     * use self::is_available() to control outcome adding possibility.
     *
     * @see self::is_available()
     *
     * TODO: make abstract when all outcomes are fixed
     *
     * @return bool
     */
    abstract public function user_can_add(): bool;

    /**
     * If the current user is able to edit this outcome.
     *
     * Outcome classes need to define this method to make user capabilities
     * and permissions check for the current configuration of outcome.
     * (e.g. user can enrol into currently selected course in enrol outcome).
     *
     * TODO: make abstract when all outcomes are fixed
     *
     * @param array $configdata
     * @return bool
     */
    abstract public function user_can_edit(array $configdata): bool;

    /**
     * If the current user is able to use this outcome.
     *
     * This method need to return true if outcome is available to user for
     * other reason than permission check (which is done in user_can_add()).
     * (e.g. user can use cohort outcome only if there is at least one cohort
     * in the system). Use get_not_available_label() function to define the reason
     * which will be shown in the interface.
     *
     * @see self::get_not_available_label()
     *
     */
    public static function is_available(): bool {
        return true;
    }

    /**
     * Outcome not available label.
     *
     * Outcomes may provide more detailed information why it is not available when
     * is_available returns false.
     *
     * @see self::is_available()
     *
     * @return string
     */
    public function get_not_available_label(): string {
        return get_string('outcomeisnotavailable', 'tool_dynamicrule');
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
     * Update configdata in outcome persistent
     *
     * @param array $configdata
     * @param bool $save Whether to save the config change to the database
     */
    public function update_configdata(array $configdata, bool $save = false) {
        $this->outcome->set('configdata', json_encode($configdata));
        $this->outcome->set('broken', 0);

        if ($save) {
            $this->outcome->save();
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
     * Validates the configform of the outcome.
     *
     * Only validate form elements data here and return array of ['element' => 'error text']
     * if there are error, as this is called from moodleform::validation().
     * Please use user_can_edit method in the outcome to validate user capabilities.
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
     * Apply this outcome on a given list of users
     *
     * @param array $users The users objects to apply the outcome to
     *
     * @deprecated since 3.10 WP-2353 - please do not use this function any more.
     */
    public function apply_to_users(array $users) {
        debugging('Using apply_to_users() is deprecated, please define apply_to_user() in your code instead.', DEBUG_DEVELOPER);
    }

    /**
     * Return the description for the outcome.
     *
     * @return string
     */
    abstract public function get_description(): string;

    /**
     * Apply this outcome to a given user.
     *
     * We are moving away of apply_to_users call, as outcomes will be applied
     * to individual users only. TODO: Make abstract eventually.
     *
     * @param \stdClass $user The user object to apply the outcome to
     */
    public function apply_to_user(\stdClass $user): void {
        // We should not reach here really, unless apply_to_user() is not defined in the outcome.
        debugging('Please define apply_to_user() in your code, using apply_to_users() is deprecated.', DEBUG_DEVELOPER);
        // Fallback to apply_to_users.
        $this->apply_to_users([$user]);
    }

    /**
     * Helper function called before outcome is applied to user.
     *
     * This is called one before apply_to_user() is executed for every user in
     * rule processing, which is more than one if rule is processed on cron.
     * In performance heavy outcomes developer may use it to create instances of
     * classes or any other operations that don't need to be repeated for
     * individual user processing in apply_to_user following calls.
     */
    public function setup_for_applying(): void {
        return;
    }

    /**
     * Outcome broken label.
     *
     * Outcomes may provide more detailed information on what is broken when
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
     * Outcome broken description.
     *
     * Outcomes may provide more detailed information on what is broken when
     * is_configuration_valid returns false.
     *
     * @see self::is_configuration_valid()
     *
     * @return string
     */
    public function get_broken_description(): string {
        return get_string('outcomeisbroken', 'tool_dynamicrule');
    }

    /**
     * Return ID of current outcome.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->outcome->get('id');
    }

    /**
     * Return rule ID of current outcome.
     *
     * @return int
     */
    public function get_ruleid(): int {
        return (int) $this->outcome->get('ruleid');
    }

    /**
     * Returns a rule this outcome belongs to
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
     * Return Tenant ID of the rule the current outcome belongs to.
     *
     * @return int
     */
    protected function get_tenantid(): int {
        return $this->get_rule()->get('tenantid');
    }

    /**
     * Configuration validity check.
     *
     * Must return true if the configuration of the outcome are still valid.
     * This should not check user permissions (e.g. for badge
     * outcome this will check that the badge exists and that it is not archived).
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
     * Mark outcome as broken.
     *
     */
    public function mark_as_broken() {
        $this->outcome->set('broken', 1);
        $this->outcome->save();
    }

    /**
     * Mark outcome as not broken.
     *
     */
    public function mark_as_not_broken(): void {
        $this->outcome->set('broken', 0);
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

    /**
     * Allow outcomes to add mapped fields during export. Outcomes should implement this method when they contain
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
     * Allow outcomes to retrieve mapped fields during import. Outcomes should implement this method when they contain
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

    /**
     * Callback executed before form is rendered
     *
     * Can be used to add extra JS, for example for placeholders.
     */
    public static function before_form_render(): void {
        return;
    }
}
