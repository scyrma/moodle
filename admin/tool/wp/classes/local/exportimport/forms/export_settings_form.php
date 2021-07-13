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
 * Class export_settings_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

defined('MOODLE_INTERNAL') || die();

/**
 * Step 2. Exporter-specific settings
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_settings_form extends export_base_form {
    /** @var int */
    protected $stage = 2;

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'exporter');
        $mform->setType('exporter', PARAM_RAW_TRIMMED);

        $mform->addElement('hidden', 'exportertenant');
        $mform->setType('exportertenant', PARAM_INT);

        $mform->addElement('hidden', 'entrypoint');
        $mform->setType('entrypoint', PARAM_ALPHANUMEXT);

        $mform->addElement('hidden', 'entrypointid');
        $mform->setType('entrypointid', PARAM_INT);

        $this->get_exporter()->add_to_options_form($this);

        $this->add_buttons();
    }

    /**
     * Process form
     *
     * @param \stdClass $data
     * @return int|mixed
     */
    public function process(\stdClass $data) {
        // We need to pass the selected settings to the "Review" screen.
        $rv = parent::process($data);
        $exporterdata = array_diff_key((array)$data, $rv);
        return $rv + ['exporterdata' => base64_encode(serialize($exporterdata))];
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_modal() {
        if (!empty($this->_ajaxformdata['exporterdata'])) {
            // We clicked "Back" from the review screen.
            $exporterdata = unserialize_array(base64_decode($this->_ajaxformdata['exporterdata']));
            $this->set_data($exporterdata);
            unset($this->_ajaxformdata['exporterdata']);
        }
        parent::set_data_for_modal();
    }
}
