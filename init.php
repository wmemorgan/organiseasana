<?php
	date_default_timezone_set("UTC");
	header('Content-Type: text/html; charset=utf-8');

	require_once('config.php');

	global $DEBUG;
	$DEBUG = false;
	if (isset($_COOKIE["debug"]))
		$DEBUG = $_COOKIE["debug"];
	if (isset($_GET["debug"]))
		$DEBUG = $_GET["debug"];

	if ($DEBUG) {
		setcookie("debug", $DEBUG);
	}

	global $APPENGINE;
	$APPENGINE = false;
	if (stream_resolve_include_path("google/appengine/api")) {
		$APPENGINE = true;
	}

	global $pusher;
	$pusher = new Pusher(
	  $config['pusher_key'],
	  $config['pusher_secret'],
	  $config['pusher_app_id'],
	  array('encrypted' => true)
	);


	global $apiKey;
	$apiKey = "";

	if (isset($_COOKIE["apiKey"]))
		$apiKey = $_COOKIE["apiKey"];
	if (isset($_POST["apiKey"]))
		$apiKey = $_POST["apiKey"];


	global $channel;
	$channel = null;

	if (isset($_POST["channel"]))
		$channel = $_POST['channel'];
?>