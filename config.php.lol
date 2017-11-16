<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'fileconvertercloudconvert';
$CFG->dbuser    = 'postgres';
$CFG->dbpass    = 'postgres';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => 5432,
  'dbsocket' => '',
);

$CFG->wwwroot   = 'http://localhost/fileconverter-cloudconvert';
$CFG->dataroot  = '/home/cameron/moodles/fileconverter-cloudconvert/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 02777;

// MDK Edit.
$CFG->wwwroot = 'http://fileconverter-cloudconvert.cloud.moodle.xn--tharkn-0ya.per.in.moodle.com';
$CFG->sessioncookiepath = '/fileconverter-cloudconvert/';

$CFG->cloudconvertapikey = '8ElRwXBpw6cRG3JMhwcKUcJMlHSR64L4b88ZAiBHJ2tn0HLmUa2M6CReoeD0cu8Har4W8XV0LAss5fdxJGkavg';
$CFG->awsconfig = [
    'region' => 'ap-southeast-2',
    'version' => 'latest',
    'credentials' => [
        'key' => 'AKIAILOUCGV73FECUQFQ',
        'secret' => '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'
    ]
];
$CFG->alternative_file_system_class = '\local_filestorage\file_storage\file_system_s3';
$CFG->s3bucket = 'moodle-dev';

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
