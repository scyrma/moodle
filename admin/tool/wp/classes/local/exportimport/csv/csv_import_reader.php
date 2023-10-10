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
 * Class csv_import_reader
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\csv;

use tool_wp\local\exportimport\import_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/csvlib.class.php');

/**
 * Class csv_import_reader
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class csv_import_reader extends \csv_import_reader {

    /** @var string */
    protected $filename;
    /** @var import_manager */
    protected $importmanager;

    /**
     * Contructor
     *
     * @param import_manager $importmanager
     */
    public function __construct(import_manager $importmanager) {
        global $USER, $CFG;
        $type = 'tool_wp';
        $iid = self::get_new_iid($type);
        parent::__construct($iid, $type);
        $this->importmanager = $importmanager;
        $settings = $importmanager->get_settings();
        $delimiter = $settings['csvdelimitername'] ?? 'comma';
        $encoding = $settings['encoding'] ?? 'UTF-8';

        // Filepath is hardcoded in the parent class.
        $this->filename = $CFG->tempdir.'/csvimport/'.$type.'/'.$USER->id.'/'.$iid;
        $this->load_csv_content($importmanager->get_file()->get_content(), $encoding, $delimiter);
    }

    /**
     * Get CSV file preview
     *
     * @param int $rowcount Set to number of rows to return, 0 to return all
     * @return array
     */
    public function get_preview(int $rowcount = 0): array {
        if ($this->get_error() || !$this->init()) {
            return [];
        }

        $columns = $this->get_columns();
        $rows = [];
        $cnt = 0;
        while ($row = $this->next()) {
            if (!$rowcount || $cnt < $rowcount) {
                $rows[] = $row;
            }
            $cnt++;
        }
        $this->close();
        return [
            'columns' => $columns,
            'rows' => $rows,
            'total' => $cnt,
        ];
    }

    /**
     * CSV rows
     *
     * @return csv_imported_entity[]
     */
    public function get_rows() {
        if ($this->get_error() || !$this->init()) {
            return [];
        }

        // TODO convert to iterator.
        $columns = $this->get_columns();
        $rows = [];
        $rowid = 0;
        while ($row = $this->next()) {
            $rowid++;
            $rows[] = new csv_imported_entity($this->importmanager, $rowid, $columns, $row);
        }
        $this->close();

        return $rows;
    }
}
