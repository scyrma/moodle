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
 * Rule conditions tab.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
 * Rule conditions tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class tab_ruleconditions extends tab {

    /** @var rule */
    protected $rule;

    /**
     * Check permission of the current user to access this tab.
     *
     * @return bool
     */
    public function is_available(): bool {
        // Trigger configuration validity check before checking permissions.
        api::validate_conditions_configuration($this->get_rule());
        return permission::can_edit_rule($this->get_rule());
    }

    /**
     * Get rule id from the data
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
        return 'tool_dynamicrule/ruleconditions';
    }

    /**
     * The label to be displayed on the tab.
     *
     * @return string
     */
    public function get_tab_label(): string {
        global $OUTPUT;
        $matchedusers = api::count_matched_users($this->get_rule_id());
        $icon = ($matchedusers > 0) ? $OUTPUT->pix_icon('i/lock', '', 'core') : null;
        return get_string('conditions', 'tool_dynamicrule') . $icon;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        $menucards = [];
        $countmatchingusers = 0;
        $editmode = false;
        if (api::count_matched_users($this->get_rule_id()) === 0) {
            $editmode = true;
            // Get all possible conditions in the system for the menu.
            $conditions = api::get_conditions();
            $menucards = (new conditions_menu($conditions))->export_for_template($output);
            $countmatchingusers = api::count_matching_users($this->get_rule_id());
        }

        // Get conditions for the rule.
        $ruleconditions = api::get_rule_conditions($this->get_rule_id());
        $conditioninstances = [];
        foreach ($ruleconditions as $rulecondition) {
            $conditioninstances[] = (new condition_instance($rulecondition, $editmode))->export_for_template($output);
        }

        $enablehelp = new \help_icon('enablehelp', 'tool_dynamicrule');

        $params = [
            'tabheading' => get_string('conditions', 'tool_dynamicrule'),
            'canenablerule' => permission::can_enable_rule($this->get_rule()),
            'enablehelp' => $enablehelp->export_for_template($output),
            'menucards' => $menucards,
            'instances' => $conditioninstances,
            'countmatchingusers' => get_string('countmatchingusers', 'tool_dynamicrule', $countmatchingusers),
            'noinstances' => empty($conditioninstances),
            'noinstancesurl' => $output->image_url('no-instance', 'tool_dynamicrule')->out(),
            'addinstancesstr' => get_string('addconditions', 'tool_dynamicrule'),
            'editmode' => $editmode,
            'showenable' => !($this->get_rule()->is_enabled())
        ];

        return $params;
    }
}
