<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	// Get some info
	$team = null;
	$targetWorkspace = getWorkspace($targetWorkspaceId);
	if ($targetWorkspaceId && $projects) {
		if (isOrganisation($targetWorkspace)) {
			if ($teamId) {
				$team = getTeam($targetWorkspaceId, $teamId);
			}
		}
	}

	$teamName = '';
	if ($team) {
		$teamName = '/' . $team['name'];
	}

	$workspaceId = $_POST['workspaceId'];
	$taskId = $_POST['taskId'];
	$newTask = $_POST['newTask'];

	progress('Copying task contents ' . $newTask['name']);
	copyTask($workspaceId, $taskId, $newTask);

?>
