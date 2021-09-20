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
 * Class create_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_reportbuilder\form;

use core_form\dynamic_form;
use tool_reportbuilder\constants;
use tool_reportbuilder\helper;
use tool_reportbuilder\manager;
use tool_reportbuilder\report_base;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->libdir/formslib.php");

/**
 * Class create_report
 *
 * @package   tool_reportbuilder
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class detail extends dynamic_form {

    /** @var report_base */
    protected $report = null;

    /**
     * Current report
     *
     * @return report_base
     */
    protected function get_report(): ?report_base {
        if (!$this->report && !empty($this->_ajaxformdata['id'])) {
            $this->report = manager::get_report((int)$this->_ajaxformdata['id']);
        }
        return $this->report;
    }

    /**
     * Form definition
     *
     * @throws \HTML_QuickForm_Error
     * @throws \coding_exception
     */
    public function definition() {

        $mform = $this->_form;
        $report = $this->get_report();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('reportname', 'tool_reportbuilder'), ['size' => 30]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 1333), 'maxlength', 1333);
        $mform->addHelpButton('name', 'reportname', 'tool_reportbuilder');

        $mform->addElement('textarea', 'description', get_string('description', 'tool_reportbuilder'),
            array('rows' => 8, 'cols' => 120, 'class' => 'invisible position-absolute'));
        $mform->addHelpButton('description', 'description', 'tool_reportbuilder');

        if (!$report) {
            $sources = helper::get_sources(true);

            $mform->addElement('selectgroups', 'source', get_string('reportsource', 'tool_reportbuilder'), $sources);
            $mform->addRule('source', get_string('error:mustselectsource', 'tool_reportbuilder'), 'required', null, 'client');
            $mform->addHelpButton('source', 'reportsource', 'tool_reportbuilder');

            $mform->addElement('advcheckbox', 'adddefault', get_string('adddefault', 'tool_reportbuilder'));
            $mform->setDefault('adddefault', 1);
            $mform->addHelpButton('adddefault', 'adddefault', 'tool_reportbuilder');
        } else {
            $mform->addElement('static', 'sourcename',
                get_string('reportsource', 'tool_reportbuilder'), $report->get_name());
        }

        $hassubtenants = $report ? hierarchy::has_subtenants($report->get_tenant_id()) :
            hierarchy::has_subtenants(tenancy::get_tenant_id());
        if ($hassubtenants) {
            $mform->addElement('advcheckbox', 'shared', get_string('availableinalltenants', 'tool_reportbuilder'));
            $mform->setDefault('shared', 1);
            $mform->addHelpButton('shared', 'availableinalltenants', 'tool_reportbuilder');
        }

        $buttontext = get_string('saveandcontinue', 'tool_reportbuilder');
    }

    /**
     * Process the form submission
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        if ($data->id) {
            $reportid = manager::update_report($data);
        } else {
            $data->type = constants::TYPE_DATASOURCE;
            $reportid = manager::save_report($data)->get('id');
        }

        return (new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $reportid]))->out(false);
    }

    /**
     * Check if current user has access to this form, otherwise throw exception
     *
     * Sometimes permission check may depend on the action and/or id of the entity.
     * If necessary, form data is available in $this->_ajaxformdata
     */
    protected function check_access_for_dynamic_submission(): void {
        $report = $this->get_report();

        if (!$report) {
            \tool_reportbuilder\permission::require_can_create();
        } else {
            \tool_reportbuilder\permission::require_can_edit($report);
        }
    }

    /**
     * Load in existing data as form defaults
     *
     * Can be overridden to retrieve existing values from db by entity id and also
     * to preprocess editor and filemanager elements
     */
    public function set_data_for_dynamic_submission(): void {
        if ($report = $this->get_report()) {
            $this->set_data($report->get_persistent()->to_record());
        }
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    public function get_page_url_for_dynamic_submission(): \moodle_url {
        $id = $this->optional_param('id', 0, PARAM_INT);
        return new \moodle_url('/admin/tool/reportbuilder/manage.php', ['id' => $id, 'entity' => 'report']);
    }

    /**
     * Returns context where this form is used
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }
}
