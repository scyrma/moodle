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

namespace tool_wp\local\exportimport;

use renderer_base;
use tool_wp\external\export_details_exporter;
use tool_wp\local\exportimport\forms\export_report_form;
use tool_wp\local\exportimport\forms\export_review_form;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\exporter_selector_form;
use stdClass;

/**
 * Information about one export or form to prepare one export
 *
 * Returns data for template tool_wp/export
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class export implements \templatable {

    /** @var int */
    protected $exportid;
    /** @var array */
    protected $data;

    /**
     * export_tab constructor.
     *
     * @param int $exportid
     * @param array $data
     */
    public function __construct(int $exportid = 0, array $data = []) {
        $this->exportid = $exportid;
        $this->data = $data;
    }

    /**
     * Function to export the renderer data for mustache template
     *
     * Returns data for template tool_wp/export
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(\renderer_base $output) {
        $stage = $this->get_stage();
        $rv = [
            'stage' . $stage => 1,
            'stageindicator' => $this->display_stage_indicator($stage),
            'mainurl' => (new \moodle_url('/admin/tool/wp/exportimport.php'))->out(false)
        ];
        $formdata = array_diff_key($this->data, ['forcestage' => 1]);
        if ($stage == 4) {
            $exportmanager = $this->get_export_manager();
            $export = new export_persistent($exportmanager->get_export_id());
            $detailsexporter = new export_details_exporter($export, ['context' => \context_system::instance()]);
            $rv['export'] = $detailsexporter->export($output);
            $url = export_manager::get_export_file_url($exportmanager->get_export_id());
            $rv['downloadurl'] = $url ? $url->out(false) : '';
            $rv['importfromfileurl'] = new \moodle_url(
                '/admin/tool/wp/import.php',
                ['exportid' => $exportmanager->get_export_id()]
            );
        } else if ($stage == 3) {
            $form = new export_review_form(null, null, 'post', '', ['class' => 'export-review-form'], true, $formdata);
        } else if ($stage == 2) {
            $form = new export_settings_form(null, null, 'post', '', [], true, $formdata);
        } else {
            $form = new exporter_selector_form(null, null, 'post', '', [], true, $formdata);
        }

        if (isset($form)) {
            $form->set_data_for_dynamic_submission();
            $rv['form'] = $form->render();
            $rv['formclass'] = get_class($form);
        }

        return $rv;
    }

    /**
     * Get export manager
     *
     * @return null|export_manager
     */
    private function get_export_manager(): ?export_manager {
        if (!empty($this->exportid)) {
            return new export_manager($this->exportid);
        }
        return null;
    }

    /**
     * Get exporter
     *
     * @return null|exporter_base
     */
    private function get_exporter(): ?exporter_base {
        if (empty($this->data['exporter'])) {
            return null;
        }
        return helper::get_available_exporter($this->data['exporter'], $this->get_entry_point(), $this->get_entry_point_id());
    }

    /**
     * Get entry point
     *
     * @return string
     */
    private function get_entry_point(): string {
        return !empty($this->data['entrypoint']) ? clean_param($this->data['entrypoint'], PARAM_ALPHANUMEXT) : '';
    }

    /**
     * Get entry point id
     *
     * @return int
     */
    private function get_entry_point_id(): int {
        return !empty($this->data['entrypointid']) ? clean_param($this->data['entrypointid'], PARAM_INT) : 0;
    }

    /**
     * Get current stage
     *
     * 1: Select exporter
     * 2: Form with export settings
     * 3: Review export settings (not implemented atm)
     * 4: View export that was already prepared (either scheduled or completed or errored)
     *
     * @return int
     */
    private function get_stage(): int {
        if (!empty($this->exportid)) {
            // Export is already prepared, no longer possible to go to any other stage except for 4 (view export details).
            return 4;
        }
        if (!$this->get_exporter()) {
            // Exporter is not selected, always go to the first stage (selecting exporter).
            return 1;
        }

        $forcestage = !empty($this->data['forcestage']) ? clean_param($this->data['forcestage'], PARAM_INT) : 0;
        $maxstage = 3;
        return $forcestage ? min($maxstage, max($forcestage, 1)) : $maxstage;
    }

    /**
     * Generate stage indicator HTML.
     *
     * @param int $currentstage
     * @return string
     */
    private function display_stage_indicator(int $currentstage): string {
        $steptitlelangs = [
            1 => 'exportgeneralsettings',
            2 => 'exportoptions',
            3 => 'exportreview',
        ];

        $content = \html_writer::start_div('stage-indicator d-flex');
        for ($stage = 1; $stage <= 3; $stage++) {
            $class = $stage < $currentstage ? 'stage-done' : ($stage == $currentstage ? 'stage-active' : '');
            $title = get_string($steptitlelangs[$stage], 'tool_wp');
            $content .= \html_writer::div($stage, 'stage ' . $class, ['title' => $title]);
        }
        $content .= \html_writer::end_div();

        return $content;
    }
}
