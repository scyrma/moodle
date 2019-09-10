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
 * Class admin_setting_sitesettext
 *
 * @package     tool_tenant
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

/**
 * Class admin_setting_sitesettext
 *
 * Override frontpage settings 'shortname' and 'fullname'. Used in settings.php
 *
 * @package     tool_tenant
 * @copyright   2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_sitesettext extends \admin_setting_sitesettext {

    /**
     * admin_setting_sitesettext constructor.
     *
     * @param \admin_setting_sitesettext $setting
     */
    public function __construct(\admin_setting_sitesettext $setting) {
        parent::__construct($setting->name, $setting->visiblename, $setting->description, $setting->defaultsetting);
    }

    /**
     * Return the current setting
     *
     * @return mixed string or null
     */
    public function get_setting() {
        global $DB;
        $site = $DB->get_record('course', ['id' => SITEID]);
        return $site->{$this->name} != '' ? $site->{$this->name} : null;
    }
}
