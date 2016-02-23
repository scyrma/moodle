<?php
// Get the HTML for the settings bits.
$html = theme_school_get_html_for_settings($OUTPUT, $PAGE);
global $USER, $PAGE, $CFG, $DB;

echo $OUTPUT->doctype();

$isregistration = $DB->get_record('config', array('name'=>'registerauth'));
?>

<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php
        echo $OUTPUT->standard_theme_head_html();
        echo $OUTPUT->standard_head_html()
    ?>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
    <?php echo $OUTPUT->standard_top_of_body_html() ?>
    <header role="banner" class="navbar navbar-fixed-top">
        <nav role="navigation" class="navbar-inner">
            <?php echo $OUTPUT->logo(); ?>

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
