<?php
// Get the HTML for the settings bits.
$html = theme_tikli_get_html_for_settings($OUTPUT, $PAGE);
GLOBAL $DB, $CFG, $OUTPUT, $USER;

$addtext = get_config('theme_tikli', 'addtext');
$slideinterval = get_config('theme_tikli', 'slideinterval');
$slideautoplay = get_config('theme_tikli', 'sliderautoplay');

if(get_config('theme_tikli', 'videotype') === "0") {
	$iframevideo = get_config('theme_tikli', 'video');
} else {
	$uploadedvideo = $PAGE->theme->setting_file_url('uploadvideo', 'uploadvideo');
}
$isregistration = $DB->get_record('config', array('name'=>'registerauth'));
$url = "'".$CFG->wwwroot."/theme/tikli/js/frontpageslider/jquery.cslider.js"."'";

$hasfeedbackheading = get_config('theme_tikli', 'feedbackheading');
$hasfeedbacksubheading = get_config('theme_tikli', 'feedbacksubheading');
$hasfeedbackiframe = get_config('theme_tikli', 'feedbackiframe');
$hasfeedbackbrieftext = get_config('theme_tikli', 'feedbackbrieftext');

for($feedbackslides = 1; $feedbackslides <= get_config('theme_tikli', 'feedbackslidecount'); $feedbackslides = $feedbackslides + 1) {
	for($feedinner = 1; $feedinner <= 4; $feedinner = $feedinner + 1) {
		$hasimg = get_config('theme_tikli', 'feedbackslideimage_'.$feedinner.'_'.$feedbackslides);
		if (!empty($hasimg)) {
			$hasfeedbacks[$feedbackslides]["feedbackslideimage_".$feedinner] = $PAGE->theme->setting_file_url('feedbackslideimage_'.$feedinner.'_'.$feedbackslides, 'feedbackslideimage_'.$feedinner.'_'.$feedbackslides);
		} else {
			$hasfeedbacks[$feedbackslides]["feedbackslideimage_".$feedinner] = $CFG->wwwroot."/theme/tikli/css/img/userimage.png";
		}
		$hasfeedbacks[$feedbackslides]["feedbackslidename_".$feedinner] = get_config('theme_tikli', 'feedbackslidename_'.$feedinner.'_'.$feedbackslides);
		$hasfeedbacks[$feedbackslides]["feedbackslidereview_".$feedinner] = get_config('theme_tikli', 'feedbackslidereview_'.$feedinner.'_'.$feedbackslides);
	}
}

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
      				<?php echo $OUTPUT->custom_menu();?>
      			</div>
      		<?php } ?>
      		</div>

			</div>
			<?php if(get_config('theme_tikli', 'frontpageimagecontent') == 0) { ?><div class="welcome-block"><?php } else if (get_config('theme_tikli', 'frontpageimagecontent') == 1) { ?><div class="welcome-block-slider"><?php } ?>

						<?php if(get_config('theme_tikli', 'frontpageimagecontent') == 0) { // if static content ?>
							<?php if($addtext || $iframevideo || $uploadedvideo) { ?>
								<?php if(get_config('theme_tikli', 'frontpagevideoalignment') == 1) { //video right?>
                                <div class="container">
									<div class="row">
									<div class="span6 static-content-align">
										<?php if(!empty($addtext)) { echo $addtext; }?>
									</div>
									<?php if(!empty($iframevideo)) { ?>
										<div class="span6 static-content-align">
											<?php if(!empty($iframevideo)) { echo $iframevideo; }?>
										</div>
									<?php } ?>
									<?php if(!empty($uploadedvideo)) { ?>
										<div class="span6 static-content-align">
											<video width="560" height="315" controls>
												<?php if(!empty($uploadedvideo)) { ?><source src="<?php echo $uploadedvideo;?>" type="video/mp4"> <?php } ?>
											</video>
										</div>
									<?php } ?>
									<?php } else if (get_config('theme_tikli', 'frontpagevideoalignment') == 0) { ?>
									<?php if(!empty($iframevideo)) { ?>
									<div class="container">
									<div class="row">
										<div class="span6 static-content-align">
											<?php if(!empty($iframevideo)) { echo $iframevideo; }?>
										</div>
									<?php } ?>
									<?php if(!empty($uploadedvideo)) { ?>
										<div class="span6 static-content-align">
											<video width="560" height="315" controls>
												<?php if(!empty($uploadedvideo)) { ?><source src="<?php echo $uploadedvideo;?>" type="video/mp4"> <?php } ?>
											</video>
										</div>
									<?php } ?>
									<div class="span6 static-content-align">
										<?php if(!empty($addtext)) { echo $addtext; }?>
									</div>
									<?php } ?>
							<?php } ?>
                            </div>
						</div><!-- END of .container -->
						<?php } else if (get_config('theme_tikli', 'frontpageimagecontent') == 1) { // if slider
							$numberofsliders = get_config('theme_tikli', 'slidercount');?>
							<!--<div class="span12">-->
								<div id="da-slider" class="da-slider">
									<?php if(!empty($numberofsliders)) { ?>
									<?php for($slidecount = 1; $slidecount <= $numberofsliders; $slidecount++) {
										$sliderimageurl = $PAGE->theme->setting_file_url('slideimage'.$slidecount, 'slideimage'.$slidecount);
										$sliderimagetext = get_config('theme_tikli', 'slidertext'.$slidecount);
										$sliderimagelink = get_config('theme_tikli', 'sliderurl'.$slidecount);
										$sliderbuttontext = get_config('theme_tikli', 'sliderbuttontext'.$slidecount);
										if(!empty($sliderimagetext) || !empty($sliderimagelink) || !empty($sliderimageurl)) {
									?>
									<div class="da-slide" data-interval="<?php if(!empty($slideinterval)) { echo $slideinterval; } ?>" data-autoplay = "<?php if(!empty($slideautoplay)) { echo $slideautoplay; } ?>">
										<?php if(!empty($sliderimagetext)) {
											echo $sliderimagetext;
										} ?>
										<?php if(!empty($sliderimagelink)) { ?>
											<a href=<?php echo $sliderimagelink;?> class="da-link"><?php if($sliderbuttontext) { echo $sliderbuttontext; } ?></a>
										<?php } ?>
										<?php if(!empty($sliderimageurl)) { ?>
											<div class="da-img"><img src="<?php echo $sliderimageurl; ?>" alt="image01" /></div>
										<?php } else { ?>
											<div class="da-img"><img src="<?php echo $CFG->wwwroot.'/theme/tikli/css/images/slider-img.png';?>" alt="image01" /></div>
										<?php } ?>
									</div>
                                    <nav class="da-arrows">
                                        <span class="da-arrows-prev"></span>
                                        <span class="da-arrows-next"></span>
                                    </nav>
									<?php }
									}
									} ?>
								</div>
							<!--</div>-->
						<?php } ?>
			</div><!-- END of .welcome-block -->
		</header><!-- END of header -->

		<div class="content">
            <?php echo $OUTPUT->frontpage_news_and_updates(); ?>

            <?php echo $OUTPUT->frontpage_courses(); ?>

			<div class="row-fluid">
			    <div  class="students-area">
			      <div class="container">
			        <div class="span5 students-area-feedback">
			          <?php if (!empty($hasfeedbackheading)) { ?><h3><?php echo $hasfeedbackheading;?></h3><?php } ?>
			          <?php if (!empty($hasfeedbacksubheading)) { ?><h2><?php echo $hasfeedbacksubheading;?></h2><?php } ?>
			          <?php if(!empty($hasfeedbackiframe)) { echo $hasfeedbackiframe; }?>
			           <?php if (!empty($hasfeedbackbrieftext)) { echo $hasfeedbackbrieftext; } ?>
			        </div>
			        <div class="span7 slidergrid">
			            <ul class="bxslidergrid">
			              <?php for($feedbackslides = 1; $feedbackslides <= get_config('theme_tikli', 'feedbackslidecount'); $feedbackslides = $feedbackslides + 1) { ?>
			              <li>
			              	<div class="div_to_hold">
				              	<?php for($feedinner = 1; $feedinner <= 2; $feedinner = $feedinner + 1) {?>
					                  <div class="grid-testimo">
					                    <div class="blog_box">
					                      <div class="blog_box_bloger"><img src="<?php echo $hasfeedbacks[$feedbackslides]["feedbackslideimage_".$feedinner]; ?>" alt=""></div>
					                     	<?php echo $hasfeedbacks[$feedbackslides]["feedbackslidename_".$feedinner]; ?>
					                       <p>“<?php echo $hasfeedbacks[$feedbackslides]["feedbackslidereview_".$feedinner]; ?>”</p>
					                     </div>
					                  </div>
				                <?php } ?>
			                </div>
			                <div class="div_to_hold">
				                <?php for($feedinner = 3; $feedinner <= 4; $feedinner = $feedinner + 1) { ?>
					                  <div class="grid-testimo">
					                    <div class="blog_box">
					                      <div class="blog_box_bloger"><img src="<?php echo $hasfeedbacks[$feedbackslides]["feedbackslideimage_".$feedinner]; ?>" alt=""></div>
					                     	<?php echo $hasfeedbacks[$feedbackslides]["feedbackslidename_".$feedinner]; ?>
					                       <p>“<?php echo $hasfeedbacks[$feedbackslides]["feedbackslidereview_".$feedinner]; ?>”</p>
					                     </div>
					                  </div>
					            <?php } ?>
				            </div>
			                <div class="clearfix"></div>
			              </li>
			              <?php } ?>

			            </ul>
			            <div class="bxslidergrid-nav">
			              <span id="bxslidergrid-prev">
			                <img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/bxslider-img/arr-l-grid.png" alt="">
			              </span>
			              <span id="bxslidergrid-next">
			                <img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/bxslider-img/arr-r-grid.png" alt="">
			              </span>
			            </div>
			        </div><!--span7-->
			    </div>
			  </div>
			</div><!--end row-fluid -->
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
