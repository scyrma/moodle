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

use tool_tenant\tenancy;
use tool_wp\importer_base;

/**
 * Represents the "step 2 General settings" for the import process
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_general_form extends import_base_form {
    /** @var int */
    protected $stage = 2;

    /** @var string */
    const DESTINATION_NOTENANT = 'notenant';
    /** @var string */
    const DESTINATION_CURRENTTENANT = 'current';
    /** @var string */
    const DESTINATION_SELECTTENANT = 'select';

    /**
     * Form definition
     */
    public function definition() {
        global $OUTPUT;
        $mform = $this->_form;
        $mform->setDisableShortforms();

        // Remember the tenantid and importer before the reset (in case we came back to this form from the next step).
        $originaltenantid = $this->get_import_manager()->get_import_tenant_id();
        $originalimporter = $this->get_import_manager()->get_importer();
        // Reset importer selection if we came back to this form and get the list of available importers.
        $this->get_import_manager()->save_general_settings(['importer' => null]);

        // Add hidden fields.
        $mform->addElement('hidden', 'importid');
        $mform->setType('importid', PARAM_INT);

        $mform->addElement('hidden', 'stage', $this->stage);
        $mform->setType('stage', PARAM_INT);

        // Add "About this file" section.
        $mform->addElement('header', 'aboutheader', get_string('aboutexportfile', 'tool_wp'));
        if ($summary = $this->get_import_manager()->get_export_file_summary_as_html()) {
            $mform->addElement('static', 'aboutbody', '', $summary);
        }
        if ($preview = $this->get_import_manager()->get_export_file_preview_as_html()) {
            $mform->addElement('static', 'aboutbodypreview', '', $preview);
        }

        // Chose importer.
        $importers = $this->get_import_manager()->get_importers();
        if (count($importers) == 1 && $this->get_import_manager()->is_workplace_export()) {
            $first = reset($importers);
            $mform->addElement('hidden', 'importer', get_class($first));
            $mform->setType('importer', PARAM_RAW_TRIMMED);
        } else {
            $mform->addElement('header', 'importerheader', get_string('selectimporter', 'tool_wp'));
            $mform->setExpanded('importerheader');
            if (!$importers) {
                $warningstr = get_string('noavailableimporter', 'tool_wp');
                $html = \html_writer::tag('div', $warningstr, ['class' => 'alert alert-danger']);
                $mform->addElement('static', 'noimporter', '', $html);
            } else {
                $elements = [];
                $separators = [];
                foreach ($importers as $importer) {
                    $type = $importer->is_format(importer_base::FORMAT_WORKPLACE) ? get_string('csvwpcolumn', 'tool_wp') : '';
                    $importerdesc = $OUTPUT->render_from_template('tool_wp/exporterimporter_selector',
                        ['name' => $importer->get_name(), 'type' => $type, 'iconurl' => $importer->get_icon_url(),
                            'description' => $importer->get_description()]);
                    $elements[] = $mform->createElement('radio', 'importer', '', $importerdesc, get_class($importer));
                    $separators[] = \html_writer::div('', 'w-100');
                }
                $mform->addGroup($elements, 'importers', '', $separators, false);
            }
        }

        // Destination (tenant).
        if ($originalimporter && !$originalimporter->is_destination_tenant_required()) {
            $mform->addElement('hidden', 'tenant', self::DESTINATION_NOTENANT);
            $mform->setType('tenant', PARAM_ALPHA);
        } else if (!\tool_tenant\permission::can_switch_tenant()) {
            $mform->addElement('hidden', 'tenantid', tenancy::get_tenant_id());
            $mform->setType('tenantid', PARAM_INT);
        } else {
            $mform->addElement('header', 'destinationheader', get_string('importdestination', 'tool_wp'));
            $mform->setExpanded('destinationheader');
            $mform->addElement('radio', 'tenant', '', get_string('importintothecurrenttenant', 'tool_wp'),
                self::DESTINATION_CURRENTTENANT);
            $mform->setDefault('tenant', self::DESTINATION_CURRENTTENANT);
            if ($this->is_no_tenant_destination_available()) {
                $mform->addElement('radio', 'tenant', '', get_string('importnotenant', 'tool_wp'), self::DESTINATION_NOTENANT);
            }
            $selecttenant[] = $mform->createElement('radio', 'tenant', '', get_string('importselecttenant', 'tool_wp'),
                self::DESTINATION_SELECTTENANT);

            $options = [];
            foreach (tenancy::get_tenants() as $tenant) {
                $options[$tenant->id] = tenancy::get_tenant_name_from_id($tenant->id);
            }
            $selecttenant[] = $mform->createElement('select', 'tenantid', get_string('importchoosetenant', 'tool_wp'), $options);
            $mform->setDefault('tenantid', tenancy::get_tenant_id());
            $mform->hideIf('tenantid', 'tenant', 'ne', self::DESTINATION_SELECTTENANT);
            $mform->addGroup($selecttenant, '', '', '', false);
        }

        // TODO shift dates settings (see mockups).

        $this->add_buttons(null, false);

        $this->set_data(['tenantid' => $originaltenantid, 'importer' => $originalimporter ? get_class($originalimporter) : '']);
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
        if (empty($data['importer'])) {
            $errors['importers'] = get_string('required');
        }
        return $errors;
    }

    /**
     * Add action buttons cancel/next
     *
     * @param string|null $nextlabel
     * @param bool $hasbackbutton
     */
    protected function add_buttons(?string $nextlabel = null, bool $hasbackbutton = true) {
        $mform = $this->_form;

        $buttonarray = array();
        $buttonarray[] = $mform->createElement('cancel');
        if ($this->get_import_manager()->get_importers()) {
            $nextlabel = $nextlabel ?: get_string('next');
            $buttonarray[] = $mform->createElement('submit', 'submitbutton', $nextlabel);
        }
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Is it possible to select "no tenant" as the destination
     *
     * @return bool
     */
    protected function is_no_tenant_destination_available() {
        if (!\tool_tenant\permission::can_switch_tenant()) {
            return false;
        }
        foreach ($this->get_import_manager()->get_importers() as $importer) {
            if (!$importer->is_tenant_required()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Process form
     *
     * @return int|mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $settings = array_intersect_key((array)$data, ['importer' => 1, 'tenantid' => 1]);
        if (!empty($data->tenant)) {
            if ($data->tenant === self::DESTINATION_NOTENANT) {
                $settings['tenantid'] = null;
            } else if ($data->tenant === self::DESTINATION_CURRENTTENANT) {
                $settings['tenantid'] = tenancy::get_tenant_id();
            }
        }
        $importmanager = $this->get_import_manager();
        $importmanager->save_general_settings($settings);
        $importmanager->retrieve_and_save_review_data();
        return parent::process_dynamic_submission();
    }

    /**
     * Load in existing data as form defaults. Usually new entry defaults are stored directly in
     * form definition (new entry form); this function is used to load in data where values
     * already exist and data is being edited (edit entry form).
     *
     * @param \stdClass|array $defaultvalues object or array of default values
     */
    public function set_data($defaultvalues) {
        $defaultvalues = (array)$defaultvalues;
        if (!empty($defaultvalues['tenantid'])) {
            $defaultvalues['tenant'] = ($defaultvalues['tenantid'] != tenancy::get_tenant_id()) ?
                self::DESTINATION_SELECTTENANT : self::DESTINATION_CURRENTTENANT;
        }
        parent::set_data($defaultvalues);
    }
}
