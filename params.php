<?php
	date_default_timezone_set("UTC");
	header('Content-Type: text/html; charset=utf-8');

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

	// Input parameters

	global $apiKey;
	$apiKey = "";
	$storeKey = null;
	$targetWorkspaceId = null;
	$copy = null;
	$workspaceId = null;
	$projects = null;
	$teamId = null;

	global $channel;
	$channel = null;
	$refresh = false;

	// Read parameters
	if (isset($_COOKIE["apiKey"]))
		$apiKey = $_COOKIE["apiKey"];
	if (isset($_POST["apiKey"]))
		$apiKey = $_POST["apiKey"];
	if (isset($_POST["storeKey"]))
		$storeKey = $_POST["storeKey"];
	if (isset($_POST["channel"]))
		$channel = $_POST['channel'];
	if (isset($_POST["refresh"]))
		$channel = $_POST['refresh'];

	if (isset($_POST["new_workspace"])) {
		$workspaceId = $_POST["new_workspace"];
	}
	else {
		if (isset($_POST["workspace"]))
			$workspaceId = $_POST["workspace"];

		if (isset($_POST["projects"]))
			$projects = $_POST["projects"];

		if (isset($_POST["new_targetWorkspace"])) {
			$targetWorkspaceId = $_POST["new_targetWorkspace"];
		}
		else {
			if (isset($_POST["targetWorkspace"]))
				$targetWorkspaceId = $_POST["targetWorkspace"];
			if (isset($_POST["copy"]))
				$copy = $_POST["copy"];
			if (isset($_POST["team"]))
				$teamId = $_POST["team"];
		}
	}

	// Store for 90 days
	if ($apiKey && $storeKey) {
		setcookie("apiKey", $apiKey, time()+60*60*24*90);
	}
?>