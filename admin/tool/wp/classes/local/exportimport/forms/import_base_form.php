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
 * Class import_base_form
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport\forms;

use tool_wp\local\exportimport\import_manager;
use tool_wp\permission;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for all import forms
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
abstract class import_base_form extends export_import_base_form {
    /** @var import_manager */
    protected $importmanager;

    /**
     * Attributes for the 'prev' button. They are passed to the tool_wp_import WS to get the previous form
     *
     * @return array
     */
    protected function prev_button_attributes(): array {
        return [
            'data-importid' => $this->optional_param('importid', 0, PARAM_INT),
            'data-forcestage' => $this->stage - 1,
        ];
    }

    /**
     * Helper method to return import manager
     *
     * @return import_manager
     * @throws \coding_exception
     */
    protected function get_import_manager() {
        if (!$this->importmanager && !empty($this->_customdata['importmanager'])) {
            $this->importmanager = $this->_customdata['importmanager'];
        } else if (!$this->importmanager) {
            $importid = $this->optional_param('importid', 0, PARAM_INT);
            if (!$importid) {
                throw new \coding_exception('Missing import id');
            }
            $this->importmanager = new import_manager($importid);
        }
        return $this->importmanager;
    }

    /**
     * Checks if current user has access to this form, otherwise throws exception
     *
     * Sometimes permission check may depend on the action and/or id of the entity.
     * If necessary, form data is available in $this->_ajaxformdata or
     * by calling $this->optional_param()
     *
     * Example:
     *     require_capability('dosomething', $this->get_context_for_dynamic_submission());
     */
    protected function check_access_for_dynamic_submission(): void {
        permission::require_can_use_export_import();
        if (($importmanager = $this->get_import_manager()) && !$importmanager->is_same_user()) {
            // This can only happen if the form input was substituted.
            throw new \moodle_exception('Importer not available');
        }
    }

    /**
     * Number of the next stage
     *
     * @return int
     */
    protected function next_stage(): int {
        return $this->stage + 1;
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return array
     */
    public function process_dynamic_submission() {
        global $OUTPUT, $PAGE;
        $OUTPUT->header();
        $PAGE->start_collecting_javascript_requirements();
        $tabsoutput = new \tool_wp\local\exportimport\import($this->importmanager, [], $this->next_stage());
        $content = $tabsoutput->export_for_template($OUTPUT);
        $jsfooter = $PAGE->requires->get_end_code();
        return [
            'importid' => $this->importmanager->get_import_id(),
            'forcestage' => $this->stage + 1,
            'content' => $content,
            'javascript' => $jsfooter
        ];
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * This is used in the form elements sensitive to the page url, such as Atto autosave in 'editor'
     *
     * If the form has arguments (such as 'id' of the element being edited), the URL should
     * also have respective argument.
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/wp/exportimport.php',
            ['action' => 'newimport', 'importid' => $this->optional_param('importid', 0, PARAM_INT),
                'stage' => $this->stage]);
    }
}
