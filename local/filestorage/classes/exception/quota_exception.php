<?php

namespace local_filestorage\exception;

require_once(dirname(dirname(__DIR__)) . '/sdk/aws-autoloader.php');

/**
 * File Quota exception.
 */
class quota_exception extends \moodle_exception {
    /**
     * Constructor
     *
     * @param string $current
     * @param string $filesize
     */
    function __construct($current, $filesize) {
        global $dynamicsite;
        $a = new \stdClass();
        $a->current = $current;
        $a->filesize = $filesize;
        parent::__construct('quotahit', 'local_filestorage', 'https://moodlecloud.com/app/en/portal/view/' . $dynamicsite  . '/plan', $a);
    }
}
