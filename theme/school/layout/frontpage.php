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

// Get the HTML for the settings bits.
$html = theme_school_get_html_for_settings($OUTPUT, $PAGE);
GLOBAL $DB, $CFG, $OUTPUT, $USER;

$isregistration = $DB->get_record('config', array('name'=>'registerauth'));

echo $OUTPUT->doctype();?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php
        echo $OUTPUT->standard_head_html();
        echo $OUTPUT->frontpage_theme_head_html();
    ?>
</head>
	<body class="landing-page">
        <?php echo $OUTPUT->standard_top_of_body_html() ?>

		<header><div class="mobile-top-head">
            <?php echo $OUTPUT->logo(); ?>
            <?php echo $OUTPUT->user_menu(); ?>
			<?php if (!empty($CFG->custommenuitems)) { ?>
			<div class="navbar">
      			<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
        			<i class="fa fa-arrow-circle-down"></i>
      			</a>
      			<div class="nav-collapse collapse">
      				<?php echo $OUTPUT->custom_menu(); ?>
      			</div>
      		<?php } ?>
      		</div>

			</div>

            <?php echo $OUTPUT->frontpage_header_content(); ?>
		</header><!-- END of header -->

		<div class="content">
            <?php echo $OUTPUT->skip_link_target('maincontent'); ?>
            <?php echo $OUTPUT->frontpage_news_and_updates(); ?>
            <?php echo $OUTPUT->frontpage_courses(); ?>
            <?php echo $OUTPUT->frontpage_feedback(); ?>
		</div><!-- END of .content -->
		<?php
			echo $OUTPUT->main_content();
            echo $OUTPUT->theme_footer();
            echo $OUTPUT->standard_end_of_body_html();

			if (isloggedin() && $isregistration->value != 'email') { ?>
                <input type="hidden" name="custommenu" value="yeslogin" id="custommenu">
            <?php } else if (!isloggedin() && $isregistration->value == 'email') { ?>
                <input type="hidden" name="custommenu" value="nologinselfreg" id="custommenu">
            <?php } else if (!isloggedin()) { ?>
                <input type="hidden" name="custommenu" value="nologin" id="custommenu">
            <?php }
		?>
</body>
</html>
