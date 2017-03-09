<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	$workspaceId = $_POST['workspaceId'];
	$taskId = $_POST['taskId'];
	$newTask = $_POST['newTask'];
	$copyTags = $_POST['copyTags'];

	progress('Copying task contents ' . $newTask['name']);
	copyTask($workspaceId, $taskId, $newTask, $copyTags);

?>
