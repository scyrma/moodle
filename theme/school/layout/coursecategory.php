<?php
// Get the HTML for the settings bits.
$html = theme_school_get_html_for_settings($OUTPUT, $PAGE);
global $DB, $CFG;

$categoryid = optional_param('categoryid', 0, PARAM_INT); // Category id

// Set default (LTR) layout mark-up for a three column page.
$regionmainbox = 'span9';
$regionmain = 'span8 pull-right';
$sidepre = 'span4 desktop-first-column';
$sidepost = 'span3 pull-right';
// Reset layout mark-up for RTL languages.
if (right_to_left()) {
    $regionmainbox = 'span9 pull-right';
    $regionmain = 'span8';
    $sidepre = 'span4 pull-right';
    $sidepost = 'span3 desktop-first-column';
}

if ($CFG->forcelogin) {
    require_login();
}

?>
<?php require('header.php'); ?>
<div id="page" class="container-fluid course-category">
    <?php echo $OUTPUT->full_header(); ?>

    <div id="page-content" class="row-fluid background-grey">
        <div id="region-main-box" class="<?php echo $regionmainbox; ?>">
            <div class="row-fluid">
                <section id="region-main" class="<?php echo $regionmain; ?>">
                    <?php echo $OUTPUT->skip_link_target('maincontent'); ?>
                    <?php echo $OUTPUT->coursecategory_index($categoryid); ?>
                    <?php echo $OUTPUT->main_content(); ?>
                </section>
                <?php echo $OUTPUT->blocks('side-pre', $sidepre); ?>
            </div>
        </div>
        <?php echo $OUTPUT->blocks('side-post', $sidepost); ?>
    </div>

    <?php
        include('footer.php');
        echo $OUTPUT->standard_end_of_body_html()
    ?>
</div>
</body>
</html>
