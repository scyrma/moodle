<?php
// This file is part of the mod_appointment plugin for Moodle - http://moodle.org/
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
 * Appointment handler for custom fields
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_appointment\customfield;

defined('MOODLE_INTERNAL') || die;

use core_customfield\api;
use core_customfield\field_controller;

/**
 * Course handler for custom fields
 *
 * @package     mod_appointment
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      Daniel Neis Araujo <daniel@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class appointment_handler extends \core_customfield\handler {

    /**
     * @var appointment_handler
     */
    static protected $singleton;

    /**
     * @var \context
     */
    protected $parentcontext;

    /**
     * Returns a singleton
     *
     * @param int $itemid
     * @return \mod_appointment\customfield\appointment_handler
     */
    public static function create(int $itemid = 0) : \core_customfield\handler {
        if (static::$singleton === null) {
            self::$singleton = new static(0);
        }
        return self::$singleton;
    }

    /**
     * The current user can configure custom fields on this component.
     *
     * @return bool true if the current can configure custom fields, false otherwise
     */
    public function can_configure() : bool {
        return \mod_appointment\permission::can_configure_custom_fields();
    }

    /**
     * The current user can edit custom fields on the given appointment.
     *
     * @param field_controller $field
     * @param int $instanceid id of the appointment session to test editing permission.
     * @return bool true if the current can edit custom fields, false otherwise
     */
    public function can_edit(field_controller $field, int $instanceid = 0) : bool {
        $context = $this->get_instance_context($instanceid);
        return \mod_appointment\permission::can_edit_sessions($context);
    }

    /**
     * The current user can view custom fields on the given course.
     *
     * @param field_controller $field
     * @param int $instanceid id of the course to test edit permission
     * @return bool true if the current can edit custom fields, false otherwise
     */
    public function can_view(field_controller $field, int $instanceid) : bool {
        return \mod_appointment\permission::can_view_custom_fields($instanceid);
    }

    /**
     * Sets parent context for the module
     *
     * This may be needed when appointment is being created, there is no module context but we need to check capabilities
     *
     * @param \context $context
     */
    public function set_parent_context(\context $context) {
        $this->parentcontext = $context;
    }

    /**
     * Returns the parent context for the appointment
     *
     * @return \context
     */
    protected function get_parent_context() : \context {
        global $PAGE;
        if ($this->parentcontext) {
            return $this->parentcontext;
        } else if (isset($PAGE->context) && $PAGE->context instanceof \context_course) {
            return $PAGE->context;
        } else {
            return \context_system::instance();
        }
    }

    /**
     * Context that should be used for new categories created by this handler
     *
     * @return \context the context for configuration
     */
    public function get_configuration_context() : \context {
        // We configure custom fields at system context.
        return \context_system::instance();
    }

    /**
     * Returns the context for the data associated with the given instanceid.
     *
     * @param int $instanceid id of the record to get the context for
     * @return \context the context for the given record
     */
    public function get_instance_context(int $instanceid = 0) : \context {
        global $DB;
        if ($instanceid > 0) {
            $appointment = $DB->get_field('appointment_sessions', 'appointment', ['id' => $instanceid], MUST_EXIST);
            $cm = get_coursemodule_from_instance('appointment', $appointment, 0, false, MUST_EXIST);
            return \context_module::instance($cm->id);
        } else {
            return $this->get_parent_context();
        }
    }

    /**
     * URL for configuration of the fields on this handler.
     *
     * @return \moodle_url The URL to configure custom fields for this component
     */
    public function get_configuration_url() : \moodle_url {
        return new \moodle_url('/mod/appointment/customfield.php');
    }
}
