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
 * Class admin_setting_sitesettext
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

/**
 * Class admin_setting_sitesettext
 *
 * Override frontpage settings 'shortname' and 'fullname'. Used in settings.php
 *
 * @package     tool_tenant
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
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
