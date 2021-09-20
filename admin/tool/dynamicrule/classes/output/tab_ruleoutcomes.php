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
 * Rule outcomes tab.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_dynamicrule\api;
use tool_dynamicrule\rule;
use tool_dynamicrule\permission;
use tool_wp\output\tab;

/**
 * Rule outcomes tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_ruleoutcomes extends tab {

    /** @var rule */
    protected $rule;

    /**
     * Check permission of the current user to access this tab.
     *
     * @return bool
     */
    public function is_available(): bool {
        // Trigger configuration validity check before checking permissions.
        api::validate_outcomes_configuration($this->get_rule());
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
     * Return true if tab content is rendered for modal.
     *
     * @return bool
     */
    public function is_for_modal(): bool {
        return !empty($this->data['formodal']) ?
            clean_param($this->data['formodal'], PARAM_BOOL) : false;
    }

    /**
     * Template to use to display tab contents.
     *
     * @return string
     */
    public function get_template(): string {
        return 'tool_dynamicrule/ruleoutcomes';
    }

    /**
     * The label to be displayed on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        return get_string('outcomes', 'tool_dynamicrule');
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $countmatchedusers = \tool_dynamicrule\api::count_matched_users($this->get_rule_id());

        // Get all possible outcomes in the system for the menu.
        $outcomes = api::get_outcomes();
        $menucards = (new outcomes_menu($outcomes))->export_for_template($output);

        // Get outcomes for the rule.
        $ruleoutcomes = api::get_rule_outcomes($this->get_rule_id());
        $outcomeinstances = [];
        foreach ($ruleoutcomes as $ruleoutcome) {
            $outcomeinstances[] = (new outcome_instance($ruleoutcome))->export_for_template($output);
        }

        if ($this->is_for_modal()) {
            $enablehelp = new \help_icon('enablehelpmodal', 'tool_dynamicrule');
        } else {
            $enablehelp = new \help_icon('enablehelp', 'tool_dynamicrule');
        }

        $params = [
            'tabheading' => get_string('outcomes', 'tool_dynamicrule'),
            'enablehelp' => $enablehelp->export_for_template($output),
            'canenablerule' => permission::can_enable_rule($this->get_rule()),
            'menucards' => $menucards,
            'instances' => $outcomeinstances,
            'noinstances' => empty($outcomeinstances),
            'noinstancesurl' => $output->image_url('no-instance', 'tool_dynamicrule')->out(),
            'addinstancesstr' => get_string('addoutcomes', 'tool_dynamicrule'),
            'editmode' => true,
            'showwarning' => $countmatchedusers > 0,
            'showenable' => !($this->get_rule()->is_enabled())
        ];

        return $params;
    }
}
