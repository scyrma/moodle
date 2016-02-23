<?php
// Get the HTML for the settings bits.
$html = theme_school_get_html_for_settings($OUTPUT, $PAGE);
// Set default (LTR) layout mark-up for a two column page (side-pre-only).
$regionmain = 'span9 pull-right';
$sidepre = 'span3 desktop-first-column left-menu-close';

// Reset layout mark-up for RTL languages.
if (right_to_left()) {
    $regionmain = 'span9';
    $sidepre = 'span3 pull-right left-menu-close';
}
?>
<?php require('header.php'); ?>
<div id="page" class="container-fluid">
    <?php echo $OUTPUT->full_header(); ?>
    <div id="page-content" class="row-fluid"><div class="custom-width">
        <section id="region-main" class="<?php echo $regionmain; ?>">
            <?php
            echo $OUTPUT->course_content_header();
            echo $OUTPUT->main_content();
            echo $OUTPUT->course_content_footer();
            ?>
        </section>
        </div>
        <?php
        echo $OUTPUT->blocks('side-pre', $sidepre);
        ?>
    </div>
    </div>

    <?php
        include('footer.php');
        echo $OUTPUT->standard_end_of_body_html()
    ?>

</div>

</body>
</html>
