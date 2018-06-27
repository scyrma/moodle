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
 * Post installation and migration code.
 *
 * @package    tool_policy
 * @copyright  2018 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

function xmldb_tool_policy_install() {
    // BEGIN MOODLECLOUD HACK.
    global $CFG, $OUTPUT, $DB;

    $policyids = [
        'privacy' => $DB->insert_record('tool_policy', ['sortorder' => 0]),
        'cookie' => $DB->insert_record('tool_policy', ['sortorder' => 1])
    ];
    $versionids = [
        'privacy' => $DB->insert_record('tool_policy_versions', [
            'name' => 'MoodleCloud policy',
            'type' => 0,
            'audience' => 0, // Change to 1 if this is for signup/logged in users only
            'usermodified' => 2, // admin
            'timecreated' => time(),
            'timemodified' => time(),
            'policyid' => $policyids['privacy'],
            'revision' => '',
            'summary' => '',
            'summaryformat' => 1, // FORMAT_HTML
            'content' => '',
            'contentformat' => 1 // FORMAT_HTML
        ]),
        'cookie' => $DB->insert_record('tool_policy_versions', [
            'name' => 'MoodleCloud cookie policy',
            'type' => 0,
            'audience' => 0,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
            'policyid' => $policyids['cookie'],
            'revision' => '',
            'summary' => '',
            'summaryformat' => 1,
            'content' => '',
            'contentformat' => 1
        ]),
    ];

    $DB->update_record('tool_policy', ['id' => $policyids['privacy'], 'currentversionid' => $versionids['privacy']]);
    $DB->update_record('tool_policy', ['id' => $policyids['cookie'], 'currentversionid' => $versionids['cookie']]);

    set_config('moodlecloudlockedversions', implode(',', $versionids), 'tool_policy');
    // END MOODLECLOUD HACK.
}
