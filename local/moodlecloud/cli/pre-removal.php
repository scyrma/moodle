<?php

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');
require_once($CFG->dirroot . '/' . $CFG->admin . '/registration/lib.php');
require_once($CFG->dirroot . '/course/publish/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . "/webservice/xmlrpc/lib.php");

@ini_set('display_errors', '1');
@ini_set('log_errors', '1');
$CFG->debug = (E_ALL | E_STRICT);
$CFG->debugdisplay = 1;

$huburl = HUB_MOODLEORGHUBURL;

$registrationmanager = new \registration_manager();
$publicationmanager = new course_publish_manager();
if ($hub = $registrationmanager->get_registeredhub($huburl)) {

    $hubcourseids = array();
    $enrollablecourses = $publicationmanager->get_publications($huburl, null, 1);
    if (!empty($enrollablecourses)) {
        foreach ($enrollablecourses as $enrollablecourse) {
            $hubcourseids[] = $enrollablecourse->hubcourseid;
        }
    }
    //downloadable courses
    $downloadablecourses = $publicationmanager->get_publications($huburl, null, 0);
    if (!empty($downloadablecourses)) {
        foreach ($downloadablecourses as $downloadablecourse) {
            $hubcourseids[] = $downloadablecourse->hubcourseid;
        }
    }

    $function = 'hub_unregister_courses';
    $params = array('courseids' => $hubcourseids);
    $serverurl = $huburl . "/local/hub/webservice/webservices.php";
    $xmlrpcclient = new webservice_xmlrpc_client($serverurl, $hub->token);
    try {
        $result = $xmlrpcclient->call($function, $params);
    } catch (Exception $e) {
        echo "Unregistration of courses failed: " . $e->getMessage() . "\n";
        exit(1);
        // Ignore this particular failure.
    }

    $function = 'hub_unregister_site';
    $params = array();
    $serverurl = $huburl . "/local/hub/webservice/webservices.php";
    $xmlrpcclient = new webservice_xmlrpc_client($serverurl, $hub->token);
    try {
        $result = $xmlrpcclient->call($function, $params);
    } catch (Exception $e) {
        echo "Unregistration of site failed: " . $e->getMessage() . "\n";
        exit(1);
    }

    $registrationmanager->delete_registeredhub($huburl);
} else {
    echo "No registered hub found for {$huburl}\n";
}
