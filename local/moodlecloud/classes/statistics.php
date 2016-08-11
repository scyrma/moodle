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
 * MoodleCloud Statistics Library.
 *
 * @package   local_moodlecloud
 * @copyright 2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_moodlecloud;

use \stdClass;
use local_logging\logger;

class statistics {
    public static function get_statistics() {
        $data = new stdClass();

        // Get file usage data.
        $data->files = array(
                'usage'     => array(
                        'including_drafts' => self::get_disk_usage(),
                        'excluding_drafts' => self::get_disk_usage(true),
                    ),
                'types'     => array(
                        'including_drafts' => self::get_file_breakdown(),
                        'excluding_drafts' => self::get_file_breakdown(true),
                    ),
            );

        // Table sizes.
        $data->logtable = array(
                'rows'      => self::get_logtable_rowcount(),
                'size'      => self::get_logtable_size(),
            );

        $data->db = array(
                'size'      => self::get_database_size(),
            );

        $timeperiods = array(
            '1_minute'      => 1    * MINSECS,
            '5_minute'      => 5    * MINSECS,
            '15_minute'     => 15   * MINSECS,
            '1_hour'        => 1    * HOURSECS,
            '8_hour'        => 8    * HOURSECS,
            '1_day'         => 1    * DAYSECS,
            '1_week'        => 1    * WEEKSECS,
            '31_days'       => 31   * DAYSECS,
            '1_year'        => 1    * YEARSECS,
        );

        // Get concurrent user counts.
        $uniquelastaccess = array();
        foreach ($timeperiods as $desc => $period) {
            $uniquelastaccess[$desc] = self::get_unique_lastaccess($period);
        }

        $data->users = array(
                // Count of all users, and deleted users.
                // This does include system users (guest).
                'total'         => self::get_user_count(),
                'deleted'       => self::get_user_count(1),
                'lastaccess'    => $uniquelastaccess,
            );

        return $data;
    }

    public static function log_statistics() {
        logger::log('statistics', (array) self::get_statistics(), 'statistics');
    }

    protected static function since_time($overlastminutes) {
        return "DATE_PART('epoch', now() + '-" . $overlastminutes . " seconds')::int";
    }

    public static function get_unique_lastaccess($overlastminutes = 1) {
        global $DB;
        $sql = "SELECT COUNT('x') AS count FROM {user}
                WHERE lastaccess > " . self::since_time($overlastminutes);
        return $DB->get_field_sql($sql);
    }

    public static function get_logtable_size() {
        global $DB;
        $sql = "SELECT pg_relation_size('{log}')";
        return $DB->get_field_sql($sql);
    }

    public static function get_logtable_rowcount() {
        global $DB;
        $sql = "SELECT COUNT('x') FROM {log}";
        return $DB->get_field_sql($sql);
    }

    public static function get_database_size() {
        global $DB, $CFG;
        $sql = "SELECT pg_database_size('" . $CFG->dbname . "')";
        return $DB->get_field_sql($sql);
    }

    public static function get_disk_usage($excludedraft = false) {
        global $DB;

        $where = '';
        $params = array();
        if ($excludedraft) {
            $where = 'AND filearea <> ?';
            $params[] = 'draft';
        }
        $sql = <<<EOF
SELECT
    SUM(f.filesize)
FROM (
    SELECT DISTINCT
        filesize
    FROM {files}
    WHERE referencefileid IS NULL
       AND component <> 'tool_recyclebin'
    {$where}
    GROUP BY filesize, contenthash
) AS f;
EOF;

        return $DB->get_field_sql($sql, $params);
    }

    public static function get_file_breakdown($excludedraft = false) {
        global $DB;

        $where = '';
        $params = array();
        if ($excludedraft) {
            $where = 'AND filearea <> ?';
            $params[] = 'draft';
        }

        $sql = <<<EOF
SELECT
    iq.mimetype,
    SUM(iq.filesize) AS filesize
FROM (
    SELECT
        filesize,
        regexp_replace(mimetype, '/.+\$', '') AS mimetype
    FROM {files}
    WHERE filesize > 0
    {$where}
    GROUP BY filesize, regexp_replace(mimetype, '/.+\$', ''), contenthash
) iq
GROUP BY iq.mimetype
EOF;

        $results = $DB->get_records_sql($sql, $params);
        $data = array();
        foreach ($results as $result) {
            $data[$result->mimetype] = $result->filesize;
        }

        return $data;
    }

    public static function get_user_count($deleted = null) {
        global $DB;
        $params = array();

        // Exclude the guest user.
        $where = 'username <> ?';
        $params[] = 'guest';

        if (null !== $deleted) {
            $where .= ' AND deleted = ?';
            $params[] = $deleted;
        }

        return $DB->count_records_select('user', $where, $params);
    }
}
