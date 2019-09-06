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
 * Renderer to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_school
 * @copyright  2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_school\output;

use context_course;
use stdClass;
use html_writer;
use coursecat_helper;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

/**
 * Renderer extension to include old Clean style outputs within the Classic BS4 templates
 *
 * @package    theme_school
 * @copyright  2019 Michael Hawkins
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \core_renderer {

    /**
     * Add a footnote to the page.
     *
     * @return string
     */
    public function footnote() {
        $return = '';

        if (!empty($this->page->theme->settings->footnote)) {
            $return = '<div class="footnote text-center">' . format_text($this->page->theme->settings->footnote) . '</div>';
        }

        return $return;
    }

    /**
     * Determine whether the navbar colour is inverted.
     *
     * @return bool
     */
    public function is_navbar_inverted() {
        return !empty($this->page->theme->settings->invert);
    }

    /**
     * Display the logo on the front page and login page, if one is defined.
     *
     * @return string
     */
    public function logo() {
        global $CFG, $SITE, $PAGE;

        $logo = $PAGE->theme->setting_file_url('logo', 'logo');
        $iconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
        $configsetting = get_config('theme_school', 'logoorsitename');
        $sitename = ($PAGE->pagelayout == 'frontpage') ? $SITE->fullname : $SITE->shortname;

        if ($configsetting === "logo" && !empty($logo)) {
            $context = array(
                'href' => $CFG->wwwroot,
                'src' => $logo
            );
            return $this->render_from_template('theme_school/logo_logo', $context);
        } else if ($configsetting === "iconsitename" && !empty($iconlogo)) {
            $context = array(
                'href' => $CFG->wwwroot,
                'src' => $iconlogo,
                'sitename' => $sitename
            );
            return $this->render_from_template('theme_school/logo_icon', $context);
        } else {
            $context = array(
                'href' => $CFG->wwwroot,
                'sitename' => $sitename
            );
            return $this->render_from_template('theme_school/logo_sitename', $context);
        }
    }

    /**
     * Renders the login form.
     *
     * @param \core_auth\output\login $form The renderable.
     * @return string
     */
    public function render_login(\core_auth\output\login $form) {
        global $CFG, $SITE, $PAGE;

        // Cloud hacks.
        $logo = $PAGE->theme->setting_file_url('logo', 'logo');
        $iconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
        $configsetting = get_config('theme_school', 'logoorsitename');

        $context = $form->export_for_template($this);

        // Override because rendering is not supported in template yet.
        if ($CFG->rememberusername == 0) {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabledonlysession');
        } else {
            $context->cookieshelpiconformatted = $this->help_icon('cookiesenabled');
        }
        $context->errorformatted = $this->error_text($context->error);

        if ($configsetting === "iconsitename" && !empty($iconlogo)) {
            $url = $this->get_logo_url();
            if ($url) {
                $url = $url->out(false);
            }
            $context->logourl = $url;
        } else if ($configsetting === "logo" && !empty($logo)) {
            $prepcontext = array(
                    'href' => $CFG->wwwroot,
                    'src' => $logo
            );
            $context->logo = $this->render_from_template('theme_school/logo_logo', $prepcontext);
        } else {
            $context->textonly = true;
        }
        $context->sitename = format_string($SITE->fullname, true,
                ['context' => context_course::instance(SITEID), "escape" => false]);

        return $this->render_from_template('core/loginform', $context);
    }

    /**
     * Return the site's logo URL, if any.
     *
     * @param int $maxwidth The maximum width, or null when the maximum width does not matter.
     * @param int $maxheight The maximum height, or null when the maximum height does not matter.
     * @return moodle_url|false
     */
    public function get_logo_url($maxwidth = null, $maxheight = 200) {
        global $CFG, $PAGE;

        $configsetting = get_config('theme_school', 'logoorsitename');
        $iconlogo = $PAGE->theme->settings->icon;
        if (($configsetting === "logo" || $configsetting == "iconsitename" ) && !empty($iconlogo)) {
            return moodle_url::make_pluginfile_url(\context_system::instance()->id, 'theme_school', 'icon', '',
            theme_get_revision(), $iconlogo);
        }

        $logo = get_config('core_admin', 'logo');
        if (empty($logo)) {
            return false;
        }

        // 200px high is the default image size which should be displayed at 100px in the page to account for retina displays.
        // It's not worth the overhead of detecting and serving 2 different images based on the device.

        // Hide the requested size in the file path.
        $filepath = ((int) $maxwidth . 'x' . (int) $maxheight) . '/';

        // Use $CFG->themerev to prevent browser caching when the file changes.
        return moodle_url::make_pluginfile_url(\context_system::instance()->id, 'core_admin', 'logo', $filepath,
            theme_get_revision(), $logo);
    }

    public function frontpage_courses() {
        global $CFG, $DB;

        $heading = get_config('theme_school', 'coursesectionheading');
        $subheading = get_config('theme_school', 'coursesectionsubheading');
        $overview = get_config('theme_school', 'coursesectionoverview');

        $hastext = true;
        if (empty($heading) && empty($subheading) && empty($overview)) {
            $hastext = false;
        }

        $categoriesurl = new \moodle_url('/course/index.php');
        $category = \core_course_category::get(0);
        $categories = array_values($category->get_children());
        $filter = function($category) {
            return $category->visible && $category->coursecount;
        };

        // Need to slice in code rather than with limit on the get_children call because we need to ensure
        // the limit search could return invalid results (invisible categories) and we could end up with
        // too few categories to display.
        $categories = array_slice(array_values(array_filter($categories, $filter)), 0, core\course_renderer::MAX_CATEGORY_COUNT);

        if (count($categories) == 1) {
            // Don't show the categories list if there is only one.
            $categories = array();
        }

        $categorydetails = core\course_renderer::serialise_categories($categories);

        $courses = $category->get_courses(array('summary' => 1, 'coursecontacts' => 1, 'recursive' => 1));
        $hascourses = !empty($courses);
        $coursedetails = array();

        if ($hascourses) {
            $coursedetails = core\course_renderer::serialise_courses($courses);
        }

        $context = array(
            'courses' => $coursedetails,
            'categories' => $categorydetails,
            'categoriesurl' => $categoriesurl->out(),
            'hastext' => $hastext,
            'hascategories' => !empty($categories),
            'heading' => format_string($heading),
            'subheading' => format_string($subheading),
            'overview' => $overview,
        );

        return $this->render_from_template('theme_school/frontpage_courses', $context);
    }

    public function frontpage_feedback() {
        global $PAGE;

        $heading = get_config('theme_school', 'feedbackheading');
        $subheading = get_config('theme_school', 'feedbacksubheading');
        $iframe = get_config('theme_school', 'feedbackiframe');
        $brieftext = get_config('theme_school', 'feedbackbrieftext');

        $context = array(
                'heading' => format_string($heading),
                'subheading' => format_string($subheading),
                'iframe' => $iframe,
                'brieftext' => $brieftext,
                'slides' => array(),
        );

        for ($slidenumber = 1; $slidenumber <= 4; $slidenumber++) {
            $name = get_config('theme_school', 'feedbackslidename_'.$slidenumber);
            $text = get_config('theme_school', 'feedbackslidereview_'.$slidenumber);

            if (empty($name) || empty($text)) {
                continue;
            }

            $slide = array(
                    'name' => $name,
                    'text' => format_string($text),
            );

            $hasimg = get_config('theme_school', 'feedbackslideimage_'.$slidenumber);
            if (!empty($hasimg)) {
                $slide['imageurl'] = $PAGE->theme->setting_file_url('feedbackslideimage_'.$slidenumber, 'feedbackslideimage_'.$slidenumber);
            }

            $context['slides'][] = $slide;
        }

        $hasslides = !empty($context['slides']);
        $hastext = true;
        if (empty($heading) && empty($subheading) && empty($iframe) && empty($brieftext)) {
            $hastext = false;
        }

        if (!$hastext && !$hasslides) {
            // We have nothing to display.
            return "";
        }

        // If we've been given a URL instead of the embedded HTML then let's roll with it.
        // The media formatter should handle embedding it for us.
        if (clean_param($iframe, PARAM_URL)) {
            $context['iframe'] = format_text(html_writer::link($iframe, get_string('video', 'theme_school')), FORMAT_HTML);
        } else {
            $context['iframe'] = $iframe;
        }

        $context['hasslides'] = $hasslides;
        $context['hastext'] = $hastext;

        return $this->render_from_template('theme_school/frontpage_feedback', $context);
    }

    public function frontpage_news_and_updates() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $forum = forum_get_course_forum(SITEID, 'news');
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        $discussions = forum_get_discussions($cm, "", false, -1, 3);
        $linkurl = new \moodle_url('mod/forum/view.php', array('f' => $forum->id));

        if (empty($discussions) && !forum_user_can_post_discussion($forum, null, -1, $cm)) {
            return "";
        }

        $context = array(
                'heading' => format_string($forum->name),
                'linkurl' => $linkurl->out(),
                'linktext' => get_string('newslink', 'theme_school'),
                'newsitems' => array()
        );

        foreach ($discussions as $discussion) {
            $linkurl = new \moodle_url('mod/forum/discuss.php', array('d' => $discussion->discussion));
            $context['newsitems'][] = array(
                    'title' => format_string($discussion->name),
                    'modified' => userdate($discussion->timemodified),
                    'linkurl' => $linkurl->out(),
            );
        }

        // get the number of news items and work out the Bootstrap column span to use
        $noofnewsitems = (isset($context['newsitems']) ? count($context['newsitems']) : 0);
        if ($noofnewsitems <= 3) {
            switch ($noofnewsitems) :
                case 3:
                    $context['columnspan'] = 3;
                    break;
                case 2:
                    $context['columnspan'] = 4;
                    break;
                case 1:
                case 0:
                    $context['columnspan'] = 6;
                    break;
                default:
                    $context['columnspan'] = 3;
                    break;
            endswitch;
        }

        return $this->render_from_template('theme_school/frontpage_news_and_updates', $context);
    }

    /**
     * Construct a user menu, returning HTML that can be echoed out by a
     * layout file.
     *
     * @param stdClass $user A user object, usually $USER.
     * @param bool $withlinks true if a dropdown should be built.
     * @return string HTML fragment.
     */
    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        if (is_null($user)) {
            $user = $USER;
        }

        // Note: this behaviour is intended to match that of core_renderer::login_info,
        // but should not be considered to be good practice; layout options are
        // intended to be theme-specific. Please don't copy this snippet anywhere else.
        if (is_null($withlinks)) {
            $withlinks = empty($this->page->layout_options['nologinlinks']);
        }

        // Add a class for when $withlinks is false.
        $usermenuclasses = 'usermenu';
        if (!$withlinks) {
            $usermenuclasses .= ' withoutlinks';
        }

        $returnstr = "";

        // If during initial install, return the empty return string.
        if (during_initial_install()) {
            return $returnstr;
        }

        $loginpage = $this->is_login_page();
        $loginurl = get_login_url();
        // If not logged in, show the typical not-logged-in string.
        if (!isloggedin()) {
            $returnstr = get_string('loggedinnot', 'moodle');
            if (!$loginpage) {
                $returnstr = "<a class=\"btn btn-primary border border-dark\" href=\"$loginurl\">" . get_string('login') . '</a>';
            }
            return html_writer::div(
                    html_writer::span(
                            $returnstr,
                            'login'
                    ),
                    $usermenuclasses
            );

        }

        // If logged in as a guest user, show a string to that effect.
        if (isguestuser()) {
            $returnstr = get_string('loggedinasguest');
            if (!$loginpage && $withlinks) {
                $returnstr .= " (<a href=\"$loginurl\">".get_string('login').'</a>)';
            }

            return html_writer::div(
                    html_writer::span(
                            $returnstr,
                            'login'
                    ),
                    $usermenuclasses
            );
        }

        // Get some navigation opts.
        $opts = user_get_user_navigation_info($user, $this->page);

        $avatarclasses = "avatars col-md-4";
        $avatarcontents = html_writer::span($opts->metadata['useravatar'], 'avatar current ml-2');
        $usertextcontents = '';

        // Other user.
        if (!empty($opts->metadata['asotheruser'])) {
            $avatarcontents .= html_writer::span(
                    $opts->metadata['realuseravatar'],
                    'avatar realuser'
            );
            $usertextcontents = $opts->metadata['realuserfullname'];
            $usertextcontents .= html_writer::tag(
                    'span',
                    get_string(
                            'loggedinas',
                            'moodle',
                            html_writer::span(
                                    $opts->metadata['userfullname'],
                                    'value'
                            )
                    ),
                    array('class' => 'meta viewingas')
            );
        }

        // Role.
        if (!empty($opts->metadata['asotherrole'])) {
            $role = \core_text::strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['rolename'])));
            $usertextcontents .= html_writer::span(
                    $opts->metadata['rolename'],
                    'meta role role-' . $role
            );
        }

        // User login failures.
        if (!empty($opts->metadata['userloginfail'])) {
            $usertextcontents .= html_writer::span(
                    $opts->metadata['userloginfail'],
                    'meta loginfailures'
            );
        }

        // MNet.
        if (!empty($opts->metadata['asmnetuser'])) {
            $mnet = strtolower(preg_replace('#[ ]+#', '-', trim($opts->metadata['mnetidprovidername'])));
            $usertextcontents .= html_writer::span(
                    $opts->metadata['mnetidprovidername'],
                    'meta mnet mnet-' . $mnet
            );
        }

        // Cloud hacks
        if ($usertextcontents !== '') {
            $usertextcontents = $user->firstname . ' ' . $user->lastname . $usertextcontents;
        } else {
            $usertextcontents = $user->firstname . ' <br/> ' . $user->lastname . $usertextcontents;

        }

        $returnstr .= html_writer::span(
                html_writer::span($usertextcontents, 'usertext col-md-8') .
                html_writer::span($avatarcontents, $avatarclasses),
                'userbutton'
        );

        // Create a divider (well, a filler).
        $divider = new \action_menu_filler();
        $divider->primary = false;

        $am = new \action_menu();
        $am->set_menu_trigger(
                $returnstr
        );
        $am->set_action_label(get_string('usermenu'));
        $am->set_alignment(\action_menu::TR, \action_menu::BR);
        $am->set_nowrap_on_items();
        if ($withlinks) {
            $navitemcount = count($opts->navitems);
            $idx = 0;
            foreach ($opts->navitems as $key => $value) {

                switch ($value->itemtype) {
                    case 'divider':
                        // If the nav item is a divider, add one and skip link processing.
                        $am->add($divider);
                        break;

                    case 'invalid':
                        // Silently skip invalid entries (should we post a notification?).
                        break;

                    case 'link':
                        // Process this as a link item.
                        $pix = null;
                        if (isset($value->pix) && !empty($value->pix)) {
                            $pix = new \pix_icon($value->pix, '', null, array('class' => 'iconsmall'));
                        } else if (isset($value->imgsrc) && !empty($value->imgsrc)) {
                            $value->title = html_writer::img(
                                            $value->imgsrc,
                                            $value->title,
                                            array('class' => 'iconsmall')
                                    ) . $value->title;
                        }

                        $al = new \action_menu_link_secondary(
                                $value->url,
                                $pix,
                                $value->title,
                                array('class' => 'icon')
                        );
                        if (!empty($value->titleidentifier)) {
                            $al->attributes['data-title'] = $value->titleidentifier;
                        }
                        $am->add($al);
                        break;
                }

                $idx++;

                // Add dividers after the first item and before the last item.
                if ($idx == 1 || $idx == $navitemcount - 1) {
                    $am->add($divider);
                }
            }
        }

        return html_writer::div(
                $this->render($am),
                $usermenuclasses
        );
    }

    public function favicon() {
        global $PAGE;
        $favicon = $PAGE->theme->setting_file_url('faviconurl', 'faviconurl');
        if($favicon) {
            return $favicon;
        } else {
            return parent::favicon();
        }
    }

    /**
     * Prepare the MoodleCloud portal link button for site owners.
     *
     * @return string HTML for the portal link button, or empty string.
     */
    public function portal_link() {
        global $USER, $CFG;

        if (isset($USER->auth) && $USER->auth === 'moodlecloud') {
            $url = new moodle_url('/auth/moodlecloud/portal.php');
            $title = get_string('cloudportallink', 'theme_school');
            $alt = get_string('cloudlogo', 'theme_school');
            $text = get_string('yourportal', 'theme_school');
            $theme = \theme_config::load('school');
            $imageurl = $theme->image_url('school-logo-inverted', 'theme');
            $imghtml = html_writer::img($imageurl, $alt);
            $linkhtml = html_writer::link($url->out(), sprintf("%s %s", $imghtml, $text),
                array('id' => 'portal-link', 'title' => $title, 'target' => '_blank'));

            return html_writer::div($linkhtml, '', array('id' => 'portal-link-container'));
        } else {
            return '';
        }
    }

    /**
     * Prepare Google Analytics template for page.
     *
     * @return string
     */
    public function get_gatc() {
        // We need the global and region property as well as the plan to output.
        if ((defined('MOODLECLOUD_GA_GLOBAL_PROPERTY') && MOODLECLOUD_GA_GLOBAL_PROPERTY) &&
            (defined('MOODLECLOUD_GA_REGION_PROPERTY') && MOODLECLOUD_GA_REGION_PROPERTY) &&
            (defined('MOODLECLOUD_PLAN') && MOODLECLOUD_PLAN)
        ) {
            return $this->render_from_template('theme_school/google_analytics', array(
                'ga_global_property' => MOODLECLOUD_GA_GLOBAL_PROPERTY,
                'ga_region_property' => MOODLECLOUD_GA_REGION_PROPERTY,
                'ga_plan' => MOODLECLOUD_PLAN
            ));
        }
    }

    /**
     * Get the course pattern datauri to show on a course card.
     *
     * The datauri is an encoded svg that can be passed as a url.
     * @param int $id Id to use when generating the pattern
     * @return string datauri
     */
    public function random_course_pattern($id) {
        global $CFG;
        $imagenumber = ($id % 20) + 1;
        return sprintf("%s/theme/school/pix/custom/course/%s.jpg", $CFG->wwwroot, $imagenumber);
    }
}
