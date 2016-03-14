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
 * @package   theme_school
 * @copyright 2016 Moodle, moodle.org
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/theme/bootstrapbase/renderers.php');
require_once($CFG->dirroot . '/lib/coursecatlib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

class theme_school_core_renderer extends theme_bootstrapbase_core_renderer {

    const NUMBER_OF_IMAGES = 20;
    const MAX_CATEGORY_COUNT = 11;

    private function serialise_courses($courses) {
        global $DB, $CFG;

        if (empty($courses)) {
            return array();
        }

        $coursedetailsarray = array();
        foreach ($courses as $course) {
            $url = new \moodle_url('/course/view.php', array('id' => $course->id));
            $enrolledusersurl = new \moodle_url('/enrol/users.php', array('id' => $course->id));
            $summary = format_text($course->summary, $course->summaryformat, array(), $course->id);
            $name = format_string(get_course_display_name_for_list($course), true, array());
            $imageurl = '';

            $courseinfo = array(
                'url' => $url->out(),
                'enrolledusersurl' => $enrolledusersurl->out(),
                'name' => $name,
                'summary' => $summary,
                'contacts' => array(),
            );

            if ($course->has_course_contacts()) {
                foreach ($course->get_course_contacts() as $userid => $coursecontact) {
                    $profileurl = new \moodle_url('/user/view.php', array('id' => $userid, 'course' => SITEID));
                    $info = array(
                        'name' => $coursecontact['username'],
                        'profileurl' => $profileurl->out(),
                        'rolename' => $coursecontact['rolename'],
                    );

                    $courseinfo['contacts'][] = $info;
                }
            }

            foreach ($course->get_course_overviewfiles() as $file) {
                $isimage = $file->is_valid_image();
                if ($isimage) {
                    $imageurl = file_encode_url("$CFG->wwwroot/pluginfile.php",
                        '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                        $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);

                    break;
                }
            }

            if (empty($imageurl)) {
                $imagenumber = ($course->id % self::NUMBER_OF_IMAGES) + 1;
                $imageurl = sprintf("%s/theme/school/pix/custom/course/%s.jpg", $CFG->wwwroot, $imagenumber);
            }

            $courseinfo['imageurl'] = $imageurl;

            $coursedetailsarray[] = $courseinfo;
        }

        return $coursedetailsarray;
    }

    private function serialise_categories($categories) {
        if (empty($categories)) {
            return array();
        }

        $helper = new \coursecat_helper();

        $serialiser = function($category) use ($helper) {
            $url = new \moodle_url('/course/index.php', array('categoryid' => $category->id));
            return array(
                'name' => $category->name,
                'url' => $url->out(),
                'description' => $helper->get_category_formatted_description($category),
                'coursecount' => $category->coursecount,
                'subcategorycount' => $category->get_children_count(),
            );
        };

        return array_map($serialiser, $categories);
    }

    public function full_header() {
        $html = html_writer::start_tag('header', array('id' => 'page-header', 'class' => 'clearfix'));
        $html .= html_writer::start_div('clearfix', array('id' => 'page-navbar'));
        $html .= html_writer::tag('nav', $this->navbar(), array('class' => 'breadcrumb-nav'));
        $html .= html_writer::div($this->page_heading_button(), 'breadcrumb-button');
        $html .= html_writer::end_div();
        $html .= html_writer::tag('div', $this->course_header(), array('id' => 'course-header'));
        $html .= html_writer::end_tag('header');
        return $html;
    }

    public function favicon() {
        global $PAGE, $CFG;
        $favicon = $PAGE->theme->setting_file_url('faviconurl', 'faviconurl');
        if($favicon) {
            return $favicon;
        } else {
            return parent::favicon();
        }
    }

    public function user_profile_picture() {
        global $USER;
        $userpic = parent::user_picture($USER, array('link' => false, 'size' => 80));
        return $userpic;
    }

    public function user_menu_logged_in($user) {
        // Get some navigation opts.
        $opts = user_get_user_navigation_info($user, $this->page);

        $data = array(
            'user_profile_picture'  => $this->user_profile_picture(),
            // TODO - do we want to separate these out?
            'fullname'              => fullname($user),
            'firstname'             => $user->firstname,
            'lastname'              => $user->lastname,
            'menuitems'             => array(),
        );

        $add_pre_divider = false;
        $itemno = 0;
        $navitemcount = count($opts->navitems);
        foreach ($opts->navitems as $key => $value) {
            switch ($value->itemtype) {
                case 'divider':
                    $add_pre_divider = true;
                    break;

                case 'invalid':
                    // Silently skip invalid entries (should we post a notification?).
                    continue;;

                case 'link':
                    // Process this as a link item.
                    $pix = null;
                    if (isset($value->pix) && !empty($value->pix)) {
                        $pix = new \pix_icon($value->pix, $value->title, null, array('class' => 'iconsmall'));
                    } else if (isset($value->imgsrc) && !empty($value->imgsrc)) {
                        $value->title = html_writer::img(
                            $value->imgsrc,
                            $value->title,
                            array('class' => 'iconsmall')
                        ) . $value->title;
                    }
                    $data['menuitems'][] = array(
                        'predivide' => $add_pre_divider,
                        'link'      => $value->url->out(false),
                        'pix'       => $this->render($pix),
                        'title'     => $value->title,
                    );
                    $add_pre_divider = false;
                    break;
            }

            $itemno++;

            // Add dividers after the first item and before the last item.
            if ($itemno == 1 || $itemno == $navitemcount - 1) {
                $add_pre_divider = true;
            }
        }

        if (isset($user->auth) && $user->auth === 'moodlecloud') {
            $url = new moodle_url('/auth/moodlecloud/portal.php');
            $data['showportallink'] = true;
            $data['cloudimgurl'] = $this->get_theme_source_img('cloud-logo.png');
            $data['cloudportalurl'] = $url->out();
        }

        return $this->render_from_template('theme_school/usermenu', $data);
    }

    public function user_menu_guest($withlinks = null) {
        $loginurl = get_login_url();
        $loginpage = $this->is_login_page();
        $returnstr = get_string('loggedinasguest');

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

    public function user_menu_logged_out() {
        $data = array(
            'loginlink' => get_login_url(),
        );

        $registration_method = get_config('moodle', 'registerauth');
        if ($registration_method) {
            $signuplink = new \moodle_url('/login/signup.php');
            $data['signuplink'] = $signuplink->out(false);
        }
        return $this->render_from_template('theme_school/usermenu_login', $data);
    }

    public function user_menu($user = null, $withlinks = null) {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        if (is_null($user)) {
            $user = $USER;
        }

        if (during_initial_install()) {
            // If during initial install, return the empty return string.
            return "";
        }

        // If not logged in, show the typical not-logged-in string.
        if (!isloggedin()) {
            return $this->user_menu_logged_out();
        }

        // If logged in as a guest user, show a string to that effect.
        if (isguestuser()) {
            return $this->user_menu_guest($withlinks);
        } else {
            return $this->user_menu_logged_in($user);
        }
    }

    public function get_theme_source_root() {
        // TODO: Use $THEME.
        global $CFG;
        return sprintf("%s/theme/%s", $CFG->wwwroot, 'school');
    }

    public function get_theme_source_css($filename) {
        return sprintf("%s/css/%s", $this->get_theme_source_root(), $filename);
    }

    public function get_theme_source_js($filename) {
        return sprintf("%s/js/%s", $this->get_theme_source_root(), $filename);
    }

    public function get_theme_source_img($filename) {
        return sprintf("%s/pix/custom/%s", $this->get_theme_source_root(), $filename);
    }

    public function base_theme_head_html($cssfiles = array(), $jsfiles = array()) {
        $context = array(
            'cssfiles' => array_map(array($this, 'get_theme_source_css'), $cssfiles),
            'jsfiles' => array_map(array($this, 'get_theme_source_js'), $jsfiles),
            'title' => $this->page_title(),
            'iconurl' => $this->favicon()
        );

        return $this->render_from_template('theme_school/head_elements', $context);
    }

    public function frontpage_theme_head_html() {
        $this->page->requires->js_call_amd('theme_school/frontpage', 'init');

        return $this->base_theme_head_html();
    }

    public function standard_theme_head_html() {
        $this->page->requires->js_call_amd('theme_school/engine', 'init');

        return $this->base_theme_head_html();
    }

    public function logo() {
        global $CFG, $SITE, $PAGE;

        $logo = $PAGE->theme->setting_file_url('logo', 'logo');
        $iconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
        $configsetting = get_config('theme_school', 'logoorsitename');

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
                'sitename' => $SITE->fullname
            );
            return $this->render_from_template('theme_school/logo_icon', $context);
        } else {
            $context = array(
                'href' => $CFG->wwwroot,
                'sitename' => $SITE->fullname
            );
            return $this->render_from_template('theme_school/logo_sitename', $context);
        }
    }

    public function frontpage_news_and_updates() {
        $forum = forum_get_course_forum(SITEID, 'news');
        $cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course, false, MUST_EXIST);
        $discussions = forum_get_discussions($cm, "", false, -1, 3);
        $linkurl = new \moodle_url('mod/forum/view.php', array('id' => $forum->id));

        if (empty($discussions) && !forum_user_can_post_discussion($forum, null, -1, $cm)) {
            return "";
        }

        $context = array(
            'heading' => $forum->name,
            'linkurl' => $linkurl->out(),
            'linktext' => get_string('newslink', 'theme_school'),
            'newsitems' => array()
        );

        foreach ($discussions as $discussion) {
            $linkurl = new \moodle_url('mod/forum/discuss.php', array('d' => $discussion->id));
            $context['newsitems'][] = array(
                'title' => $discussion->name,
                'modified' => userdate($discussion->timemodified),
                'linkurl' => $linkurl->out(),
            );
        }

        return $this->render_from_template('theme_school/frontpage_news_and_updates', $context);
    }

    public function frontpage_courses() {
        $coursecategory = coursecat::get(0);
        $courses = $coursecategory->get_courses(array('summary' => 1, 'coursecontacts' => 1, 'recursive' => 1));

        if (empty($courses)) {
            return "";
        }

        $coursedetails = $this->serialise_courses($courses);

        $categoriesurl = new \moodle_url('/course/index.php');
        $category = coursecat::get(0);
        $categories = array_values($category->get_children());
        $filter = function($category) {
            return $category->visible && $category->coursecount;
        };

        // Need to slice in code rather than with limit on the get_children call because we need to ensure
        // the limit search could return invalid results (invisible categories) and we could end up with
        // too few categories to display.
        $categories = array_slice(array_values(array_filter($categories, $filter)), 0, self::MAX_CATEGORY_COUNT);

        if (count($categories) == 1) {
            // Don't show the categories list if there is only one.
            $categories = array();
        }

        $categorydetails = $this->serialise_categories($categories);
        $heading = get_config('theme_school', 'coursesectionheading');
        $subheading = get_config('theme_school', 'coursesectionsubheading');
        $overview = get_config('theme_school', 'coursesectionoverview');
        $hastext = !empty($heading) || !empty($subheading) || !empty($overview);

        $context = array(
            'courses' => $coursedetails,
            'categories' => $categorydetails,
            'categoriesurl' => $categoriesurl->out(),
            'hastext' => $hastext,
            'hascategories' => !empty($categories),
            'heading' => $heading,
            'subheading' => $subheading,
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
            'heading' => $heading,
            'subheading' => $subheading,
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
                'text' => $text,
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

        $context['hasslides'] = $hasslides;
        $context['hastext'] = $hastext;

        return $this->render_from_template('theme_school/frontpage_feedback', $context);
    }


    public function frontpage_header_content_static() {
        global $PAGE;

        $text = get_config('theme_school', 'addtext');
        $iframehtml = get_config('theme_school', 'video');
        $videosrc = $this->page->theme->setting_file_url('uploadvideo', 'uploadvideo');
        $imageurl = $this->page->theme->setting_file_url('frontpagemediaimage', 'frontpagemediaimage');
        $ismediaimage = get_config('theme_school', 'frontpagestaticcontentselect') ? false : true;
        $frontpagesettingsurl = new \moodle_url('/admin/settings.php', array('section' => 'theme_school_frontpage'));
        $hascontent = true;
        $hasmedia = true;

        if (empty($text) && empty($iframehtml) && empty($videosrc) && empty($imageurl)) {
            // No content configured.
            $hascontent = false;
            $hasmedia = false;
        }

        $context = array(
            'text' => $text,
            'mediaalignleft' => get_config('theme_school', 'frontpagemediaalignment') == 1 ? false : true,
            'mediaimage' => $ismediaimage,
            'isadmin' => is_siteadmin(),
            'frontpagesettingsurl' => $frontpagesettingsurl->out(),
            'hascontent' => $hascontent,
        );

        if ($ismediaimage) {
            $context['imageurl'] = $imageurl;

            if (empty($imageurl)) {
                $hasmedia = false;
            }
        } else {
            if(get_config('theme_school', 'videotype') === "0") {
                $context['iframevideo'] = true;
                $context['iframehtml'] = $iframehtml;

                if (empty($iframehtml)) {
                    $hasmedia = false;
                }
            } else {
                $context['iframevideo'] = false;
                $context['videosrc'] = $videosrc;

                if (empty($videosrc)) {
                    $hasmedia = false;
                }
            }
        }

        $context['hasmedia'] = $hasmedia;

        return $this->render_from_template('theme_school/frontpage_header_content_static', $context);
    }

    public function frontpage_header_content_slider() {
        global $PAGE, $CFG;
        $numberofslides = get_config('theme_school', 'slidercount');
        $frontpagesettingsurl = new \moodle_url('/admin/settings.php', array('section' => 'theme_school_frontpage'));
        $hascontent = true;

        if (empty($numberofslides)) {
            $hascontent = false;;
        }

        $context = array(
            'slides' => array(),
            'slideinterval' => get_config('theme_school', 'slideinterval'),
            'slideautoplay' => get_config('theme_school', 'sliderautoplay'),
            'isadmin' => is_siteadmin(),
            'frontpagesettingsurl' => $frontpagesettingsurl->out(),
            'hascontent' => $hascontent,
        );

        for ($slidecount = 1; $slidecount <= $numberofslides; $slidecount++) {
            $imageurl = $PAGE->theme->setting_file_url('slideimage'.$slidecount, 'slideimage'.$slidecount);
            $title = get_config('theme_school', 'slidertitle'.$slidecount);
            $text = get_config('theme_school', 'slidertext'.$slidecount);
            $linkurl = get_config('theme_school', 'sliderurl'.$slidecount);
            $linktext = get_config('theme_school', 'sliderbuttontext'.$slidecount);

            if (!empty($text) || !empty($linkurl) || !empty($imageurl)) {
                $context['slides'][] = array(
                    'imageurl' => $imageurl,
                    'title' => $title,
                    'text' => $text,
                    'linkurl' => $linkurl,
                    'linktext' => $linktext
                );
            }
        }

        return $this->render_from_template('theme_school/frontpage_header_content_slider', $context);
    }

    public function frontpage_header_content() {
        if (empty(get_config('theme_school', 'frontpageimagecontent'))) {
            return $this->frontpage_header_content_static();
        } else {
            return $this->frontpage_header_content_slider();
        }
    }

    public function coursecategory_categories() {
        global $CFG;

        $category = coursecat::get(0);
        $childcategories = array_values($category->get_children());
        $moodlecontext = get_category_or_system_context($category->id);
        $coursesearchurl = new \moodle_url('/course/search.php');
        $addcourseurl = new \moodle_url('/course/edit.php', array('category' => $CFG->defaultrequestcategory, 'returnto' => 'topcat'));
        $context = array(
            'categories' => $this->serialise_categories($childcategories),
            'showaddcourse' => has_capability('moodle/course:create', $moodlecontext),
            'urls' => array(
                'coursesearch' => $coursesearchurl->out(),
                'addcourse' => $addcourseurl->out(),
            ),
        );

        return $this->render_from_template('theme_school/coursecategory_categories', $context);
    }

    public function coursecategory_courses($coursecategory) {
        $categorypickers = array();
        if ($coursecategory->id) {
            $html = html_writer::start_tag('div', array('class' => 'categorypicker'));
            $select = new single_select(new \moodle_url('/course/index.php'), 'categoryid',
                    coursecat::make_categories_list(), $coursecategory->id, null, 'all-category-picker');
            $select->set_label(get_string('allcategories').':');
            $html .= $this->render($select);
            $html .= html_writer::end_tag('div');

            $categorypickers['all'] = $html;
        }

        $courses = $coursecategory->get_courses(array('summary' => 1, 'coursecontacts' => 1));
        $hascourses = !empty($courses);
        $coursedetails = array();

        if ($hascourses) {
            $coursedetails = $this->serialise_courses($courses);
        }

        $childcategories = array_values($coursecategory->get_children());
        $haschildcategories = !empty($childcategories);

        if ($haschildcategories) {
            $categorylist = array('-1' => '');
            foreach ($childcategories as $category) {
                $categorylist[$category->id] = $category->name;
            }

            $html = html_writer::start_tag('div', array('class' => 'categorypicker'));
            $select = new single_select(new \moodle_url('/course/index.php'), 'categoryid',
                    $categorylist, $coursecategory->id, null, 'subcategory-picker');
            $select->set_label(get_string('subcategories').':');
            $html .= $this->render($select);
            $html .= html_writer::end_tag('div');

            $categorypickers['sub'] = $html;
        }

        $coursesearchurl = new \moodle_url('/course/search.php');
        $addcourseurl = new \moodle_url('/course/edit.php', array('category' => $coursecategory->id, 'returnto' => 'category'));
        $moodlecontext = get_category_or_system_context($coursecategory->id);

        $context = array(
            'hascourses' => $hascourses,
            'courses' => $coursedetails,
            'haschildcategories' => $haschildcategories,
            'categorypickers' => $categorypickers,
            'urls' => array(
                'coursesearch' => $coursesearchurl->out(),
                'addcourse' => $addcourseurl->out(),
            ),
            'showaddcourse' => has_capability('moodle/course:create', $moodlecontext),
        );

        return $this->render_from_template('theme_school/coursecategory_courses', $context);
    }

    public function coursecategory_index($categoryid) {
        if (!$categoryid) {
            // If no id is given and we've only got one category just show those courses.
            if (coursecat::count_all() == 1) {
                return $this->coursecategory_courses(coursecat::get_default());
            } else {
            // Otherwise show a list of the categories.
                return $this->coursecategory_categories();
            }
        } else {
        // If we were given a category id then show that one specifically.
            $coursecategory = coursecat::get($categoryid);
            return $this->coursecategory_courses($coursecategory);
        }
    }

    public function login_page_header() {
        $html = html_writer::start_div('login-logo');
        $html .= $this->logo();
        $html .= html_writer::end_div();

        return $html;
    }

    public function theme_footer() {
        global $USER;

        $context = array(
            'coursefooter' => $this->course_footer(),
            'doclinks' => $this->page_doc_link(),
            'logininfo' => $this->login_info(),
            'standardfooterhtml' => $this->standard_footer_html(),
        );

        $leftfootnote = get_config('theme_school', 'leftfootnote');
        if (!empty($leftfootnote)) {
            $context['leftfootnote'] = $leftfootnote;
        }

        $footnote = get_config('theme_school', 'footnote');
        if (!empty($footnote)) {
            $context['footnote'] = format_text($footnote);
        }

        $footnotelinks = array();
        for ($i = 1; $i <= 6; $i++) {
            $text = get_config('theme_school', sprintf('leftfootnotesection%d', $i));
            $url = get_config('theme_school', sprintf('leftfootnotesectionlink%d', $i));

            if (!empty($text) && !empty($url)) {
                $footnotelinks[] = array('text' => $text, 'url' => $url);
            }
        }

        $context['footnotelinks'] = $footnotelinks;

        $context['facebookurl'] = get_config('theme_school', 'facebook');
        $context['twitterurl'] = get_config('theme_school', 'twitter');
        $context['googleplusurl'] = get_config('theme_school', 'googleplus');
        $context['youtubeurl'] = get_config('theme_school', 'youtube');
        $context['address'] = get_config('theme_school', 'contactaddress');
        $context['phone'] = get_config('theme_school', 'contactphone');
        $context['email'] = get_config('theme_school', 'contactemail');
        $context['hascontacts'] = !empty($context['facebookurl']) || !empty($context['twitterurl']) || !empty($context['googleplusurl'])
            || !empty($context['youtubeurl']) || !empty($context['address']) || !empty($context['phone']) || !empty($context['email']);

        if (isset($USER->auth) && $USER->auth === 'moodlecloud') {
            $url = new moodle_url('/auth/moodlecloud/portal.php');
            $context['showportallink'] = true;
            $context['cloudinvertedimgurl'] = $this->get_theme_source_img('cloud-logo-inverted.png');
            $context['cloudportalurl'] = $url->out();
        }

        return $this->render_from_template('theme_school/footer', $context);
    }
}
