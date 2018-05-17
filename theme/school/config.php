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

$THEME->name = 'school';

/////////////////////////////////
// The only thing you need to change in this file when copying it to
// create a new theme is the name above. You also need to change the name
// in version.php and lang/en/theme_school.php as well.
//////////////////////////////////
//
$THEME->iconsystem = \core\output\icon_system::FONTAWESOME;
$THEME->doctype = 'html5';
$THEME->parents = array('bootstrapbase');
$THEME->lessfile = 'styles';
$THEME->parents_exclude_sheets = array('bootstrapbase' => array('moodle'));
$THEME->lessvariablescallback = 'theme_school_less_variables';
$THEME->sheets = array('moodlecloud', 'jquery.bxslider', 'cssliderstyle', 'custom');
$THEME->supportscssoptimisation = false;
$THEME->yuicssmodules = array();
$THEME->enable_dock = true;
$THEME->editor_sheets = array();
$THEME->blockrtlmanipulations = array(
    'side-pre' => 'side-pre',
    'side-post' => 'side-post'
);
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->csspostprocess = 'theme_school_process_css';
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

