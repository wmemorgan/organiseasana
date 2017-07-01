<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	$targetWorkspaceId = $_POST['workspaceId'];
	$taskId = $_POST['taskId'];
	$newTask = $_POST['newTask'];
	$copyTags = $_POST['copyTags'];

	progress("Copy content for '" . $newTask['name'] . "'");
	copyTask($targetWorkspaceId, $taskId, $newTask, $copyTags);

?>
