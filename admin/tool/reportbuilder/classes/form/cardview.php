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

namespace tool_reportbuilder\form;

use coding_exception;
use context;
use context_system;
use core_form\dynamic_form;
use HTML_QuickForm_Error;
use moodle_url;
use tool_reportbuilder\manager;
use tool_reportbuilder\permission;
use tool_reportbuilder\reportbuilder;
use tool_reportbuilder\reportbuilder_column;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Class cardview settings
 *
 * @package   tool_reportbuilder
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cardview extends dynamic_form {

    /**
     * Form definition
     * @throws HTML_QuickForm_Error
     * @throws coding_exception
     */
    public function definition(): void {
        $this->set_display_vertical();

        $mform = $this->_form;

        $reportid = (int) $this->_ajaxformdata['reportid'];
        $totalcolumns = reportbuilder_column::count_records(['reportid' => $reportid]);
        $visibilityarray = [];
        for ($i = 1; $i <= $totalcolumns; $i++) {
            $visibilityarray[$i] = $i;
        }

        $mform->addElement('hidden', 'reportid');
        $mform->setType('reportid', PARAM_INT);

        $group = [];
        $group[] =& $mform->createElement('select', 'visibility', '', $visibilityarray);
        $mform->setType('visibility', PARAM_INT);
        $mform->addGroup($group, 'visibilityformgroup', get_string('contentvisibility', 'tool_reportbuilder'), ' ', false);

        $group = [];
        $group[] =& $mform->createElement('selectyesno', 'showtitle', '');
        $mform->setDefault('showtitle', 0);
        $mform->setType('showtitle', PARAM_BOOL);
        $mform->addGroup($group, 'titleformgroup', get_string('showfirstcolumntitle', 'tool_reportbuilder'), ' ', false);

        $mform->disable_form_change_checker();

        $this->add_action_buttons(false);
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     */
    public function check_access_for_dynamic_submission(): void {
        $report = manager::get_report((int) $this->_ajaxformdata['reportid']);
        permission::require_can_edit($report);
    }

    /**
     * Store the conditions values and operators
     *
     * @return mixed|void
     * @throws coding_exception
     * @throws \core\invalid_persistent_exception
     */
    public function process_dynamic_submission() {
        $values = $this->get_data();

        $settings = [
            'showtitle' => (int)$values->showtitle,
            'visibility' => max((int)$values->visibility, 1)
        ];
        $reportpersistent = new reportbuilder((int)$values->reportid);
        $cardviewsettings = json_encode($settings);
        $reportpersistent->set('cardviewsettings', $cardviewsettings);
        $reportpersistent->update();
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $reportid = (int) $this->_ajaxformdata['reportid'];
        return new moodle_url('/admin/tool/reportbuilder/manage.php', [
            'form' => get_class($this),
            'reportid' => $reportid,
        ]);
    }
}
