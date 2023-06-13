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

namespace tool_dynamicrule\output;

use core_form\dynamic_form;
use renderer_base;
use tool_dynamicrule\api;
use tool_dynamicrule\form\rule_details;
use tool_dynamicrule\rule;
use tool_dynamicrule\permission;
use tool_wp\output\tab_form;

/**
 * Rule outcomes tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2022 Moodle Pty Ltd <support@moodle.com>
 * @author      2022 Odei Alba <odei.alba@moodle.com>
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_ruledetails extends tab_form {

    /** @var rule */
    protected $rule;

    /**
     * Name of the class that contains the form (must extend \core_form\dynamic_form)
     *
     * @return string
     */
    public function get_form_class(): string {
        return rule_details::class;
    }

    /**
     * The label to be displayed on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('details');
    }

    /**
     * Check permission of the current user to access this tab.
     *
     * @return bool
     */
    public function is_available(): bool {
        return permission::can_edit_rule($this->get_rule());
    }

    /**
     * Get rule id from the data.
     *
     * @return int
     */
    protected function get_rule_id(): int {
        return !empty($this->data['ruleid']) ?
            clean_param($this->data['ruleid'], PARAM_INT) : 0;
    }

    /**
     * Get current rule.
     *
     * @return rule
     */
    protected function get_rule(): rule {
        if (!isset($this->rule)) {
            $this->rule = api::get_rule($this->get_rule_id());
        }
        return $this->rule;
    }

    /**
     * Template to use to display tab contents.
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_dynamicrule/edit_rule_details';
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $classname = $this->get_form_class();
        if (!class_exists($classname) || !is_subclass_of($classname, dynamic_form::class)) {
            throw new \coding_exception('Form class does not exist or is invalid');
        }
        /** @var dynamic_form $form */
        $form = new $classname(null, null, 'post', '', [], $this->is_available(), $this->data);
        $form->set_data_for_dynamic_submission();
        $data = $form->render();
        $enablehelp = new \help_icon('enablehelp', 'tool_dynamicrule');

        return [
            'form' => $data,
            'formclass' => $classname,
            'tabheading' => get_string('details'),
            'canenablerule' => permission::can_enable_rule($this->get_rule()),
            'enablehelp' => $enablehelp->export_for_template($output),
            'editmode' => true,
            'showenable' => !($this->get_rule()->is_enabled())
        ];
    }
}
