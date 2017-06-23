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
 * Files list controller.
 *
 * @package    tool_fileslist
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_fileslist;

use moodle_url;
use local_cloud\db_row_collection_factory;

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_fileslist/files');

global $OUTPUT;
global $DB;

$PAGE->set_context(\context_system::instance());

echo $OUTPUT->header();

echo \html_writer::start_tag('div');
(function($quota, $used) {
    echo get_string('usedquotaconsiderupgrade', 'tool_fileslist', (object)[
        'percentage' => round(
            ($used / $quota) * 100,
            0,
            PHP_ROUND_HALF_UP
        ),
        'total' => (function($bytes, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');

            $bytes = max($bytes, 0);
            $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
            $pow = min($pow, count($units) - 1);
            $bytes /= (1 << (10 * $pow));

            return round($bytes, $precision) . ' ' . $units[$pow];
        })($quota),
        'url' => (object)['url' => (new \moodle_url('http://example.com'))->out()]
    ]);
})(
    ...defined('FILESTORAGE_QUOTA') ? [FILESTORAGE_QUOTA, \local_filestorage\file_storage\file_system_s3::unique_storage_size_used()]
                                    : [209715200, 8388608]
);
echo \html_writer::end_tag('div');

echo $OUTPUT->render_from_template('tool_fileslist/main', []);
echo $OUTPUT->footer();

