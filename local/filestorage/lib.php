<?php

use local_filestorage\file_storage\file_system_s3;
use local_filestorage\exception\quota_exception;

function local_filestorage_before_file_created($newfile, $fileinfo) {
    if (!defined('FILESTORAGE_QUOTA')) {
        return;
    }

    // Sometimes the new file will be null (example, directory creation).
    if (!$newfile) {
        return;
    }

    if ($newfile->component == 'tool_recyclebin') {
        return;
    }

    if ($newfile->component == 'backup' && $newfile->mimetype == 'application/vnd.moodle.backup') {
        return;
    }

    if ($newfile->component == 'assignfeedback_editpdf') {
        return;
    }

    if (local_filestorage_site_has_unlimited_quota()) {
        return;
    }

    $filesize = $fileinfo['content'] ? strlen($fileinfo['content']) : filesize($fileinfo['pathname']);

    // If somehow we get here and the filesize is still zero just quit. It won't affect the quota.
    if ($filesize === 0) {
        return;
    }

    $current = file_system_s3::unique_storage_size_used();

    if (($current + $filesize) > FILESTORAGE_QUOTA) {
        throw new quota_exception($current, $filesize);
    }
}

/**
 * Does this site have unlimited quota?
 *
 * @return bool true if site has unlimited quota.
 */
function local_filestorage_site_has_unlimited_quota() {
    return defined('FILESTORAGE_QUOTA') && FILESTORAGE_QUOTA == -1;
}
