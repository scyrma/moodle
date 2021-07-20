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
 * Plugin reportbuilder column class.
 *
 * @package    tool_reportbuilder
 * @copyright  2018 Moodle Pty Ltd <support@moodle.com>
 * @author     2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder;

defined('MOODLE_INTERNAL') || die();

use core\persistent;

/**
 * Class reportbuilder_column
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
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

    /**
     * Hook to execute before a delete.
     *
     * Make sure that we mark the respective report as "changed"
     *
     * @return void
     */
    protected function before_delete() {
        global $DB;
        $reportid = $this->raw_get('reportid');
        if (!$reportid) {
            $reportid = $DB->get_field(static::TABLE, 'reportid', ['id' => $this->raw_get('id')]);
        }
        report_base::report_configuration_modified((int)$reportid);
    }

    /**
     * Hook to execute after an update.
     *
     * Make sure that we mark the respective report as "changed"
     *
     * @param bool $result Whether or not the update was successful.
     * @return void
     */
    protected function after_update($result) {
        global $DB;
        $reportid = $this->raw_get('reportid');
        if (!$reportid) {
            $reportid = $DB->get_field(static::TABLE, 'reportid', ['id' => $this->raw_get('id')]);
        }
        report_base::report_configuration_modified((int)$reportid);
    }

    /**
     * Hook to execute after a create.
     *
     * Make sure that we mark the respective report as "changed"
     *
     * @return void
     */
    protected function after_create() {
        report_base::report_configuration_modified((int)$this->raw_get('reportid'));
    }
}
