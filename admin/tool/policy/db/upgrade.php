<?php declare(strict_types=1);
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
 * tool_policy upgrade script.
 *
 * @package    tool_policy
 * @copyright  2018 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_policy_upgrade($oldversion) {
    global $CFG, $DB;

    if ($oldversion < 2018051401) {
        $policyid = $DB->insert_record('tool_policy', ['sortorder' => 1]);

        $versionid = $DB->insert_record('tool_policy_versions', [
            'name' => 'MoodleCloud cookies policy',
            'type' => 0,
            'audience' => 0,
            'usermodified' => 2,
            'timecreated' => time(),
            'timemodified' => time(),
            'policyid' => $policyid,
            'revision' => '',
            'summary' => '',
            'summaryformat' => 1,
            'content' => '',
            'contentformat' => 1
        ]);

        $DB->update_record('tool_policy', ['id' => $policyid, 'currentversionid' => $versionid]);

        set_config('moodlecloudlockedversions',
                   get_config('tool_policy', 'moodlecloudlockedversions') . ',' . $versionid, 'tool_policy'
        );

        list($privacyid, $cookieid) = preg_split('/,/', get_config('tool_policy', 'moodlecloudlockedversions'), -1, PREG_SPLIT_NO_EMPTY);

        // Fix sortorders so ours come first and second.
        $sortorder = 2;
        foreach ($DB->get_records('tool_policy') as $record) {
            if ($record-> id == $privacyid) {
                $DB->set_field('tool_policy', 'sortorder', 0, ['id' => $record->id]);
                continue;
            }

            if ($record-> id == $cookieid) {
                $DB->set_field('tool_policy', 'sortorder', 1, ['id' => $record->id]);
                continue;
            }

            $DB->set_field('tool_policy', 'sortorder', $sortorder, ['id' => $record->id]);
            $sortorder++;
        }

        upgrade_plugin_savepoint(true, 2018051401, 'tool', 'policy');
    }

    return true;
}