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
 * MoodleCloud trial block installation.
 *
 * @package    block_moodlecloudtrial
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install the MoodleCloud trial block
 * @return true
 * @throws coding_exception
 * @throws dml_exception
 */
function xmldb_block_moodlecloudtrial_install() {
    global $DB;
    $now = date('U');

    // Use defaults for block configuration.
    set_config(
        'contentbaseurl',
        get_string('contentbaseurldefault', 'block_moodlecloudtrial'),
        'block_moodlecloudtrial'
    );

    set_config(
        'uselocalcontent',
        get_string('uselocalcontentdefault', 'block_moodlecloudtrial'),
        'block_moodlecloudtrial'
    );

    // Enable in dashboard for admin user if not already there.
    $params = [
        'blockname' => 'moodlecloudtrial',
        'parentcontextid' => 5
    ];
    $blockinstance = $DB->get_record('block_instances', $params);
    if (!$blockinstance) {
        $blockinstance = [
            'blockname' => 'moodlecloudtrial',
            'parentcontextid' => 5,
            'showinsubcontexts' => false,
            'pagetypepattern' => 'my-index',
            'subpagepattern' => 3,
            'defaultregion' => 'content',
            'defaultweight' => -101,
            'configdata' => '',
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $DB->insert_record('block_instances', $blockinstance);

        return true;
    }
}
