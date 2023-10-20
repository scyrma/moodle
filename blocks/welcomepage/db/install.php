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
 * Welcome page block installation.
 *
 * @package    block_welcomepage
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Install the Welcome Page block
 * @return true
 * @throws coding_exception
 * @throws dml_exception
 */
function xmldb_block_welcomepage_install() {
    global $DB;
    $now = date('U');

    // Use defaults for block configuration.
    set_config(
        'contentbaseurl',
        get_string('contentbaseurldefault', 'block_welcomepage'),
        'block_welcomepage'
    );

    set_config(
        'uselocalcontent',
        get_string('uselocalcontentdefault', 'block_welcomepage'),
        'block_welcomepage'
    );

    set_config(
        'hidden',
        get_string('hiddendefault', 'block_welcomepage'),
        'block_welcomepage'
    );

    // Is the first log entry on the site more than 24 hours ago?
    // If so, this is not a new site, don't set up the block to show.

    $firstlogentry = $DB->get_record('logstore_standard_log', ['id' => 1]);
    $newsitetimethreshold = 24 * 60 * 60; // 24 hours in seconds, adjust accordingly.

    if ($firstlogentry->timecreated + $newsitetimethreshold > $now) {
        // Enable in dashboard for admin user if not already there.
        $params = [
            'blockname' => 'welcomepage',
            'parentcontextid' => 5
        ];
        $blockinstance = $DB->get_record('block_instances', $params);
        if (!$blockinstance) {
            $blockinstance = [
                'blockname' => 'welcomepage',
                'parentcontextid' => 5,
                'showinsubcontexts' => false,
                'pagetypepattern' => 'my-index',
                'subpagepattern' => 3,
                'defaultregion' => 'content',
                'defaultweight' => -100, // Always at the top of the dashboard.
                'configdata' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ];

            $DB->insert_record('block_instances', $blockinstance);

            return true;
        }
    }
    // Customise Welcome to site message.
    $params = [
        'lang' => 'en',
        'componentid' => 1,
        'stringid' => 'welcometosite'
    ];

    $welcometosite = $DB->get_record('tool_customlang', $params);
    if (!$welcometosite) {
        $welcometositedata = [
            'lang' => 'en',
            'componentid' => 1,
            'stringid' => 'welcometosite',
            'original' => 'Welcome, {$a->firstname}! 👋',
            'master' => 'Welcome, {$a->firstname}! 👋',
            'local' => 'Hi, {$a->firstname}! 👋',
            'timemodified' => $now,
            'timecustomized' => $now,
            'outdated' => 0,
            'modified' => 0,
        ];
        $DB->insert_record('tool_customlang', $welcometositedata);
    }

    // Customise Welcome back message.
    $params = [
        'lang' => 'en',
        'componentid' => 1,
        'stringid' => 'welcomeback'
    ];

    $welcomeback = $DB->get_record('tool_customlang', $params);
    if (!$welcomeback) {
        $welcomebackdata = [
            'lang' => 'en',
            'componentid' => 1,
            'stringid' => 'welcomeback',
            'original' => 'Welcome back, {$a->firstname}! 👋',
            'master' => 'Welcome back, {$a->firstname}! 👋',
            'local' => 'Hi, {$a->firstname}! 👋',
            'timemodified' => $now,
            'timecustomized' => $now,
            'outdated' => 0,
            'modified' => 0,
        ];
        $DB->insert_record('tool_customlang', $welcomebackdata);
    }

    // Set the support contact email if empty (MC-6211).
    // This will be the case for older sites, use the admin user's email address.
    $params = [
        'name' => 'supportemail'
    ];
    $supportemail = $DB->get_record('config', $params);
    if (!$supportemail) {
        $params = [
            'id' => 2 // Admin user is always presumed to be user with ID=2.
        ];
        $adminuser = $DB->get_record('user', $params);
        // Set the support contact email to the admin user's email address.
        if ($adminuser && isset($adminuser->email)) {
            $supportemail = [
                'name' => 'supportemail',
                'value' => $adminuser->email
            ];
            $DB->insert_record('config', $supportemail);
        }
    }
}
