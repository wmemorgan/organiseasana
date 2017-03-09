<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	$subtaskId = $_POST['subtaskId'];
	$newSubId = $_POST['newSubId'];
	$workspaceId = $_POST['workspaceId'];
	$depth = $_POST['depth'];
	$copyTags = $_POST['copyTags'];

	progress('Copying subtask ' . $newSubId);
	copySubtask($subtaskId, $newSubId, $workspaceId, $depth, $copyTags);

?>
