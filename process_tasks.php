<?php 
	/*
	 * On app engine, processes a task queue
	 */

	require __DIR__ . '/vendor/autoload.php';
	include "init.php";
	include "asana.php";

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
	$newTaskId = $_POST['newTaskId'];

	progress('Copying task ' . $taskId);
	copyTask($workspaceId, $taskId, $newTaskId);

?>
