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
 * NPS Survey block Language pack.
 *
 * @package    block_nps_survey
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'NPS';

// Plugin Settings.
$string['surveytitle'] = 'Survey Title';
$string['surveytitledefault'] = 'MoodleCloud Site Feedback';
$string['surveytitleexplanation'] = 'Enter the survey title.';

$string['surveytext'] = 'Survey Text';
$string['surveytextdefault'] = 'The purpose of this survey is to collect your feedback to improve MoodleCloud’s products and services.';
$string['surveytextexplanation'] = 'Enter explanation text for the survey here.';

$string['surveylink'] = 'Survey Link';
$string['surveylinkdefault'] = 'https://feedback.moodle.org/index.php?r=survey/index&sid=417133&lang=en';
$string['surveylinkexplanation'] = 'Enter the URL for the NPS survey here.';

$string['surveyopendate'] = 'Survey open date';
$string['surveyopendatedefault'] = '20221017';
$string['surveyopendateexplanation'] = 'Enter the date when the survey should open in YYYYMMDD format. If empty, the survey will be open immediately';

$string['surveyclosedate'] = 'Survey close date';
$string['surveyclosedatedefault'] = '20221130';
$string['surveyclosedateexplanation'] = 'Enter the date when the survey should close in YYYYMMDD format. If empty, the survey will remain open indefinitely (never close).';
