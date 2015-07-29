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

	progress('Copying Projects to '. $targetWorkspace['name'] . $teamName);

	for ($i = count($projects) - 1; $i >= 0; $i--) {
		$project = getProject($projects[$i], false);
		$targetProjectName = $project['name'];
		$notes = $project['notes'];

		// Check for an existing project in the target workspace
		$targetProjects = getProjects($targetWorkspaceId);
		if ($DEBUG) pre($targetProjects);

		$count = 2;
		$found = false;
		do {
			$found = false;
			for ($j = 0; $j < count($targetProjects); $j++) {
				if (strcmp($targetProjects[$j]['name'], $targetProjectName) == 0) {
					$targetProjectName = $project['name'] . ' ' . $count++;
					$found = true;
					break;
				}
			}
		}
		while ($found == true && $count < 100);

		// Create target project
		progress('Copying ' . $project['name'] . ' to ' . $targetWorkspace['name'] . $teamName . '/' . $targetProjectName);
		$targetProject = createProject($targetWorkspaceId, $targetProjectName, $teamId, $notes);

		// Run copy
		queueTasks($project['id'], $targetProject['id']);

		notifyCreated($targetProject);
	}

?>
