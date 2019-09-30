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
 * outcome_notification_placeholders renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_dynamicrule\output;

defined('MOODLE_INTERNAL') || die();

use renderer_base;

/**
 * outcome_notification_placeholders renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_notification_placeholders implements \renderable, \templatable {

    /** @var \tool_dynamicrule\tool_dynamicrule\outcome\notification */
    protected $outcome;

    /**
     * Constructor.
     *
     * @param \tool_dynamicrule\tool_dynamicrule\outcome\notification $outcome
     */
    public function __construct($outcome) {
        $this->outcome = $outcome;
    }

    /**
     * Exports for template.
     *
     * @param renderer_base $output
     * @return array|\stdClass
     */
    public function export_for_template(renderer_base $output) {
        // User placeholders.
        $placeholders = $this->outcome->get_available_user_placeholders();
        $userplaceholders = array_map(function($key, $descr) {
            return ['placeholderkey' => $key, 'placeholderdescription' => $descr];
        }, array_keys($placeholders), array_values($placeholders));

        // Conditions placeholders.
        $conditionsplaceholders = [];
        $conditions = \tool_dynamicrule\api::get_rule_conditions($this->outcome->get_ruleid());
        foreach ($conditions as $condition) {
            $placeholders = $condition->get_available_data_for_outcome($this->outcome);
            if (count($placeholders)) {
                $conditionplaceholders = array_map(function($key, $descr) {
                    return ['placeholderkey' => $key, 'placeholderdescription' => $descr];
                }, array_keys($placeholders), array_values($placeholders));

                $conditiondata = [
                    'conditiontitle' => $condition->get_title(),
                    'conditionplaceholders' => $conditionplaceholders
                ];
                $conditionsplaceholders[] = $conditiondata;
            }
        }

        $params = [
            'elementid' => random_string(),
            'userplaceholders' => $userplaceholders,
            'conditionsplaceholders' => $conditionsplaceholders,
        ];
        return $params;
    }
}
