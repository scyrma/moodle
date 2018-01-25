<?php

use local_filestorage\file_storage\file_system_s3;
use local_filestorage\exception\quota_exception;

//require_once(__DIR__ . '/sdk/aws-autoloader.php');
//use Aws\DynamoDb\Exception\DynamoDbException;
//use Aws\DynamoDb\Marshaler;

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

/*
function local_filestorage_after_file_created($newfile) {
    if ($newfile->filearea == "draft") {
        return;
    }

    // $dynamicsite comes from config.php
    global $dynamicsite;

    $sdk = new Aws\Sdk([
        'endpoint'   => null,
        'region'   => 'us-east-1',
        'version'  => 'latest'
    ]);

    $dynamodb = $sdk->createDynamoDb();
    $marshaler = new Marshaler();

    $table = 's3metadata';

    $userid = $newfile->userid;
    if (!$userid) {
        $userid = "None";
    }

    $item = $marshaler->marshalJson('
        {
            "id": "'.$dynamicsite.':'.$newfile->id.'",
            "contenthash": "'.$newfile->contenthash.'",
            "site": "'.$dynamicsite.'",
            "filename": "'.$newfile->filename.'",
            "filesize": "'.$newfile->filesize.'",
            "userid": "'.$userid.'",
            "filearea": "'.$newfile->filearea.'",
            "timecreated": "'.$newfile->timecreated.'",
            "deleted": 0
        }
    ');

    $params = [
        'TableName' => $table,
        'Item' => $item
    ];

    try {
        $result = $dynamodb->putItem($params);
        error_log("Added item to DynamoDB: ".$newfile->contenthash);
    } catch (DynamoDbException $e) {
        error_log("Unable to add item to DynamoDB: ".$e->getMessage());
    }
}

function local_filestorage_after_file_deleted($file) {
    if ($file->filearea == "draft") {
        return;
    }
    // $dynamicsite comes from config.php
    global $dynamicsite;

    $sdk = new Aws\Sdk([
        'endpoint'   => null,
        'region'   => 'us-east-1',
        'version'  => 'latest'
    ]);

    $dynamodb = $sdk->createDynamoDb();
    $marshaler = new Marshaler();

    $table = 's3metadata';

    $userid = $file->userid;
    if (!$userid) {
        $userid = "None";
    }

    $key = $marshaler->marshalJson('
        {
            "id": "'.$dynamicsite.':'.$file->id.'"
        }
    ');

    // Delete the item
    $params = [
        'TableName' => $table,
        'Key' => $key
    ];

    try {
        $result = $dynamodb->deleteItem($params);
        error_log("Deleted item from dynamodb: ".$file->contenthash);
    } catch (DynamoDbException $e) {
        error_log("Unable to delete from dynamodb item: ".$file->contenthash." : ".$e->getMessage());
    }
}
*/
