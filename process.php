<?php 
	/*
	 * On app engine, processes a task queue
	 */

	require __DIR__ . '/vendor/autoload.php';
	include "config.php";
	include "params.php";
	include "asana.php";

	global $USE_MEMCACHE;
	$USE_MEMCACHE = false;

	global $pusher;
	$pusher = new Pusher(
	  $config['pusher_key'],
	  $config['pusher_secret'],
	  $config['pusher_app_id'],
	  array('encrypted' => true)
	);

	$teamName = '';
	if ($team) {
		$teamName = '/' . $team['name'];
	}
	progress('Copying Projects to '. $targetWorkspace['name'] . $teamName);

	$newProjects = array();

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
		$newProjects[] = $targetProject;

		// Run copy
		copyTasks($project['id'], $targetProject['id']);

		copied($targetProject);
	}

?>
