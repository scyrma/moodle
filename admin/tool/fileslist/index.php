<?php declare(strict_types=1);
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
 * Files list index.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

use html_writer;
use moodle_url;

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/local/filestorage/lib.php');

admin_externalpage_setup('tool_fileslist/files');

$PAGE->set_context(\context_system::instance());

echo $OUTPUT->header();
echo \html_writer::start_tag('div');

// When developing locally, the quota stuff isn't available. So check for it here
// and use it if present, otherwise just use 200MB and some random amount of usage between 0 and 200MB
list($quota, $used) = defined('FILESTORAGE_QUOTA') ? [(int)FILESTORAGE_QUOTA, (int)\local_filestorage\file_storage\file_system_s3::unique_storage_size_used()]
                                                   : [209715200, rand(0,209715200)];

$bytestohumanreadable = function(int $bytes) : string {
    $index = floor(log($bytes, 1024));
    return round($bytes/1024**$index, 2) . ' ' . ['B', 'KB', 'MB', 'GB'][$index];
};

echo local_filestorage_site_has_unlimited_quota()
    ? get_string('usedquotaunlimited', 'tool_fileslist', (object)['used' => $bytestohumanreadable($used)])
    : get_string('usedquotaconsiderupgrade',
                'tool_fileslist',
                (object)[
                    'used' => $bytestohumanreadable($used),
                    'percentage' => round(
                        ($used / $quota) * 100,
                        0,
                        PHP_ROUND_HALF_UP
                    ),
                    'total' => $bytestohumanreadable($quota),
                    'url' => (new moodle_url('/auth/moodlecloud/portal.php'))->out()
                ]
);

echo html_writer::start_tag('br');
echo html_writer::end_tag('br');

echo get_string('listoffiles', 'tool_fileslist');
echo html_writer::end_tag('div');

echo $OUTPUT->render_from_template(
    'tool_fileslist/main',
    [
        'numitems' => $DB->count_records_sql(
            "SELECT COUNT(*) FROM {files} WHERE filesize > 0 AND mimetype IS NOT NULL AND referencefileid IS NULL"
        ),
        'help' => [
            'action' => $OUTPUT->help_icon('action', 'tool_fileslist')
        ]
    ]
);

echo $OUTPUT->footer();
