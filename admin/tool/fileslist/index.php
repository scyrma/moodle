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

\html_writer::start_tag('div');
if (defined('FILESTORAGE_QUOTA')) {
    echo get_string('usedquota', 'tool_fileslist', (object)[
        'percentage' => (\local_filestorage\file_storage\file_system_s3::unique_storage_size_used() / FILESTORAGE_QUOTA) * 100,
        'total' => FILESTORAGE_QUOTA
    ]);
}

echo $OUTPUT->render_from_template('tool_fileslist/main', []);
echo $OUTPUT->footer();

