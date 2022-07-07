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

namespace tool_program\form;

use core_form\dynamic_form;
use tool_program\api;
use tool_program\constants;
use tool_program\permission;
use tool_program\persistent\program;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

/**
 * Modal form to allocate users into programs.
 *
 * @package   tool_program
 * @copyright 2021 Moodle Pty Ltd <support@moodle.com>
 * @author    2021 David Matamoros <davidmc@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class programs_selector_form_modal extends dynamic_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;

        foreach ($this->_ajaxformdata['userids'] as $i => $userid) {
            $mform->addElement('hidden', "userids[$i]", clean_param($userid, PARAM_INT));
            $mform->setType("userids[$i]", PARAM_INT);
        }

        // Program select (autocomplete) field.
        $selectprogramstr = get_string('selectprogramstoallocate', 'tool_program');
        $missingprogramstr = get_string('missingprogram', 'tool_program');
        $options = [
            'multiple' => true,
            'class'    => 'select_program_field'
        ];
        $mform->addElement('autocomplete', 'programids', $selectprogramstr, $this->get_programs_list(), $options);
        $mform->addRule('programids', $missingprogramstr, 'required', null, 'client');
        $mform->addHelpButton('programids', 'selectprogramstoallocate', 'tool_program');
        $mform->setType('programids', PARAM_INT);

        // Status.
        $choices = [
            constants::STATUS_OVERRIDE_DEFAULT => get_string('active'),
            constants::STATUS_OVERRIDE_SUSPENDED => get_string('suspended'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'tool_program'), $choices);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'status', 'tool_program');
    }

    /**
     * Returns a list with all active programs for the given users
     *
     * @return array
     */
    private function get_programs_list(): array {
        $programslist = [];
        $tenantids = array_unique(array_values(tenancy::get_tenant_ids_bulk($this->get_userids())));
        $sqls = [];
        $params = [];
        foreach ($tenantids as $tenantid) {
            [$sqlt, $paramst] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1', $tenantid);
            $sqls[] = "($sqlt)";
            $params = $params + $paramst;
        }

        $params['archived'] = 0;
        $programs = program::get_records_select("(" . join(' AND ', $sqls) . ") " .
            ' AND archived = :archived', $params, 'fullname');
        foreach ($programs as $program) {
            $programslist[$program->get('id')] = $program->get_formatted_name();
        }

        return $programslist;
    }

    /**
     * Get users ids.
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_userids(): array {
        return array_map(function($userid) {
            return clean_param($userid, PARAM_INT);
        }, array_values($this->_ajaxformdata['userids']));
    }

    /**
     * Require capabilities.
     */
    public function check_access_for_dynamic_submission(): void {
        if (!permission::can_allocate_anybody_as_organisation_manager() && !permission::has_allocateuser_capability()) {
            throw new \moodle_exception('errorcantallocateusers', 'tool_program');
        }
    }

    /**
     * Process data submited by the form.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $successcount = 0;
        $skippedcount = 0;

        foreach ($data->programids as $programid) {
            $program = new program($programid);
            foreach ($data->userids as $userid) {
                if (permission::can_allocate_user($program, $userid, true, true, false)) {
                    $programuserdata = (object) [
                        'userid' => $userid,
                        'allocationtype' => constants::ALLOCATION_MANUAL,
                        'certificationid' => 0,
                        'status' => $data->status
                    ];
                    api::allocate_user($program, $programuserdata);
                    $successcount++;
                } else {
                    $skippedcount++;
                }
            }
        }

        return [
            'successcount' => $successcount,
            'skippedcount' => $skippedcount,
        ];
    }

    /**
     * Set data
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data($this->_ajaxformdata);
    }

    /**
     * Returns context where this form is used
     *
     * @return \context
     */
    public function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/admin/tool/tenant/index.php', [
            'form' => get_class($this),
        ]);
    }
}
