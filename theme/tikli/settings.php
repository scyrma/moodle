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

        $name = 'theme_tikli/addlink';
        $title = get_string('addlink', 'theme_tikli');
        $description = get_string('addlinkdesc', 'theme_tikli');
        $default = 'javascript:void(0);';
        $setting = new admin_setting_configtext($name, $title, $description, $default);
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
            $default = '<iframe src="https://player.vimeo.com/video/45232468" width="560" height="300" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';
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
        $setting = new admin_setting_configselect($name, $title, $description, 0,
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

    $temp->add(new admin_setting_heading('theme_tikli_blocksection', get_string('frontpageblocks', 'theme_tikli'),
        format_text(get_string('frontpageblocksdesc', 'theme_tikli'), FORMAT_MARKDOWN)));

    $name = 'theme_tikli/frontpageblockheading';
    $title = get_string('frontpageblockheading', 'theme_tikli');
    $description = get_string('frontpageblockheadingdesc', 'theme_tikli');
    $default = 'News &amp; Updates';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblock';
    $title = get_string('frontpageblock', 'theme_tikli');
    $description = get_string('frontpageblockdesc', 'theme_tikli');
    $default = 'See all announcements';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblocklink';
    $title = get_string('frontpageblocklink', 'theme_tikli');
    $description = get_string('frontpageblocklinkdesc', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /*block section 1*/
    $name = 'theme_tikli/frontpageblocksection1';
    $title = get_string('frontpageblocksection1', 'theme_tikli');
    $description = get_string('frontpageblocksectiondesc1', 'theme_tikli');
    $default = "Ahead of World Teachers' Day, UN highlights education challenges:";
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblocklinksection1';
    $title = get_string('frontpageblocklinksection1', 'theme_tikli');
    $description = get_string('frontpageblocklinksectiondesc1', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblockdescriptionsection1';
    $title = get_string('frontpageblockdescriptionsection1', 'theme_tikli');
    $description = get_string('frontpageblockdescriptionsectiondesc1', 'theme_tikli');
    $default = 'Friday, July 27, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /*block section 2*/
    $name = 'theme_tikli/frontpageblocksection2';
    $title = get_string('frontpageblocksection2', 'theme_tikli');
    $description = get_string('frontpageblocksectiondesc2', 'theme_tikli');
    $default = "MIT launches first ever free online courses that could lead to degree:";
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblocklinksection2';
    $title = get_string('frontpageblocklinksection2', 'theme_tikli');
    $description = get_string('frontpageblocklinksectiondesc2', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblockdescriptionsection2';
    $title = get_string('frontpageblockdescriptionsection2', 'theme_tikli');
    $description = get_string('frontpageblockdescriptionsectiondesc2', 'theme_tikli');
    $default = 'Monday, August 15, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /*block section 3*/
    $name = 'theme_tikli/frontpageblocksection3';
    $title = get_string('frontpageblocksection3', 'theme_tikli');
    $description = get_string('frontpageblocksectiondesc3', 'theme_tikli');
    $default = 'Essentials for Effective Online Courses in K-12:';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblocklinksection3';
    $title = get_string('frontpageblocklinksection3', 'theme_tikli');
    $description = get_string('frontpageblocklinksectiondesc3', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/frontpageblockdescriptionsection3';
    $title = get_string('frontpageblockdescriptionsection3', 'theme_tikli');
    $description = get_string('frontpageblockdescriptionsectiondesc3', 'theme_tikli');
    $default = 'Saturday, June 5, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $temp->add(new admin_setting_heading('theme_tikli_downsection', get_string('marketspot', 'theme_tikli'),
        format_text(get_string('marketspotdesc', 'theme_tikli'), FORMAT_MARKDOWN)));

    $name = 'theme_tikli/marketspotfontawesomeicon';
    $title = get_string('marketspotfontawesomeicon', 'theme_tikli');
    $description = get_string('marketspotfontawesomeicondesc', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'marketspotfontawesomeicon');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/marketspot';
    $title = get_string('marketspothead', 'theme_tikli');
    $description = get_string('marketspotheaddesc', 'theme_tikli');
    $default = 'Forum Discussion';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);


    $name = 'theme_tikli/marketspotsectionfontawesomeicon1';
    $title = get_string('marketspotsectionfontawesomeicon1', 'theme_tikli');
    $description = get_string('marketspotsectionfontawesomeicondesc1', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'marketspotsectionfontawesomeicon1');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsection1';
    $title = get_string('marketspotsection1', 'theme_tikli');
    $description = get_string('marketspotsectiondesc1', 'theme_tikli');
    $default = 'Best online education practises';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionlink1';
    $title = get_string('marketspotsectionlink1', 'theme_tikli');
    $description = get_string('marketspotsectionlinkdesc1', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectiontext1';
    $title = get_string('marketspotsectiontext1', 'theme_tikli');
    $description = get_string('marketspotsectiontextdesc1', 'theme_tikli');
    $default = 'John Smith - Friday, July 27, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionfontawesomeicon2';
    $title = get_string('marketspotsectionfontawesomeicon2', 'theme_tikli');
    $description = get_string('marketspotsectionfontawesomeicondesc2', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'marketspotsectionfontawesomeicon2');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsection2';
    $title = get_string('marketspotsection2', 'theme_tikli');
    $description = get_string('marketspotsectiondesc2', 'theme_tikli');
    $default = 'Re: Which is a better option among the three - Sociology , Philosophy and Sports nutrition.';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionlink2';
    $title = get_string('marketspotsectionlink2', 'theme_tikli');
    $description = get_string('marketspotsectionlinkdesc2', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectiontext2';
    $title = get_string('marketspotsectiontext2', 'theme_tikli');
    $description = get_string('marketspotsectiontextdesc2', 'theme_tikli');
    $default = 'John Doe - Monday, August 15, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionfontawesomeicon3';
    $title = get_string('marketspotsectionfontawesomeicon3', 'theme_tikli');
    $description = get_string('marketspotsectionfontawesomeicondesc3', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'marketspotsectionfontawesomeicon3');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/marketspotsection3';
    $title = get_string('marketspotsection3', 'theme_tikli');
    $description = get_string('marketspotsectiondesc3', 'theme_tikli');
    $default = 'Re: The Payment gateway is very extensive.';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionlink3';
    $title = get_string('marketspotsectionlink3', 'theme_tikli');
    $description = get_string('marketspotsectionlinkdesc3', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectiontext3';
    $title = get_string('marketspotsectiontext3', 'theme_tikli');
    $description = get_string('marketspotsectiontextdesc3', 'theme_tikli');
    $default = 'Murali Vijay - Saturday, June 5, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionfontawesomeicon4';
    $title = get_string('marketspotsectionfontawesomeicon4', 'theme_tikli');
    $description = get_string('marketspotsectionfontawesomeicondesc4', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'marketspotsectionfontawesomeicon4');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/marketspotsection4';
    $title = get_string('marketspotsection4', 'theme_tikli');
    $description = get_string('marketspotsectiondesc4', 'theme_tikli');
    $default = 'Illustration practises need a more broader approach.';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionlink4';
    $title = get_string('marketspotsectionlink4', 'theme_tikli');
    $description = get_string('marketspotsectionlinkdesc4', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectiontext4';
    $title = get_string('marketspotsectiontext4', 'theme_tikli');
    $description = get_string('marketspotsectiontextdesc4', 'theme_tikli');
    $default = 'Lisa Abott - Thursday, January 26, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionbelowlink';
    $title = get_string('marketspotsectionbelowlink', 'theme_tikli');
    $description = get_string('marketspotsectionbelowlinkdesc', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/marketspotsectionbelowlinkname';
    $title = get_string('marketspotsectionbelowlinkname', 'theme_tikli');
    $description = get_string('marketspotsectionbelowlinknamedesc', 'theme_tikli');
    $default = 'View all discussion';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $temp->add(new admin_setting_heading('theme_tikli_anothersection', get_string('secondmarketspot', 'theme_tikli'),
        format_text(get_string('secondmarketspotdesc', 'theme_tikli'), FORMAT_MARKDOWN)));

    $name = 'theme_tikli/secondmarketspotfontawesomeicon';
    $title = get_string('secondmarketspotfontawesomeicon', 'theme_tikli');
    $description = get_string('secondmarketspotfontawesomeicondesc', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'secondmarketspotfontawesomeicon');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/secondmarketspot';
    $title = get_string('secondmarketspot', 'theme_tikli');
    $description = get_string('secondmarketspotdesc', 'theme_tikli');
    $default = 'DOWNLOAD RESOURCES';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionfontawesomeicon1';
    $title = get_string('secondmarketspotsectionfontawesomeicon1', 'theme_tikli');
    $description = get_string('secondmarketspotsectionfontawesomeicondesc1', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'secondmarketspotsectionfontawesomeicon1');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsection1';
    $title = get_string('secondmarketspotsection1', 'theme_tikli');
    $description = get_string('secondmarketspotsectiondesc1', 'theme_tikli');
    $default = 'Plugins: Etherpad Lite';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionlink1';
    $title = get_string('secondmarketspotsectionlink1', 'theme_tikli');
    $description = get_string('secondmarketspotsectionlinkdesc1', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectiontext1';
    $title = get_string('secondmarketspotsectiontext1', 'theme_tikli');
    $description = get_string('secondmarketspotsectiontextdesc1', 'theme_tikli');
    $default = 'Itamar Tzadok - Thursday, June 4, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionfontawesomeicon2';
    $title = get_string('secondmarketspotsectionfontawesomeicon2', 'theme_tikli');
    $description = get_string('secondmarketspotsectionfontawesomeicondesc2', 'theme_tikli');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'secondmarketspotsectionfontawesomeicon2');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/secondmarketspotsection2';
    $title = get_string('secondmarketspotsection2', 'theme_tikli');
    $description = get_string('secondmarketspotsectiondesc2', 'theme_tikli');
    $default = 'Plugins: LenAuth';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionlink3';
    $title = get_string('secondmarketspotsectionlink3', 'theme_tikli');
    $description = get_string('secondmarketspotsectionlinkdesc3', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectiontext2';
    $title = get_string('secondmarketspotsectiontext2', 'theme_tikli');
    $description = get_string('secondmarketspotsectiontextdesc2', 'theme_tikli');
    $default = 'Itamar Tzadok - Thursday, June 4, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionfontawesomeicon3';
    $title = get_string('secondmarketspotsectionfontawesomeicon3', 'theme_tikli');
    $description = get_string('secondmarketspotsectionfontawesomeicondesc3', 'theme_tikli');
    $default = 'secondmarketspotsectionfontawesomeicon3';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsection3';
    $title = get_string('secondmarketspotsection3', 'theme_tikli');
    $description = get_string('secondmarketspotsectiondesc3', 'theme_tikli');
    $default = 'Course Format Development Enhanced Topic Format Contract';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionlink3';
    $title = get_string('secondmarketspotsectionlink3', 'theme_tikli');
    $description = get_string('secondmarketspotsectionlinkdesc3', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectiontext3';
    $title = get_string('secondmarketspotsectiontext3', 'theme_tikli');
    $description = get_string('secondmarketspotsectiontextdesc3', 'theme_tikli');
    $default = 'Itamar Tzadok - Thursday, June 4, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionfontawesomeicon4';
    $title = get_string('secondmarketspotsectionfontawesomeicon4', 'theme_tikli');
    $description = get_string('secondmarketspotsectionfontawesomeicondesc4', 'theme_tikli');
    $default = 'secondmarketspotsectionfontawesomeicon4';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/secondmarketspotsection4';
    $title = get_string('secondmarketspotsection4', 'theme_tikli');
    $description = get_string('secondmarketspotsectiondesc4', 'theme_tikli');
    $default = 'Jobs: LMS provider for State of Michigan - Michigan, USA';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionlink4';
    $title = get_string('secondmarketspotsectionlink4', 'theme_tikli');
    $description = get_string('secondmarketspotsectionlinkdesc4', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectiontext4';
    $title = get_string('secondmarketspotsectiontext4', 'theme_tikli');
    $description = get_string('secondmarketspotsectiontextdesc4', 'theme_tikli');
    $default = 'Itamar Tzadok - Thursday, June 4, 2015';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionbelowlink';
    $title = get_string('secondmarketspotsectionbelowlink', 'theme_tikli');
    $description = get_string('secondmarketspotsectionbelowlinkdesc', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/secondmarketspotsectionbelowlinkname';
    $title = get_string('secondmarketspotsectionbelowlinkname', 'theme_tikli');
    $description = get_string('secondmarketspotsectionbelowlinknamedesc', 'theme_tikli');
    $default = 'View all resources';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $temp->add(new admin_setting_heading('theme_tikli_nextsection', get_string('contactwithus', 'theme_tikli'),
        format_text(get_string('contactwithusdesc', 'theme_tikli'), FORMAT_MARKDOWN)));
    //contact with us
    $name = 'theme_tikli/contactwithusfontawesomeicon';
    $title = get_string('contactwithusfontawesomeicon', 'theme_tikli');
    $description = get_string('contactwithusfontawesomeicondesc', 'theme_tikli');
    $default = 'contactwithusfontawesomeicon';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/contactwithus';
    $title = get_string('contactwithusheading', 'theme_tikli');
    $description = get_string('contactwithusheadingdesc', 'theme_tikli');
    $default = 'Contact With Us';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/socialfontawesomeicon1';
    $title = get_string('socialfontawesomeicon1', 'theme_tikli');
    $description = get_string('socialfontawesomeicondesc1', 'theme_tikli');
    $default = 'socialfontawesomeicon1';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/socialicon1';
    $title = get_string('socialicon', 'theme_tikli');
    $description = get_string('socialicondesc', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/socialfontawesomeicon2';
    $title = get_string('socialfontawesomeicon2', 'theme_tikli');
    $description = get_string('socialfontawesomeicondesc2', 'theme_tikli');
    $default = 'socialfontawesomeicon2';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/socialicon2';
    $title = get_string('socialicon', 'theme_tikli');
    $description = get_string('socialicondesc', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/socialfontawesomeicon3';
    $title = get_string('socialfontawesomeicon3', 'theme_tikli');
    $description = get_string('socialfontawesomeicondesc3', 'theme_tikli');
    $default = 'socialfontawesomeicon3';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/socialicon3';
    $title = get_string('socialicon', 'theme_tikli');
    $description = get_string('socialicondesc', 'theme_tikli');
    $default = 'socialicon';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    $name = 'theme_tikli/socialfontawesomeicon4';
    $title = get_string('socialfontawesomeicon4', 'theme_tikli');
    $description = get_string('socialfontawesomeicondesc4', 'theme_tikli');
    $default = 'socialfontawesomeicon4';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/socialicon4';
    $title = get_string('socialicon', 'theme_tikli');
    $description = get_string('socialicondesc', 'theme_tikli');
    $default = 'javascript:void(0);';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    $ADMIN->add('theme_tikli', $temp);

    $name = 'theme_tikli/addressfontawesomeicon';
    $title = get_string('addressfontawesomeicon', 'theme_tikli');
    $description = get_string('addressfontawesomeicondesc', 'theme_tikli');
    $default = 'addressfontawesomeicon';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/address';
    $title = get_string('address', 'theme_tikli');
    $description = get_string('addressdesc', 'theme_tikli');
    $default = 'BB 164, Salt Lake Sector 1 Kolkata 700064 West Bengal, India';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/phonefontawesomeicon';
    $title = get_string('phonefontawesomeicon', 'theme_tikli');
    $description = get_string('phonefontawesomeicondesc', 'theme_tikli');
    $default = 'phonefontawesomeicon';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/phone';
    $title = get_string('phone', 'theme_tikli');
    $description = get_string('phonedesc', 'theme_tikli');
    $default = '+91 33 64578322';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/emailfontawesomeicon';
    $title = get_string('emailfontawesomeicon', 'theme_tikli');
    $description = get_string('emailfontawesomeicondesc', 'theme_tikli');
    $default = 'emailfontawesomeicon';
    $setting = new admin_setting_configstoredfile($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_tikli/email';
    $title = get_string('email', 'theme_tikli');
    $description = get_string('emaildesc', 'theme_tikli');
    $default = 'tikli@dualcube.com';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    //$ADMIN->add('theme_tikli', $temp);

    //usernavbar setting
    $temp = new admin_settingpage('theme_tikli_user_nav',  get_string('usernavsettings', 'theme_tikli'));
    //temp for user navigation
    // Enable My.
    $name = 'theme_tikli/enablemy';
    $title = get_string('enablemy', 'theme_tikli');
    $description = get_string('enablemydesc', 'theme_tikli');
    $default = true;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default, true, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    
    // Enable View Profile.
    $name = 'theme_tikli/enableprofile';
    $title = get_string('enableprofile', 'theme_tikli');
    $description = get_string('enableprofiledesc', 'theme_tikli');
    $default = true;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default, true, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    

    // Enable Private Files.
    $name = 'theme_tikli/enableprivatefiles';
    $title = get_string('enableprivatefiles', 'theme_tikli');
    $description = get_string('enableprivatefilesdesc', 'theme_tikli');
    $default = false;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default, true, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Enable Badges.
    $name = 'theme_tikli/enablebadges';
    $title = get_string('enablebadges', 'theme_tikli');
    $description = get_string('enablebadgesdesc', 'theme_tikli');
    $default = false;
    $setting = new admin_setting_configcheckbox($name, $title, $description, $default, true, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    $ADMIN->add('theme_tikli', $temp);
    $temp = new admin_settingpage('theme_tikli_colors',  get_string('colorsettings', 'theme_tikli'));

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

    /* Analytics temp */
    $temp = new admin_settingpage('theme_tikli_analytics', get_string('analytics', 'theme_tikli'));
    $temp->add(new admin_setting_heading('theme_tikli_analytics', get_string('analyticsheadingsub', 'theme_tikli'),
        format_text(get_string('analyticsdesc', 'theme_tikli'), FORMAT_MARKDOWN)));

    $name = 'theme_tikli/analyticstrackingid';
    $title = get_string('analyticstrackingid', 'theme_tikli');
    $description = get_string('analyticstrackingiddesc', 'theme_tikli');
    $default = 'UA-XXXXXXXX-X';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $temp->add($setting);

    $name = 'theme_tikli/analyticstrackingscript';
    $title = get_string('analyticstrackingscript', 'theme_tikli');
    $description = get_string('analyticstrackingscriptdesc', 'theme_tikli');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    $ADMIN->add('theme_tikli', $temp);

}
