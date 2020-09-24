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
 * Information about one import or form to prepare one import
 *
 * Returns data for template tool_wp/import
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use renderer_base;
use stdClass;
use tool_wp\external\import_details_exporter;
use tool_wp\local\exportimport\forms\import_conflict_form;
use tool_wp\local\exportimport\forms\import_general_form;
use tool_wp\local\exportimport\forms\import_report_form;
use tool_wp\local\exportimport\forms\import_review_form;
use tool_wp\local\exportimport\forms\import_settings_form;
use tool_wp\local\exportimport\forms\import_upload_form;

defined('MOODLE_INTERNAL') || die();

/**
 * Information about one import or form to prepare one import
 *
 * Returns data for template tool_wp/import
 *
 * @package   tool_wp
 * @copyright 2020 Moodle Pty Ltd <support@moodle.com>
 * @author    2020 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import implements \templatable {

    /** @var import_manager */
    protected $importmanager;
    /** @var array */
    protected $data;
    /** @var int */
    protected $stage = null;

    /**
     * Constructor.
     *
     * @param import_manager|null $importmanager
     * @param array $data
     * @param int|null $stage stage of the import, only if known, otherwise it will be calculated
     */
    public function __construct(?import_manager $importmanager, array $data = [], ?int $stage = null) {
        $this->importmanager = $importmanager;
        $this->data = $data;
        $this->stage = $stage;
    }

    /**
     * Function to import the renderer data for mustache template
     *
     * Returns data for template tool_wp/import
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return stdClass|array
     */
    public function export_for_template(\renderer_base $output) {

        $stage = $this->stage ?? $this->calculate_stage();
        $rv = [
            'stage' . $stage => 1,
            'stageindicator' => $this->display_stage_indicator($stage),
            'mainurl' => (new \moodle_url('/admin/tool/wp/exportimport.php', [], 'imports'))->out(false)
        ];

        if ($stage > 1) {
            $importmanager = $this->get_import_manager();
            $rv['importid'] = $importmanager->get_import_id();
            $url = import_manager::get_import_file_url($importmanager->get_import_id());
            $rv['downloadurl'] = $url ? $url->out(false) : '';
            $formdata = ['importid' => $importmanager->get_import_id(), 'stage' => $stage];
            $customdata = ['importmanager' => $importmanager];
            if ($stage == 6) {
                $import = new import_persistent($importmanager->get_import_id());
                $detailsexporter = new import_details_exporter($import, ['context' => \context_system::instance()]);
                $rv['import'] = $detailsexporter->export($output);
                $rv['log'] = $importmanager->get_import_logs();
            } else if ($stage == 5) {
                $rv['stageindicator'] = $this->display_stage_indicator(5, $importmanager->has_conflicts());
                $form = new import_review_form(null, $customdata, 'post', '', ['class' => 'import-review-form'], true, $formdata);
            } else if ($stage == 4) {
                $form = new import_conflict_form(null, $customdata, 'post', '', [], true, $formdata);
            } else if ($stage == 3) {
                $form = new import_settings_form(null, $customdata, 'post', '', [], true, $formdata);
            } else if ($stage == 2) {
                $form = new import_general_form(null, $customdata, 'post', '', [], true, $formdata);
            }
        } else {
            $form = new import_upload_form(null, null, 'post', '', [], true, $this->data);
        }

        if (isset($form)) {
            $form->set_data_for_modal();
            $rv['form'] = $form->render();
            $rv['formclass'] = get_class($form);
        }

        return $rv;
    }

    /**
     * Get import manager
     *
     * @return null|import_manager
     */
    private function get_import_manager(): ?import_manager {
        return $this->importmanager;
    }

    /**
     * Get current stage
     *
     * 1: Upload a file / select previous export
     * 2: Select importer
     * 3: Import settings
     * 4: Conflict resolution (may be skipped if there are no conflicts)
     * 5: Review (not on mock-ups but stage number is reserved)
     * 6: View import that was already prepared (either scheduled or completed or errored)
     *
     * @return int
     */
    protected function calculate_stage() {
        if (!$importmanager = $this->get_import_manager()) {
            // Import was not yet created (this means we don't have a file).
            return 1;
        }
        if ($importmanager->is_prepared()) {
            // Import is already prepared, no longer possible to go to any other stage except for 6 (view import details).
            return 6;
        }

        $forcestage = !empty($this->data['forcestage']) ? clean_param($this->data['forcestage'], PARAM_INT) : 0;
        $maxstage = max($forcestage, 2);

        // Even when 'forcestage' is passed, make sure we don't jump ahead and user has actually filled all forms.
        if ($forcestage > 2 && !$importmanager->has_general_settings()) {
            $maxstage = 2;
        } else if ($forcestage > 3 && !$importmanager->has_settings()) {
            $maxstage = 3;
        } else if ($forcestage > 4 && $importmanager->has_conflicts() && !$importmanager->has_conflicts_settings()) {
            $maxstage = 4;
        } else if ($forcestage > 5) {
            $maxstage = 5;
        }
        return $forcestage ? min($maxstage, max($forcestage, 2)) : $maxstage;
    }

    /**
     * Generate stage indicator HTML.
     *
     * @param int $currentstage
     * @param bool $hasconflicts
     * @return string
     */
    private function display_stage_indicator(int $currentstage, bool $hasconflicts = false): string {
        $steptitlelangs = [
            1 => 'importselectsource',
            2 => 'importgeneralsettings',
            3 => 'importoptions',
            4 => 'importconflicts',
            5 => 'importreview'
        ];

        $content = \html_writer::start_div('stage-indicator d-flex');
        for ($stage = 1; $stage <= 5; $stage++) {
            $class = $stage < $currentstage ? 'stage-done' : ($stage == $currentstage ? 'stage-active' : '');
            if ($currentstage == 5 && $stage == 4 && !$hasconflicts) {
                $class .= ' text-gray070';
                $title = get_string('noconflictsfound', 'tool_wp');
            } else {
                $title = get_string($steptitlelangs[$stage], 'tool_wp');
            }
            $content .= \html_writer::div($stage, 'stage ' . $class, ['title' => $title]);
        }
        $content .= \html_writer::end_div();

        return $content;
    }
}
