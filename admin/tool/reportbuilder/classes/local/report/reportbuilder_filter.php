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
 * Persistent class.
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

namespace tool_reportbuilder\local\report;

defined('MOODLE_INTERNAL') || die();

use core\persistent;
use tool_reportbuilder\helper;

/**
 * Class reportbuilder_filter
 *
 * @package   tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */
class reportbuilder_filter extends persistent {

    /** Main table */
    const TABLE = 'tool_reportbuilder_filter';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'reportid' => array(
                'type' => PARAM_INT,
            ),
            'entity' => array(
                'type' => PARAM_ALPHANUMEXT,
            ),
            'name' => array(
                'type' => PARAM_ALPHANUMEXT
            ),
            'sortorder' => array(
                'type' => PARAM_INT,
            ),
            'heading' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            )
        );
    }

    /**
     * Get the maxid for the sortorder of the new column.
     *
     * @param int $reportid
     *
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_max_sortorder($reportid) {
        global $DB;
        $record = $DB->get_record(static::TABLE, ['reportid' => $reportid], 'MAX(sortorder) as maxsortorder');
        return $record->maxsortorder;
    }

    /**
     * Get the key of the filter.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_unique_identifier() : string {
        return $this->get('entity') . ':' . $this->get('name');
    }
}