<?php

use local_filestorage\file_storage\file_system_s3;
use local_filestorage\exception\quota_exception;

function local_filestorage_before_file_created($newfile, $fileinfo) {
    if (!defined('FILESTORAGE_QUOTA')) {
        return;
    }

    if ($newfile->component == 'tool_recyclebin') {
        return;
    }

    if ($newfile->component == 'backup' && $newfile->mimetype == 'application/vnd.moodle.backup') {
        return;
    }

    $filesize = $fileinfo['content'] ? strlen($fileinfo['content']) : filesize($fileinfo['pathname']);

    $current = file_system_s3::unique_storage_size_used();

    if (($current + $filesize) > FILESTORAGE_QUOTA) {
        throw new quota_exception($current, $filesize);
    }
}
