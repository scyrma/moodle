<?php
// This file is part of Moodle - http://moodle.org/
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

/**
 * Rule conditions tab.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_dynamicrule\permission;
use tool_wp\output\tab;

/**
 * Rule conditions tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_ruleconditions extends tab {

    /**
     * Check permission of the current user to access this tab.
     *
     * @return mixed
     */
    public function is_available(): bool {
        $rule = \tool_dynamicrule\api::get_rule($this->get_rule_id());
        return permission::can_edit_rule($rule);
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
        return get_string('conditions', 'tool_dynamicrule');
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        // Get all possible conditions in the system for the menu.
        $conditions = \tool_dynamicrule\api::get_conditions();
        $menucards = (new conditions_menu($conditions))->export_for_template($output);

        // Get conditions for the rule.
        $ruleconditions = \tool_dynamicrule\api::get_rule_conditions($this->get_rule_id());
        $conditioninstances = [];
        foreach ($ruleconditions as $rulecondition) {
            $conditioninstances[] = (new condition_instance($rulecondition))->export_for_template($output);
        }

        $countmatchingusers = \tool_dynamicrule\api::count_matching_users($this->get_rule_id());

        $enablehelp = new \help_icon('enablehelp', 'tool_dynamicrule');
        $rule = \tool_dynamicrule\api::get_rule($this->get_rule_id());

        $params = [
            'tabheading' => get_string('conditions', 'tool_dynamicrule'),
            'canenablerule' => permission::can_enable_rule($rule),
            'enablehelp' => $enablehelp->export_for_template($output),
            'menucards' => $menucards,
            'instances' => $conditioninstances,
            'countmatchingusers' => get_string('countmatchingusers', 'tool_dynamicrule', $countmatchingusers),
            'listurl' => (new \moodle_url('/admin/tool/dynamicrule/index.php'))->out(),
            'noinstances' => empty($conditioninstances),
            'noinstancesurl' => $output->image_url('no-instance', 'tool_dynamicrule')->out(),
            'addinstancesstr' => get_string('addconditions', 'tool_dynamicrule'),
        ];

        return $params;
    }
}
