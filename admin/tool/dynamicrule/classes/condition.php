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
 * Rule condition instance persistent.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule condition instance persistent class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core\persistent {

    /** @var string table. */
    const TABLE = 'tool_dynamicrule_condition';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'ruleid' => array(
                'type' => PARAM_INT,
                'description' => 'The rule reference.',
            ),
            'classname' => array(
                'type' => PARAM_TEXT,
                'description' => 'The classname reference.',
            ),
            'configdata' => array(
                'type' => PARAM_RAW,
                'description' => 'Condition instance configuration properties.',
                'default' => '{}',
            ),
            'broken' => array(
                'type' => PARAM_INT,
                'description' => 'Conditions are broek when its configuration is not valid.',
                'default' => 0,
            ),
            'timecreated' => array(
                'type' => PARAM_INT,
                'description' => 'Time the condition instance was created.',
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
                'description' => 'Time the condition instance was modified.',
            ),
        );
    }

    /**
     * Update rule broken status if condition fixed.
     *
     * @param bool $result Whether or not the update was successful.
     * @return void
     */
    protected function after_update($result) {
        if ($result) {
            if ($this->get('broken') == 0) {
                \tool_dynamicrule\api::mark_rule_as_not_broken($this->get('ruleid'));
            }
        }
        return $result;
    }
}
