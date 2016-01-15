<?php
if (isset($_GET['new'])) {
    echo $OUTPUT->user_menu();
} else {
?>
    <?php if(isloggedin()) {
      if ( $CFG->version >= '2015051100.00' ) {
        $file = get_string('privatefiles');
      } else {
          $file = get_string('myfiles');
      }
    ?>
      <div class="usermenu">
        <div>
          <ul class="menubar menubars">
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
          <ul class="menu menulist">
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
      <?php if (!empty($CFG->custommenuitems)) { ?>
        <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
          <i class="fa fa-arrow-circle-down"></i>
        </a>
        <div class="nav-collapse collapse">
        <?php echo $OUTPUT->custom_menu();?>
        </div>
      <?php } ?>
    <?php } else { ?>
    <div class="logining-wr">
        <a href="<?php echo $CFG->wwwroot; ?>/login/index.php"><?php echo get_string('login');?></a>
        <?php if($isregistration->value == 'email') { ?>
          <a href="<?php echo $CFG->wwwroot; ?>/login/signup.php?"><?php echo get_string('startsignup'); ?></a>
        <?php } ?>
    </div>
    <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
      <i class="fa fa-arrow-circle-down"></i>
    </a>
    <div class="nav-collapse collapse">
      <?php echo $OUTPUT->custom_menu();?>
    </div>
    <?php } ?>
<?php } ?>
