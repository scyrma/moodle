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
 * outcome_notification_placeholders renderable.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\output;

use renderer_base;

/**
 * outcome_notification_placeholders renderable class.
 *
 * @package     tool_dynamicrule
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Ruslan Kabalin
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
        $placeholders = $this->render_placeholders($this->outcome->get_available_user_placeholders(), $output);

        $userplaceholders = array_map(function($key, $descr) {
            return ['placeholderkey' => $key, 'placeholderdescription' => $descr];
        }, array_keys($placeholders), array_values($placeholders));

        // Site placeholders.
        $placeholders = $this->render_placeholders($this->outcome->get_available_site_placeholders(), $output);
        $siteplaceholders = array_map(function($key, $descr) {
            return ['placeholderkey' => $key, 'placeholderdescription' => $descr];
        }, array_keys($placeholders), array_values($placeholders));

        // Conditions placeholders.
        $conditionsplaceholders = [];
        $conditions = \tool_dynamicrule\api::get_rule_conditions($this->outcome->get_ruleid());
        foreach ($conditions as $condition) {
            $placeholders = $this->render_placeholders($condition->get_available_data_for_outcome($this->outcome), $output);
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
            'siteplaceholders' => $siteplaceholders,
            'conditionsplaceholders' => $conditionsplaceholders,
        ];
        return $params;
    }

    /**
     * Render placeholder icons to each placeholder in the array
     *
     * @param array $placeholders
     * @param renderer_base $output
     * @return array Rendered placeholders.
     */
    private function render_placeholders(array $placeholders, renderer_base $output): array {
        $data = [];
        foreach ($placeholders as $placeholder => $value) {
            $data[$placeholder] = $value . $output->render_from_template('tool_wp/copy_to_clipboard',
                ['text' => '{{' . $placeholder . '}}']);
        }
        return $data;
    }
}
