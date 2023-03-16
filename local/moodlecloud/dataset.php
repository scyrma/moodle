<?php

/**
 * MoodleCloud Dataset manual trigger.
 *
 * @package    local_moodlecloud
 * @copyright  MoodleCloud Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud;
define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');

use local_logging\logger;


// TODO: Implement this for production to restrict access.
/*
$token = required_param('token', PARAM_ALPHANUM);

if (isset($CFG->dataset_tokens) && is_array($CFG->dataset_tokens)) {
    $validtokens = $CFG->dataset_tokens;
} else {
    $validtokens = array();
}

if (!in_array($token, $validtokens)) {
    throw new require_login_exception('Invalid dataset token');
}
*/
$dataset = \local_moodlecloud\dataset::gather();
logger::log('datalake', (array)$dataset, 'datalake');
echo json_encode($dataset, JSON_PRETTY_PRINT);
