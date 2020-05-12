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
 * Special configcheckbox that can only be saved if you are the site owner.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

final class siteowner_configcheckbox extends admin_setting_configcheckbox {
    private static $notified;

    public function write_setting($data) {
        global $USER, $CFG;

        // Setting will be null during install, write the default.
        if ($this->get_setting() === null) {
            return parent::write_setting($this->get_defaultsetting());
        }

        // Only write setting for Superman.
        require_once($CFG->dirroot . '/local/moodlecloud/lib.php');
        if (local_moodlecloud_is_super_admin($CFG->moodlecloud_super_admins, (int)$USER->id)) {
            return parent::write_setting($data);
        }

        // This may be called multiple times, only notify once.
        // Disgusting, but what are you going to do.
        if (!self::$notified) {
            self::$notified = true;
            \core\notification::warning(get_string('onlysiteownercanchangesettings', 'local_moodlecloud'));
        }

        // Refuse to write.
        return '';
    }
}
