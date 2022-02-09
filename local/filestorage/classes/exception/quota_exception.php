<?php

namespace local_filestorage\exception;

require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

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
        $a->sitename = $dynamicsite;
        parent::__construct('quotahit', 'local_filestorage', '', $a);
    }
}
