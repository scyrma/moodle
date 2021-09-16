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
 * Rule details form.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\form;

defined('MOODLE_INTERNAL') || die();

use tool_dynamicrule\api;
use tool_dynamicrule\permission;
use tool_dynamicrule\rule;
use tool_wp\modal_form;

/**
 * Rule details form class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class rule_details extends modal_form {

    /** @var rule */
    protected $rule;
    /** @var int default match limit */
    protected const DEFAULTMATCHLIMIT = 1;
    /** @var int default match interval */
    protected const DEFAULTMATCHINTERVAL = DAYSECS;

    /**
     * Current rule
     *
     * @return rule
     */
    protected function get_rule(): rule {
        if (!$this->rule) {
            $id = !empty($this->_ajaxformdata['id']) ? $this->_ajaxformdata['id'] : 0;
            $this->rule = $id ? api::get_rule($id) : new rule();
        }
        return $this->rule;
    }

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('rulename', 'tool_dynamicrule'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 400), 'maxlength', 400, 'client');

        $mform->addElement('advcheckbox', 'limitingenabled', get_string('rulematchfreq', 'tool_dynamicrule'),
            get_string('rulematchfreqenable', 'tool_dynamicrule'));
        $mform->addHelpButton('limitingenabled', 'rulematchfreq', 'tool_dynamicrule');

        $restrictionrule = array();
        $restrictionrule[] = $mform->createElement('static', 'desc0', '', get_string('rulematchfreqdesc0', 'tool_dynamicrule'));
        $restrictionrule[] = $mform->createElement('text', 'matchlimit', '', ['size' => '3']);
        $restrictionrule[] = $mform->createElement('static', 'desc1', '', get_string('rulematchfreqdesc1', 'tool_dynamicrule'));
        $duringoptions = [0 => get_string('ever', 'tool_dynamicrule'), 1 => get_string('per', 'tool_dynamicrule')];
        $restrictionrule[] = $mform->createElement('select', 'duringselector', '', $duringoptions);
        $restrictionrule[] = $mform->createElement('duration', 'matchinterval', '', ['defaultunit' => DAYSECS]);
        $mform->addGroup($restrictionrule, 'limitingrule', '', '&nbsp;', false);
        $mform->setType('matchlimit', PARAM_INT);
        $mform->setDefault('matchlimit', self::DEFAULTMATCHLIMIT);
        $mform->setDefault('duringselector', 0);
        $mform->setDefault('matchinterval', self::DEFAULTMATCHINTERVAL);
        $mform->hideIf('limitingrule', 'limitingenabled', 'notchecked');
        $mform->hideIf('matchinterval', 'duringselector', 'eq', 0);
    }

    /**
     * Check permissions.
     */
    public function require_access() {
        $rule = $this->get_rule();
        if ($rule->get('id')) {
            permission::require_can_edit_rule($rule);
        } else {
            permission::require_can_create_rule();
        }
    }

    /**
     * Process form submission.
     *
     * @param \stdClass $data
     * @return string
     */
    public function process(\stdClass $data) {
        $record = new \stdClass();
        $record->name = $data->name;
        $record->matchlimit = 0;
        $record->matchinterval = 0;

        // Configure limiting rule matching frequency.
        if ($data->limitingenabled) {
            $record->matchlimit = $data->matchlimit;
            if ($data->duringselector) {
                $record->matchinterval = $data->matchinterval;
            }
        }

        // Save data.
        if (!$data->id) {
            // New rule.
            $rule = \tool_dynamicrule\api::create_rule($record);
        } else {
            // Editing rule.
            $rule = \tool_dynamicrule\api::update_rule($data->id, $record);
        }
        return (new \moodle_url('/admin/tool/dynamicrule/rule.php', ['id' => $rule->get('id')]))->out(false);
    }

    /**
     * Form validation.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        // If matching limitation is enabled, check that we have a valid number here.
        if ($data['limitingenabled']) {
            if (!is_number($data['matchlimit']) || $data['matchlimit'] < 1 ) {
                return array('limitingrule' => get_string('matchlimitinvalid', 'tool_dynamicrule'));
            }
        }
        return [];
    }

    /**
     * Set data in the modal form.
     */
    public function set_data_for_modal() {
        $rule = $this->get_rule();
        if ($rule->get('id')) {
            $rulerecord = $rule->to_record();

            // Tweak the form display.
            if ($rulerecord->matchlimit) {
                $rulerecord->limitingenabled = 1;
            }
            if (empty($rulerecord->limitingenabled)) {
                // Set to default if limiting enabled is not set.
                $rulerecord->matchlimit = self::DEFAULTMATCHLIMIT;
            }
            if ($rulerecord->matchinterval) {
                $rulerecord->duringselector = 1;
            }
            if (empty($rulerecord->duringselector)) {
                // Set to default if during selector is not set.
                $rulerecord->matchinterval = self::DEFAULTMATCHINTERVAL;
            }
            // Populate form with exisiting data.
            $this->set_data($rulerecord);
        }
    }
}
