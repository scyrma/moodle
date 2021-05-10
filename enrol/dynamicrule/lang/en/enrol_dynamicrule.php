<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Strings for component 'enrol_dynamicrule', language 'en'.
 *
 * @package     enrol_dynamicrule
 * @category    string
 * @copyright   2018 Moodle Pty Ltd <support@moodle.com>
 * @author      2018 Workplace team
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['actiondisableenrolment'] = 'Disable enrolment';
$string['actiondisableenrolmentremoveroles'] = 'Disable enrolment and remove roles';
$string['actionunenrol'] = 'Unenrol user from course';
$string['duration'] = 'Duration';
$string['dynamicrule:config'] = 'Configure dynamicrule enrol instances';
$string['dynamicrule:enrol'] = 'Enrol user using course enrol action';
$string['dynamicrule:unenrol'] = 'Unenrol user using course unenrol action or manually';
$string['enddate'] = 'End date';
$string['errorinvalidaction'] = 'Invalid action';
$string['errorinvalidcoursetoenrol'] = 'Invalid course to enrol';
$string['errorinvalidcoursetounenrol'] = 'Invalid course to unenrol';
$string['errorinvaliddurationandenddate'] = 'You must choose enrolment end date OR duration, not both.';
$string['errorinvalidrole'] = 'Invalid role';
$string['group'] = 'Group name';
$string['group_help'] = 'The name of the group the users will be added as member on.';
$string['ingroup'] = 'in the group';
$string['noavailablecoursesenrol'] = 'No courses where you can enrol users using Dynamic rule enrolment method.';
$string['noavailablecoursesunenrol'] = 'No courses where you can unenrol users using Dynamic rule enrolment method.';
$string['outcomecourseenrol'] = 'Enrol users into a course';
$string['outcomecourseenroldescription'] = 'Enrol in course \'{$a->coursename}\'<br />
Role: {$a->role}<br />
Group: {$a->groupname}';
$string['outcomecourseenroldescriptionwithduration'] = 'Enrol in course \'{$a->coursename}\'<br />
Role: {$a->role}<br />
Group: {$a->groupname}<br />
Duration: {$a->duration} {$a->durationtype}';
$string['outcomecourseenroldescriptionwithenddate'] = 'Enrol in course \'{$a->coursename}\'<br />
Role: {$a->role}<br />
Group: {$a->groupname}<br />
End date: {$a->enddate}';
$string['outcomecourseunenrol'] = 'Unenrol users from a course';
$string['outcomecourseunenroldescription'] = 'Unenrol from course \'{$a->coursename}\'<br />
Action: \'{$a->action}\'';
$string['pluginname'] = 'Dynamic rules';
$string['privacy:metadata'] = 'The Dynamic rules plugin does not store any personal data about any user.';
$string['selectcourse'] = 'Select course';
