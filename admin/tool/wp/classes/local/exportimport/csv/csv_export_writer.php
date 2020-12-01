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
 * Class csv_export_writer
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\csv;

use tool_wp\local\exportimport\export_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/csvlib.class.php');

/**
 * Class csv_export_writer
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class csv_export_writer extends \csv_export_writer {
    /** @var array  */
    protected $headers = null;
    /** @var export_manager */
    protected $exportmanager;

    /**
     * Constructor for the csv export writer
     *
     * @param export_manager $exportmanager
     */
    public function __construct(export_manager $exportmanager) {
        $this->exportmanager = $exportmanager;
        parent::__construct('cfg', '"', null);
    }

    /**
     * Set the file path to the temporary file.
     */
    protected function set_temp_file_path() {
        $dir = make_request_directory();
        $this->path = $dir . '/' . $this->filename;
    }

    /**
     * Add data to the temporary file in csv format
     *
     * @param array $row  An array of values.
     */
    public function add_data($row) {
        if ($this->headers === null) {
            $this->headers = array_keys($row);
            parent::add_data($this->headers);
        }
        $record = [];
        foreach ($this->headers as $key) {
            $record[] = array_key_exists($key, $row) ? $row[$key] : null;
        }
        parent::add_data($record);
    }

    /**
     * Store the result in file storage.
     *
     * @param int $contextid context ID
     * @param string $component component
     * @param string $filearea file area
     * @param int $itemid item ID
     * @param string $filepath file path
     * @param string $filename file name
     * @return \stored_file|bool false if error stored_file instance if ok
     */
    public function csv_to_storage(int $contextid,
                                       string $component, string $filearea, int $itemid, string $filepath, string $filename) {
        global $USER;
        $fs = get_file_storage();
        fclose($this->fp);
        $this->fp = null;

        $filerecord = new \stdClass();
        $filerecord->contextid = $contextid;
        $filerecord->component = $component;
        $filerecord->filearea  = $filearea;
        $filerecord->itemid    = $itemid;
        $filerecord->filepath  = $filepath;
        $filerecord->filename  = $filename;
        $filerecord->userid    = $USER->id;
        $filerecord->mimetype  = $this->mimetype;

        return $fs->create_file_from_pathname($filerecord, $this->path);
    }

    /**
     * Make sure that everything is closed when we are finished.
     */
    public function __destruct() {
        $this->fp && fclose($this->fp);
    }

}
