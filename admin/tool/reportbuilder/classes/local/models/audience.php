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
 * Class containing report audience persistent
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\local\models;

use core\persistent;
use lang_string;
use tool_reportbuilder\reportbuilder;

defined('MOODLE_INTERNAL') || die;

/**
 * Model class
 *
 * @package     tool_reportbuilder
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Paul Holden <paulh@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class audience extends persistent {

    /** @var string Table name */
    public const TABLE = 'tool_reportbuilder_audience';

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
            'departmentid' => [
                'type' => PARAM_INT,
            ],
            'positionid' => [
                'type' => PARAM_INT,
            ],
            'subdepartments' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
            'subpositions' => [
                'type' => PARAM_BOOL,
                'default' => false,
            ],
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
}