<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	$taskId = $_POST['taskId'];
	$newTaskId = $_POST['newTaskId'];
	$attachmentId = $_POST['attachmentId'];
	$attachmentName = $_POST['attachmentName'];

	progress("Copying attachment $attachmentName ($attachmentId)");
	copyAttachment($taskId, $newTaskId, $attachmentId, $attachmentName);

?>
