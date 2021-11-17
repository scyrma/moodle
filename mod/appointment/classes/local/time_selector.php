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
 * Group of time input element
 *
 * Contains class for a group of elements used to input a date and time.
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/form/group.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Element used to input a date and time.
 *
 * Class for a group of elements used to input a date and time.
 *
 * @package   mod_appointment
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_time_selector extends MoodleQuickForm_group {

    /**
     * Options for the element.
     *
     * timezone => int|float|string (optional) timezone modifier used for edge case only.
     *      If not specified, then date is caclulated based on current user timezone.
     *      Note: dst will be calculated for string timezones only
     *      {$see http://docs.moodle.org/dev/Time_API#Timezone}
     * step => step to increment minutes by
     * @var array
     */
    protected $_options = array();

    // @codingStandardsIgnoreStart
    /**
     * Class constructor
     *
     * @param string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $options = [], $attributes = null) {
        parent::__construct($elementName, $elementLabel);
        $this->setAttributes($attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        // We can safely use date_time_selector type and template.
        $this->_type = 'date_time_selector';

        // Set the options, do not bother setting bogus ones.
        $this->_options = ['timezone' => 99, 'step' => 1];
        if (is_array($options)) {
            foreach ($options as $name => $value) {
                if (isset($this->_options[$name])) {
                    $this->_options[$name] = $value;
                }
            }
        }
    }

    /**
     * This will create time group element consisting of hour and minute.
     */
    function _createElements() {
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }
        for ($i = 0; $i < 60; $i += $this->_options['step']) {
            $minutes[$i] = sprintf("%02d", $i);
        }
        $this->_elements = [];
        if (right_to_left()) {
            // Display time in RTL mode.
            $this->_elements[] = $this->createFormElement('select', 'minute', '', $minutes, $this->getAttributes(), true);
            $this->_elements[] = $this->createFormElement('select', 'hour', '', $hours, $this->getAttributes(), true);
        } else {
            // Display time in LTR mode.
            $this->_elements[] = $this->createFormElement('select', 'hour', '', $hours, $this->getAttributes(), true);
            $this->_elements[] = $this->createFormElement('select', 'minute', '', $minutes, $this->getAttributes(), true);
        }
        foreach ($this->_elements as $element) {
            if (method_exists($element, 'setHiddenLabel')) {
                $element->setHiddenLabel(true);
            }
        }
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param string $event Name of event
     * @param mixed $arg event arguments
     * @param object $caller calling object
     * @return bool
     */
    function onQuickFormEvent($event, $arg, &$caller) {
        $this->setMoodleForm($caller);
        switch ($event) {
            case 'updateValue':
                if ($this->getType() !== 'date_time_selector') {
                    // We don't plan changes for other elements, leave here if got different one.
                    break;
                }
                // Constant values override both default and submitted ones
                // default values are overriden by submitted.
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    // We do not check isSubmitted here as this may be misleading
                    // for group elements when add more elements button is pressed.
                    // So, if submitted values are there, use them, if not use defaults.
                    $value = $this->_findValue($caller->_submitValues);
                    if (null === $value) {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                if ($value == 0) {
                    // Default to current time, if default value is not provided.
                    $value = time();
                }
                if (!is_array($value)) {
                    $calendartype = \core_calendar\type_factory::get_calendar_instance();
                    $currentdate = $calendartype->timestamp_to_date_array($value, $this->_options['timezone']);
                    // Round minutes to the previous multiple of step.
                    $currentdate['minutes'] -= $currentdate['minutes'] % $this->_options['step'];
                    $value = [
                        'minute' => $currentdate['minutes'],
                        'hour' => $currentdate['hours'],
                    ];
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
                break;
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
        return true;
    }

    /**
     * Output seconds since midnight. Give it the name of the group.
     *
     * @param array $submitValues values submitted.
     * @param bool $assoc specifies if returned array is associative
     * @return array
     */
    function exportValue(&$submitValues, $assoc = false) {
        $valuearray = array();
        foreach ($this->_elements as $element) {
            $thisexport = $element->exportValue($submitValues[$this->getName()], true);
            if ($thisexport!=null) {
                $valuearray += $thisexport;
            }
        }
        $value = ($valuearray['hour'] * 3600) + ($valuearray['minute'] * 60);
        return $this->_prepareValue($value, $assoc);
    }
    // @codingStandardsIgnoreEnd
}
