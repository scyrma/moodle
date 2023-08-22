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

declare(strict_types=1);

namespace tool_custompage\local\audience;

use core_plugin_manager;
use core\output\notification;
use MoodleQuickForm;
use tool_custompage\permission_exception;
use tool_custompage\local\models\audience;
use stdClass;

/**
 * Page audience base class
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class base {

    /** @var int Maximim number of multi-select elements to show in description, before appending "plus X more" */
    private const MULTI_SELECT_LIMIT = 5;

    /** @var audience The persistent object associated with this audience */
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
    final public static function instance(int $id = 0, ?stdClass $record = null): ?self {
        $persistent = new audience($id, $record);
        // Needed for get_audience_types() method.
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
     * @param int $pageid
     * @param array $configdata
     * @return self
     */
    final public static function create(int $pageid, array $configdata): self {
        $record = new stdClass();
        $record->pageid = $pageid;
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
     *
     * @return string
     */
    final public function get_category(): string {
        [$component] = explode('\\', get_class($this));

        if ($plugininfo = core_plugin_manager::instance()->get_plugin_info($component)) {
            return $plugininfo->displayname;
        }

        return get_string('site');
    }

    /**
     * If the current user is able to add this audience type
     *
     * @param bool $global True if current page is global, otherwise false
     * @return bool
     */
    abstract public function user_can_add(bool $global = false): bool;

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
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Return user friendly name of the audience type
     *
     * @return string
     */
    abstract public function get_name(): string;

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
        global $OUTPUT;

        // Warn user if there are no elements (because they may no longer exist).
        $elementcount = count($elements);
        if ($elementcount === 0) {
            $notification = new notification(get_string('nothingtodisplay'), notification::NOTIFY_WARNING);
            return $OUTPUT->render($notification);
        }

        $listseparator = get_string('listsep', 'langconfig') . ' ';
        if ($elementcount > self::MULTI_SELECT_LIMIT) {
            $elements = array_slice($elements, 0, self::MULTI_SELECT_LIMIT);

            // Append overflow element.
            $elementoverflow = $elementcount - self::MULTI_SELECT_LIMIT;
            $params = [
                'elements' => implode($listseparator, $elements),
                'morecount' => $elementoverflow,
            ];
            $description = get_string('audiencemultiselectpostfix', 'core_reportbuilder', $params);
        } else {
            $description = implode($listseparator, $elements);
        }

        return $description;
    }

    /**
     * Adds audience-specific form elements
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
    final public function get_configdata(): array {
        return json_decode($this->audience->get('configdata'), true);
    }

    /**
     * Update configdata in audience persistent
     *
     * @param array $configdata
     */
    final public function update_configdata(array $configdata): void {
        $this->audience->set('configdata', json_encode($configdata));
        $this->audience->save();
    }

    /**
     * Returns $configdata from form data suitable for use in DB record.
     *
     * @param stdClass $data data obtained from $mform->get_data()
     * @return array $configdata
     */
    final public static function retrieve_configdata(stdClass $data): array {
        $configdata = (array) $data;
        $invalidkeys = array_fill_keys(['id', 'pageid', 'classname'], '');
        return array_diff_key($configdata, $invalidkeys);
    }

    /**
     * Return audience persistent.
     *
     * @return audience
     */
    public function get_persistent(): audience {
        return $this->audience;
    }

    /**
     * Require current user is able to add this audience type
     *
     * @param bool $global True if current page is global, otherwise false
     * @throws permission_exception
     */
    final public function require_user_can_add(bool $global = false): void {
        if (!$this->user_can_add($global)) {
            throw new permission_exception('errorpageedit');
        }
    }

    /**
     * Require current user is able to edit this audience type
     *
     * @throws permission_exception
     */
    final public function require_user_can_edit(): void {
        if (!$this->user_can_edit()) {
            throw new permission_exception('errorpageedit');
        }
    }
}
