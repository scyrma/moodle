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
 * Class tool_certification\certifications_selector_form_modal
 *
 * @package    tool_certification
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

use core_form\dynamic_form;
use tool_tenant\hierarchy;
use tool_tenant\tenancy;

/**
 * Class certifications_selector_form_modal
 *
 * @package    tool_certification
 * @author     2021 David Matamoros <davidmc@moodle.com>
 * @copyright  2021 Moodle Pty Ltd <support@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class certifications_selector_form_modal extends dynamic_form {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->setDisableShortforms();
        // Add empty header for consistency.
        $mform->addElement('header', 'hdr', '');

        foreach ($this->_ajaxformdata['userids'] as $i => $userid) {
            $mform->addElement('hidden', "userids[$i]", clean_param($userid, PARAM_INT));
            $mform->setType("userids[$i]", PARAM_INT);
        }

        $options = [
            'multiple' => true,
            'class'    => 'select_certification'
        ];
        $selectstr = get_string('selectcertificationstoallocate', 'tool_certification');
        $missingcertstr = get_string('missingcertification', 'tool_certification');
        $mform->addElement('autocomplete', 'certificationids', $selectstr, $this->get_certifications_list(), $options);
        $mform->addRule('certificationids', $missingcertstr, 'required', null, 'client');
        $mform->addHelpButton('certificationids', 'selectcertificationstoallocate', 'tool_certification');
        $mform->setType('certificationids', PARAM_INT);

        // Status.
        $choices = [
            constants::STATUS_OVERRIDE_DEFAULT => get_string('active'),
            constants::STATUS_OVERRIDE_SUSPENDED => get_string('suspended'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'tool_certification'), $choices);
        $mform->setDefault('status', constants::STATUS_OVERRIDE_DEFAULT);
        $mform->addHelpButton('status', 'userstatus', 'tool_certification');
    }

    /**
     * Returns a list with all active certifications for the given users
     *
     * @return array
     */
    private function get_certifications_list(): array {
        $certificationslist = [];
        $tenantids = array_unique(array_values(tenancy::get_tenant_ids_bulk($this->get_userids())));
        $sqls = [];
        $params = [];
        foreach ($tenantids as $tenantid) {
            [$sqlt, $paramst] = hierarchy::filter_own_or_parent_shared_entities_sql('tenantid', 'shared=1', $tenantid);
            $sqls[] = "($sqlt)";
            $params = $params + $paramst;
        }

        $params['archived'] = 0;
        $certifications = certification::get_records_select("(" . join(' AND ', $sqls) . ") " .
            ' AND archived = :archived', $params, 'fullname');
        foreach ($certifications as $certification) {
            $certificationslist[$certification->get('id')] = $certification->get_formatted_name();
        }

        return $certificationslist;
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

        foreach ($data->certificationids as $certificationid) {
            $certification = new certification($certificationid);
            foreach ($data->userids as $userid) {
                if (permission::can_allocate_user($certification, $userid, true, false)) {
                    $certuserdata = (object) [
                        'userid' => $userid,
                        'allocationtype' => constants::ALLOCATION_MANUAL,
                        'status' => $data->status
                    ];
                    api::allocate_user($certification, $certuserdata);
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
     * Sets data for form.
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
