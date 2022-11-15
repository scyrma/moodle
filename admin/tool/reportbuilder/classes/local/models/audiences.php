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
 * Class containing report audiences persistent
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\models;

use cache;
use core\persistent;
use lang_string;
use tool_reportbuilder\reportbuilder;

/**
 * Model class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audiences extends persistent {

    /** @var string Table name */
    public const TABLE = 'tool_reportbuilder_audiences';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() : array {
        return [
            'reportid' => [
                'type' => PARAM_INT,
            ],
            'classname' => array(
                'type' => PARAM_TEXT,
                'description' => 'The classname reference.',
            ),
            'configdata' => array(
                'type' => PARAM_RAW,
                'description' => 'Condition instance configuration properties.',
                'default' => '{}',
            ),
            'usercreated' => array(
                'type' => PARAM_INT,
                'default' => static function(): int {
                    global $USER;

                    return (int) $USER->id;
                },
            ),
        ];
    }

    /**
     * Validate reportid property
     *
     * @param int $reportid
     * @return bool|lang_string
     */
    protected function validate_reportid(int $reportid) {
        if (!reportbuilder::record_exists($reportid)) {
            return new lang_string('invaliddata', 'error');
        }

        return true;
    }

    /**
     * Purge userreports cache after create
     */
    protected function after_create(): void {
        cache::make('tool_reportbuilder', 'userreports')->purge();
    }

    /**
     * Purge userreports cache after update
     * @param bool $result
     */
    protected function after_update($result): void {
        cache::make('tool_reportbuilder', 'userreports')->purge();
    }

    /**
     * Purge userreports cache after delete
     * @param bool $result
     */
    protected function after_delete($result): void {
        cache::make('tool_reportbuilder', 'userreports')->purge();
    }
}
