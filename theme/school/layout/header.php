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
global $USER, $PAGE, $CFG, $DB;

echo $OUTPUT->doctype();

$isregistration = $DB->get_record('config', array('name'=>'registerauth'));
?>

<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php
        echo $OUTPUT->standard_theme_head_html();
        echo $OUTPUT->standard_head_html()
    ?>
</head>
<body <?php echo $OUTPUT->body_attributes(); ?>>
    <?php echo $OUTPUT->standard_top_of_body_html() ?>
    <header role="banner" class="navbar navbar-fixed-top">
        <nav role="navigation" class="navbar-inner">
            <?php echo $OUTPUT->logo(); ?>

            <button class="side-pre-menu menu-toggle">
                <span>toggle menu</span>
            </button>

            <?php echo $OUTPUT->user_menu(); ?>

            <?php if (isloggedin() && !empty($CFG->custommenuitems)) { ?>
                <a class="btn btn-navbar">
                    <i class="fa fa-arrow-circle-down"></i>
                </a>
                <div class="nav-collapse collapse">
                    <?php echo $OUTPUT->custom_menu();?>
                </div>
            <?php } ?>
        </nav>
    </header>
