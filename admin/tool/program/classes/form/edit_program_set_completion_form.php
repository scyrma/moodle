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
 * Form to edit program set completion.
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_program\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;
use tool_program\persistent\program_set;

/**
 * Class edit_program_set_completion_form
 *
 * @package   tool_program
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018 Mitxel Moriana
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class edit_program_set_completion_form extends moodleform {
    /**
     * Form definition. Abstract method - always override!
     */
    protected function definition(): void {
        $mform = $this->_form;
        $mform->setDisableShortforms();
        // No empty header needed as the form is embedded.

        $mform->disable_form_change_checker();
        $formid = $this->_customdata['id'];

        $mform->addElement('hidden', 'setid', 0);
        $mform->setType('setid', PARAM_INT);

        $allinorderstr = get_string('allinorder', 'tool_program');
        $allinanyorderstr = get_string('allinanyorder', 'tool_program');
        $atleaststr = get_string('atleast', 'tool_program');
        $criteriaoptions = [
            program_set::COMPLETION_ALL_IN_ORDER => $allinorderstr,
            program_set::COMPLETION_ALL_IN_ANY_ORDER => $allinanyorderstr,
            program_set::COMPLETION_AT_LEAST => $atleaststr,
        ];

        $group = [];
        $group[] =& $mform->createElement('select', 'completioncriteria', null,
            $criteriaoptions, 'data-formid="' . $formid . '"');
        $group[] =& $mform->createElement('text', 'completionatleast', get_string('completionatleast', 'tool_program'),
            'maxlength="3" size="3" data-formid="' . $formid . '"');
        $mform->addGroup($group, 'completioncriteriagroup', '', ' ', false);

        $mform->setType('completioncriteria', PARAM_INT);
        $mform->setType('completionatleast', PARAM_INT);
        $mform->hideIf('completionatleast', 'completioncriteria', 'noteq', program_set::COMPLETION_AT_LEAST);
        $mform->disabledIf('completionatleast', 'completioncriteria', 'noteq', program_set::COMPLETION_AT_LEAST);
    }

    /**
     * Gets form identifier.
     *
     * @return null|string|string[]
     */
    protected function get_form_identifier() {
        $identifier = 'set_completion_form_' . $this->_customdata['id'];
        return preg_replace('/[^a-z0-9_]/i', '_', $identifier);
    }
}
