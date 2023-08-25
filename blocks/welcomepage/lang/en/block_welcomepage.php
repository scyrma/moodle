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
 * Welcome page block Language pack.
 *
 * @package    block_welcomepage
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Welcome Page';
$string['title'] = 'Welcome';
$string['welcomepage:addinstance'] = 'Add Welcome Page';
$string['dismiss'] = 'Got it';
$string['imagedescription'] = 'Welcome to your new MoodleCloud site';

// Plugin Settings.
$string['contentbaseurl'] = 'Content Base URL';
$string['contentbaseurldefault'] = 'https://assets.gl.moodlecloud.com/welcome/';
$string['contentbaseurlexplanation'] = 'Specify the base URL of the welcome block content in S3.';

$string['uselocalcontent'] = 'Use local content?';
$string['uselocalcontentdefault'] = false;
$string['uselocalcontentexplanation'] = 'Specify if the block should use local content.';

$string['dismissed'] = 'Has the block been dismissed?';
$string['dismisseddefault'] = false;
$string['dismissedexplanation'] = 'Specify if the block has been dismissed and is no longer visible on the dashboard. Note if you want to restore this, you need to logout from the site once unset here.';
