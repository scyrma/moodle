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
 * Plugin reportbuilder column class.
 *
 * @package     tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

use core\persistent;

/**
 * Class reportbuilder_column
 *
 * @package tool_reportbuilder
 * @copyright 2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reportbuilder_column extends persistent {

    /** Main table of the reportbuilder column persistent */
    const TABLE = 'tool_reportbuilder_column';

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
                'type' => PARAM_TEXT,
            ),
            'columnorder' => array(
                'type' => PARAM_INT,
            ),
            'aggregate' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ),
            'heading' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ),
            'hidden' => array(
                'type' => PARAM_BOOL,
                'null' => NULL_NOT_ALLOWED,
                'default' => 0,
            ),
            'sortenabled' => array(
                'type' => PARAM_BOOL,
                'default' => false,
            ),
            'sortdirection' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => SORT_ASC,
            ),
            'sortorder' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            )
        );
    }

    /**
     * Get the max sort index.
     * @param int $reportid
     * @param string $column
     *
     * @return mixed
     * @throws \dml_exception
     */
    public static function get_max_columnorder($reportid, $column) {
        global $DB;
        $column = ($column === 'sortorder') ? 'sortorder' : 'columnorder';
        $record = $DB->get_record(static::TABLE, ['reportid' => $reportid], "MAX($column) as maxsortorder");
        return $record->maxsortorder;
    }

    /**
     * Check if the column already exists in DB.
     *
     * @param int           $reportid
     * @param report_column $column
     *
     * @return reportbuilder_column
     */
    public static function check_column(int $reportid, report_column $column) {
        $params = [
            'reportid' => $reportid,
            'entity' => $column->get_entity(),
            'name' => $column->get_name(),
        ];

        return self::get_record($params);
    }

    /**
     * Get the identifier for this column that is unique for the datasource
     *
     * @return mixed
     */
    public function get_unique_identifier() : string {
        return $this->get('entity') . ':' . $this->get('name');
    }
}