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
 * Language strings.
 *
 * @package   local_moodlecloud
 * @copyright 2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['emailnotifications'] = 'Email';
$string['emailnotificationsinfo'] = 'Email notifications are sent to the regisitered site account owner when site limits are approaching or have been reached.
Disable the notifications if the account owner no longer wishes to receive these emails.';
$string['onlysiteownercanchangesettings'] = 'Only the site owner can modify these settings.';
$string['pluginname'] = 'MoodleCloud';
$string['reporting_task'] = 'MoodleCloud Statistics Reporting';
$string['touchpoint_task'] = 'MoodleCloud Touchpoints';
$string['userquotahit'] = 'You have reached your quota for the number of users you may have on your site. To get more users, upgrade your plan using the following link https://moodlecloud.com/app/en/portal/view/{$a->sitename}/plan';

$string['send_user_limit_warning'] = 'Send user limit warning';
$string['send_user_limit_warning_description'] = 'An email will be sent when your site is approaching the user limit. You will be able to continue to add users until you reach your limit.';

$string['send_user_limit_reached'] = 'Send user limit reached';
$string['send_user_limit_reached_description'] = 'An email will be sent when your site user limit is reached. You will not be able to add any further users until you delete users you no longer need or you increase your limit by upgrading to a larger plan.';

$string['send_file_storage_limit_warning'] = 'Send file storage limit warning';
$string['send_file_storage_limit_warning_description'] = 'An email will be sent when your site is approaching the file storage limit. You will be able to continue to add files if they are smaller in size than the file storage amount remaining';

$string['send_file_storage_limit_reached'] = 'Send file storage limit reached';
$string['send_file_storage_limit_reached_description'] = 'An email will be sent when your site file limit is reached. You will not be able to add any further files until you delete files you no longer need or you increase your limit by upgrading to a larger plan.';

$string['conversioncleanup'] = 'Stale document conversion cleanup.';

$string['emails'] = 'Email';
$string['notifications'] = 'Notifications';
$string['sitenotifications'] = 'Site Notifications';
$string['nonotifications'] = 'No notifications to show';
$string['sitenotifications'] = 'Site Notifications';
$string['viewnotification'] = 'View notification';

$string['privacy:metadata'] = 'The MoodleCloud plugin stores events and triggers for the site administrator but does not store any user IDs or otherwise personally identifiable information.';

$string['quotas'] = 'Quotas';
$string['mbused'] = 'MB used';
$string['spaceused'] = 'Space used';
$string['filetype'] = 'File type';
$string['users'] = 'Users';
$string['storage'] = 'Storage';
