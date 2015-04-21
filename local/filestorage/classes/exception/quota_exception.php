<?php

namespace local_filestorage\exception;

require_once(dirname(dirname(__DIR__)) . '/vendor/autoload.php');

/**
 * File Quota exception.
 */
class quota_exception extends \moodle_exception {
    /**
     * Constructor
     *
     * @param string $errorcode error code
     * @param stdClass $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     */
    function __construct($current, $filesize) {
        $a = new \stdClass();
        $a->current = $current;
        $a->filesize = $filesize;
        $a->helpurl = 'https://moodle.com/faq/quota';
        parent::__construct('quotahit', 'local_filestorage', '', $a);
    }
}
