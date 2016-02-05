<?php
GLOBAL $CFG;
$color_scheme = get_config('theme_tikli', 'colorscheme');
if ( get_config('theme_tikli', 'logoorsitename') === "sitename" ||  (get_config('theme_tikli', 'logoorsitename') === "iconsitename") ) {
?>
<style type = "text/css">
	.loginpanel h2:before, a.click {
		line-height: 81px !important;
		font-size: 22px !important;
		background-image: none !important;
	}
	#page .loginbox h2 a:hover {
		color: #FFF!important;
	}
</style>
<?php } ?>
