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
 * Class export_review_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use tool_wp\exporter_base;
use tool_wp\local\exportimport\export_manager;
use tool_wp\local\exportimport\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Stage 4: Review of an export
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export_review_form extends export_base_form {
    /** @var int */
    protected $stage = 3;

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

        $mform->addElement('hidden', 'exporterdata');
        $mform->setType('exporterdata', PARAM_RAW);

        $mform->addElement('header', 'aboutthefile', get_string('aboutthefile', 'tool_program'));
        $mform->setExpanded('aboutthefile');

        // Call callback from the exporter and ask to display settings.
        $exporter = $this->get_exporter();
        $instances = export_manager::get_instances_for_review_form($exporter);
        $mform->addElement('static', 'reviewdata', '',
            $exporter->get_summary_for_review_step(false) .
            helper::display_instances_list($instances));

        $this->add_buttons(get_string('doexport', 'tool_wp'), true, !empty($instances));
    }

    /**
     * Attributes for the 'prev' button. They are passed to the tool_wp_export WS to get the previous form
     *
     * @return array
     */
    protected function prev_button_attributes(): array {
        return parent::prev_button_attributes() +
            ['data-exporterdata' => $this->get_exporter_data_as_string()];
    }

    /**
     * Add action buttons back/cancel/next
     *
     * @param string|null $nextlabel
     * @param bool $hasbackbutton
     * @param bool $hasinstances
     */
    protected function add_buttons(?string $nextlabel = null, bool $hasbackbutton = true, bool $hasinstances = true) {
        $mform = $this->_form;

        $buttonarray = array();
        $previouslabel = get_string('previous');
        $nextlabel = $nextlabel ?: get_string('next');
        $buttonarray[] = $previous = $mform->createElement('submit', 'prevbutton', $previouslabel,
            $this->prev_button_attributes(), false, ['customclassoverride' => 'btn-outline-secondary']);
        $buttonarray[] = $mform->createElement('cancel');
        $attributes = !$hasinstances ? ['disabled', 'title' => get_string('nothingtoexport', 'tool_wp')] : [];
        $buttonarray[] = $mform->createElement('submit', 'exportbutton', $nextlabel, $attributes);
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Process form
     *
     * @return int|mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $exportdata = [
                'entrypoint' => $data->entrypoint,
                'entrypointid' => $data->entrypointid,
                'exporter' => $data->exporter ?? '',
                'exportertenant' => $data->exportertenant ?? 0,
            ] + $this->get_exporter_data();
        $exportid = export_manager::schedule_export($exportdata);
        return ['exportid' => $exportid];
    }

    /**
     * Get exporterdata property as it is
     *
     * @return string
     */
    protected function get_exporter_data_as_string(): string {
        return $this->optional_param('exporterdata', '', PARAM_RAW);
    }

    /**
     * Get exporter data as an array
     *
     * @return array
     */
    protected function get_exporter_data(): array {
        $exporterdata = $this->get_exporter_data_as_string();
        if (strlen($exporterdata)) {
            return unserialize_array(base64_decode($exporterdata)) ?: [];
        }
        return [];
    }

    /**
     * Get current exporter
     *
     * @return exporter_base
     * @throws \coding_exception
     */
    protected function get_exporter(): exporter_base {
        if (!$this->exporter) {
            $exporterclass = $this->optional_param('exporter', null, PARAM_RAW_TRIMMED);
            $exportertenant = $this->optional_param('exportertenant', 0, PARAM_INT);
            $entrypoint = $this->optional_param('entrypoint', null, PARAM_ALPHANUMEXT);
            $entrypointid = $this->optional_param('entrypointid', 0, PARAM_INT);
            $this->exporter = export_manager::create_exporter($exporterclass, $entrypoint, $entrypointid,
                $this->get_exporter_data(), $exportertenant);
        }
        return $this->exporter;
    }
}
