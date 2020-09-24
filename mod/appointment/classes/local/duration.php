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
 * Customised duration form element
 *
 * Contains class to create length of time for element.
 *
 * @package   mod_appointment
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    Ruslan Kabalin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/form/group.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * Repeat-friendly duration element.
 *
 * Check if this file is still needed when MDL-43156 lands.
 *
 * @package   mod_appointment
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_appointment_duration extends MoodleQuickForm_group {
    /**
     * Options for the element.
     *
     * defaultunit => 1|60|3600|86400|604800 - the default unit to display when the time is blank.
     *                If not specified, minutes is used.
     * @var array
     */
    protected $_options = [];

    /** @var array associative array of time units (days, hours, minutes, seconds) */
    private $_units = null;


    // @codingStandardsIgnoreStart
    /**
     * constructor
     *
     * @param string $elementName Element's name
     * @param mixed $elementLabel Label(s) for an element
     * @param array $options Options to control the element's display. Recognised values are
     *              'defaultunit' => 1|60|3600|86400|604800 - the default unit to display when the time is blank.
     *              If not specified, minutes is used.
     * @param mixed $attributes Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $options = [], $attributes = null) {
        parent::__construct($elementName, $elementLabel);
        $this->setAttributes($attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'duration';
        $this->_options = ['defaultunit' => MINSECS];

        if (isset($options['defaultunit'])) {
            if (!array_key_exists($options['defaultunit'], $this->get_units())) {
                throw new coding_exception($options['defaultunit'] .
                        ' is not a recognised unit in MoodleQuickForm_duration.');
            }
            $this->_options['defaultunit'] = $options['defaultunit'];
        }
    }

    /**
     * Returns time associative array of unit length.
     *
     * @return array unit length in seconds => string unit name.
     */
    public function get_units() {
        if (is_null($this->_units)) {
            $this->_units = array(
                WEEKSECS => get_string('weeks'),
                DAYSECS => get_string('days'),
                HOURSECS => get_string('hours'),
                MINSECS => get_string('minutes'),
                1 => get_string('seconds'),
            );
        }
        return $this->_units;
    }

    /**
     * Converts seconds to the best possible time unit. for example
     * 1800 -> array(30, 60) = 30 minutes.
     *
     * @param int $seconds an amout of time in seconds.
     * @return array associative array ($number => $unit)
     */
    public function seconds_to_unit($seconds) {
        if ($seconds == 0) {
            return array(0, $this->_options['defaultunit']);
        }
        foreach ($this->get_units() as $unit => $notused) {
            if (fmod($seconds, $unit) == 0) {
                return array($seconds / $unit, $unit);
            }
        }
        return array($seconds, 1);
    }

    /**
     * Override of standard quickforms method to create this element.
     */
    function _createElements() {
        $attributes = $this->getAttributes();
        if (is_null($attributes)) {
            $attributes = array();
        }
        if (!isset($attributes['size'])) {
            $attributes['size'] = 3;
        }
        $this->_elements = array();
        // E_STRICT creating elements without forms is nasty because it internally uses $this
        $number = $this->createFormElement('text', 'number', get_string('time', 'form'), $attributes, true);
        $number->set_force_ltr(true);
        $this->_elements[] = $number;
        unset($attributes['size']);
        $this->_elements[] = $this->createFormElement('select', 'timeunit', get_string('timeunit', 'form'), $this->get_units(), $attributes, true);
        foreach ($this->_elements as $element){
            if (method_exists($element, 'setHiddenLabel')){
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
                if ($this->getType() !== 'duration') {
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

                if (!is_array($value)) {
                    list($number, $unit) = $this->seconds_to_unit($value);
                    $value = array('number' => $number, 'timeunit' => $unit);
                }
                if (null !== $value){
                    $this->setValue($value);
                }
                break;
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
        return true;
    }

    /**
     * Output a timestamp. Give it the name of the group.
     * Override of standard quickforms method.
     *
     * @param  array $submitValues
     * @param  bool  $assoc  whether to return the value as associative array
     * @return array field name => value. The value is the time interval in seconds.
     */
    function exportValue(&$submitValues, $assoc = false) {
        // Get the values from all the child elements.
        $valuearray = array();
        foreach ($this->_elements as $element) {
            $thisexport = $element->exportValue($submitValues[$this->getName()], true);
            if (!is_null($thisexport)) {
                $valuearray += $thisexport;
            }
        }

        // Convert the value to an integer number of seconds.
        if (empty($valuearray)) {
            return null;
        }
        return $this->_prepareValue($valuearray['number'] * $valuearray['timeunit'], $assoc);
    }
    // @codingStandardsIgnoreEnd
}
