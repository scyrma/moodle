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
 * Class exporter_selector_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use tool_wp\exporter_base;
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
        global $OUTPUT;

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
            $type = $plugin->is_format(exporter_base::FORMAT_WORKPLACE) ? get_string('csvwpcolumn', 'tool_wp') : '';
            $exporterdesc = $OUTPUT->render_from_template('tool_wp/exporterimporter_selector', ['name' => $plugin->get_name(),
                'type' => $type, 'iconurl' => $plugin->get_icon_url(), 'description' => $plugin->get_description()]
            );
            $elements[] = $mform->createElement('radio', 'exporter', '', $exporterdesc, get_class($plugin));
            $separators[] = \html_writer::div('', 'w-100');
        }

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
