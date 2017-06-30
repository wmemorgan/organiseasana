<?php
	date_default_timezone_set("UTC");
	header('Content-Type: text/html; charset=utf-8');

	require_once __DIR__ . '/vendor/autoload.php';
	require_once 'config.php';

	function postDefault($key, $default = null, $get = false, $cookie = false) {
		$result = $default;
		if ($cookie && isset($_COOKIE[$key])) {
			$result = $_COOKIE[$key];
		}
		if (isset($_POST[$key])) {
			$result = $_POST[$key];
		}
		if ($get && isset($_GET[$key])) {
			$result = $_GET[$key];
		}

		return $result;
	}

	global $DEBUG;
	$DEBUG = postDefault('debug', 0, true, true);

	if ($DEBUG != null) {
		setcookie("debug", $DEBUG);
	}

	global $pusher;
	$pusher = new Pusher(
	  $config['pusher_key'],
	  $config['pusher_secret'],
	  $config['pusher_app_id'],
	  array('encrypted' => true)
	);


	global $authToken;
	$authToken = false;

	if (isset($_COOKIE["auth_token"]))
		$authToken = json_decode($_COOKIE["auth_token"], true);
	$authToken = postDefault('authToken', $authToken, true);

	global $channel;
	$channel = postDefault('channel', null, true);
?>