<?php
// Get the HTML for the settings bits.
$html = theme_tikli_get_html_for_settings($OUTPUT, $PAGE);
GLOBAL $DB, $CFG, $OUTPUT, $USER;
$colorscheme = get_config('theme_tikli', 'colorscheme');
$course = $DB->get_records_sql('SELECT c.* FROM {course} c where id != ?',array(1));

$coursedetailsarray = array();
foreach ($course as $key => $coursevalue) {   	
	$coursedetailsarray[$key]["courseid"] = $CFG->wwwroot."/course/view.php?id=".$coursevalue->id;
	$coursedetailsarray[$key]["coursename"] = $coursevalue->fullname;
	$courseteacher = $DB->get_record_sql('SELECT u.*
	FROM {course} c
	JOIN {context} ct ON c.id = ct.instanceid
	JOIN {role_assignments} ra ON ra.contextid = ct.id
	JOIN {user} u ON u.id = ra.userid
	JOIN {role} r ON r.id = ra.roleid Where c.id = ? and r.shortname = ?', array($coursevalue->id, 'editingteacher'));
	if(!empty($courseteacher)) {
		$coursedetailsarray[$key]["teachername"] = $courseteacher->firstname." ".$courseteacher->lastname;
	} else {
		$coursedetailsarray[$key]["teachername"] = "";
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
$checkcontactusheadingicon = $PAGE->theme->setting_file_url('contactwithusfontawesomeicon', 'contactwithusfontawesomeicon');
if(!empty($checkcontactusheadingicon)) {
	$contactusheadingicon = $PAGE->theme->setting_file_url('contactwithusfontawesomeicon', 'contactwithusfontawesomeicon');
} else {
	$contactusheadingicon = $CFG->wwwroot."/theme/tikli/css/img/i-label.png";
}

$checksocialfontawesomeicon1 = $PAGE->theme->setting_file_url('socialfontawesomeicon1', 'socialfontawesomeicon1');

if(!empty($checksocialfontawesomeicon1)) {
	$socialfontawesomeicon1 = $PAGE->theme->setting_file_url('socialfontawesomeicon1', 'socialfontawesomeicon1');
} else {
	$socialfontawesomeicon1 = $CFG->wwwroot."/theme/tikli/css/img/i-p.png";
}

$checksocialfontawesomeicon2 = $PAGE->theme->setting_file_url('socialfontawesomeicon2', 'socialfontawesomeicon2');
if(!empty($checksocialfontawesomeicon2)) {
	$socialfontawesomeicon2 = $PAGE->theme->setting_file_url('socialfontawesomeicon2', 'socialfontawesomeicon2');
} else {
	$socialfontawesomeicon2 = $CFG->wwwroot."/theme/tikli/css/img/i-rss.png";
}
$checksocialfontawesomeicon3 = $PAGE->theme->setting_file_url('socialfontawesomeicon3', 'socialfontawesomeicon3');
if(!empty($checksocialfontawesomeicon3)) {
	$socialfontawesomeicon3 = $PAGE->theme->setting_file_url('socialfontawesomeicon3', 'socialfontawesomeicon3');
} else {
	$socialfontawesomeicon3 = $CFG->wwwroot."/theme/tikli/css/img/i-l.png";
}

$checksocialfontawesomeicon4 = $PAGE->theme->setting_file_url('socialfontawesomeicon4', 'socialfontawesomeicon4');
if(!empty($checksocialfontawesomeicon4)) {
	$socialfontawesomeicon4 = $PAGE->theme->setting_file_url('socialfontawesomeicon4', 'socialfontawesomeicon4');
} else {
	$socialfontawesomeicon4 = $CFG->wwwroot."/theme/tikli/css/img/i-t.png";
}

$checkaddressfontawesomeicon = $PAGE->theme->setting_file_url('addressfontawesomeicon', 'addressfontawesomeicon');
if(!empty($checkaddressfontawesomeicon)) {
	$addressfontawesomeicon = $PAGE->theme->setting_file_url('addressfontawesomeicon', 'addressfontawesomeicon');
} else {
	$addressfontawesomeicon = $CFG->wwwroot."/theme/tikli/css/img/i-label-2.png";
}

$checkphonefontawesomeicon = $PAGE->theme->setting_file_url('phonefontawesomeicon', 'phonefontawesomeicon');
if(!empty($checkphonefontawesomeicon)) {
	$phonefontawesomeicon = $PAGE->theme->setting_file_url('phonefontawesomeicon', 'phonefontawesomeicon');
} else {
	$phonefontawesomeicon = $CFG->wwwroot."/theme/tikli/css/img/i-phone.png";
}

$checkemailfontawesomeicon = $PAGE->theme->setting_file_url('emailfontawesomeicon', 'emailfontawesomeicon');
if(!empty($checkemailfontawesomeicon)) {
	$emailfontawesomeicon = $PAGE->theme->setting_file_url('emailfontawesomeicon', 'emailfontawesomeicon');
} else {
	$emailfontawesomeicon = $CFG->wwwroot."/theme/tikli/css/img/i-mail.png";
}
$address = get_config('theme_tikli', 'address');
$phone = get_config('theme_tikli', 'phone');
$email = get_config('theme_tikli', 'email');
$contactwithus = get_config('theme_tikli', 'contactwithus');
$facebook = get_config('theme_tikli', 'socialicon1');
$googleplus = get_config('theme_tikli', 'socialicon2');
$youtube = get_config('theme_tikli', 'socialicon3');
$twitter = get_config('theme_tikli', 'socialicon4');

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


$checkmarketspotfontawesomeicon = $PAGE->theme->setting_file_url('marketspotfontawesomeicon', 'marketspotfontawesomeicon');
if(!empty($checkmarketspotfontawesomeicon)) {
	$marketspotfontawesomeicon = $PAGE->theme->setting_file_url('marketspotfontawesomeicon', 'marketspotfontawesomeicon');
} else {
	$marketspotfontawesomeicon = $CFG->wwwroot."/theme/tikli/css/img/i-download.png";
}

$marketspot = get_config('theme_tikli', 'marketspot');
$checkmarketspotsectionfontawesomeicon1 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon1', 'marketspotsectionfontawesomeicon1');
if(!empty($checkmarketspotsectionfontawesomeicon1)) {
	$marketspotsectionfontawesomeicon1 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon1', 'marketspotsectionfontawesomeicon1');
} else {
	$marketspotsectionfontawesomeicon1 = $CFG->wwwroot."/theme/tikli/css/img/i-user-1.png";
}
$marketspotsection1 = get_config('theme_tikli', 'marketspotsection1');
$marketspotsectionlink1 = get_config('theme_tikli', 'marketspotsectionlink1');
$marketspotsectiontext1 = get_config('theme_tikli', 'marketspotsectiontext1');

$checkmarketspotsectionfontawesomeicon2 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon2', 'marketspotsectionfontawesomeicon2');
if(!empty($checkmarketspotsectionfontawesomeicon2)) {
	$marketspotsectionfontawesomeicon2 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon2', 'marketspotsectionfontawesomeicon2');
} else {
	$marketspotsectionfontawesomeicon2 = $CFG->wwwroot."/theme/tikli/css/img/i-user-2.png";
}
$marketspotsection2 = get_config('theme_tikli', 'marketspotsection2');
$marketspotsectionlink2 = get_config('theme_tikli', 'marketspotsectionlink2');
$marketspotsectiontext2 = get_config('theme_tikli', 'marketspotsectiontext2');


$checkmarketspotsectionfontawesomeicon3 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon3', 'marketspotsectionfontawesomeicon3');
if(!empty($checkmarketspotsectionfontawesomeicon3)) {
	$marketspotsectionfontawesomeicon3 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon3', 'marketspotsectionfontawesomeicon3');
} else {
	$marketspotsectionfontawesomeicon3 = $CFG->wwwroot."/theme/tikli/css/img/i-user-3.png";
}
$marketspotsection3 = get_config('theme_tikli', 'marketspotsection3');
$marketspotsectionlink3 = get_config('theme_tikli', 'marketspotsectionlink3');
$marketspotsectiontext3 = get_config('theme_tikli', 'marketspotsectiontext3');

$checkmarketspotsectionfontawesomeicon4 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon4', 'marketspotsectionfontawesomeicon4');
if(!empty($checkmarketspotsectionfontawesomeicon4)) { 
	$marketspotsectionfontawesomeicon4 = $PAGE->theme->setting_file_url('marketspotsectionfontawesomeicon4', 'marketspotsectionfontawesomeicon4');
} else {
	$marketspotsectionfontawesomeicon4 = $CFG->wwwroot."/theme/tikli/css/img/i-user-4.png";
}
$marketspotsection4 = get_config('theme_tikli', 'marketspotsection4');
$marketspotsectionlink4 = get_config('theme_tikli', 'marketspotsectionlink4');
$marketspotsectiontext4 = get_config('theme_tikli', 'marketspotsectiontext4');

$checksecondmarketspotfontawesomeicon = $PAGE->theme->setting_file_url('secondmarketspotfontawesomeicon', 'secondmarketspotfontawesomeicon');
if(!empty($checksecondmarketspotfontawesomeicon)) {
	$secondmarketspotfontawesomeicon = $PAGE->theme->setting_file_url('secondmarketspotfontawesomeicon', 'secondmarketspotfontawesomeicon');
} else {
	$secondmarketspotfontawesomeicon = $CFG->wwwroot."/theme/tikli/css/img/i-forum.png";
}
$secondmarketspot = get_config('theme_tikli', 'secondmarketspot');

$checksecondmarketspotsectionfontawesomeicon1 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon1', 'secondmarketspotsectionfontawesomeicon1');
if(!empty($checksecondmarketspotsectionfontawesomeicon1)) {
	$secondmarketspotsectionfontawesomeicon1 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon1', 'secondmarketspotsectionfontawesomeicon1');
} else {
	$secondmarketspotsectionfontawesomeicon1 = $CFG->wwwroot."/theme/tikli/css/img/i-settings.png";
}
$secondmarketspotsection1 = get_config('theme_tikli', 'secondmarketspotsection1');
$secondmarketspotsectionlink1 = get_config('theme_tikli', 'secondmarketspotsectionlink1');
$secondmarketspotsectiontext1 = get_config('theme_tikli', 'secondmarketspotsectiontext1');


$checksecondmarketspotsectionfontawesomeicon2 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon2', 'secondmarketspotsectionfontawesomeicon2');
if(!empty($checksecondmarketspotsectionfontawesomeicon2)) {
	$secondmarketspotsectionfontawesomeicon2 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon2', 'secondmarketspotsectionfontawesomeicon2');
} else {
	$secondmarketspotsectionfontawesomeicon2 = $CFG->wwwroot."/theme/tikli/css/img/i-settings.png";
}
$secondmarketspotsection2 = get_config('theme_tikli', 'secondmarketspotsection2');
$secondmarketspotsectionlink2 = get_config('theme_tikli', 'secondmarketspotsectionlink2');
$secondmarketspotsectiontext2 = get_config('theme_tikli', 'secondmarketspotsectiontext2');


$checksecondmarketspotsectionfontawesomeicon3 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon3', 'secondmarketspotsectionfontawesomeicon3');
if(!empty($checksecondmarketspotsectionfontawesomeicon3)) {
	$secondmarketspotsectionfontawesomeicon3 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon3', 'secondmarketspotsectionfontawesomeicon3');
} else {
	$secondmarketspotsectionfontawesomeicon3 = $CFG->wwwroot."/theme/tikli/css/img/i-book.png";
}
$secondmarketspotsection3 = get_config('theme_tikli', 'secondmarketspotsection3');
$secondmarketspotsectionlink3 = get_config('theme_tikli', 'secondmarketspotsectionlink3');
$secondmarketspotsectiontext3 = get_config('theme_tikli', 'secondmarketspotsectiontext3');


$checksecondmarketspotsectionfontawesomeicon4 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon4', 'secondmarketspotsectionfontawesomeicon4');
if(!empty($checksecondmarketspotsectionfontawesomeicon4)) {
	$secondmarketspotsectionfontawesomeicon4 = $PAGE->theme->setting_file_url('secondmarketspotsectionfontawesomeicon4', 'secondmarketspotsectionfontawesomeicon4');
} else {
	$secondmarketspotsectionfontawesomeicon4 = $CFG->wwwroot."/theme/tikli/css/img/i-science.png";
}
$secondmarketspotsection4 = get_config('theme_tikli', 'secondmarketspotsection4');
$secondmarketspotsectionlink4 = get_config('theme_tikli', 'secondmarketspotsectionlink4');
$secondmarketspotsectiontext4 = get_config('theme_tikli', 'secondmarketspotsectiontext4');

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
	<link type="text/css" rel="Stylesheet" href="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/styles.css">

	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery-2.1.4.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/bootstrap.min.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery.bxslider.min.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/frontpage.js"></script>
	<script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/font.js"></script>
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
			<?php if (!isloggedin()) { ?>
			<div class="logining-wr">
				<a href="<?php echo $CFG->wwwroot; ?>/login/index.php"><?php echo get_string('login');?></a>
				<?php if ($isregistration->value == 'email') { ?>
          			<a href="<?php echo $CFG->wwwroot; ?>/login/signup.php?"><?php echo get_string('startsignup'); ?></a>
        		<?php } ?>
			</div>
			<?php } else { ?>

			<div class="usermenu">
        <div>
          <ul class="menubar">
            <li>
              <a href="javascript:void(0);">
                <span class="userbutton">
                  <span>
                    <span class="avatar current">
                      <?php echo $OUTPUT->user_profile_picture(); ?>
                    </span>
                  </span>
                  <span><?php echo $USER->firstname;?> <span><?php echo $USER->lastname;?></span></span>
                </span>
              </a>
            </li>
          </ul>
          <ul class="menu">
            <?php $checkenablemy = $PAGE->theme->settings->enablemy; if (!empty($checkenablemy)) { ?>
            <li>
              <a href="<?php echo $CFG->wwwroot; ?>/my">
                <span class="i-wr">
                  <i class="i-def"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/column3/i-home-1.png" alt=""></i>
                  <i class="i-hov"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-home-1-h.png" alt=""></i>
                </span>
                <span><?php echo get_string('myhome'); ?></span>
              </a>
            </li>
            <?php } ?>
            <?php $checkenableprofile = $PAGE->theme->settings->enableprofile; if (!empty($checkenableprofile)) { ?>
            <li>
              <a href="<?php echo $CFG->wwwroot; ?>/user/profile.php?id=<?php echo $USER->id;?>">
                <span class="i-wr">
                  <i class="i-def"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/column3/i-user-1.png" alt=""></i>
                  <i class="i-hov"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-user-1-h.png" alt=""></i>
                </span>
                <span><?php echo get_string('profile'); ?></span>
              </a>
            </li>
            <?php } ?>
            <li>
              <a href="<?php echo $CFG->wwwroot; ?>/message/index.php">
                <span class="i-wr">
                  <i class="i-def"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/column3/i-mail-1.png" alt=""></i>
                  <i class="i-hov"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-mail-1-h.png" alt=""></i>
                </span>
                <span><?php echo get_string('messages', 'chat'); ?></span>
              </a>
            </li>
            <?php $checkprivatefiles = $PAGE->theme->settings->enableprivatefiles; if (!empty($checkprivatefiles)) { ?>
            <li>
              <a href="<?php echo $CFG->wwwroot; ?>/user/files.php">
                <span class="i-wr">
                  <i class="i-def"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/column3/i-file-1.png" alt=""></i>
                  <i class="i-hov"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-file-1-h.png" alt=""></i>
                </span>
                <span><?php echo $file;?></span>
              </a>
            </li>
            <?php } ?>
            <?php  $checkenablebadges = $PAGE->theme->settings->enablebadges; if (!empty($checkenablebadges)) { ?>
            <li>
              <a href="<?php echo $CFG->wwwroot; ?>/badges/view.php?type=1">
                <span class="i-wr">
                  <i class="i-def"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/column3/i-badge-1.png" alt=""></i>
                  <i class="i-hov"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-badge-1-h.png" alt=""></i>
                </span>
                <span><?php echo get_string('badges', 'badges');?></span>
              </a>
            </li>
            <?php } ?>
            <li>
              <a href="<?php echo $CFG->wwwroot; ?>/login/logout.php">
                <span class="i-wr">
                  <i class="i-def"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/column3/i-out-1.png" alt=""></i>
                  <i class="i-hov"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/<?php echo $colorscheme ?>/i-out-1-h.png" alt=""></i>
                </span>
                <span><?php echo get_string('logout');?></span>
              </a>
            </li>
          </ul>
        </div>
      </div>
      
			<?php } ?>
			<div class="navbar">
      			<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
        			<i class="fa fa-arrow-circle-down"></i>
      			</a>
      			<div class="nav-collapse collapse">
      				<?php echo $OUTPUT->custom_menu();?>
      			</div>
      		</div>
			</div>
			<?php if(get_config('theme_tikli', 'frontpageimagecontent') == 0) { ?><div class="welcome-block"><?php } else if (get_config('theme_tikli', 'frontpageimagecontent') == 1) { ?><div class="welcome-block-slider"><?php } ?>
				
						<?php if(get_config('theme_tikli', 'frontpageimagecontent') == 0) { // if static content ?> 
							<?php if($addtext || $iframevideo || $uploadedvideo) { ?>
								<?php if(get_config('theme_tikli', 'frontpagevideoalignment') == 1) { //video right?>
                                <div class="container">
									<div class="row">
									<div class="span6">
										<?php if(!empty($addtext)) { echo $addtext; }?>
									</div>
									<?php if(!empty($iframevideo)) { ?>
										<div class="span6">
											<?php if(!empty($iframevideo)) { echo $iframevideo; }?>
										</div>
									<?php } ?>
									<?php if(!empty($uploadedvideo)) { ?>
										<div class="span6">
											<video width="560" height="315" controls>
												<?php if(!empty($uploadedvideo)) { ?><source src="<?php echo $uploadedvideo;?>" type="video/mp4"> <?php } ?>
											</video>
										</div>
									<?php } ?>
									<?php } else if (get_config('theme_tikli', 'frontpagevideoalignment') == 0) { ?>
									<?php if(!empty($iframevideo)) { ?>
										<div class="span6">
											<?php if(!empty($iframevideo)) { echo $iframevideo; }?>
										</div>
									<?php } ?>
									<?php if(!empty($uploadedvideo)) { ?>
										<div class="span6">
											<video width="560" height="315" controls>
												<?php if(!empty($uploadedvideo)) { ?><source src="<?php echo $uploadedvideo;?>" type="video/mp4"> <?php } ?>
											</video>
										</div>
									<?php } ?>
									<div class="span6">
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
					<div class="popular-courses-nav">
						<div><span id="slider-prev"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/i-arr-l-1.png" alt=""></span></div>
						<div>
							<h3>Our Popular courses</h3>
							<p><a href="<?php echo $CFG->wwwroot; ?>/course/index.php">View All Courses</a></p>
						</div>
						<div><span id="slider-next"><img src="<?php echo $CFG->wwwroot; ?>/theme/tikli/css/img/i-arr-r-1.png" alt=""></span></div>
					</div>
					<div class="course-items">
						<ul class="popular-courses-slider">
							<?php foreach($coursedetailsarray as $coursedetailsarrayvalue) { ?>
							<li>
								<div class="course-item">
									<div class="img-wr">
										<a href="<?php echo $coursedetailsarrayvalue['courseid'];?>"><img src="<?php echo $coursedetailsarrayvalue['courseimage'];?>" alt=""></a>
									</div>
									<div class="course-item-cont">
										<h5><a href="<?php echo $coursedetailsarrayvalue['courseid'];?>"><?php echo $coursedetailsarrayvalue['coursename'];?></a></h5>
										<?php if($coursedetailsarrayvalue['teachername'] != '') { ?>
											<h6><?php echo get_string('defaultcourseteacher');?> : <a href="javascript:void(0);"><?php echo $coursedetailsarrayvalue['teachername'];?></a></h6>
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
				</div><!-- END of .container-fluid -->
			</div><!-- END of .popular-courses -->
			<?php } ?>
			<div class="block-links">
				<div class="container">
					<div class="row">
						<div class="block-links-item span4 block-links-item-extraclass">
							<?php if(!empty($marketspot)) { ?><h3><?php if(!empty($marketspotfontawesomeicon)) { ?><i><img class="block-links-top-head" src="<?php echo $marketspotfontawesomeicon; ?>" alt=""></i><?php } ?><?php if(!empty($marketspot)) {echo $marketspot;} ?></h3><?php } ?>
							<?php if($marketspotsectiontext1 || $marketspotsectiontext2 || $marketspotsectiontext3 || $marketspotsectiontext4) { ?>
								<div class="vertical-aligned">
									<?php if(!empty($marketspotsection1)) { ?>
									<div><h5>
										<?php if(!empty($marketspotsectionfontawesomeicon1)) { ?><i><img class="block-links-mid-conts" src="<?php echo $marketspotsectionfontawesomeicon1; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h6><?php if(!empty($marketspotsectionlink1)) { ?><a href=<?php echo $marketspotsectionlink1; ?>><?php } if(!empty($marketspotsection1)) { echo $marketspotsection1; } ?></a></h6>
											<p><?php if(!empty($marketspotsectiontext1)) { echo $marketspotsectiontext1; } ?></p>
										</div>
									</div>
									<?php } if(!empty($marketspotsection2)) { ?>
									<div><h5>
										<?php if(!empty($marketspotsectionfontawesomeicon2)) { ?><i><img class="block-links-mid-conts" src="<?php echo $marketspotsectionfontawesomeicon2; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h6><?php if(!empty($marketspotsectionlink2)) { ?><a href=<?php echo $marketspotsectionlink2; ?>><?php } if(!empty($marketspotsection2)) { echo $marketspotsection2; } else { ?>Re: Quiz Notify Grader - Get Notification When Submitted  Notifies ALL teachers <?php } ?></a></h6>
											<p><?php if(!empty($marketspotsectiontext2)) { echo $marketspotsectiontext2; } ?></p>
										</div>
									</div>
									<?php } if(!empty($marketspotsection3)) { ?>
									<div><h5>
										<?php if(!empty($marketspotsectionfontawesomeicon3)) { ?><i><img class="block-links-mid-conts" src="<?php echo $marketspotsectionfontawesomeicon3; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h6><?php if(!empty($marketspotsectionlink3)) { ?><a href=<?php echo $marketspotsectionlink3; ?>><?php } if(!empty($marketspotsection3)) { echo $marketspotsection3; } else { ?>Re: Adding Text to the PayPal payment screen <?php } ?></a></h6>
											<p><?php if(!empty($marketspotsectiontext3)) { echo $marketspotsectiontext3; } ?></p>
										</div>
									</div>
									<?php } if(!empty($marketspotsection4)) { ?>
									<div><h5>
										<?php if(!empty($marketspotsectionfontawesomeicon4)) { ?><i><img class="block-links-mid-conts" src="<?php echo $marketspotsectionfontawesomeicon4; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h6><?php if(!empty($marketspotsectionlink4)) { ?><a href=<?php echo $marketspotsectionlink4; ?>><?php } if(!empty($marketspotsection4)) { echo $marketspotsection4; } else { ?>Observation log overview illustration <?php } ?></a></h6>
											<p><?php if(!empty($marketspotsectiontext4)) { echo $marketspotsectiontext4; } ?></p>
										</div>
									</div>
									<?php } ?>
								</div>
							<?php } ?>
							<?php $marketspotsectionbelowlinkname = get_config('theme_tikli', 'marketspotsectionbelowlinkname'); if(!empty($marketspotsectionbelowlinkname)) { ?>
								<a href="<?php echo get_config('theme_tikli', 'marketspotsectionbelowlink'); ?>" class="btn-view-all"><?php echo get_config('theme_tikli', 'marketspotsectionbelowlinkname'); ?></a>
							<?php } ?>
						</div>
						<div class="block-links-item block-links-download span4 block-links-item-extraclass">
							<?php if(!empty($secondmarketspot)) { ?><h3><?php if(!empty($secondmarketspotfontawesomeicon)) { ?><i><img class="block-links-top-head" src="<?php echo $secondmarketspotfontawesomeicon; ?>" alt=""></i><?php } ?><?php if(!empty($secondmarketspot)) { echo $secondmarketspot;} ?></h3><?php } ?>
							<?php if($secondmarketspotsectiontext1 || $secondmarketspotsectiontext2 || $secondmarketspotsectiontext3 || $secondmarketspotsectiontext4) { ?>
								<div class="vertical-aligned">
									<?php if(!empty($secondmarketspotsection1)) { ?>
									<div><h5>
										<?php if(!empty($secondmarketspotsectionfontawesomeicon1)) { ?><i><img class="block-links-mid-conts" src="<?php echo $secondmarketspotsectionfontawesomeicon1; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h5><?php if(!empty($secondmarketspotsectionlink1)) { ?><a href=<?php echo $secondmarketspotsectionlink1; ?>><?php } if(!empty($secondmarketspotsection1)) { echo $secondmarketspotsection1; } ?></a></h5>
											<p><?php if(!empty($secondmarketspotsectiontext1)) { echo $secondmarketspotsectiontext1; } ?></p>
										</div>
									</div>
									<?php } if(!empty($secondmarketspotsection2)) { ?>
									<div><h5>
										<?php if(!empty($secondmarketspotsectionfontawesomeicon2)) { ?><i><img class="block-links-mid-conts" src="<?php echo $secondmarketspotsectionfontawesomeicon2; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h5><?php if(!empty($secondmarketspotsectionlink2)) { ?><a href=<?php echo $secondmarketspotsectionlink2; ?>><?php } if(!empty($secondmarketspotsection2)) { echo $secondmarketspotsection2; } ?></a></h5>
											<p><?php if(!empty($secondmarketspotsectiontext2)) { echo $secondmarketspotsectiontext2; } ?></p>
										</div>
									</div>
									<?php } if(!empty($secondmarketspotsection3)) { ?>
									<div><h5>
										<?php if(!empty($secondmarketspotsectionfontawesomeicon3)) { ?><i><img class="block-links-mid-conts" src="<?php echo $secondmarketspotsectionfontawesomeicon3; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h5><?php if(!empty($secondmarketspotsectionlink3)) { ?><a href=<?php echo $secondmarketspotsectionlink3; ?>><?php } if(!empty($secondmarketspotsection3)) { echo $secondmarketspotsection3; } ?></a></h5>
											<p><?php if(!empty($secondmarketspotsectiontext3)) { echo $secondmarketspotsectiontext3; } ?></p>
										</div>
									</div>
									<?php } if(!empty($secondmarketspotsection4)) { ?>
									<div><h5>
										<?php if(!empty($secondmarketspotsectionfontawesomeicon4)) { ?><i><img class="block-links-mid-conts" src="<?php echo $secondmarketspotsectionfontawesomeicon4; ?>" alt=""></i><?php } ?>
										</h5><div>
											<h5><?php if(!empty($secondmarketspotsectionlink4)) { ?><a href=<?php echo $secondmarketspotsectionlink4; ?>><?php } if(!empty($secondmarketspotsection4)) { echo $secondmarketspotsection4; } ?></a></h5>
											<p><?php if(!empty($secondmarketspotsectiontext4)) { echo $secondmarketspotsectiontext4; } ?></p>
										</div>
									</div>
									<?php } ?>
								</div>
							<?php } ?>
							<?php $secondmarketspotsectionbelowlinkname = get_config('theme_tikli', 'secondmarketspotsectionbelowlinkname'); if(!empty($secondmarketspotsectionbelowlinkname)) { ?>
								<a href="<?php echo get_config('theme_tikli', 'secondmarketspotsectionbelowlink'); ?>" class="btn-view-all"><?php echo get_config('theme_tikli', 'secondmarketspotsectionbelowlinkname'); ?></a>
							<?php } ?>
						</div>
						<?php if(!empty($contactwithus)) { ?>
						<div class="block-links-item block-links-connect span4 block-links-item-extraclass">
							<h3><?php if(!empty($contactusheadingicon)) { ?><i><img class="block-links-top-head" src="<?php echo $contactusheadingicon;?>" alt=""></i><?php } ?><?php if(!empty($contactwithus)) {echo $contactwithus;} ?></h3>
							<div class="vertical-aligned">
								<?php if($facebook || $googleplus || $youtube || $twitter) { ?>
									<div class="social-links">
										<?php if(!empty($facebook)) { ?>
											<a href="<?php echo $facebook; ?>"><?php if(!empty($socialfontawesomeicon1)) { ?><img class="block-links-socailcontacts" src="<?php echo $socialfontawesomeicon1;?>" alt=""><?php } ?></a>
										<?php } ?>
										<?php if(!empty($googleplus)) { ?>
											<a href="<?php echo $googleplus; ?>"><?php if(!empty($socialfontawesomeicon2)) { ?><img class="block-links-socailcontacts" src="<?php echo $socialfontawesomeicon2;?>" alt=""><?php } ?></a>
										<?php } ?>
										<?php if(!empty($youtube)) { ?>
											<a href="<?php echo $youtube; ?>"><?php if(!empty($socialfontawesomeicon3)) { ?><img class="block-links-socailcontacts" src="<?php echo $socialfontawesomeicon3;?>" alt=""><?php } ?></a>
										<?php } ?>
										<?php if(!empty($twitter)) { ?>
											<a href="<?php echo $twitter; ?>"><?php if(!empty($socialfontawesomeicon4)) { ?><img class="block-links-socailcontacts" src="<?php echo $socialfontawesomeicon4;?>" alt=""><?php } ?></a>
										<?php } ?>
									</div>
								<?php } ?>
								<h2>MOODLE LMS</h2>
								<?php if($address || $phone || $email) { ?>
									<div>
										<?php if(!empty($addressfontawesomeicon)) { ?><i><img class="block-links-contacts" src="<?php echo $addressfontawesomeicon; ?>" alt=""></i><?php } ?>
										<div>
											<?php if(!empty($address)) { ?>
												<p><?php echo $address;?></p>
											<?php } ?>
										</div>
									</div>
									<div>
										<?php if(!empty($phonefontawesomeicon)) { ?><i><img class="block-links-contacts" src="<?php echo $phonefontawesomeicon;?>" alt=""></i><?php } ?>
										<div>
											<?php if(!empty($phone)) { ?>
												<p><?php echo $phone; ?></p>
											<?php } ?>
										</div>
									</div>
									<div>
										<?php if(!empty($emailfontawesomeicon)) { ?><i><img class="block-links-contacts" src="<?php echo $emailfontawesomeicon;?>" alt=""></i><?php } ?>
										<div>
											<?php if(!empty($email)) { ?>
												<p><?php echo $email; ?></p>
											<?php } ?>
										</div>
									</div>
								<?php } ?>
							</div>
						</div>
						<?php } ?>
					</div>
				</div><!-- END of .container -->
			</div><!-- END of .block-links -->
		</div><!-- END of .content -->
		<?php
			echo $OUTPUT->main_content();
			include('footer.php');
		?>
	
<?php if (!isloggedin()) { ?>
	<script type="text/javascript">
	$(".btn-navbar").on("click",function() {
	   $(this).toggleClass('active-drop');
	   $(".nav-collapse").toggleClass('landing-no-login');
	});
	</script>
<?php } else { ?>
	<script type="text/javascript">
	$(".btn-navbar").on("click",function() {
		$(this).toggleClass('active-drop');
		$(".nav-collapse").toggleClass('drop-logged-in');
	});
	</script>
<?php } ?>
<script type="text/javascript" src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/frontpageslider/jquery.cslider.js"></script>
</body>
</html>