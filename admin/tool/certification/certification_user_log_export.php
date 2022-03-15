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
 * Certification log download exporter
 *
 * @package    tool_certification
 * @author     2019 Mikel Martín <mikel@moodle.com>
 * @copyright  2019 Moodle Pty Ltd <support@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license    Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

use tool_certification\permission;
use tool_certification\user_log;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/dataformatlib.php');

$dataformat = required_param('dataformat', PARAM_ALPHA);
$certificationid = required_param('certificationid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
require_login();
permission::require_can_view_user_progress($userid);

$user = core_user::get_user($userid);
$certification = new tool_certification\certification($certificationid);
$userlog = new user_log($user->id, $certification->get('id'));
$log = $userlog->get_user_log();
$columns = array(
    'user' => get_string('fullnameuser'),
    'action' => get_string('useractivity'),
    'date' => get_string('date')
);
// TODO: Correct filename with certification.
$override = has_capability('moodle/site:viewfullnames', $context);
$filename = clean_filename(get_string(
    'certificationuserlogfilename',
    'tool_certification',
    ['user' => fullname($user, $override), 'certification' => $certification->get('fullname')]
));

// Add last allocation date to the beginning of the log.
$date = userdate($log['lastallocationdate'], get_string('strftimedatetimeshort', 'langconfig'));
$allocationlog = [
  'user' => ['fullname' => fullname($user)],
  'event' => get_string('lastallocationdate', 'tool_certification', $date),
  'date' => $log['lastallocationdate'],
];
array_unshift($log['log'] , $allocationlog);

\core\dataformat::download_data($filename, $dataformat, $columns, new \ArrayIterator($log['log']),
    function($userevent) {
        $exportdata = array();
        $exportdata['user'] = $userevent['user']['fullname'];
        $exportdata['event'] = $userevent['event'];
        $exportdata['date'] = userdate($userevent['date'], get_string('strftimedatetimeshort', 'langconfig'));
        return $exportdata;
    }
);
