<?php
// Get the HTML for the settings bits.
$html = theme_school_get_html_for_settings($OUTPUT, $PAGE);
require('header.php');
if (!isloggedin() && $isregistration->value == 'email') { ?>
    <input type="hidden" name="custommenu" value="nologinselfreg" id="custommenu">
<?php } ?>
<div id="page" class="container-fluid">

    <div id="page-content" class="row-fluid">
        <section id="region-main" class="span12">
            <?php
            echo $OUTPUT->course_content_header();
            echo $OUTPUT->main_content();
            echo $OUTPUT->course_content_footer();
            ?>
        </section>
    </div>

    <?php
        include('footer.php');
        echo $OUTPUT->standard_end_of_body_html()
    ?>

</div>
</body>
</html>
