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
 * Log store lang strings.
 *
 * @package    logstore_moodlecloud
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'MoodleCloud log';
$string['pluginname_desc'] = 'A log store which stores MoodleCloud logs';
$string['privacy:metadata:logstore_moodlecloud:context'] = 'Additional context to accompany the event.';
$string['privacy:metadata:logstore_moodlecloud:event'] = 'The event to log.';
$string['privacy:metadata:logstore_moodlecloud:params'] = 'The query parameters passed to the external logging service.';
$string['privacy:metadata:logstore_moodlecloud:externalpurpose'] = 'This information is sent to an external log aggregation service. The logs are temporarily kept on the service provider\'s servers and periodically purged. Currently logentries is used to provide this service.';