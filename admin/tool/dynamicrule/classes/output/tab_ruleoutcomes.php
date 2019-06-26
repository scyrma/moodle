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
 * Rule outcomes tab.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use tool_wp\output\tab;

/**
 * Rule outcomes tab class.
 *
 * @package     tool_dynamicrule
 * @copyright   2018 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_ruleoutcomes extends tab {

    /**
     * Check permission of the current user to access this tab.
     *
     * @return mixed
     */
    public function is_available(): bool {
        return (bool) \tool_dynamicrule\api::get_rule($this->get_rule_id());
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
     * return true if rule can be enabled
     * @return bool
     */
    protected function can_enable_rule(): bool {
        return (new \tool_dynamicrule\rule($this->get_rule_id()))->can_enable();
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
        // Get all possible outcomes in the system for the menu.
        $outcomes = \tool_dynamicrule\api::get_outcomes();
        $menucards = (new outcomes_menu($outcomes))->export_for_template($output);

        // Get outcomes for the rule.
        $ruleoutcomes = \tool_dynamicrule\api::get_rule_outcomes($this->get_rule_id());
        $outcomeinstances = [];
        foreach ($ruleoutcomes as $ruleoutcome) {
            $outcomeinstances[] = (new outcome_instance($ruleoutcome))->export_for_template($output);
        }

        $enablehelp = new \help_icon('enablehelp', 'tool_dynamicrule');

        $params = [
            'tabheading' => get_string('outcomes', 'tool_dynamicrule'),
            'enablehelp' => $enablehelp->export_for_template($output),
            'canenablerule' => $this->can_enable_rule(),
            'menucards' => $menucards,
            'outcomeinstances' => $outcomeinstances,
            'listurl' => (new \moodle_url('/admin/tool/dynamicrule/index.php'))->out(),
        ];

        return $params;
    }
}
