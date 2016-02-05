<?php
$THEME->name = 'tikli';

/////////////////////////////////
// The only thing you need to change in this file when copying it to
// create a new theme is the name above. You also need to change the name
// in version.php and lang/en/theme_tikli.php as well.
//////////////////////////////////
//
$THEME->doctype = 'html5';
$THEME->parents = array('bootstrapbase');
$THEME->lessfile = 'styles';
#$THEME->parents_exclude_sheets = array('bootstrapbase' => array('moodle'));
$THEME->lessvariablescallback = 'theme_tikli_less_variables';
$THEME->sheets = array('custom', 'moodlecloud');
$THEME->supportscssoptimisation = false;
$THEME->yuicssmodules = array();
$THEME->enable_dock = true;
$THEME->editor_sheets = array();
$THEME->blockrtlmanipulations = array(
    'side-pre' => 'side-pre',
    'side-post' => 'side-post'
);
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->csspostprocess = 'theme_tikli_process_css';
$THEME->layouts = array(
    // The site home page.
    'frontpage' => array(
        'file' => 'frontpage.php',
        'regions' => array(),
        'options' => array('nonavbar' => true),
    ),
    'login' => array(
        'file' => 'login.php',
        'regions' => array(),
        'options' => array('langmenu' => true),
    ),
    'coursecategory' => array(
        'file' => 'coursecategory.php',
        'defaultregion' => array(),
        'regions' => array('side-pre', 'side-post'),
        'options' => array('nonavbar' => false),
    ),
    'admin' => array(
        'file' => 'columns3.php',
        'defaultregion' => array(),
        'regions' => array('side-pre', 'side-post'),
    ),
);

