<?php
// Get the HTML for the settings bits.
$html = theme_tikli_get_html_for_settings($OUTPUT, $PAGE);
global $USER, $PAGE, $CFG, $DB;

$logo = $PAGE->theme->setting_file_url('logo', 'logo');
if(empty($logo)) {
    $logo = $CFG->wwwroot.'/theme/tikli/pix/logo-2.png';
}

$iconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
if(empty($iconlogo)) {
    $iconlogo = $CFG->wwwroot.'/theme/tikli/pix/icon-logo.png';
}

echo $OUTPUT->doctype();

$isregistration = $DB->get_record('config', array('name'=>'registerauth'));
?>

<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php
        echo $OUTPUT->standard_theme_head_html();
        include($CFG->dirroot . '/theme/tikli/settings/colorchange.php');
        echo $OUTPUT->standard_head_html()
    ?>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
    <?php echo $OUTPUT->standard_top_of_body_html() ?>
    <header role="banner" class="navbar navbar-fixed-top">
        <nav role="navigation" class="navbar-inner">
            <?php if (get_config('theme_tikli', 'logoorsitename') === "logo") { ?>
                <div class="logo-wr">
                    <a class="logo-img" href="<?php echo $CFG->wwwroot; ?>">
                        <img alt="logo" src="<?php echo $logo;?>" />
                    </a>
                </div>
            <?php } else if (get_config('theme_tikli', 'logoorsitename') === "sitename") { ?>
                <div class="logo-wr">
                    <a class="logo-img" href="<?php echo $CFG->wwwroot; ?>">
                        <h1><?php echo $SITE->fullname; ?></h1>
                    </a>
                </div>
            <?php } else if (get_config('theme_tikli', 'logoorsitename') === "iconsitename") { ?>
                <div class="logo-wr">
                    <a class="logo-img" href="<?php echo $CFG->wwwroot; ?>">
                        <h1><span class="logoicon"><img alt="logo" src="<?php echo $iconlogo;?>" /></span><span><?php echo $SITE->fullname; ?></span></h1>
                    </a>
                </div>
            <?php } ?>

            <button class="side-pre-menu menu-toggle">
                <span>toggle menu</span>
            </button>

            <?php echo $OUTPUT->user_menu(); ?>

            <?php if (isloggedin() && !empty($CFG->custommenuitems)) { ?>
                <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                    <i class="fa fa-arrow-circle-down"></i>
                </a>
                <div class="nav-collapse collapse">
                    <?php echo $OUTPUT->custom_menu();?>
                </div>
            <?php } ?>
        </nav>
    </header>
