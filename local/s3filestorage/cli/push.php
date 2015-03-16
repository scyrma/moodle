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

define('CLI_SCRIPT', true);
require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');

if (!isset($CFG->filesystem_handler_class) ||
    $CFG->filesystem_handler_class !== '\local_s3filestorage\file_storage\file_system' ||
    !isset($CFG->s3bucket)) {
    echo "S3 Is not configured.\n";
    exit(255);
}

echo "Syncing the file directory to S3 bucket {$CFG->s3bucket}\n";
$fs = get_file_storage();
$fs->sync_filedir(true);
echo "Sync complete.\n";
