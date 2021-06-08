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
 * Class csv_imported_entity
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\csv;

use tool_wp\importer_base;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\local\exportimport\imported_entity;

defined('MOODLE_INTERNAL') || die();

/**
 * Class csv_imported_entity
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class csv_imported_entity extends imported_entity {

    /**
     * wp_imported_entity constructor.
     *
     * To initiate call importer_base::get_entities_in_workplace_export_file()
     * and iterate through results
     *
     * @param import_manager $importmanager
     * @param int $rowid
     * @param array $headers
     * @param array $data
     */
    public function __construct(import_manager $importmanager, int $rowid, array $headers, array $data) {
        $this->importmanager = $importmanager;
        $this->entityid = $rowid;
        $this->data = array_combine($headers, $data);
        $this->entityname = importer_base::CSV_DATA;
    }

    /**
     * Prepare record with all necessary fields excluded/mapped, ready for import
     *
     * This function is called after validation passed before actual import. This function is never
     * called when collecting errors or reviewing import settings.
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_record_for_import(): array {
        $array = [];
        $settings = $this->importmanager->get_settings();
        foreach ($settings as $key => $value) {
            if (preg_match('/^'.importer_base::SETTING_CSV_COLUMNS_MAPPING.':(.*)$/', $key, $matches)) {
                $defaultkey = importer_base::SETTING_CSV_COLUMNS_DEFAULT.':'.$matches[1];
                if ($value) {
                    $array[$matches[1]] = $this->get_raw_field($value);
                } else if (array_key_exists($defaultkey, $settings)) {
                    $array[$matches[1]] = $settings[$defaultkey];
                } else {
                    $array[$matches[1]] = null;
                }
            }
        }

        return $array;
    }

    /**
     * Executed after import
     */
    protected function post_import() {
        null;
    }
}
