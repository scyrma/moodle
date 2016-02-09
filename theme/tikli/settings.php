<?php
defined('MOODLE_INTERNAL') || die;
$settings = null;
if (is_siteadmin()) {
    $ADMIN->add('themes', new admin_category('theme_tikli', 'Tikli'));
    $temp = new admin_settingpage('theme_tikli_general',  get_string('generalsettings', 'theme_tikli'));

    $name = 'theme_tikli/logoorsitename';
    $title = get_string('logoorsitename', 'theme_tikli');
    $description = get_string('logoorsitenamedesc', 'theme_tikli');
    $default = 'logo';
    $setting = new admin_setting_configselect($name, $title, $description, $default, array(
        'logo' => get_string('onlylogo', 'theme_tikli'),
        'sitename' => get_string('onlysitename', 'theme_tikli'),
        'iconsitename' => get_string('iconsitename', 'theme_tikli')
    ));
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    if (get_config('theme_tikli', 'logoorsitename') === "logo") {
        // Logo file setting.
        $name = 'theme_tikli/logo';
        $title = get_string('logo','theme_tikli');
        $description = get_string('logodesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
        // Logo background file setting.
        $name = 'theme_tikli/logobackgroundimage';
        $title = get_string('logobackgroundimage','theme_tikli');
        $description = get_string('logobackgroundimagedesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'logobackgroundimage');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    } else if (get_config('theme_tikli', 'logoorsitename') === "iconsitename") {
        // Logo file setting.
        $name = 'theme_tikli/icon';
        $title = get_string('logoicon','theme_tikli');
        $description = get_string('logoicondesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'icon');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    }
     //custom favicon temp
    $name = 'theme_tikli/faviconurl';
    $title = get_string('favicon', 'theme_tikli');
    $description = get_string('favicondesc', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'faviconurl');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Custom CSS file.
    $name = 'theme_tikli/customcss';
    $title = get_string('customcss', 'theme_tikli');
    $description = get_string('customcssdesc', 'theme_tikli');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Footnote setting.
    $name = 'theme_tikli/footnote';
    $title = get_string('rightfootnote', 'theme_tikli');
    $description = get_string('rightfootnotedesc', 'theme_tikli');
    $default = 'Powered By Dualcube';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnote';
    $title = get_string('leftfootnote', 'theme_tikli');
    $description = get_string('leftfootnotedesc', 'theme_tikli');
    $default = 'All content on this website is made available under the Creative Commons Attribution-ShareAlike 3.0 Unported License, unless otherwise stated.';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesection1';
    $title = get_string('leftfootnotesection1', 'theme_tikli');
    $description = get_string('leftfootnotedescsection1', 'theme_tikli');
    $default = 'Contact';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesectionlink1';
    $title = get_string('leftfootnotesectionlink1', 'theme_tikli');
    $description = get_string('leftfootnotelinkdescsection1', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesection2';
    $title = get_string('leftfootnotesection2', 'theme_tikli');
    $description = get_string('leftfootnotedescsection2', 'theme_tikli');
    $default = 'Privacy policy';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesectionlink2';
    $title = get_string('leftfootnotesectionlink2', 'theme_tikli');
    $description = get_string('leftfootnotelinkdescsection2', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesection3';
    $title = get_string('leftfootnotesection3', 'theme_tikli');
    $description = get_string('leftfootnotedescsection3', 'theme_tikli');
    $default = 'Terms of use';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesectionlink3';
    $title = get_string('leftfootnotesectionlink3', 'theme_tikli');
    $description = get_string('leftfootnotelinkdescsection3', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesection4';
    $title = get_string('leftfootnotesection4', 'theme_tikli');
    $description = get_string('leftfootnotedescsection4', 'theme_tikli');
    $default = 'Register';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesectionlink4';
    $title = get_string('leftfootnotesectionlink4', 'theme_tikli');
    $description = get_string('leftfootnotelinkdescsection4', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesection5';
    $title = get_string('leftfootnotesection5', 'theme_tikli');
    $description = get_string('leftfootnotedescsection5', 'theme_tikli');
    $default = 'Site Policy';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesectionlink5';
    $title = get_string('leftfootnotesectionlink5', 'theme_tikli');
    $description = get_string('leftfootnotelinkdescsection5', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesection6';
    $title = get_string('leftfootnotesection6', 'theme_tikli');
    $description = get_string('leftfootnotedescsection6', 'theme_tikli');
    $default = 'Development';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/leftfootnotesectionlink6';
    $title = get_string('leftfootnotesectionlink6', 'theme_tikli');
    $description = get_string('leftfootnotelinkdescsection6', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $ADMIN->add('theme_tikli', $temp);


    //frontpage temp
    $temp = new admin_settingpage('theme_tikli_frontpage',  get_string('frontpagesettings', 'theme_tikli'));
    $temp->add(new admin_setting_heading('theme_tikli_upsection', get_string('frontpageimagecontent', 'theme_tikli'),
        format_text(get_string('frontpageimagecontentdesc', 'theme_tikli'), FORMAT_MARKDOWN)));
    $name = 'theme_tikli/frontpageimagecontent';
    $title = get_string('frontpageimagecontentstyle', 'theme_tikli');
    $description = get_string('frontpageimagecontentstyledesc', 'theme_tikli');
    $setting = new admin_setting_configselect($name, $title, $description, 1,
    array(
            0 => get_string('staticcontent', 'theme_tikli'),
            1 => get_string('slidercontent', 'theme_tikli'),
        ));
    $temp->add($setting);
    if (get_config('theme_tikli', 'frontpageimagecontent') === "0") {
        $name = 'theme_tikli/addtext';
        $title = get_string('addtext', 'theme_tikli');
        $description = get_string('addtextdesc', 'theme_tikli');
        $default = '<h2><span style="font-size:22px;">Education is a time-tested path to progress</span><br>YOU ENTER TO LEARN, LEAVE TO ACHIEVE</h2><p>Education ignites a purpose within us and beckons us on a path of enlightenment. It allows for a progressive mind to flourish that builds a self-sustaining society.</p><a class="btn-1" href="javascript:void(0);">Find Out More</a>';
        $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/videotype';
        $title = get_string('videotype', 'theme_tikli');
        $description = get_string('videotypedesc', 'theme_tikli');
        $setting = new admin_setting_configselect($name, $title, $description, 0,
        array(
            0 => get_string('iframe', 'theme_tikli'),
            1 => get_string('upload', 'theme_tikli'),
        ));
        $temp->add($setting);
        if (get_config('theme_tikli', 'videotype') === "0") {
            $name = 'theme_tikli/video';
            $title = get_string('video', 'theme_tikli');
            $description = get_string('videodesc', 'theme_tikli');
            $default = '<iframe src="https://player.vimeo.com/video/45232468" width="560" height="300"></iframe>';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        } elseif (get_config('theme_tikli', 'videotype') === "1") {
            $name = 'theme_tikli/uploadvideo';
            $title = get_string('uploadvideo','theme_tikli');
            $description = get_string('uploadvideodesc', 'theme_tikli');
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'uploadvideo', $itemid = 0, array(
			'accepted_types' => '.mp4'
			));
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }

        $name = 'theme_tikli/frontpagevideoalignment';
        $title = get_string('frontpagevideoalignment', 'theme_tikli');
        $description = get_string('frontpagevideoalignmentdesc', 'theme_tikli');
        $setting = new admin_setting_configselect($name, $title, $description, 1,
        array(
            0 => get_string('videoleft', 'theme_tikli'),
            1 => get_string('videoright', 'theme_tikli'),
        ));
        $temp->add($setting);
    } else if (get_config('theme_tikli', 'frontpageimagecontent') === "1"){
        $name = 'theme_tikli/slideinterval';
        $title = get_string('slideinterval', 'theme_tikli');
        $description = get_string('slideintervaldesc', 'theme_tikli');
        $default = 5000;
        $setting = new admin_setting_configtext($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/sliderautoplay';
        $title = get_string('sliderautoplay', 'theme_tikli');
        $description = get_string('sliderautoplaydesc', 'theme_tikli');
        $setting = new admin_setting_configselect($name, $title, $description, 1,
        array(
                1 => get_string('true', 'theme_tikli'),
                2 => get_string('false', 'theme_tikli'),
            ));
        $temp->add($setting);

        $name = 'theme_tikli/slidercount';
        $title = get_string('slidercount', 'theme_tikli');
        $description = get_string('slidercountdesc', 'theme_tikli');
        $setting = new admin_setting_configselect($name, $title, $description, 5,
        array(
                1 => get_string('one', 'theme_tikli'),
                2 => get_string('two', 'theme_tikli'),
                3 => get_string('three', 'theme_tikli'),
                4 => get_string('four', 'theme_tikli'),
                5 => get_string('five', 'theme_tikli'),
            ));
        $temp->add($setting);

        for($slidecounts = 1; $slidecounts <= get_config('theme_tikli', 'slidercount'); $slidecounts = $slidecounts + 1) {
            $name = 'theme_tikli/slideimage'.$slidecounts;
            $title = get_string('slideimage', 'theme_tikli');

            $description = get_string('slideimagedesc', 'theme_tikli');
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'slideimage'.$slidecounts);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_tikli/slidertitle'.$slidecounts;
            $title = get_string('slidertitle', 'theme_tikli');
            $description = get_string('slidertitledesc', 'theme_tikli');
            $default = '<h2><span>Education is a time-tested path to progress</span><br>YOU ENTER TO LEARN, LEAVE TO ACHIEVE</h2><p>Education ignites a purpose within us and beckons us on a path of enlightenment. It allows for a progressive mind to flourish that builds a self-sustaining society.</p>';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_tikli/slidertext'.$slidecounts;
            $title = get_string('slidertext', 'theme_tikli');
            $description = get_string('slidertextdesc', 'theme_tikli');
            $default = '<h2><span>Education is a time-tested path to progress</span><br>YOU ENTER TO LEARN, LEAVE TO ACHIEVE</h2><p>Education ignites a purpose within us and beckons us on a path of enlightenment. It allows for a progressive mind to flourish that builds a self-sustaining society.</p>';
            $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_tikli/sliderbuttontext'.$slidecounts;
            $title = get_string('sliderbuttontext', 'theme_tikli');
            $description = get_string('sliderbuttontextdesc', 'theme_tikli');
            $default = 'Read more';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_tikli/sliderurl'.$slidecounts;
            $title = get_string('sliderurl', 'theme_tikli');
            $description = get_string('sliderurldesc', 'theme_tikli');
            $default = '#';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }
    }

    $temp->add(new admin_setting_heading('theme_tikli_coursequicklinks', get_string('coursequicklinks', 'theme_tikli'),
        format_text(get_string('coursequicklinksdesc', 'theme_tikli'), FORMAT_MARKDOWN)));

    $name = 'theme_tikli/coursesectionheading';
    $title = get_string('coursesectionheading', 'theme_tikli');
    $description = get_string('coursesectionheadingdesc', 'theme_tikli');
    $default = 'COURSES & ACADEMICS';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/coursesectionsubheading';
    $title = get_string('coursesectionsubheading', 'theme_tikli');
    $description = get_string('coursesectionsubheadingdesc', 'theme_tikli');
    $default = 'What we offer to our students';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/coursesectionoverview';
    $title = get_string('coursesectionoverview', 'theme_tikli');
    $description = get_string('coursesectionoverview', 'theme_tikli');
    $default = "Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry's standard.";
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/quicklinkscolumns';
    $title = get_string('quicklinkscolumns', 'theme_tikli');
    $description = get_string('quicklinkscolumnsdesc', 'theme_tikli');
    $setting = new admin_setting_configselect($name, $title, $description, 3,
    array(
            1 => get_string('one', 'theme_tikli'),
            2 => get_string('two', 'theme_tikli'),
            3 => get_string('three', 'theme_tikli'),
        ));
    $temp->add($setting);

    for($quicklinkcols = 1; $quicklinkcols <= get_config('theme_tikli', 'quicklinkscolumns'); $quicklinkcols = $quicklinkcols + 1) {
        $name = 'theme_tikli/quicklinksrows'.$quicklinkcols;
        $title = get_string('quicklinksrows', 'theme_tikli');
        $description = get_string('quicklinksrowsdesc', 'theme_tikli');
        $setting = new admin_setting_configselect($name, $title, $description, 5,
        array(
                1 => get_string('one', 'theme_tikli'),
                2 => get_string('two', 'theme_tikli'),
                3 => get_string('three', 'theme_tikli'),
                4 => get_string('four', 'theme_tikli'),
                5 => get_string('five', 'theme_tikli'),
            ));
        $temp->add($setting);

        for($quicklinkrows = 1; $quicklinkrows <= get_config('theme_tikli', 'quicklinksrows'.$quicklinkcols); $quicklinkrows = $quicklinkrows + 1) {
            $name = 'theme_tikli/text'.$quicklinkcols.'_'.$quicklinkrows;
            $title = get_string('text', 'theme_tikli');
            $description = get_string('textdesc', 'theme_tikli');
            $default = '';
            $setting = new admin_setting_configtext($name, $title,  $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_tikli/link'.$quicklinkcols.'_'.$quicklinkrows;
            $title = get_string('link', 'theme_tikli');
            $description = get_string('linkdesc', 'theme_tikli');
            $default = '';
            $setting = new admin_setting_configtext($name, $title,  $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }
    }

    $temp->add(new admin_setting_heading('theme_tikli_feedbacksection', get_string('feedback', 'theme_tikli'),
    format_text(get_string('feedbackdesc', 'theme_tikli'), FORMAT_MARKDOWN)));

    $name = 'theme_tikli/feedbackheading';
    $title = get_string('feedbackheading', 'theme_tikli');
    $description = get_string('feedbackheadingdesc', 'theme_tikli');
    $default = 'STUDENTS FEEDBACK';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/feedbacksubheading';
    $title = get_string('feedbacksubheading', 'theme_tikli');
    $description = get_string('feedbacksubheadingdesc', 'theme_tikli');
    $default = 'We love our students, they love us too..';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/feedbackiframe';
    $title = get_string('feedbackiframe', 'theme_tikli');
    $description = get_string('feedbackiframedesc', 'theme_tikli');
    $default = '<iframe src="https://player.vimeo.com/video/45232468" width="560" height="300"></iframe>';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/feedbackbrieftext';
    $title = get_string('feedbackbrieftext', 'theme_tikli');
    $description = get_string('feedbackbrieftextdesc', 'theme_tikli');
    $default = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Mauris consectetur dui et ullamcorper eleifend. Aliquam condimentum id ante eu commodo. Quisque pulvinar tempor ultricies.</p><p><strong>Etiam vel suscipit odio.</strong></p>';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/feedbackslidecount';
    $title = get_string('feedbackslidecount', 'theme_tikli');
    $description = get_string('feedbackslidecountdesc', 'theme_tikli');
    $setting = new admin_setting_configselect($name, $title, $description, 3,
    array(
            1 => get_string('one', 'theme_tikli'),
            2 => get_string('two', 'theme_tikli'),
            3 => get_string('three', 'theme_tikli'),
        ));
    $temp->add($setting);

    for($feedbackslides = 1; $feedbackslides <= get_config('theme_tikli', 'feedbackslidecount'); $feedbackslides = $feedbackslides + 1) {
        $name = 'theme_tikli/feedbackslideimage_1_'.$feedbackslides;
        $title = get_string('feedbackslideimage', 'theme_tikli');
        $description = get_string('feedbackslideimagedesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_1_'.$feedbackslides);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidename_1_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = '<strong>Rozy Verottie ,</strong> Australia';
        $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidereview_1_'.$feedbackslides;
        $title = get_string('feedbackslidereview', 'theme_tikli');
        $description = get_string('feedbackslidereviewdesc', 'theme_tikli');
        $default = 'Duis turpis elit, rutrum eu laoreet non, faucibus a urna. Pellentesque eu tempor velit. Cras vitae velit ut magna finibus auctor vel vitae velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra. Duis turpis elit, rutrum eu laoreet non, faucibus a urna. Pellentesque eu tempor velit.
        Cras vitae velit ut magna finibus auctor vel vitae velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra.';
        $setting = new admin_setting_configtextarea($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslideimage_2_'.$feedbackslides;
        $title = get_string('feedbackslideimage', 'theme_tikli');
        $description = get_string('feedbackslideimagedesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_2_'.$feedbackslides);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidename_2_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = '<strong>Rozy Verottie ,</strong> Australia';
        $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidereview_2_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = 'Duis turpis elit, rutrum eu laoreet non, faucibus a urna. Pellentesque eu tempor velit. Cras vitae velit ut magna finibus auctor vel vitae velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra.';
        $setting = new admin_setting_configtextarea($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslideimage_3_'.$feedbackslides;
        $title = get_string('feedbackslideimage', 'theme_tikli');
        $description = get_string('feedbackslideimagedesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_3_'.$feedbackslides);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidename_3_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = '<strong>Rozy Verottie ,</strong> Australia';
        $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidereview_3_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = 'Duis turpis elit, rutrum eu laoreet non, faucibus a urna. Pellentesque eu tempor velit. Cras vitae velit ut magna finibus auctor vel vitae velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra.';
        $setting = new admin_setting_configtextarea($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslideimage_4_'.$feedbackslides;
        $title = get_string('feedbackslideimage', 'theme_tikli');
        $description = get_string('feedbackslideimagedesc', 'theme_tikli');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_4_'.$feedbackslides);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidename_4_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = '<strong>Rozy Verottie ,</strong> Australia';
        $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_tikli/feedbackslidereview_4_'.$feedbackslides;
        $title = get_string('feedbackslidename', 'theme_tikli');
        $description = get_string('feedbackslidenamedesc', 'theme_tikli');
        $default = 'Duis turpis elit, rutrum eu laoreet non, faucibus a urna. Pellentesque eu tempor velit. Cras vitae velit ut magna finibus auctor vel vitae velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra.Duis turpis elit, rutrum eu laoreet non, faucibus a urna. Pellentesque eu tempor velit.
        Cras vitae velit ut magna finibus auctor vel vitae velit. Class aptent taciti sociosqu ad litora torquent per conubia nostra.';
        $setting = new admin_setting_configtextarea($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    }

    $ADMIN->add('theme_tikli', $temp);

    $temp = new admin_settingpage('theme_tikli_colors',  get_string('colorsettings', 'theme_tikli'));

    /*
    $name = 'theme_tikli/colorscheme';
    $title = get_string('colorscheme', 'theme_tikli');
    $description = get_string('colorschemedesc', 'theme_tikli');
    $default = 'red-orange';
    $setting = new admin_setting_configselect($name, $title, $description, $default, array(
        'red-orange' => get_string('redorange', 'theme_tikli'),
        'green' => get_string('green', 'theme_tikli'),
        'orange' => get_string('orange', 'theme_tikli'),
        'blue' => get_string('blue', 'theme_tikli'),
        'purple' => get_string('purple', 'theme_tikli')

    ));
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    $ADMIN->add('theme_tikli', $temp);
    */

    $name = 'theme_tikli/primarycolour';
    $title = get_string('colorscheme', 'theme_tikli');
    $description = get_string('colorschemedesc', 'theme_tikli');
    $default = '#f39c11';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondarycolour';
    $title = get_string('colorscheme', 'theme_tikli');
    $description = get_string('colorschemedesc', 'theme_tikli');
    $default = '#3498db';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    $ADMIN->add('theme_tikli', $temp);

    /*font*/

    $temp = new admin_settingpage('theme_tikli_font',  get_string('fontsettings', 'theme_tikli'));
    $name = 'theme_tikli/fontselect';
    $title = get_string('fontselect', 'theme_tikli');
    $description = get_string('fontselectdesc', 'theme_tikli');
    $default = 1;
    $choices = array(
        1 => get_string('fonttypestandard', 'theme_tikli'),
        2 => get_string('fonttypecustom', 'theme_tikli'),
    );
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    // Heading font name
    $name = 'theme_tikli/fontnameheading';
    $title = get_string('fontnameheading', 'theme_tikli');
    $description = get_string('fontnameheadingdesc', 'theme_tikli');
    $default = 'OpenSans';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Text font name

    $name = 'theme_tikli/fontnamebody';
    $title = get_string('fontnamebody', 'theme_tikli');
    $description = get_string('fontnamebodydesc', 'theme_tikli');
    $default = 'Raleway';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    if (get_config('theme_tikli', 'fontselect') === "2") {

        if (floatval($CFG->version) >= 2014111005.01) {
            $woff2 = true;
        } else {
            $woff2 = false;
        }

        // This is the descriptor for the font files
        $name = 'theme_tikli/fontfiles';
        $heading = get_string('fontfiles', 'theme_tikli');
        $information = get_string('fontfilesdesc', 'theme_tikli');
        $setting = new admin_setting_heading($name, $heading, $information);
        $temp->add($setting);

        // Heading Fonts.
        // TTF Font.
        $name = 'theme_tikli/fontfilettfheading';
        $title = get_string('fontfilettfheading', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilettfheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // OTF Font.
        $name = 'theme_tikli/fontfileotfheading';
        $title = get_string('fontfileotfheading', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileotfheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // WOFF Font.
        $name = 'theme_tikli/fontfilewoffheading';
        $title = get_string('fontfilewoffheading', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewoffheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        if ($woff2) {
            // WOFF2 Font.
            $name = 'theme_tikli/fontfilewofftwoheading';
            $title = get_string('fontfilewofftwoheading', 'theme_tikli');
            $description = '';
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewofftwoheading');
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }

        // EOT Font.
        $name = 'theme_tikli/fontfileeotheading';
        $title = get_string('fontfileeotheading', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileweotheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // SVG Font.
        $name = 'theme_tikli/fontfilesvgheading';
        $title = get_string('fontfilesvgheading', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilesvgheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // Body fonts.
        // TTF Font.
        $name = 'theme_tikli/fontfilettfbody';
        $title = get_string('fontfilettfbody', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilettfbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // OTF Font.
        $name = 'theme_tikli/fontfileotfbody';
        $title = get_string('fontfileotfbody', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileotfbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // WOFF Font.
        $name = 'theme_tikli/fontfilewoffbody';
        $title = get_string('fontfilewoffbody', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewoffbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        if ($woff2) {
            // WOFF2 Font.
            $name = 'theme_tikli/fontfilewofftwobody';
            $title = get_string('fontfilewofftwobody', 'theme_tikli');
            $description = '';
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewofftwobody');
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }

        // EOT Font.
        $name = 'theme_tikli/fontfileeotbody';
        $title = get_string('fontfileeotbody', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileweotbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // SVG Font.
        $name = 'theme_tikli/fontfilesvgbody';
        $title = get_string('fontfilesvgbody', 'theme_tikli');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilesvgbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    }
    $ADMIN->add('theme_tikli', $temp);
}
