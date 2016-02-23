<?php
defined('MOODLE_INTERNAL') || die;
$settings = null;
if (is_siteadmin()) {
    $ADMIN->add('themes', new admin_category('theme_school', 'school'));
    $temp = new admin_settingpage('theme_school_general',  get_string('generalsettings', 'theme_school'));

    $name = 'theme_school/logoorsitename';
    $title = get_string('logoorsitename', 'theme_school');
    $description = get_string('logoorsitenamedesc', 'theme_school');
    $default = 'sitename';
    $setting = new admin_setting_configselect($name, $title, $description, $default, array(
        'logo' => get_string('onlylogo', 'theme_school'),
        'sitename' => get_string('onlysitename', 'theme_school'),
        'iconsitename' => get_string('iconsitename', 'theme_school')
    ));
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    if (get_config('theme_school', 'logoorsitename') === "logo") {
        // Logo file setting.
        $name = 'theme_school/logo';
        $title = get_string('logo','theme_school');
        $description = get_string('logodesc', 'theme_school');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'logo');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    } else if (get_config('theme_school', 'logoorsitename') === "iconsitename") {
        // Logo file setting.
        $name = 'theme_school/icon';
        $title = get_string('logoicon','theme_school');
        $description = get_string('logoicondesc', 'theme_school');
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'icon');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    }

    //custom favicon temp
    $name = 'theme_school/faviconurl';
    $title = get_string('favicon', 'theme_school');
    $description = get_string('favicondesc', 'theme_school');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'faviconurl');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Custom CSS file.
    $name = 'theme_school/customcss';
    $title = get_string('customcss', 'theme_school');
    $description = get_string('customcssdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Footnote setting.
    $name = 'theme_school/footnote';
    $title = get_string('rightfootnote', 'theme_school');
    $description = get_string('rightfootnotedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnote';
    $title = get_string('leftfootnote', 'theme_school');
    $description = get_string('leftfootnotedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesection1';
    $title = get_string('leftfootnotesection1', 'theme_school');
    $description = get_string('leftfootnotedescsection1', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesectionlink1';
    $title = get_string('leftfootnotesectionlink1', 'theme_school');
    $description = get_string('leftfootnotelinkdescsection1', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesection2';
    $title = get_string('leftfootnotesection2', 'theme_school');
    $description = get_string('leftfootnotedescsection2', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesectionlink2';
    $title = get_string('leftfootnotesectionlink2', 'theme_school');
    $description = get_string('leftfootnotelinkdescsection2', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesection3';
    $title = get_string('leftfootnotesection3', 'theme_school');
    $description = get_string('leftfootnotedescsection3', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesectionlink3';
    $title = get_string('leftfootnotesectionlink3', 'theme_school');
    $description = get_string('leftfootnotelinkdescsection3', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesection4';
    $title = get_string('leftfootnotesection4', 'theme_school');
    $description = get_string('leftfootnotedescsection4', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesectionlink4';
    $title = get_string('leftfootnotesectionlink4', 'theme_school');
    $description = get_string('leftfootnotelinkdescsection4', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesection5';
    $title = get_string('leftfootnotesection5', 'theme_school');
    $description = get_string('leftfootnotedescsection5', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesectionlink5';
    $title = get_string('leftfootnotesectionlink5', 'theme_school');
    $description = get_string('leftfootnotelinkdescsection5', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesection6';
    $title = get_string('leftfootnotesection6', 'theme_school');
    $description = get_string('leftfootnotedescsection6', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/leftfootnotesectionlink6';
    $title = get_string('leftfootnotesectionlink6', 'theme_school');
    $description = get_string('leftfootnotelinkdescsection6', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $ADMIN->add('theme_school', $temp);


    //frontpage temp
    $temp = new admin_settingpage('theme_school_frontpage',  get_string('frontpagesettings', 'theme_school'));
    $temp->add(new admin_setting_heading('theme_school_upsection', get_string('frontpageimagecontent', 'theme_school'),
        format_text(get_string('frontpageimagecontentdesc', 'theme_school'), FORMAT_MARKDOWN)));
    $name = 'theme_school/frontpageimagecontent';
    $title = get_string('frontpageimagecontentstyle', 'theme_school');
    $description = get_string('frontpageimagecontentstyledesc', 'theme_school');
    $setting = new admin_setting_configselect($name, $title, $description, 0,
    array(
            0 => get_string('staticcontent', 'theme_school'),
            1 => get_string('slidercontent', 'theme_school'),
        ));
    $temp->add($setting);
    if (get_config('theme_school', 'frontpageimagecontent') === "0") {
        $name = 'theme_school/addtext';
        $title = get_string('addtext', 'theme_school');
        $description = get_string('addtextdesc', 'theme_school');
        $default = '';
        $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_school/videotype';
        $title = get_string('videotype', 'theme_school');
        $description = get_string('videotypedesc', 'theme_school');
        $setting = new admin_setting_configselect($name, $title, $description, 0,
        array(
            0 => get_string('iframe', 'theme_school'),
            1 => get_string('upload', 'theme_school'),
        ));
        $temp->add($setting);
        if (get_config('theme_school', 'videotype') === "0") {
            $name = 'theme_school/video';
            $title = get_string('video', 'theme_school');
            $description = get_string('videodesc', 'theme_school');
            $default = '';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        } elseif (get_config('theme_school', 'videotype') === "1") {
            $name = 'theme_school/uploadvideo';
            $title = get_string('uploadvideo','theme_school');
            $description = get_string('uploadvideodesc', 'theme_school');
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'uploadvideo', $itemid = 0, array(
			'accepted_types' => '.mp4'
			));
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }

        $name = 'theme_school/frontpagevideoalignment';
        $title = get_string('frontpagevideoalignment', 'theme_school');
        $description = get_string('frontpagevideoalignmentdesc', 'theme_school');
        $setting = new admin_setting_configselect($name, $title, $description, 1,
        array(
            0 => get_string('videoleft', 'theme_school'),
            1 => get_string('videoright', 'theme_school'),
        ));
        $temp->add($setting);
    } else if (get_config('theme_school', 'frontpageimagecontent') === "1"){
        $name = 'theme_school/slideinterval';
        $title = get_string('slideinterval', 'theme_school');
        $description = get_string('slideintervaldesc', 'theme_school');
        $default = 5000;
        $setting = new admin_setting_configtext($name, $title, $description, $default);
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        $name = 'theme_school/sliderautoplay';
        $title = get_string('sliderautoplay', 'theme_school');
        $description = get_string('sliderautoplaydesc', 'theme_school');
        $setting = new admin_setting_configselect($name, $title, $description, 1,
        array(
                1 => get_string('true', 'theme_school'),
                2 => get_string('false', 'theme_school'),
            ));
        $temp->add($setting);

        $name = 'theme_school/slidercount';
        $title = get_string('slidercount', 'theme_school');
        $description = get_string('slidercountdesc', 'theme_school');
        $setting = new admin_setting_configselect($name, $title, $description, 1,
        array(
                1 => get_string('one', 'theme_school'),
                2 => get_string('two', 'theme_school'),
                3 => get_string('three', 'theme_school'),
                4 => get_string('four', 'theme_school'),
                5 => get_string('five', 'theme_school'),
            ));
        $temp->add($setting);

        for($slidecounts = 1; $slidecounts <= get_config('theme_school', 'slidercount'); $slidecounts = $slidecounts + 1) {
            $name = 'theme_school/slideimage'.$slidecounts;
            $title = get_string('slideimage', 'theme_school', $slidecounts);

            $description = get_string('slideimagedesc', 'theme_school', $slidecounts);
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'slideimage'.$slidecounts);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_school/slidertitle'.$slidecounts;
            $title = get_string('slidertitle', 'theme_school', $slidecounts);
            $description = get_string('slidertitledesc', 'theme_school');
            $default = '';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_school/slidertext'.$slidecounts;
            $title = get_string('slidertext', 'theme_school', $slidecounts);
            $description = get_string('slidertextdesc', 'theme_school');
            $default = '';
            $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_school/sliderbuttontext'.$slidecounts;
            $title = get_string('sliderbuttontext', 'theme_school', $slidecounts);
            $description = get_string('sliderbuttontextdesc', 'theme_school');
            $default = '';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);

            $name = 'theme_school/sliderurl'.$slidecounts;
            $title = get_string('sliderurl', 'theme_school', $slidecounts);
            $description = get_string('sliderurldesc', 'theme_school');
            $default = '';
            $setting = new admin_setting_configtext($name, $title, $description, $default);
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }
    }

    $temp->add(new admin_setting_heading('theme_school_coursequicklinks', get_string('coursequicklinks', 'theme_school'),
        format_text(get_string('coursequicklinksdesc', 'theme_school'), FORMAT_MARKDOWN)));

    $name = 'theme_school/coursesectionheading';
    $title = get_string('coursesectionheading', 'theme_school');
    $description = get_string('coursesectionheadingdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/coursesectionsubheading';
    $title = get_string('coursesectionsubheading', 'theme_school');
    $description = get_string('coursesectionsubheadingdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/coursesectionoverview';
    $title = get_string('coursesectionoverview', 'theme_school');
    $description = get_string('coursesectionoverview', 'theme_school');
    $default = "";
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $temp->add(new admin_setting_heading('theme_school_feedbacksection', get_string('feedback', 'theme_school'),
    format_text(get_string('feedbackdesc', 'theme_school'), FORMAT_MARKDOWN)));

    $name = 'theme_school/feedbackheading';
    $title = get_string('feedbackheading', 'theme_school');
    $description = get_string('feedbackheadingdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbacksubheading';
    $title = get_string('feedbacksubheading', 'theme_school');
    $description = get_string('feedbacksubheadingdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackiframe';
    $title = get_string('feedbackiframe', 'theme_school');
    $description = get_string('feedbackiframedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackbrieftext';
    $title = get_string('feedbackbrieftext', 'theme_school');
    $description = get_string('feedbackbrieftextdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslideimage_1';
    $title = get_string('feedbackslideimage', 'theme_school');
    $description = get_string('feedbackslideimagedesc', 'theme_school');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_1');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidename_1';
    $title = get_string('feedbackslidename', 'theme_school');
    $description = get_string('feedbackslidenamedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidereview_1';
    $title = get_string('feedbackslidereview', 'theme_school');
    $description = get_string('feedbackslidereviewdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslideimage_2';
    $title = get_string('feedbackslideimage', 'theme_school');
    $description = get_string('feedbackslideimagedesc', 'theme_school');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_2');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidename_2';
    $title = get_string('feedbackslidename', 'theme_school');
    $description = get_string('feedbackslidenamedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidereview_2';
    $title = get_string('feedbackslidereview', 'theme_school');
    $description = get_string('feedbackslidereviewdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslideimage_3';
    $title = get_string('feedbackslideimage', 'theme_school');
    $description = get_string('feedbackslideimagedesc', 'theme_school');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_3');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidename_3';
    $title = get_string('feedbackslidename', 'theme_school');
    $description = get_string('feedbackslidenamedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidereview_3';
    $title = get_string('feedbackslidereview', 'theme_school');
    $description = get_string('feedbackslidereviewdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslideimage_4';
    $title = get_string('feedbackslideimage', 'theme_school');
    $description = get_string('feedbackslideimagedesc', 'theme_school');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'feedbackslideimage_4');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidename_4';
    $title = get_string('feedbackslidename', 'theme_school');
    $description = get_string('feedbackslidenamedesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_confightmleditor($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/feedbackslidereview_4';
    $title = get_string('feedbackslidereview', 'theme_school');
    $description = get_string('feedbackslidereviewdesc', 'theme_school');
    $default = '';
    $setting = new admin_setting_configtextarea($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $ADMIN->add('theme_school', $temp);

    $temp = new admin_settingpage('theme_school_colors',  get_string('colorsettings', 'theme_school'));

    /* COLOUR SETTINGS */

    /* Primary Colour */
    $name = 'theme_school/primarycolour';
    $title = get_string('primarycolour', 'theme_school');
    $description = get_string('primarycolourdesc', 'theme_school');
    $default = '#3498db';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/primaryfontcolour';
    $title = get_string('primaryfontcolour', 'theme_school');
    $description = get_string('primaryfontcolourdesc', 'theme_school');
    $default = '#ffffff';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/primarylinkcolour';
    $title = get_string('primarylinkcolour', 'theme_school');
    $description = get_string('primarylinkcolourdesc', 'theme_school');
    $default = '#ffffff';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /* Secondary Colour */

    $name = 'theme_school/secondarycolour';
    $title = get_string('secondarycolour', 'theme_school');
    $description = get_string('secondarycolourdesc', 'theme_school');
    $default = '#f39c11';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/secondaryfontcolour';
    $title = get_string('secondaryfontcolour', 'theme_school');
    $description = get_string('secondaryfontcolourdesc', 'theme_school');
    $default = '#ffffff';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/secondarylinkcolour';
    $title = get_string('secondarylinkcolour', 'theme_school');
    $description = get_string('secondarylinkcolourdesc', 'theme_school');
    $default = '#ffffff';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /* Footer Colour */

    $name = 'theme_school/footercolour';
    $title = get_string('footercolour', 'theme_school');
    $description = get_string('footercolourdesc', 'theme_school');
    $default = '#242b32';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/footerfontcolour';
    $title = get_string('footerfontcolour', 'theme_school');
    $description = get_string('footerfontcolourdesc', 'theme_school');
    $default = '#bdc3c7';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $name = 'theme_school/footerlinkcolour';
    $title = get_string('footerlinkcolour', 'theme_school');
    $description = get_string('footerlinkcolourdesc', 'theme_school');
    $default = '#3498db';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /* Block colour */

    $name = 'theme_school/blocklinkcolour';
    $title = get_string('blocklinkcolour', 'theme_school');
    $description = get_string('blocklinkcolourdesc', 'theme_school');
    $default = '#3498db';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    /* Main content colour */

    $name = 'theme_school/mainlinkcolour';
    $title = get_string('mainlinkcolour', 'theme_school');
    $description = get_string('mainlinkcolourdesc', 'theme_school');
    $default = '#3498db';
    $setting = new admin_setting_configcolourpicker($name, $title, $description, $default, null, false);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    $ADMIN->add('theme_school', $temp);

    /*font*/

    $temp = new admin_settingpage('theme_school_font',  get_string('fontsettings', 'theme_school'));
    $name = 'theme_school/fontselect';
    $title = get_string('fontselect', 'theme_school');
    $description = get_string('fontselectdesc', 'theme_school');
    $default = 1;
    $choices = array(
        1 => get_string('fonttypestandard', 'theme_school'),
        2 => get_string('fonttypecustom', 'theme_school'),
    );
    $setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);
    // Heading font name
    $name = 'theme_school/fontnameheading';
    $title = get_string('fontnameheading', 'theme_school');
    $description = get_string('fontnameheadingdesc', 'theme_school');
    $default = 'OpenSans';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    // Text font name

    $name = 'theme_school/fontnamebody';
    $title = get_string('fontnamebody', 'theme_school');
    $description = get_string('fontnamebodydesc', 'theme_school');
    $default = 'Raleway';
    $setting = new admin_setting_configtext($name, $title, $description, $default);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $temp->add($setting);

    if (get_config('theme_school', 'fontselect') === "2") {

        if (floatval($CFG->version) >= 2014111005.01) {
            $woff2 = true;
        } else {
            $woff2 = false;
        }

        // This is the descriptor for the font files
        $name = 'theme_school/fontfiles';
        $heading = get_string('fontfiles', 'theme_school');
        $information = get_string('fontfilesdesc', 'theme_school');
        $setting = new admin_setting_heading($name, $heading, $information);
        $temp->add($setting);

        // Heading Fonts.
        // TTF Font.
        $name = 'theme_school/fontfilettfheading';
        $title = get_string('fontfilettfheading', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilettfheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // OTF Font.
        $name = 'theme_school/fontfileotfheading';
        $title = get_string('fontfileotfheading', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileotfheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // WOFF Font.
        $name = 'theme_school/fontfilewoffheading';
        $title = get_string('fontfilewoffheading', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewoffheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        if ($woff2) {
            // WOFF2 Font.
            $name = 'theme_school/fontfilewofftwoheading';
            $title = get_string('fontfilewofftwoheading', 'theme_school');
            $description = '';
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewofftwoheading');
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }

        // EOT Font.
        $name = 'theme_school/fontfileeotheading';
        $title = get_string('fontfileeotheading', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileweotheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // SVG Font.
        $name = 'theme_school/fontfilesvgheading';
        $title = get_string('fontfilesvgheading', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilesvgheading');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // Body fonts.
        // TTF Font.
        $name = 'theme_school/fontfilettfbody';
        $title = get_string('fontfilettfbody', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilettfbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // OTF Font.
        $name = 'theme_school/fontfileotfbody';
        $title = get_string('fontfileotfbody', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileotfbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // WOFF Font.
        $name = 'theme_school/fontfilewoffbody';
        $title = get_string('fontfilewoffbody', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewoffbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        if ($woff2) {
            // WOFF2 Font.
            $name = 'theme_school/fontfilewofftwobody';
            $title = get_string('fontfilewofftwobody', 'theme_school');
            $description = '';
            $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilewofftwobody');
            $setting->set_updatedcallback('theme_reset_all_caches');
            $temp->add($setting);
        }

        // EOT Font.
        $name = 'theme_school/fontfileeotbody';
        $title = get_string('fontfileeotbody', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfileweotbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);

        // SVG Font.
        $name = 'theme_school/fontfilesvgbody';
        $title = get_string('fontfilesvgbody', 'theme_school');
        $description = '';
        $setting = new admin_setting_configstoredfile($name, $title, $description, 'fontfilesvgbody');
        $setting->set_updatedcallback('theme_reset_all_caches');
        $temp->add($setting);
    }
    $ADMIN->add('theme_school', $temp);
}
