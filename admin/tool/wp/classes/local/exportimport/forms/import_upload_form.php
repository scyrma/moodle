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
 * Class import_upload_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use tool_wp\importer_base;
use tool_wp\local\exportimport\csv\csv_import_reader;
use tool_wp\local\exportimport\helper;
use tool_wp\local\exportimport\import_manager;
use tool_wp\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * Class import_upload_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_upload_form extends import_base_form {
    /** @var int */
    protected $stage = 1;
    /** @var array */
    protected $filemanageroptions;

    /**
     * Form definition
     */
    public function definition() {
        global $CFG;
        $mform = $this->_form;

        $mform->addElement('hidden', 'stage', $this->stage);
        $mform->setType('stage', PARAM_INT);

        $this->filemanageroptions = [
            'maxbytes' => $CFG->maxbytes,
            'subdirs' => 1,
            'maxfiles' => 1 // TODO possibly later more than one file.
        ];
        $mform->addElement('filemanager', 'importfile', get_string('uploadimportfile', 'tool_wp'), '',
            $this->filemanageroptions);

        $options = [
            0 => get_string('importformatauto', 'tool_wp'),
            importer_base::FORMAT_WORKPLACE => get_string('importformatworkplace', 'tool_wp'),
            importer_base::FORMAT_CSV => get_string('importformatcsv', 'tool_wp'),
        ];
        $mform->addElement('select', 'fileformat', get_string('importformat', 'tool_wp'), $options);

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'csvdelimitername', get_string('csvdelimiter', 'tool_uploadcourse'), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('csvdelimitername', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('csvdelimitername', 'semicolon');
        } else {
            $mform->setDefault('csvdelimitername', 'comma');
        }
        $mform->addHelpButton('csvdelimitername', 'csvdelimiter', 'tool_uploadcourse');

        $choices = \core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'tool_uploadcourse'), $choices);
        $mform->setDefault('encoding', 'UTF-8');
        $mform->addHelpButton('encoding', 'encoding', 'tool_uploadcourse');

        $this->add_buttons(null, false);
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        $errors = [];

        $draftitemid = $data['importfile'];
        $usercontext = \context_user::instance($USER->id);
        $draftfiles = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id',
            false);
        if (count($draftfiles) != 1) {
            $errors['importfile'] = get_string('required');
        } else if (empty($data['fileformat'])) {
            $file = reset($draftfiles);
            if (!$fileformat = helper::detect_file_format($file)) {
                $errors['fileformat'] = get_string('importunknownformat', 'tool_wp');
            }
        }

        return $errors;
    }

    /**
     * Access validation
     */
    public function require_access() {
        permission::require_can_use_export_import();
    }

    /**
     * Process form
     *
     * @param \stdClass $data
     * @return int|mixed
     */
    public function process(\stdClass $data) {
        $importdata = array_diff_key((array)$data, ['importfile' => 1]);
        $this->importmanager = import_manager::create_import_from_draftfile($importdata, $data->importfile);
        return parent::process($data);
    }
}
