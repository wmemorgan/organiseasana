<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	$subtask = $_POST['subtask'];
	$subtaskId = $_POST['subtaskId'];
	$newSubId = $_POST['newSubId'];
	$workspaceId = $_POST['workspaceId'];
	$depth = $_POST['depth'];
	$copyTags = $_POST['copyTags'];

	if ($subtask) {
		progress("Copying subtask content for '" . $subtask['name'] . "'");
	} else {
		progress('Copying subtask content for ' . $newSubId);
	}
	copySubtask($subtaskId, $newSubId, $workspaceId, $depth, $copyTags);

?>
