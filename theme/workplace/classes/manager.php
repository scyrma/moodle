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
// Moodle Workplace™ Code is the discrete and self-executable
// collection of software scripts (plugins and modifications, and any
// derivations thereof) that are exclusively owned and licensed by
// Moodle Pty Ltd (Moodle) under the terms of its proprietary Moodle
// Workplace License ("MWL") made available with Moodle's open software
// package ("Moodle LMS") offering which itself is freely downloadable
// at "download.moodle.org" and which is provided by Moodle under a
// single GNU General Public License version 3.0, dated 29 June 2007
// ("GPL"). MWL is strictly controlled by Moodle Pty Ltd and its Moodle
// Certified Premium Partners. Wherever conflicting terms exist, the
// terms of the MWL shall prevail.

namespace theme_workplace;

use moodle_url;

/**
 * Manager class for theme workplace.
 *
 * @package    theme_workplace
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     Bas Brands <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class manager {
    /**
     * Returns the actions for {@see core_userfeedback::print_reminder_block} adding the Workplace feedback
     *
     * @return array
     */
    public static function get_feedback_reminder_actions() {
        $params = [
            'utm_source' => 'workplace',
            'utm_medium' => 'platform',
            'utm_campaign' => 'name~WorkplaceReviews+cat~workplace+mp~no+type~landingpage+date~05-11-21',
        ];
        $wpfeedbackurl = new moodle_url('https://moodle.com/moodleworkplace-reviews/', $params);
        return [
            ['title' => get_string('calltofeedback_give'), 'url' => \core_userfeedback::make_link()->out(false),
                'data' => ['action' => 'give', 'record' => 1, 'hide' => 1], 'newwindow' => true, ],
            ['title' => get_string('shareyourexperience', 'theme_workplace'), 'url' => $wpfeedbackurl->out(false),
                'data' => ['action' => 'give'], 'newwindow' => true, ],
            ['title' => get_string('calltofeedback_remind'), 'url' => '#',
                'data' => ['action' => 'remind', 'record' => 1, 'hide' => 1], ],
        ];
    }

    /**
     * Allows to modify URL and cache file for the theme CSS for the tenants
     *
     * @param moodle_url[] $urls
     */
    public static function alter_css_urls(array &$urls) {
        global $CFG, $PAGE;

        if (during_initial_install() || !class_exists('\tool_tenant\tenancy') || !\tool_tenant\tenancy::is_site_multi_tenant()) {
            return;
        }
        if (defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING) {
            // No CSS switch during behat runs, or it will take ages to run a scenario.
            return;
        }

        $themename = !empty($PAGE->theme->name) ? $PAGE->theme->name : 'workplace';
        $tenantid = \tool_tenant\tenancy::get_tenant_id();
        $tenantid = \tool_tenant\sharedspace::is_shared_space($tenantid)
            ? \tool_tenant\tenancy::get_default_tenant_id()
            : $tenantid;
        $alltenants = \tool_tenant\tenancy::get_tenants();

        if (array_key_exists($tenantid, $alltenants)) {
            $tenant = $alltenants[$tenantid];
            $tenantid .= '-' . $tenant->timemodified;
        }

        if (theme_get_revision() == -1) {
            foreach (array_keys($urls) as $i) {
                if ($urls[$i]->get_param('type') == 'scss') {
                    unset($urls[$i]);
                }
            }
        } else {
            $urls = [];
        }

        $rev = $CFG->themerev;
        $subrev = theme_get_sub_revision_for_theme($themename);

        // Use always this url, also for different themename.
        $themecss = new moodle_url('/theme/workplace/wpcss.php');
        $cssfile = right_to_left() ? 'all-' . $tenantid . '-rtl' : 'all-' . $tenantid;
        if (!empty($CFG->slasharguments)) {
            $themecss->set_slashargument('/' . $themename . '/' . $rev . '_' . $subrev . '/' . $cssfile);
        } else {
            $params = ['theme' => $themename, 'rev' => $rev, 'type' => $cssfile];
            $themecss->params($params);
        }
        $urls[] = $themecss;
    }
}
