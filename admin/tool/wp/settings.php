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
 * Hack to the settings tree
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!defined('BEHAT_SITE_RUNNING')) {
    // Rename the "Courses" tab into "Learning".
    if ($coursestab = $ADMIN->locate('courses')) {
        $coursestab->visiblename = new lang_string('coursesadmintab', 'tool_wp');
    }
}

// Automatically hide the parent languages for workplace language packs.
if ($hassiteconfig && ($langsettings = $ADMIN->locate('langsettings'))) {
    $setting = new admin_setting_configcheckbox('wphideparentlang',
        new lang_string('confighideparentlang', 'tool_wp'),
        new lang_string('confighideparentlangdesc', 'tool_wp'), 1);
    $langsettings->add($setting);
}
