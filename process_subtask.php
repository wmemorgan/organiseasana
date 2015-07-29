<?php 
	/*
	 * On app engine, processes a task queue
	 */

	require __DIR__ . '/vendor/autoload.php';
	include "init.php";
	include "asana.php";

	$subtaskId = $_POST['subtaskId'];
	$newSubId = $_POST['newSubId'];
	$workspaceId = $_POST['workspaceId'];
	$depth = $_POST['depth'];

	progress('Copying subtask ' . $newSubId);
	copySubtask($subtaskId, $newSubId, $workspaceId, $depth);

?>
