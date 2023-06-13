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
 * Global theme elements.
 *
 * @package   theme_workplace
 * @copyright 2019 Moodle Pty Ltd <support@moodle.com>
 * @author    Bas Brands <bas@moodle.com>
 * @license   Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace theme_workplace;

use stdClass;
use moodle_url;
use Exception;
use moodle_exception;
use navigation_node;
use flat_navigation_node;


/**
 * The theme workplace main class.
 *
 * @package    theme_workplace
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @author     Bas Brands <bas@moodle.com>
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class workplace {

    /**
     * Remove items from the flat navigation menu.
     */
    public function removenav() {
        global $PAGE, $DB, $COURSE, $CFG;
        $flatnav = $PAGE->flatnav;

        $addcustomnav = false;

        // Turn this into the current section.
        if ($PAGE->context->contextlevel == CONTEXT_MODULE) {
            if ($cm = $DB->get_record_sql(
                "SELECT cm.*, md.name AS modname, cs.section as sectionnum
                                           FROM {course_modules} cm
                                           JOIN {modules} md ON md.id = cm.module
                                           JOIN {course_sections} cs ON cm.section = cs.id
                                           WHERE cm.id = ?",
                [ $PAGE->context->instanceid ]
            )) {
                $addcustomnav = true;

                $format = course_get_format($PAGE->course);
                $sectionname = $format->get_section_name($cm->sectionnum);
                $sectionnumber = $cm->sectionnum;
                $coursemoduleid = $cm->id;

            }
        }

        $firstaction = false;
        foreach ($flatnav as $action) {
            if ($PAGE->pagelayout == 'mydashboard' && $action->key == 'myhome') {
                $action->make_active();
            }
            if ($PAGE->pagelayout == 'admin' && $action->key == 'sitesettings') {
                $action->make_active();
            }
            if (!$firstaction && $action->key != 'myhome') {
                $firstaction = $action;
            }
            if ($firstaction && $action->key == 'myhome') {
                $flatnav->remove($action->key);
                $flatnav->add($action, 'calendar');
            }
            if ($action->key == 'mycourses') {
                $flatnav->remove($action->key);
            }
            if (($action->key == 'home') && ($CFG->defaulthomepage == 1)) {
                $flatnav->remove($action->key);
            }
            if ($addcustomnav && $action->key == $coursemoduleid) {
                // New section node.
                $flatnav->remove($action->key);

                $sectionurl = new moodle_url('/course/view.php', ['id' => $COURSE->id]);
                $sectionurl->set_anchor('section-' . $sectionnumber);
                $snode = navigation_node::create($sectionname, $sectionurl);
                $sectionnode = new flat_navigation_node($snode, 0);
                $sectionnode->key = 'section' .  $sectionnumber;
                $sectionnode->icon = new \pix_icon('bookmark', '', 'tool_wp');
                $flatnav->add($sectionnode, 'participants');

                $activityurl = $action->action;
                $anode = navigation_node::create($action->text, $activityurl);
                $activitynode = new flat_navigation_node($anode, 0);
                $activitynode->key = 'activity';
                $activitynode->icon = new \pix_icon('e/paste', '');
                $activitynode->make_active();
                $flatnav->add($activitynode, 'participants');

                // Move the activity node.
                $addcustomnav = false;

            }
            if ($action->key == 'participants') {
                $action->set_showdivider(true, 'participants');
            }
            if ($action->key == 'sitesettings') {
                $action->set_showdivider(true, 'sitesettings');
            }
            if (isset($action->parent) && $action->parent->key == 'mycourses') {
                $flatnav->remove($action->key);
            }
        }
    }

    /**
     * Get the configured login background.
     */
    public function loginbackgroundimage() {
        $theme = \theme_config::load('workplace');
        return $theme->setting_file_url('loginbackgroundimage', 'loginbackgroundimage');
    }

    /**
     * Get the Workplace custom dashboard content.
     *
     * @return Object Template data for template dashboard_content
     */
    public function dashboard() {
        global $USER, $OUTPUT, $PAGE;

        $template = new stdClass();
        $template->tabs = [];
        $template->hastabs = false;

        if ($PAGE->user_is_editing()) {
            $template->editing = true;
        }

        $config = get_config('theme_workplace');

        // Teams tab.
        if (class_exists('\tool_organisation\output\managed_users_view') && !empty($config->dashboardteams)) {
            // Add fixed block to the dashboard with my teams overview.
            $output = $PAGE->get_renderer('tool_organisation');
            $view = new \tool_organisation\output\managed_users_view();
            $content = $output->render($view);
            if (trim($content) !== '') {
                $tab = new stdClass();
                $tab->name = 'teams';
                $tab->active = true;
                $tab->classes = 'wp-organisation pt-1';
                $tab->title = get_string('myteams', 'tool_organisation');
                $tab->content = $content;
                $template->hastabs = true;
                $template->tabs[] = $tab;
            }
        }

        // Learning tab.
        if (class_exists('\tool_program\output\programs_overview_view') && !empty($config->dashboardlearning)) {
            // Add fixed block to the dashboard with programs overview.
            $output = $PAGE->get_renderer('tool_program');
            $view = new \tool_program\output\programs_overview_view($USER->id);

            $tab = new stdClass();
            $tab->name = 'learning';
            $tab->active = $template->hastabs ? false : true;
            $tab->classes = 'wp-learning p-3';
            $tab->title = get_string('learning', 'tool_program');
            $tab->content = $output->render($view);
            $template->hastabs = true;
            $template->tabs[] = $tab;
        }
        return $template;
    }

    /**
     * Site name to display in the header
     */
    public function get_site_name() {
        global $SITE, $CFG;
        // TODO SP-256 this only replaces site name in the theme header. We need a better solution.
        if (!during_initial_install() && class_exists('\tool_tenant\tenancy') && empty($CFG->upgraderunning)) {
            return \tool_tenant\tenancy::get_site_name() ?: $SITE->shortname;
        }
        return $SITE->shortname;
    }

    /**
     * Returns the actions for {@see core_userfeedback::print_reminder_block} adding the Workplace feedback
     */
    public static function get_feedback_reminder_actions() {
        $params = [
            'utm_source' => 'workplace',
            'utm_medium' => 'platform',
            'utm_campaign' => 'name~WorkplaceReviews+cat~workplace+mp~no+type~landingpage+date~05-11-21'
        ];
        $wpfeedbackurl = new moodle_url('https://moodle.com/moodleworkplace-reviews/', $params);
        return [
            ['title' => get_string('calltofeedback_give'), 'url' => \core_userfeedback::make_link()->out(false),
                'data' => ['action' => 'give', 'record' => 1, 'hide' => 1]],
            ['title' => get_string('shareyourexperience', 'theme_workplace'), 'url' => $wpfeedbackurl->out(false),
                'data' => ['action' => 'give']],
            ['title' => get_string('calltofeedback_remind'), 'url' => '#',
                'data' => ['action' => 'remind', 'record' => 1, 'hide' => 1]],
        ];
    }
}
