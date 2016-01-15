<?php
// Get the HTML for the settings bits.
$html = theme_tikli_get_html_for_settings($OUTPUT, $PAGE);
GLOBAL $DB, $CFG, $OUTPUT, $USER;
$colorscheme = get_config('theme_tikli', 'colorscheme');
$course = $DB->get_records_sql('SELECT c.* FROM {course} c where id != ? and visible = ?',array(1, 1));

$coursedetailsarray = array();
foreach ($course as $key => $coursevalue) {
	$coursedetailsarray[$key]["courseid"] = $CFG->wwwroot."/course/view.php?id=".$coursevalue->id;
	$coursedetailsarray[$key]["coursename"] = $coursevalue->fullname;
	$courseteacher = $DB->get_records_sql('SELECT u.*
	FROM {course} c
	JOIN {context} ct ON c.id = ct.instanceid
	JOIN {role_assignments} ra ON ra.contextid = ct.id
	JOIN {user} u ON u.id = ra.userid
	JOIN {role} r ON r.id = ra.roleid Where c.id = ? and r.shortname = ?', array($coursevalue->id, 'editingteacher'));
	if(!empty($courseteacher)) {
		foreach ($courseteacher as $keycourseteacher => $courseteachervalue) {
			$coursedetailsarray[$key]["teachername"][$keycourseteacher] = $courseteachervalue->firstname." ".$courseteachervalue->lastname;
			$coursedetailsarray[$key]["teacherid"][$keycourseteacher] = $CFG->wwwroot."/user/profile.php?id=".$courseteachervalue->id;
		}
	} else {
		$coursedetailsarray[$key]["teachername"] = "";
		$coursedetailsarray[$key]["teacherid"] = "";
	}
	$coursecontext = context_course::instance($coursevalue->id);
	$isfile = $DB->get_records_sql("Select * from {files} where contextid = ? and filename != ?", array($coursecontext->id, "."));
	if($isfile) {
		foreach ($isfile as $key1 => $isfilevalue) {
			$courseimage =  $CFG->wwwroot . "/pluginfile.php/" . $isfilevalue->contextid ."/". $isfilevalue->component . "/" . $isfilevalue->filearea . "/" . $isfilevalue->filename;
		}
	}
	if(!empty($courseimage)) {
		$coursedetailsarray[$key]["courseimage"] = $courseimage;
	} else {
		$coursedetailsarray[$key]["courseimage"] = $CFG->wwwroot."/theme/tikli/data/nopic.jpg";
	}
	$courseimage = '';
}
$addtext = get_config('theme_tikli', 'addtext');
$slideinterval = get_config('theme_tikli', 'slideinterval');
$slideautoplay = get_config('theme_tikli', 'sliderautoplay');

$frontpageblockheading = get_config('theme_tikli', 'frontpageblockheading');
$frontpageblock = get_config('theme_tikli', 'frontpageblock');
$frontpageblocklink = get_config('theme_tikli', 'frontpageblocklink');

$frontpageblocksection1 = get_config('theme_tikli', 'frontpageblocksection1');
$frontpageblocklinksection1 = get_config('theme_tikli', 'frontpageblocklinksection1');
$frontpageblockdescriptionsection1 = get_config('theme_tikli', 'frontpageblockdescriptionsection1');

$frontpageblocksection2 = get_config('theme_tikli', 'frontpageblocksection2');
$frontpageblocklinksection2 = get_config('theme_tikli', 'frontpageblocklinksection2');
$frontpageblockdescriptionsection2 = get_config('theme_tikli', 'frontpageblockdescriptionsection2');

$frontpageblocksection3 = get_config('theme_tikli', 'frontpageblocksection3');
$frontpageblocklinksection3 = get_config('theme_tikli', 'frontpageblocklinksection3');
$frontpageblockdescriptionsection3 = get_config('theme_tikli', 'frontpageblockdescriptionsection3');

$checkhaslogo = $PAGE->theme->setting_file_url('logo', 'logo');
if(!empty($checkhaslogo)) {
	$haslogo = $PAGE->theme->setting_file_url('logo', 'logo');
} else {
	$haslogo = $CFG->wwwroot.'/theme/tikli/pix/logo-2.png';
}

$checkhasiconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
if(!empty($checkhasiconlogo)) {
  $hasiconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
} else {
  $hasiconlogo = $CFG->wwwroot.'/theme/tikli/pix/icon-logo.png';
}

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
	<meta charset="utf-8">
  	<meta http-equiv="X-UA-Compatible" content="IE=edge">
  	<meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <link type="text/css" rel="Stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/bootstrap.css">
	<link type="text/css" rel="Stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/bootstrap-responsive.css">
	<link type="text/css" rel="Stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/jquery.bxslider.css">
	<link type="text/css" rel="stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/font-awesome.min.css">
	<link type="text/css" rel="stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/animation.css" />
	<link type="text/css" rel="Stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/styles.css">

	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery-2.1.4.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/bootstrap.min.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery.bxslider.min.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/frontpage.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/font.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery.animateNumber.js"></script>
	<link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/frontpageslider/cssliderdemo.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/frontpageslider/cssliderstyle.css" />

	<style>
	ul, ol {
	    margin: 0px !important;
	}
	*[role="main"] {
		display: none;
	}
	</style>
	<script type="text/javascript" src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/frontpageslider/modernizr.custom.28468.js"></script>

	<script type="text/javascript">
		var url = <?php echo $url;?>;

		  $( window ).load(function() {
		  	$.getScript( url, function() {
				$('#da-slider').cslider();
		  	});

		});
	</script>
	<?php
	    include($CFG->dirroot . '/theme/tikli/settings/colorchange.php');
      if ( $CFG->version >= '2015051100.00' ) {
        $file = get_string('privatefiles');
      } else {
          $file = get_string('myfiles');
      }
	?>
	<?php echo $OUTPUT->standard_head_html() ?>
</head>
	<body class="landing-page">
		<header><div class="mobile-top-head">
			<?php if (get_config('theme_tikli', 'logoorsitename') === "logo") { ?>
		    <div class="logo-wr">
		      <a class="logo-img" href="<?php echo $CFG->wwwroot; ?>">
		        <img alt="logo" src="<?php echo $haslogo;?>" />
		      </a>
		    </div>
		    <?php } else if (get_config('theme_tikli', 'logoorsitename') === "sitename") { ?>
		    <div class="logo-wr">
      			<a class="logo-img text" href="<?php echo $CFG->wwwroot; ?>">
		    		<h1><?php echo $SITE->fullname; ?></h1>
		    	</a>
    		</div>
		    <?php } else if (get_config('theme_tikli', 'logoorsitename') === "iconsitename") { ?>
		    <div class="logo-wr">
      			<a class="logo-img icontext" href="<?php echo $CFG->wwwroot; ?>">
		    		<h1><span class="logoicon"><img alt="logo" src="<?php echo $hasiconlogo;?>" /></span><span><?php echo $SITE->fullname; ?></span></h1>
		    	</a>
		    </div>
		    <?php } ?>
<?php
    require_once(__DIR__ . '/usermenu.php');
?>
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
			<div class="news-updates">
				<div class="container">
					<div class="row">
						<?php if(!empty($frontpageblockheading)) { ?>
							<div class="span3">
								<h3><?php if(!empty($frontpageblockheading)) { echo $frontpageblockheading; } ?></h3>
								<p><a href=<?php if(!empty($frontpageblocklink)) { echo $frontpageblocklink; } ?> class="btn-see-all"><?php if(!empty($frontpageblock)) { echo $frontpageblock; } ?><i><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-arr-r-2.png" alt=""></i></a></p>
							</div>
							<?php if(!empty($frontpageblocklinksection1)) { ?>
							<div class="span3 news-updates-extraclass">
								<p><a href="<?php if(!empty($frontpageblocklinksection1)) { echo $frontpageblocklinksection1; } ?>"><?php if(!empty($frontpageblocksection1)) { echo $frontpageblocksection1; } ?></a></p>
								<h5><?php if(!empty($frontpageblockdescriptionsection1)) { echo $frontpageblockdescriptionsection1; } ?></h5>
							</div>
							<?php } if(!empty($frontpageblocklinksection2)) { ?>
							<div class="span3 news-updates-extraclass">
								<p><a href="<?php if(!empty($frontpageblocklinksection2)) { echo $frontpageblocklinksection2; } ?>"><?php if(!empty($frontpageblocksection2)) { echo $frontpageblocksection2; } ?></a></p>
								<h5><?php if(!empty($frontpageblockdescriptionsection2)) { echo $frontpageblockdescriptionsection2; } ?></h5>
							</div>
							<?php } if(!empty($frontpageblocklinksection3)) { ?>
							<div class="span3 news-updates-extraclass">
								<p><a href="<?php if(!empty($frontpageblocklinksection3)) { echo $frontpageblocklinksection3; } ?>"><?php if(!empty($frontpageblocksection3)) { echo $frontpageblocksection3; } ?></a></p>
								<h5><?php if(!empty($frontpageblockdescriptionsection3)) { echo $frontpageblockdescriptionsection3; } ?></h5>
							</div>
							<?php } ?>
						<?php } ?>
					</div>
				</div><!-- END of .container -->
			</div><!-- END of .news-updates -->

			<?php if(!empty($course)) { ?>
			<div class="popular-courses">
				<div class="container-fluid">
					<div class="container nheading">
						<?php if (!empty(get_config('theme_tikli', 'coursesectionheading'))) { ?>
				        	<div class="span5 popular-course-nheading">
				              <?php if (!empty(get_config('theme_tikli', 'coursesectionheading'))) { ?><h4><?php echo get_config('theme_tikli', 'coursesectionheading'); ?></h4><?php } ?>
				              <?php if (!empty(get_config('theme_tikli', 'coursesectionsubheading'))) { ?><h2><?php echo get_config('theme_tikli', 'coursesectionsubheading'); ?></h2><?php } ?>
				              <?php if (!empty(get_config('theme_tikli', 'coursesectionoverview'))) { ?><p><?php echo get_config('theme_tikli', 'coursesectionoverview'); ?></p><?php } ?>
				        	</div>
				        <?php } ?>
			            <div class="span7 popular-course-links">
			            	<?php for($quicklinkcols = 1; $quicklinkcols <= get_config('theme_tikli', 'quicklinkscolumns'); $quicklinkcols = $quicklinkcols + 1) { ?>
				            	<ul>
				            	<?php for($quicklinkrows = 1; $quicklinkrows <= get_config('theme_tikli', 'quicklinksrows'.$quicklinkcols); $quicklinkrows = $quicklinkrows + 1) { ?>
					                <li><a <?php if (($quicklinkrows == get_config('theme_tikli', 'quicklinksrows'.$quicklinkcols)) && ($quicklinkcols == get_config('theme_tikli', 'quicklinkscolumns'))) { ?>class = "viewmore" <?php } ?>href="<?php echo get_config('theme_tikli', 'link'.$quicklinkcols.'_'.$quicklinkrows); ?>"><?php echo get_config('theme_tikli', 'text'.$quicklinkcols.'_'.$quicklinkrows); ?></a></li>
				                <?php } ?>
				                </ul>
			                <?php } ?>
			            </div>
        			</div>

					<div class="course-items">
						<ul class="popular-courses-slider">
							<?php foreach($coursedetailsarray as $keycoursedetail => $coursedetailsarrayvalue) { ?>
							<li>
								<div class="course-item">
									<div class="img-wr">
										<a href="<?php echo $coursedetailsarrayvalue['courseid'];?>"><img src="<?php echo $coursedetailsarrayvalue['courseimage'];?>" alt=""></a>
									</div>
									<div class="course-item-cont">
										<h5><a href="<?php echo $coursedetailsarrayvalue['courseid'];?>"><?php echo $coursedetailsarrayvalue['coursename'];?></a></h5>
										<?php if($coursedetailsarrayvalue['teachername'] != '') {
											foreach ($coursedetailsarrayvalue['teachername'] as $keys => $value) { ?>
												<h6><?php echo get_string('defaultcourseteacher');?> : <a href="<?php echo $coursedetailsarrayvalue['teacherid'][$keys];?>"><?php echo $value;?></a></h6>
										<?php }
										?>


										<?php } else { ?>
											<h6><?php echo get_string('defaultcourseteacher');?> : <a href="javascript:void(0);">Not assigned</a></h6>
										<?php } ?>
									</div>
									<a href="<?php echo $coursedetailsarrayvalue['courseid'];?>" class="btn-plus"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-plus.png" height="42" width="42" alt=""></a>
								</div>
							</li><!-- END of .slide -->
							<?php } ?>
						</ul><!-- END of .popular-courses-slider -->
					</div><!-- END of .course-items -->
					<div class="popular-courses-nav">
						<div><span id="slider-prev"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/i-arr-l-1.png" alt=""></span></div>
						<div><span id="slider-next"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/i-arr-r-1.png" alt=""></span></div>
					</div>
				</div><!-- END of .container-fluid -->
			</div><!-- END of .popular-courses -->
			<?php } ?>

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
