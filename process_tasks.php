<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	$fromProjectId = $_POST['fromProjectId'];
	$toProjectId = $_POST['toProjectId'];
	$offset = $_POST['offset'];

	progress('Copying tasks for ' . $fromProjectId);
	copyTasks($fromProjectId, $toProjectId, $offset);

?>
