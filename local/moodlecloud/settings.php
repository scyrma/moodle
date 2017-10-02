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
 * MoodleCloud settings (for now controls site admin touchpoint preferences).
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_moodlecloud\common\functions;

if ($hassiteconfig) {
    global $CFG;
    require_once($CFG->dirroot . '/local/moodlecloud/classes/siteowner_configcheckbox.php');

    $container = new \local_moodlecloud\touchpoints\container((include $CFG->dirroot . '/local/moodlecloud/classes/touchpoints/config/DI.php'));

    $touchpoints = functions::pipe_forward(
        $container->get('touchpoints.callables.getRepository'),
        $container->get('touchpoints.callables.getTouchpoints')
    );

    $temp = new admin_settingpage('moodlecloudnotifications', new lang_string('moodlecloudnotifications','local_moodlecloud'));
    $temp->add(new admin_setting_heading('emailnotifications', new lang_string('emailnotifications', 'local_moodlecloud'), new lang_string('emailnotificationsinfo', 'local_moodlecloud')));
    foreach ($touchpoints as $touchpoint) {
        $nicename = strtolower(str_replace(' ', '_', $touchpoint->get_name()));
        $temp->add(
            new siteowner_configcheckbox(
                'moodlecloudnotifications/touchpoints_' . $nicename,
                get_string($nicename, 'local_moodlecloud'),
                get_string($nicename . '_description', 'local_moodlecloud'),
                1
            )
        );
    }

    $ADMIN->add('server', $temp);
}
