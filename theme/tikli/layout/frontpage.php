<?php
// Get the HTML for the settings bits.
$html = theme_tikli_get_html_for_settings($OUTPUT, $PAGE);
GLOBAL $DB, $CFG, $OUTPUT, $USER;

$isregistration = $DB->get_record('config', array('name'=>'registerauth'));
$url = "'".$CFG->wwwroot."/theme/tikli/js/frontpageslider/jquery.cslider.js"."'";

echo $OUTPUT->doctype();?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->frontpage_theme_head_html(); ?>

	<script type="text/javascript" src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/frontpageslider/modernizr.custom.28468.js"></script>

	<script type="text/javascript">
		var url = <?php echo $url;?>;

		  $( window ).load(function() {
		  	$.getScript( url, function() {
				$('#da-slider').cslider();
		  	});

		});
	</script>
	<?php include($CFG->dirroot . '/theme/tikli/settings/colorchange.php'); ?>
	<?php echo $OUTPUT->standard_head_html() ?>
</head>
	<body class="landing-page">
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
            <?php echo $OUTPUT->frontpage_news_and_updates(); ?>

            <?php echo $OUTPUT->frontpage_courses(); ?>

            <?php echo $OUTPUT->frontpage_feedback(); ?>
		</div><!-- END of .content -->
		<?php
			echo $OUTPUT->main_content();
			include('footer.php');
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
