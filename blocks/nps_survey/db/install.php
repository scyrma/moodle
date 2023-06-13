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
 * NPS Survey block installation.
 *
 * @package    block_nps_survey
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_block_nps_survey_install() {
    global $DB;
    $future_epoch = '2000000002';
    $now = date('U');

    // Turn off user feedback for admin user to replace with NPS survey.
    $params = [
        'userid' => 2,
        'name' => 'core_userfeedback_remind'
    ];
    $userpreference = $DB->get_record('user_preferences', $params);
    if ($userpreference && $userpreference->value < $future_epoch) {
        $userpreference->value = $future_epoch;
        $DB->update_record('user_preferences', $userpreference);
    }
    if (!$userpreference) {
        $hideuserfeedback = array_merge($params, ['value' => $future_epoch]);
        $DB->insert_record('user_preferences', $hideuserfeedback);
    }

    // Enable in dashboard for admin user if not already there.
    $params = [
        'blockname' => 'nps_survey',
        'parentcontextid' => 5
    ];
    $blockinstance = $DB->get_record('block_instances', $params);
    if (!$blockinstance) {
        $blockinstance = [
            'blockname' => 'nps_survey',
            'parentcontextid' => 5,
            'showinsubcontexts' => false,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => 3,
            'defaultregion' => 'content',
            'defaultweight' => -99, // Always at the top of the dashboard.
            'configdata' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('block_instances', $blockinstance);
    }

    // Block configuration.
    set_config('surveytext', '', 'block_nps_survey');
    set_config('surveylink', '', 'block_nps_survey');

    return true;
}
