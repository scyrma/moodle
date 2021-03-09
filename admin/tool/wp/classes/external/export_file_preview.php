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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * External class for retrieving an export file preview
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Paul Holden <paulh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\external;

use context_system;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use html_table;
use html_writer;
use tool_wp\permission;
use tool_wp\local\exportimport\import_manager;

/**
 * External class
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Paul Holden <paulh@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_file_preview extends external_api {

    /**
     * Describes the parameters for retrieving the export file preview
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([
            'importid' => new external_value(PARAM_INT, 'Import ID', VALUE_REQUIRED),
        ]);
    }

    /**
     * External function to retrieve the export file preview
     *
     * @param int $importid
     * @return array
     */
    public static function execute(int $importid): array {
        [
            'importid' => $importid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'importid' => $importid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);

        permission::require_can_view_import($importid);
        $importmanager = new import_manager($importid);

        $table = new html_table();
        $table->attributes['class'] = 'admintable generaltable';

        [
            'columns' => $table->head,
            'rows' => $table->data,
        ] = $importmanager->get_csv_reader()->get_preview();

        return [
            'html' => html_writer::table($table),
        ];
    }

    /**
     * Describes the data returned from the external function
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Raw HTML of the file preview table'),
        ]);
    }
}
