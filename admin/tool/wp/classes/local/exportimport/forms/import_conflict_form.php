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

namespace tool_wp\local\exportimport\forms;

/**
 * Represents the "step 4 Conflict resolution" for the import process
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_conflict_form extends import_base_form {
    /** @var int */
    protected $stage = 4;

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;
        $mform->setDisableShortforms();

        $mform->addElement('hidden', 'importid');
        $mform->setType('importid', PARAM_INT);

        $mform->addElement('hidden', 'stage', $this->stage);
        $mform->setType('stage', PARAM_INT);

        $importmanager = $this->get_import_manager();
        $importmanager->conflict_form_definition($this);

        $this->add_buttons();
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->get_import_manager()->get_settings());
        parent::set_data_for_dynamic_submission();
    }

    /**
     * Process form
     *
     * @return int|mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $settings = array_diff_key((array)$data, ['importid' => 1, 'stage' => 1]);
        $this->get_import_manager()->save_settings($settings);
        return parent::process_dynamic_submission();
    }
}
