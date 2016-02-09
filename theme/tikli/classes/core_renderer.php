<?php

require_once($CFG->dirroot . '/theme/bootstrapbase/renderers.php');
require_once($CFG->dirroot . '/lib/coursecatlib.php');

class theme_tikli_core_renderer extends theme_bootstrapbase_core_renderer {

    private $cssfiles = array(
        //'bootstrap.css',
        //'bootstrap-responsive.css',
        'font-awesome.min.css',
        //'styles.css'
    );

    private $jsfiles = array(
        'jquery-2.1.4.js',
        'bootstrap.min.js'
    );

    private function get_course_details() {
        global $DB, $CFG;

        $courses = $DB->get_records_sql('SELECT c.* FROM {course} c where id != ? and visible = ?',array(1, 1));

        if (empty($courses)) {
            return array();
        }

        $coursedetailsarray = array();
        foreach ($courses as $key => $coursevalue) {
            $url = new \moodle_url('/course/view.php', array('id' => $coursevalue->id));
            $enrolledusersurl = new \moodle_url('/enrol/users.php', array('id' => $coursevalue->id));

            $courseinfo = array(
                'url' => $url->out(),
                'enrolledusersurl' => $enrolledusersurl->out(),
                'name' => $coursevalue->fullname,
                'summary' => $coursevalue->summary,
                'teachers' => array(),
            );

            $courseteacher = $DB->get_records_sql('SELECT u.*
            FROM {course} c
            JOIN {context} ct ON c.id = ct.instanceid
            JOIN {role_assignments} ra ON ra.contextid = ct.id
            JOIN {user} u ON u.id = ra.userid
            JOIN {role} r ON r.id = ra.roleid Where c.id = ? and r.shortname = ?', array($coursevalue->id, 'editingteacher'));
            if(!empty($courseteacher)) {
                foreach ($courseteacher as $keycourseteacher => $courseteachervalue) {
                    $profileurl = new \moodle_url('/user/profile.php', array('id' => $courseteachervalue->id));

                    $courseinfo['teachers'][] = array(
                        'name' => $courseteachervalue->firstname." ".$courseteachervalue->lastname,
                        'profileurl' => $profileurl->out()
                    );
                }
            } else {
                $courseinfo['teachers'][] = array(
                    // TODO: Lang strings.
                    'name' => 'Not assigned',
                    'profileurl' => 'javascript:void(0);'
                );
            }

            $courseimage = '';
            $coursecontext = context_course::instance($coursevalue->id);
            $isfile = $DB->get_records_sql("Select * from {files} where contextid = ? and filename != ?", array($coursecontext->id, "."));
            if ($isfile) {
                foreach ($isfile as $key1 => $isfilevalue) {
                    $courseimage =  $CFG->wwwroot . "/pluginfile.php/" . $isfilevalue->contextid ."/". $isfilevalue->component . "/" . $isfilevalue->filearea . "/" . $isfilevalue->filename;
                }
            }
            if (empty($courseimage)) {
                $courseimage = $CFG->wwwroot."/theme/tikli/data/nopic.jpg";
            }

            $courseinfo['imageurl'] = $courseimage;

            $coursedetailsarray[] = $courseinfo;
        }

        return $coursedetailsarray;
    }

    public function full_header() {
        $html = html_writer::start_tag('header', array('id' => 'page-header', 'class' => 'clearfix'));
        //$html .= $this->context_header();
        $html .= html_writer::start_div('clearfix', array('id' => 'page-navbar'));
        $html .= html_writer::tag('nav', $this->navbar(), array('class' => 'breadcrumb-nav'));
        $html .= html_writer::div($this->page_heading_button(), 'breadcrumb-button');
        $html .= html_writer::end_div();
        $html .= html_writer::tag('div', $this->course_header(), array('id' => 'course-header'));
        $html .= html_writer::end_tag('header');
        return $html;
    }

    public function favicon() {
        GLOBAL $PAGE, $CFG;
        $hasfavicon = $PAGE->theme->setting_file_url('faviconurl', 'faviconurl');
        if($hasfavicon) {
            return $PAGE->theme->setting_file_url('faviconurl', 'faviconurl');
        } else {
            return $CFG->wwwroot.'/theme/tikli/pix/favicon.ico';
        }
    }

    public function user_profile_picture() {
        GLOBAL $USER;
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

        return $this->render_from_template('theme_tikli/usermenu', $data);
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
        return $this->render_from_template('theme_tikli/usermenu_login', $data);
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
        return sprintf("%s/theme/%s", $CFG->wwwroot, 'tikli');
    }

    public function get_theme_source_css($filename) {
        return sprintf("%s/css/%s", $this->get_theme_source_root(), $filename);
    }

    public function get_theme_source_js($filename) {
        return sprintf("%s/js/%s", $this->get_theme_source_root(), $filename);
    }

    public function base_theme_head_html($cssfiles = array(), $jsfiles = array()) {
        $cssfiles = array_merge($this->cssfiles, $cssfiles);
        $jsfiles = array_merge($this->jsfiles, $jsfiles);
        $context = array(
            'cssfiles' => array_map(array($this, 'get_theme_source_css'), $cssfiles),
            'jsfiles' => array_map(array($this, 'get_theme_source_js'), $jsfiles),
            'title' => $this->page_title(),
            'iconurl' => $this->favicon()
        );

        return $this->render_from_template('theme_tikli/head_elements', $context);
    }

    public function frontpage_theme_head_html() {
        $frontpagecssfiles = array(
            'jquery.bxslider.css',
            'animation.css',
            'frontpageslider/cssliderstyle.css'
        );
        $frontpagejsfiles = array(
            'jquery.bxslider.min.js',
            'frontpage.js',
            'font.js',
        );

        return $this->base_theme_head_html($frontpagecssfiles, $frontpagejsfiles);
    }

    public function standard_theme_head_html() {
        $standardcssfiles = array();
        $standardjsfiles = array('engine.js');

        return $this->base_theme_head_html($standardcssfiles, $standardjsfiles);
    }

    public function logo() {
        global $CFG, $SITE, $PAGE;

        $logo = $PAGE->theme->setting_file_url('logo', 'logo');
        $iconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
        $configsetting = get_config('theme_tikli', 'logoorsitename');

        if ($configsetting === "logo" && !empty($logo)) {
            $context = array(
                'href' => $CFG->wwwroot,
                'src' => $logo
            );
            return $this->render_from_template('theme_tikli/logo_logo', $context);
        } else if ($configsetting === "iconsitename" && !empty($iconlogo)) {
            $context = array(
                'href' => $CFG->wwwroot,
                'src' => $iconlogo,
                'sitename' => $SITE->fullname
            );
            return $this->render_from_template('theme_tikli/logo_icon', $context);
        } else {
            $context = array(
                'href' => $CFG->wwwroot,
                'sitename' => $SITE->fullname
            );
            return $this->render_from_template('theme_tikli/logo_sitename', $context);
        }
    }

    public function frontpage_news_and_updates() {
        $frontpageblockheading = get_config('theme_tikli', 'frontpageblockheading');

        if (empty($frontpageblockheading)) {
            return "";
        }

        $frontpageblock = get_config('theme_tikli', 'frontpageblock');
        $frontpageblocklink = get_config('theme_tikli', 'frontpageblocklink');

        $frontpageblocksection1 = get_config('theme_tikli', 'frontpageblocksection1');
        $frontpageblocklinksection1 = get_config('theme_tikli', 'frontpageblocklinksection1');
        $frontpageblockdescriptionsection1 = get_config('theme_tikli', 'frontpageblockdescriptionsection1');

        $frontpageblocksection2 = get_config('theme_tikli', 'frontpageblocksection2');
        $frontpageblocklinksection2 = get_config('theme_tikli', 'frontpageblocklinksection2');
        $frontpageblockdescriptionsection2 = get_config('theme_tikli', 'frontpageblockdescriptionsection2');

        $frontpageblocksection3 = get_config('theme_tikli', 'frontpageblocksection3');
        $frontpageblocklinksection3 = get_config('theme_tikli', 'frontpageblocklinksection3');
        $frontpageblockdescriptionsection3 = get_config('theme_tikli', 'frontpageblockdescriptionsection3');

        $colourscheme = get_config('theme_tikli', 'colorscheme');

        $context = array(
            'heading' => $frontpageblockheading,
            'linkurl' => $frontpageblocklink,
            'linktext' => $frontpageblock,
            'linkiconurl' => $this->get_theme_source_css(sprintf('img/%s/i-arr-r-2.png', $colourscheme)),
            'newsitems' => array()
        );

        $context['newsitems'][] = array(
            'title' => $frontpageblocksection1,
            'linkurl' => $frontpageblocklinksection1,
            'description' => $frontpageblockdescriptionsection1
        );

        $context['newsitems'][] = array(
            'title' => $frontpageblocksection2,
            'linkurl' => $frontpageblocklinksection2,
            'description' => $frontpageblockdescriptionsection2
        );

        $context['newsitems'][] = array(
            'title' => $frontpageblocksection3,
            'linkurl' => $frontpageblocklinksection3,
            'description' => $frontpageblockdescriptionsection3
        );

        return $this->render_from_template('theme_tikli/frontpage_news_and_updates', $context);
    }

    public function frontpage_courses() {
        $coursedetails = $this->get_course_details();

        if (empty($coursedetails)) {
            return "";
        }

        $categorydetails = array();
        $categoriesurl = new \moodle_url('/course/index.php');
        $categorieslist = coursecat::make_categories_list();
        $categoryids = array_keys($categorieslist);
        $categories = coursecat::get_many($categoryids);
        unset($categorieslist);

        foreach ($categories as $category) {
            // Only show visible categories that have at least one course.
            if ($category->visible && $category->coursecount) {
                $categorydetails[] = array('name' => $category->name);
            }
        }

        $context = array(
            'courses' => $coursedetails,
            'categories' => $categorydetails,
            'categoriesurl' => $categoriesurl->out(),
            'heading' => get_config('theme_tikli', 'coursesectionheading'),
            'subheading' => get_config('theme_tikli', 'coursesectionsubheading'),
            'overview' => get_config('theme_tikli', 'coursesectionoverview'),
            'imageurls' => array(
                'sliderprev' => $this->get_theme_source_css('img/i-arr-l-1.png'),
                'slidernext' => $this->get_theme_source_css('img/i-arr-r-1.png'),
            )
        );

        return $this->render_from_template('theme_tikli/frontpage_courses', $context);
    }

    public function frontpage_feedback() {
        global $PAGE;

        $context = array(
            'heading' => get_config('theme_tikli', 'feedbackheading'),
            'subheading' => get_config('theme_tikli', 'feedbacksubheading'),
            'iframe' => get_config('theme_tikli', 'feedbackiframe'),
            'brieftext' => get_config('theme_tikli', 'feedbackbrieftext'),
            'slides' => array(),
            'imageurls' => array(
                'sliderprev' => $this->get_theme_source_css('img/bxslider-img/arr-l-grid.png'),
                'slidernext' => $this->get_theme_source_css('img/bxslider-img/arr-r-grid.png'),
            )
        );

        for($feedbackslides = 1; $feedbackslides <= get_config('theme_tikli', 'feedbackslidecount'); $feedbackslides = $feedbackslides + 1) {
            $slide = array();
            $column = 'column1';
            for($feedinner = 1; $feedinner <= 4; $feedinner = $feedinner + 1) {
                if ($feedinner >= 3) {
                    $column = 'column2';
                }

                $feedback = array();
                $hasimg = get_config('theme_tikli', 'feedbackslideimage_'.$feedinner.'_'.$feedbackslides);
                if (!empty($hasimg)) {
                    $feedback['imageurl'] = $PAGE->theme->setting_file_url('feedbackslideimage_'.$feedinner.'_'.$feedbackslides, 'feedbackslideimage_'.$feedinner.'_'.$feedbackslides);
                } else {
                    $feedback['imageurl'] = $this->get_theme_source_css('img/userimage.png');
                }

                $feedback['name'] = get_config('theme_tikli', 'feedbackslidename_'.$feedinner.'_'.$feedbackslides);
                $feedback['text'] = get_config('theme_tikli', 'feedbackslidereview_'.$feedinner.'_'.$feedbackslides);

                $slide[$column][] = $feedback;
            }

            $context['slides'][] = $slide;
        }

        return $this->render_from_template('theme_tikli/frontpage_feedback', $context);
    }


    public function frontpage_header_content_static() {
        global $PAGE;

        $text = get_config('theme_tikli', 'addtext');
        $iframehtml = get_config('theme_tikli', 'video');
        $videosrc = $PAGE->theme->setting_file_url('uploadvideo', 'uploadvideo');

        if (empty($text) && empty($iframehtml) && empty($videosrc)) {
            // No content configured.
            return "";
        }

        $context = array(
            'text' => $text,
            'videoalignleft' => get_config('theme_tikli', 'frontpagevideoalignment') == 1 ? false : true,
        );

        if(get_config('theme_tikli', 'videotype') === "0") {
            $context['iframevideo'] = true;
            $context['iframehtml'] = $iframehtml;
        } else {
            $context['iframevideo'] = false;
            $context['videosrc'] = $videosrc;
        }

        return $this->render_from_template('theme_tikli/frontpage_header_content_static', $context);
    }

    public function frontpage_header_content_slider() {
        global $PAGE, $CFG;
        $numberofslides = get_config('theme_tikli', 'slidercount');

        if (empty($numberofslides)) {
            return "";
        }

        $context = array(
            'slides' => array(),
            'slideinterval' => get_config('theme_tikli', 'slideinterval'),
            'slideautoplay' => get_config('theme_tikli', 'sliderautoplay')
        );

        for ($slidecount = 1; $slidecount <= $numberofslides; $slidecount++) {
            $imageurl = $PAGE->theme->setting_file_url('slideimage'.$slidecount, 'slideimage'.$slidecount);
            $title = get_config('theme_tikli', 'slidertitle'.$slidecount);
            $text = get_config('theme_tikli', 'slidertext'.$slidecount);
            $linkurl = get_config('theme_tikli', 'sliderurl'.$slidecount);
            $linktext = get_config('theme_tikli', 'sliderbuttontext'.$slidecount);

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

        return $this->render_from_template('theme_tikli/frontpage_header_content_slider', $context);
    }

    public function frontpage_header_content() {
        switch(get_config('theme_tikli', 'frontpageimagecontent')) {
            case 0:
                return $this->frontpage_header_content_static();
            case 1:
                return $this->frontpage_header_content_slider();
            default:
                return "";
        }
    }

    public function coursecategory_courses() {
        $coursedetails = $this->get_course_details();

        if (empty($coursedetails)) {
            return "";
        }

        $colorscheme = get_config('theme_tikli', 'colorscheme');
        $coursesearchurl = new \moodle_url('/course/search.php');
        $addcourseurl = new \moodle_url('/course/edit.php', array('category' => 1, 'returnto' => 'category'));
        $coursecontext = context_course::instance(1);

        $context = array(
            'courses' => $coursedetails,
            'imageurls' => array(
                'courselink' => $this->get_theme_source_css(sprintf("/img/%s/i-c-1.png", $colorscheme)),
                'enrolleduserslink' => $this->get_theme_source_css(sprintf("/img/%s/i-c-2.png", $colorscheme)),
            ),
            'urls' => array(
                'coursesearch' => $coursesearchurl->out(),
                'addcourse' => $addcourseurl->out(),
            ),
            'showaddcourse' => has_capability('moodle/course:create', $coursecontext),
        );

        return $this->render_from_template('theme_tikli/coursecategory_courses', $context);
    }
}
