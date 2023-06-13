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
 * File for class edit_certification_recertification_form.
 *
 * @package   tool_certification
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_certification;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot. '/'.$CFG->admin.'/tool/wp/periodduration.php');

use core_form\dynamic_form;
use html_writer;
use tool_program\persistent\program;

/**
 * Class edit_certification_recertification_form
 *
 * @package   tool_certification
 * @author    2019 David Matamoros <davidmc@moodle.com>
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_certification_recertification_form extends dynamic_form {

    /** @var certification */
    protected $certification;

    /**
     * Current certification
     *
     * @return certification
     */
    protected function get_certification(): certification {
        if (!$this->certification) {
            $this->certification = new certification($this->optional_param('id', 0, PARAM_INT));
        }
        return $this->certification;
    }

    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition() {
        $mform = $this->_form;

        $certification = $this->get_certification();
        $caneditdetails = permission::can_edit_details($certification);

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'expirydatetype', 0);
        $mform->setType('expirydatetype', PARAM_INT);

        // Re-certification Basic details section.
        $mform->addElement('header', 'recertificationhdr', get_string('basicdetails', 'tool_certification'));
        $mform->setExpanded('recertificationhdr');

        // Require recertification.
        $requirerecertstr = get_string('requirerecertification', 'tool_certification');
        $requirerecertification = $mform->addElement('selectyesno', 'requirerecertification', $requirerecertstr, null);
        $mform->addHelpButton('requirerecertification', 'requirerecertification', 'tool_certification');

        if ($caneditdetails) {
            // Expiry date set to never warning.
            $warningstr = get_string('recertexpirydatewarning', 'tool_certification');
            $html = html_writer::tag('div', $warningstr, ['class' => 'alert alert-warning']);
            $group = [];
            $group[] =& $mform->createElement('static', 'recertexpirydatewarning', '', $html);
            $mform->addGroup($group, 'expirydatewarningformgroup', '', ' ', false);
            $mform->hideIf('expirydatewarningformgroup', 'expirydatetype', 'noteq', constants::DATE_NEVER);
        }

        // Select a different program.
        $selectdiffprogstr = get_string('selectadifferentprogram', 'tool_certification');
        $recertdifferentprogram = $mform->addElement('selectyesno', 'recertdifferentprogram', $selectdiffprogstr, null);
        $mform->addHelpButton('recertdifferentprogram', 'recertdifferentprogram', 'tool_certification');
        $mform->hideIf('recertdifferentprogram', 'requirerecertification', 'noteq', 1);

        // Re-certification program.
        $params = $this->get_recertification_program();
        $selectprogramstr = get_string('selectprogram', 'tool_certification');
        $options = array(
            'ajax' => 'tool_program/form_potential_program_selector',
            'multiple' => false,
            'class' => 'select_recertification_program_field',
            'data-exclude' => $certification->get_certification_program()->get('id'),
        );
        $recertificationprogram = $mform->addElement('autocomplete', 'recertificationprogram',
            $selectprogramstr, $params, $options);
        $mform->addHelpButton('recertificationprogram', 'recertificationprogram', 'tool_certification');
        $mform->hideIf('recertificationprogram', 'recertdifferentprogram', 'noteq', 1);
        $mform->hideIf('recertificationprogram', 'requirerecertification', 'noteq', 1);

        $startdatestr = get_string('startdate', 'tool_certification');
        $duedatestr = get_string('duedate', 'tool_certification');
        $expirydatestr = get_string('expirydate', 'tool_certification');

        // Start date.
        $beforeprevcertexpirydate = get_string('beforepreviouscertexpdate', 'tool_certification');
        $group = [];
        $group[] =& $mform->createElement('periodduration', 'recertstartdaterelative', '', null);
        $group[] =& $mform->createElement('html', html_writer::tag('span', $beforeprevcertexpirydate));
        $startdategroup = $mform->addGroup($group, 'startdateformgroup', $startdatestr, ' ', false);
        $mform->addHelpButton('startdateformgroup', 'recertstartdaterelative', 'tool_certification');
        $mform->hideIf('startdateformgroup', 'requirerecertification', 'noteq', 1);

        // Start date warning.
        $warningstr = get_string('recertstartdatewarning', 'tool_certification');
        $html = html_writer::tag('div', $warningstr, ['class' => 'alert alert-warning']);
        $group = [];
        $group[] =& $mform->createElement('static', 'recertstartdatewarning', '', $html);
        $mform->addGroup($group, 'startdatewarningformgroup', '', ' ', false);
        $mform->hideIf('startdatewarningformgroup', 'requirerecertification', 'noteq', 1);

        // Due date.
        $prevcertexpdatestr = get_string('previouscertexpirydate', 'tool_certification');
        $group = [];
        $group[] =& $mform->createElement('static', 'recertduedate', $duedatestr, $prevcertexpdatestr, null, null);
        $mform->addGroup($group, 'recertduedateformgroup', $duedatestr, ' ', false);
        $mform->addHelpButton('recertduedateformgroup', 'recertduedaterelative', 'tool_certification');
        $mform->hideIf('recertduedateformgroup', 'requirerecertification', 'noteq', 1);

        // Grace period.
        $afterpreviouscertexpdate = get_string('afterpreviouscertexpdate', 'tool_certification');
        $recertgraceperiodstr = get_string('recertgraceperiod', 'tool_certification');
        $group = [];
        $group[] =& $mform->createElement('periodduration', 'recertgraceperiod', '', null);
        $group[] =& $mform->createElement('html', html_writer::tag('span', $afterpreviouscertexpdate));
        $graceperiodgroup = $mform->addGroup($group, 'recertgraceperiodformgroup', $recertgraceperiodstr, ' ', false);
        $mform->addHelpButton('recertgraceperiodformgroup', 'recertgraceperiod', 'tool_certification');
        $mform->hideIf('recertgraceperiodformgroup', 'requirerecertification', 'noteq', 1);
        $mform->hideIf('recertgraceperiodformgroup', 'recertdifferentprogram', 'noteq', 1);

        // Expiry date.
        $group = [];
        $recertneverstr = get_string('never', 'tool_certification');
        $recertafterprevcompl = get_string('afteractualcertcompletion', 'tool_certification');
        $recertafterprevexp = get_string('afterpreviouscertexpdate', 'tool_certification');
        $recertafterlatest = get_string('afterlatest', 'tool_certification');
        $choices = [
            constants::RECERT_EXPIRY_DATE_NEVER_DATE => $recertneverstr,
            constants::RECERT_EXPIRY_DATE_AFTR_PREV_COMPL => $recertafterprevcompl,
            constants::RECERT_EXPIRY_DATE_AFTR_PREV_EXP => $recertafterprevexp,
            constants::RECERT_EXPIRY_DATE_AFTR_LATEST => $recertafterlatest,
        ];
        $group[] =& $mform->createElement('select', 'recertexpirydatetype', $expirydatestr, $choices);
        $group[] =& $mform->createElement('periodduration', 'recertexpirydaterelative', '', null, null);
        $expirydategroup = $mform->addGroup($group, 'expirydateformgroup', $expirydatestr, ' ', false);
        $mform->hideIf('recertexpirydaterelative', 'recertexpirydatetype', 'eq', constants::RECERT_EXPIRY_DATE_NEVER_DATE);
        $mform->addHelpButton('expirydateformgroup', 'recertexpirydate', 'tool_certification');
        $mform->hideIf('expirydateformgroup', 'requirerecertification', 'noteq', 1);

        // If user has no edit permission disable form elements.
        if (!$caneditdetails) {
            $requirerecertification->freeze();
            $recertdifferentprogram->freeze();
            $recertificationprogram->freeze();
            $startdategroup->freeze();
            $graceperiodgroup->freeze();
            $expirydategroup->freeze();
        } else {
            $this->add_action_buttons(false);
        }
    }

    /**
     * Require access.
     */
    public function check_access_for_dynamic_submission(): void {
        permission::require_can_view_details($this->get_certification());
    }

    /**
     * Process data.
     *
     * @return int|mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        if (permission::can_edit_details($this->get_certification())) {
            return api::update_certification_recertification($data);
        }
        return false;
    }

    /**
     * Sets data for form.
     */
    public function set_data_for_dynamic_submission(): void {
        // Ajaxformdata comes from attributes.
        $id = $this->optional_param('id', 0, PARAM_INT);
        if ($id) {
            $certification = new certification($id);
            $certificationdata = $certification->to_record();
            $certificationdata->certification_tags = \core_tag_tag::get_item_tags_array(
                'tool_certification', 'tool_certification', $certificationdata->id);
        } else {
            $certification = new certification();
            $certificationdata = $certification->to_record();
            $certificationdata->program = null;
        }
        $this->set_data($certificationdata);
    }

    /**
     * Returns associated recertification program ID and fullname
     *
     * @return array
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    private function get_recertification_program(): array {
        $certification = $this->get_certification();
        $programid = $certification->get('recertificationprogram');
        try {
            $program = new program($programid);
        } catch (\dml_missing_record_exception $ex) {
            throw new \moodle_exception('errormissingassociatedprogram', 'tool_certification');
        }
        $programname = format_string($program->get('fullname'));
        return [$programid => $programname];
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files): array {
        $errors = [];

        $certification = new certification($data['id']);
        if ((int)$certification->get('program') === (int)$data['recertificationprogram']
            && (int)$data['recertdifferentprogram'] === 1
            && (int)$data['requirerecertification'] === 1) {
            $errors['recertificationprogram'] = get_string('errorrecertificationprogram', 'tool_certification');
        }

        return $errors;
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
            'id' => $this->optional_param('id', 0, PARAM_INT),
        ]);
    }

}
