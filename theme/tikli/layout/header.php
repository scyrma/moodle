<?php
// Get the HTML for the settings bits.
$html = theme_tikli_get_html_for_settings($OUTPUT, $PAGE);
GLOBAL $USER, $PAGE, $CFG, $DB;

$checklogo = $PAGE->theme->setting_file_url('logo', 'logo');
if(!empty($checklogo)) {
  $haslogo = $PAGE->theme->setting_file_url('logo', 'logo');
} else {
  $haslogo = $CFG->wwwroot.'/theme/tikli/pix/logo-2.png';
}

$checkicon = $PAGE->theme->setting_file_url('icon', 'icon');
if(!empty($checkicon)) {
  $hasiconlogo = $PAGE->theme->setting_file_url('icon', 'icon');
} else {
  $hasiconlogo = $CFG->wwwroot.'/theme/tikli/pix/icon-logo.png';
}
echo $OUTPUT->doctype();

$isregistration = $DB->get_record('config', array('name'=>'registerauth'));
$colorscheme = get_config('theme_tikli', 'colorscheme');
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo $OUTPUT->page_title(); ?></title>
  <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
  <link rel="stylesheet" href="<?php echo $CFG->wwwroot ?>/theme/tikli/css/font-awesome.css">
  <link href="<?php echo $CFG->wwwroot ?>/theme/tikli/css/styles.css" rel="stylesheet">
  <script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery-2.1.4.js"></script>
  <script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/bootstrap.min.js"></script>
  <script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/jquery.bxslider.min.js"></script>
  <script src="<?php echo $CFG->wwwroot; ?>/theme/tikli/js/engine.js"></script>
  <link href="<?php echo $CFG->wwwroot ?>/theme/tikli/css/bootstrap.css" rel="stylesheet">
  <link href="<?php echo $CFG->wwwroot ?>/theme/tikli/css/bootstrap-responsive.css" rel="stylesheet">
  <link href="<?php echo $CFG->wwwroot ?>/theme/tikli/css/jquery.bxslider.css" rel="stylesheet">
  <?php
      include($CFG->dirroot . '/theme/tikli/settings/colorchange.php');
  ?>
  <?php echo $OUTPUT->standard_head_html() ?>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
<?php echo $OUTPUT->standard_top_of_body_html() ?>
<header role="banner" class="navbar navbar-fixed-top">
  <nav role="navigation" class="navbar-inner">
    <?php if (get_config('theme_tikli', 'logoorsitename') === "logo") { ?>
    <div class="logo-wr">
      <a class="logo-img" href="<?php echo $CFG->wwwroot; ?>">
        <img alt="logo" src="<?php echo $haslogo;?>" />
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
        <h1><span class="logoicon"><img alt="logo" src="<?php echo $hasiconlogo;?>" /></span><span><?php echo $SITE->fullname; ?></span></h1>
      </a>
    </div>
    <?php } ?>
    <button class="side-pre-menu menu-toggle">
        <span>toggle menu</span>
    </button>

<?php
    require_once(__DIR__ . '/usermenu.php');
?>
  </nav>
</header>
