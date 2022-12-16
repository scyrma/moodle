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

declare(strict_types=1);

namespace tool_custompage\form;

use context;
use context_system;
use moodle_url;
use core_form\dynamic_form;
use tool_custompage\permission;
use tool_custompage\local\helpers\page as helper;
use tool_custompage\local\models\page as model;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/formslib.php");

/**
 * Custom page creation form
 *
 * @package     tool_custompage
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Paul Holden <paulh@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class page extends dynamic_form {

    /**
     * Return the context for the form
     *
     * @return context
     */
    public function get_context_for_dynamic_submission(): context {
        return context_system::instance();
    }

    /**
     * Ensure current user is able to use this form
     */
    protected function check_access_for_dynamic_submission(): void {
        $pageid = $this->optional_param('id', 0, PARAM_INT);
        if ($pageid) {
            $page = new model($pageid);
            permission::require_can_edit_page($page);
        } else {
            permission::require_can_create_page();
        }

        // Ensure user can create a global page.
        if ($this->optional_param('global', false, PARAM_BOOL)) {
            permission::require_can_create_global_page();
        }
    }

    /**
     * Form definition
     */
    public function definition() {
        $pageid = $this->optional_param('id', 0, PARAM_INT);

        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'global');
        $mform->setType('global', PARAM_BOOL);

        $mform->addElement('text', 'name', get_string('name', 'tool_custompage'));
        $mform->addHelpButton('name', 'name', 'tool_custompage');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255);

        $mform->addElement('text', 'title', get_string('title', 'tool_custompage'));
        $mform->addHelpButton('title', 'title', 'tool_custompage');
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255);

        // Page weight.
        for ($weight = -10; $weight <= 10; $weight++) {
            $weights[$weight] = $weight;
        }
        $first = reset($weights);
        $weights[$first] = get_string('weightfirst', 'tool_custompage', $first);
        $last = end($weights);
        $weights[$last] = get_string('weightlast', 'tool_custompage', $last);

        $mform->addElement('select', 'weight', get_string('weight', 'tool_custompage'), $weights);
        $mform->addHelpButton('weight', 'weight', 'tool_custompage');
        $mform->setDefault('weight', 0);

        // If the form is being displayed inside a modal, don't duplicate it's action buttons.
        if (!$this->optional_param('modal', false, PARAM_BOOL)) {
            $this->add_action_buttons(false, get_string('save'));
        }

        // If user cannot edit the page, freeze all elements.
        if ($pageid && !permission::can_edit_page(new model($pageid))) {
            $mform->hardFreeze();
        }
    }

    /**
     * Load existing data into form
     */
    public function set_data_for_dynamic_submission(): void {
        $pageid = $this->optional_param('id', 0, PARAM_INT);
        if ($pageid) {
            $page = new model($pageid);
            $this->set_data($page->to_record());
        } else if ($this->optional_param('global', false, PARAM_BOOL)) {
            $this->set_data(['global' => true]);
        }
    }

    /**
     * Process the form submission
     *
     * @return string The URL to advance to upon completion
     */
    public function process_dynamic_submission() {
        $submission = $this->get_data();

        if (empty($submission->id)) {
            $page = helper::create_page($submission);
        } else {
            $page = helper::update_page($submission->id, $submission);
        }

        return (new moodle_url('/admin/tool/custompage/manage.php', ['id' => $page->get('id')]))->out(false);
    }

    /**
     * URL of the page using this form
     *
     * @return moodle_url
     */
    public function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/custompage/index.php');
    }

    /**
     * Perform some extra moodle validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = [];

        if (trim($data['name']) === '') {
            $errors['name'] = get_string('required');
        }

        return $errors;
    }
}
