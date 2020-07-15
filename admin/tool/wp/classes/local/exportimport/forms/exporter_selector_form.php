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
 * Class exporter_selector_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use tool_wp\local\exportimport\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Step 1. Select exporter
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class exporter_selector_form extends export_base_form {
    /** @var int */
    protected $stage = 1;

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'exporterheader', get_string('selectexporter', 'tool_wp'));

        $mform->addElement('hidden', 'entrypoint');
        $mform->setType('entrypoint', PARAM_ALPHANUMEXT);

        $mform->addElement('hidden', 'entrypointid');
        $mform->setType('entrypointid', PARAM_INT);

        $elements = [];
        $separators = [];
        $exporters = helper::get_all_exporters();
        \core_collator::asort_objects_by_method($exporters, 'get_name');
        foreach ($exporters as $plugin) {
            $elements[] = $mform->createElement('radio', 'exporter', '', $plugin->get_name(), get_class($plugin));
            if ($plugin->get_description()) {
                $separators[] = \html_writer::div(
                    \html_writer::div($plugin->get_description(), 'ml-3 text-gray080'),
                    'w-100 mt-1 mb-4'
                );
            } else {
                $separators[] = \html_writer::div('', 'w-100 mb-4');
            }
        }
        // Create an empty element to show the last separator.
        $elements[] = $mform->createElement('static', '', '');

        $mform->addGroup($elements, 'exporters', '', $separators, false);
        $this->add_buttons(null, false);
    }

    /**
     * Allow exporter to perform validation on their form data
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['exporter'])) {
            $errors['exporters'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Access validation
     *
     * @throws \moodle_exception
     */
    public function require_access() {
        \tool_wp\permission::require_can_use_export_import();
    }
}
