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
 * Rule outcome instance persistent.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */


namespace tool_dynamicrule;

defined('MOODLE_INTERNAL') || die();

/**
 * Rule outcome instance persistent class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class outcome extends \core\persistent {

    /** @var string table. */
    const TABLE = 'tool_dynamicrule_outcome';

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
                'description' => 'Outcome instance configuration properties.',
                'default' => '{}',
            ),
            'broken' => array(
                'type' => PARAM_INT,
                'description' => 'Conditions are broek when its configuration is not valid.',
                'default' => 0,
            ),
            'timecreated' => array(
                'type' => PARAM_INT,
                'description' => 'Time the outcome instance was created.',
            ),
            'timemodified' => array(
                'type' => PARAM_INT,
                'description' => 'Time the outcome instance was modified.',
            ),
        );
    }
}
