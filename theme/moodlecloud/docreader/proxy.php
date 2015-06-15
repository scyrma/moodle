<?php
	require('../../../config.php');
	// Require user to be logged in
	require_login();
	global $CFG;

	// Get session cookie
	$sessionCookie = $_COOKIE['MoodleSession'.$CFG->sessioncookie];

	// Get protocol
	$protocol = (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';

	$drurl = (!isset($_GET['drurl'])) ? $protocol.'docreader.readspeaker.com/docreader/' : $_GET['drurl'];
	// Get cURL resource
	$curl = curl_init();
	// Set some options
	curl_setopt_array($curl, array(
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_URL => $drurl.'?'.$_SERVER['QUERY_STRING'].'&sessioncookie='.urlencode('MoodleSession'.$CFG->sessioncookie.'='.$sessionCookie),
		CURLOPT_COOKIE => 'MoodleSession'.$CFG->sessioncookie.'='.$sessionCookie,
		CURLOPT_FOLLOWLOCATION => false,
		// Do not cache results
		CURLOPT_FRESH_CONNECT => 1
	));
	// Send the request & save response to $resp
	$resp = curl_exec($curl);
	// Close request to clear up some resources
	curl_close($curl);

	echo $resp;
?>