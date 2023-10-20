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
 * MoodleCloud Dataset class defines data to be collected from each site.
 *
 * @package    local_moodlecloud
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud;

use local_logging\logger;
use stdClass;

class dataset {
    public static function gather() {
        $dataset = new stdClass();
        $dataset->owner_login_history = self::get_owner_login_history();
        return $dataset;

    }

    public static function get_owner_login_history() {
        global $DB;
        $sql = "
                select 
                    min(timecreated) as first_login_by_owner,
                    max(timecreated) as last_login_by_owner,
                    count(timecreated) as total_logins_by_owner
                from {logstore_standard_log} 
                where eventname = :eventname 
                and action = :action
                and objectid = :owneruserid
                group by objectid
            ";

        // The assumption here is that the owner of the Moodle cloud site
        // will always get user ID 2 in the database as their main login.

        $params = [
            'eventname' => '\core\event\user_loggedin',
            'action' => 'loggedin',
            'owneruserid' => 2
        ];

        $result = $DB->get_record_sql($sql, $params);

        $data = new stdClass();
        $data->first_login = gmdate("Y-m-d H:i:s T", $result->first_login_by_owner);
        $data->last_login = gmdate("Y-m-d H:i:s T", $result->last_login_by_owner);
        $data->total_logins = $result->total_logins_by_owner;
        return $data;
    }

    public static function get_site_name() {

    }

    public static function log_data() {
        logger::log('dataset', (array)self::gather(), 'dataset');
    }
}
