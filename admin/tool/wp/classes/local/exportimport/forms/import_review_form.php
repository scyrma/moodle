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

namespace tool_wp\local\exportimport\forms;

use tool_wp\local\exportimport\helper;

/**
 * Represents the "step 5 Review" for the import process
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class import_review_form extends import_base_form {
    /** @var int */
    protected $stage = 5;

    /**
     * Form definition
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'importid');
        $mform->setType('importid', PARAM_INT);

        $mform->addElement('hidden', 'stage', $this->stage);
        $mform->setType('stage', PARAM_INT);

        $mform->addElement('header', 'willbeimported', get_string('willbeimported', 'tool_wp'));
        $mform->setExpanded('willbeimported');

        // Call callback from the importer and ask to display settings.
        $text = \html_writer::tag('h3', get_string('filecontent', 'tool_wp'));
        $importer = $this->get_import_manager()->get_importer();

        // Call callback from the importer and ask to display settings.
        $text .= $importer->get_summary_for_review_step($this->get_import_manager()->is_completed());
        $instances = $this->get_import_manager()->get_instances();
        // Instances with id==0 will not be imported.
        $instancestoimport = array_filter($instances, function($instance) {
            return $instance['id'] != 0;
        });
        $text .= helper::display_instances_list($instances);
        $mform->addElement('static', 'importreview', '', $text);

        // Display conflict resolution review.
        $conflicts = $importer->get_conflicts_review();
        if ($conflicts) {
            $text = \html_writer::tag('h3', get_string('conflicts', 'tool_wp'));
            foreach ($conflicts as $conflict) {
                // TODO strings (avoid concatenation), layout.
                $text .= \html_writer::div(get_string('problem', 'tool_wp') . ': ' . $conflict[0], 'conflictheader');
                $text .= \html_writer::div(get_string('solution', 'tool_wp'). ': ' . $conflict[1], 'conflictsolution mb-3');
            }
            $mform->addElement('static', 'conflictsreview', '', $text);
        }

        $this->add_buttons(get_string('doimport', 'tool_wp'), true, !empty($instancestoimport));
    }

    /**
     * Attributes for the 'prev' button. They are passed to the tool_wp_import WS to get the previous form
     *
     * @return array
     */
    protected function prev_button_attributes(): array {
        $rv = parent::prev_button_attributes();
        if (!$this->get_import_manager()->has_conflicts()) {
            $rv['data-forcestage']--;
        }
        return $rv;
    }

    /**
     * Add action buttons back/cancel/next
     *
     * @param string|null $nextlabel
     * @param bool $hasbackbutton
     * @param bool $hasinstances
     */
    protected function add_buttons(?string $nextlabel = null, bool $hasbackbutton = true, bool $hasinstances = true) {
        $mform = $this->_form;

        $buttonarray = array();
        $previouslabel = get_string('previous');
        $nextlabel = $nextlabel ?: get_string('next');
        $buttonarray[] = $previous = $mform->createElement('submit', 'prevbutton', $previouslabel,
            $this->prev_button_attributes(), false, ['customclassoverride' => 'btn-outline-secondary']);
        $buttonarray[] = $mform->createElement('cancel');
        $attributes = !$hasinstances ? ['disabled', 'title' => get_string('nothingtoimport', 'tool_wp')] : [];
        $buttonarray[] = $mform->createElement('submit', 'importbutton', $nextlabel, $attributes);
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Process form
     *
     * @return int|mixed
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $this->get_import_manager()->schedule_import();
        $url = helper::import_url($data->importid);
        return parent::process_dynamic_submission() + ['url' => $url->out(false)];
    }

}
