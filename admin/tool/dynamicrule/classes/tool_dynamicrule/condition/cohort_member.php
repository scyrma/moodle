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
 * This file contains the class for cohort_member condition.
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_dynamicrule\tool_dynamicrule\condition;

use tool_wp\exporter_base;
use tool_wp\importer_base;

/**
 * The class for cohort_member condition
 *
 * @package    tool_dynamicrule
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     2019 Daniel Neis Araujo <daniel@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cohort_member extends cohort_condition {

    /**
     * Returns the title of the condition.
     *
     * @return string The title as formated string
     */
    public function get_title(): string {
        return get_string('conditioncohortmember', 'tool_dynamicrule');
    }

    /**
     * Adds condition's elements to the given mform.
     *
     * @param \MoodleQuickForm $mform The form to add elements to
     */
    public function get_config_form(\MoodleQuickForm $mform) {
        parent::get_config_form($mform);

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'timeadded', get_string('timeadded', 'tool_dynamicrule'), $options);
        $mform->setDefault('timeadded', time());
    }

    /**
     * Helps to build SQL to retrieve users that matches the current condition
     *
     * @return array array of three elements [$join, $where, $params]
     */
    public function get_sql(): array {

        $cm = \tool_dynamicrule\api::generate_alias();

        $join = "JOIN {cohort_members} {$cm}
                   ON ({$cm}.userid = u.id)";

        $cohortid = \tool_dynamicrule\api::generate_param_name();

        $where = "{$cm}.cohortid = :{$cohortid}";

        $params = [$cohortid => $this->get_cohortid()];

        $timeadded = $this->get_timeadded();
        if (!is_null($timeadded)) {
            $timeparam = \tool_dynamicrule\api::generate_param_name();

            $where .= " AND {$cm}.timeadded >= :{$timeparam}";
            $params[$timeparam] = $timeadded;
        }

        return [$join, $where, $params];
    }

    /**
     * Return the description for the condition.
     *
     * @return string
     */
    public function get_description(): string {
        if ($date = $this->get_timeadded()) {
            $strid = 'conditioncohortmemberdescriptionwithdate';
            $date = userdate($date, get_string('strftimedatefullshort'));
            $strparams = ['name' => $this->get_cohort_name(), 'conditiondate' => $date];
            $description = get_string($strid, 'tool_dynamicrule', $strparams);
        } else {
            $description = get_string('conditioncohortmemberdescription', 'tool_dynamicrule', $this->get_cohort_name());
        }

        return $description;
    }

    /**
     * Return the configured time.
     *
     * @return int|null
     */
    private function get_timeadded(): ?int {
        return $this->get_configdata()['timeadded'] ?? null;
    }

    /**
     * Event subscription.
     *
     * @return array
     */
    public function get_event_subscription() {
        return [
            \core\event\cohort_member_added::class,
            \core\event\cohort_member_removed::class,
        ];
    }
}
