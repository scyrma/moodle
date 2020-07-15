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
 * Some workplace-specific callbacks
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp;

defined('MOODLE_INTERNAL') || die();

/**
 * Class used to display workplace copyright information
 *
 * @package     tool_wp
 * @copyright   2019 Moodle Pty Ltd <support@moodle.com>
 * @author      2019 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class workplace {

    /**
     * Moodle Workplace copyright information
     *
     * @return string
     */
    public static function copyright() {
        global $OUTPUT;
        $release = self::get_release();

        // Copyright notice.
        // IT IS ILLEGAL TO HIDE, REMOVE OR MODIFY THIS COPYRIGHT NOTICE.
        $copyrighttext = 'This installation contains ' .
            \html_writer::link('https://moodle.com/workplace', 'Moodle Workplace ' . $release) . '<br />' .
            'Copyright &copy; 2018 onwards, Moodle Pty Ltd<br />'.
            'Workplace components are dual-licensed via Moodle Workplace License and GPLv3. ' .
            'Do not distribute without permission.';
        // End of copyright notice.

        return $OUTPUT->box($copyrighttext, 'copyright workplacecopyright');
    }

    /**
     * Get workplace release
     *
     * @return int|mixed|string
     */
    protected static function get_release() {
        $plugin = \core_plugin_manager::instance()->get_plugins_of_type('tool')['wp'];
        $release = !empty($plugin->release) ? $plugin->release : $plugin->versiondb;
        return $release;
    }

    /**
     * Displays a Workplace logo in the bottom of each page during web installation
     *
     * @return string
     */
    public static function install_logo() {
        global $CFG;
        $title = 'Moodle Workplace ' . self::get_release() . ', Moodle ' . ($CFG->target_release ?? $CFG->release);
        return \html_writer::div(\html_writer::link('https://docs.moodle.org/en/Moodle_Workplace_Installation',
            \html_writer::img(new \moodle_url('/admin/tool/wp/pix/workplacelogo.png'), ''), ['title' => $title]),
            'text-center sitelink');
    }

    /**
     * Workplace copyright notice for web installation
     *
     * @param \core_admin_renderer $renderer
     * @param string|\moodle_url $pageurl
     * @return string
     */
    public static function copyright_notice(\core_admin_renderer $renderer, $pageurl) {
        $output = $renderer->header();
        $output .= self::copyright_notice_text($renderer, $pageurl, true);
        $output .= $renderer->footer();

        return $output;
    }

    /**
     * Text of a copyright notice (without header and footer)
     *
     * @param \core_admin_renderer $renderer
     * @param null $pageurl
     * @param bool $withbutton display "Continue" button.
     * @return string
     */
    public static function copyright_notice_text(\core_admin_renderer $renderer, $pageurl = null, bool $withbutton = false) {
        global $CFG;
        $output = '';

        $copyrightnotice = text_to_html(get_string('workplacelicense', 'tool_wp'));

        $output .= $renderer->heading('Moodle Workplace', 2);
        $output .= $renderer->heading(get_string('copyrightnotice'), 3);
        $output .= $renderer->box($copyrightnotice, '');
        $output .= \html_writer::empty_tag('br');
        if ($withbutton) {
            $continue = new \single_button(new \moodle_url($pageurl, array(
                'lang' => $CFG->lang, 'agreelicense' => 1)), get_string('continue'), 'get');
            $output .= $renderer->confirm(get_string('doyouagree'), $continue, "https://moodle.com/workplace");
        }

        return $output;
    }

    /**
     * Displays CLI workplace copyright notice
     *
     * Used in admin/cli/install.php
     *
     * @return bool
     * @throws \coding_exception
     */
    public static function print_cli_copyright_notice() {
        echo 'Moodle Workplace' . PHP_EOL . PHP_EOL;
        echo wordwrap(trim(get_string('workplacelicense', 'tool_wp')), 80) . PHP_EOL . PHP_EOL;
        return true;
    }

    /**
     * Workplace CLI logo
     *
     * Used in admin/cli/install.php
     *
     * @return bool
     * @throws \coding_exception
     */
    public static function print_cli_logo() {
        echo <<<EOL
                             _ _
   _ __ ___   ___   ___   __| | | ___
  | '_ ` _ \ / _ \ / _ \ / _` | |/ _ \
  | | | | | | (_) | (_) | (_| | |  __/
  |_| |_| |_|\___/ \___/ \__,_|_|\___|
                      _          _
  __      _____  _ __| | ___ __ | | __ _  ___ ___
  \ \ /\ / / _ \| '__| |/ / '_ \| |/ _` |/ __/ _ \
   \ V  V / (_) | |  |   <| |_) | | (_| | (_|  __/
    \_/\_/ \___/|_|  |_|\_\ .__/|_|\__,_|\___\___|
                          |_|
EOL;

        echo PHP_EOL . PHP_EOL;
        echo get_string('cliinstallheader', 'install', 'Workplace ' . self::get_release()) . PHP_EOL;
        return true;
    }

    /**
     * Check if workplace license has been agreed to
     *
     * @return bool
     */
    public static function workplace_license_not_agreed_message(): ?string {
        global $CFG, $OUTPUT;
        if (!empty($CFG->wplicensepending)) {
            $link = '';
            if (is_siteadmin()) {
                $link = ' ' . \html_writer::link(new \moodle_url('/admin/tool/wp/license.php'),
                        get_string('viewlicense', 'tool_wp'));
            }
            return $OUTPUT->notification(get_string('workplacelicensenotagreed', 'tool_wp') . $link, 'error');
        }
        return null;
    }

    /**
     * Redirect to the workplace license page if the license is not agreed.
     *
     * @param \moodle_url|string $redirect
     */
    public static function workplace_license_redirect($redirect = null) {
        global $CFG;
        if (!defined('WORKPLACELICENSEPAGE') && !empty($CFG->wplicensepending) &&
                is_siteadmin() && !is_major_upgrade_required()) {
            $params = $redirect ? ['redirect' => (new \moodle_url($redirect))->out_as_local_url(false)] : [];
            $url = new \moodle_url('/admin/tool/wp/license.php', $params);
            redirect($url);
        }
    }

}
