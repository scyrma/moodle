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
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class admin_setting_manageauths
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_tenant;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');

/**
 * Class admin_setting_manageauths
 *
 * Override frontpage settings 'shortname' and 'fullname'. Used in settings.php
 *
 * @package     tool_tenant
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class admin_setting_manageauths extends \admin_setting_manageauths {

    /**
     * Return XHTML to display control
     *
     * @param mixed $data Unused
     * @param string $query
     * @return string highlight
     */
    public function output_html($data, $query='') {
        global $OUTPUT;

        $warning = '';
        if (auth_manager::login_auth_is_different_for_different_tenants()) {
            $url = (new \moodle_url('/admin/settings.php', array('section' => 'mobileauthentication')))->out();
            $str = get_string('authtypeofloginwarning', 'tool_tenant', $url);
            $warning = '<div class="alert alert-warning" role="alert">' . $str . '</div>';
        }

        $return = $OUTPUT->heading(get_string('actauthhdr', 'auth'), 3, 'main');
        $return .= $warning;
        $return .= $OUTPUT->box_start('generalbox authsui');
        $return .= $OUTPUT->render_from_template('tool_tenant/auth_plugins_default',
            ['authplugins' => array_values(auth_manager::get_default_auth_plugins())]);

        $return .= get_string('configauthenticationplugins', 'admin') . '<br />' .
            get_string('tablenosave', 'filters');
        $return .= $OUTPUT->box_end();
        return highlight($query, $return);
    }
}
