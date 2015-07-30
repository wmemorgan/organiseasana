<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		return;
	}

	// Read task parameters
	$targetWorkspaceId = $_POST['targetWorkspace'];
	$workspaceId = $_POST['workspace'];
	$projects = $_POST['projects'];
	$teamId = $_POST['team'];

	$projectOffset = 0;
	if (isset($_POST['projectOffset']))
		$projectOffset = $_POST['projectOffset'];

	$taskOffset = -1;
	if (isset($_POST['taskOffset']))
		$taskOffset = $_POST['taskOffset'];

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

	for ($i = $projectOffset; $i < count($projects); $i++) {
		$project = getProject($projects[$i], false);
		$targetProjectName = $project['name'];

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
		
	    // GET Project tasks
	    // TODO once OAuth is used, add support for external field
	    $fromProjectId = $project['id'];
	    $toProjectId = $targetProject['id'];
	    $result = asanaRequest("projects/$fromProjectId/tasks?opt_fields=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes");
		$tasks = $result['data'];

		global $APPENGINE;

	    // copy Tasks
	    // TODO check timing and re-queue after 5mins
	    $start = count($tasks) - 1;
	    if ($taskOffset >= 0) {
	    	$start = $taskOffset;
	    	$taskOffset = -1;
	    }
	    for ($j = $start; $j >= 0; $j--)
		{

			// Potential calls to the API
			// createTask: 2
			// copyHistory: 2
			// copyTags: 1 + 3N tags
			// copySubtasks: 1 + 2N subtasks + descendants

			// Allow for minimum (6), rest will be deferred if not enough allowance
			$pending = incrementRequests(6);
			$rateLimit = getRateLimit();

			if (isCancelled($channel)) {
				pre("User cancelled job", null, "danger");
				return;
			}

			// Are there too many pending requests?
			if ($pending > 90 || $rateLimit > time()) {
				// Re-queue task creation at the current point
				$params = [
					'channel' => $channel,
					'apiKey' => $apiKey,
					'targetWorkspace' => $targetWorkspaceId,
					'workspace' => $workspaceId,
					'projects' => $projects,
					'team' => $teamId,
					'projectOffset' => $i,
					'taskOffset' => $j
				];
				$delay = 60;
				if ($rateLimit > time()) {
					$delay = $rateLimit - time() + 10;
				}
				$options = ['delay_seconds' => $delay];
				$task = new \google\appengine\api\taskqueue\PushTask('/process/project', $params, $options);
				$task_name = $task->add();
				return;
			}

			$task = $tasks[$j];

			$taskId = $task['id'];

			$newTask = $task;
			unset($newTask['id']);
			$newTask['assignee'] = $newTask['assignee']['id'];
			foreach ($newTask as $key => $value)
			{
				if (empty($value))
				{
					unset($newTask[$key]);
				}
			}

			$newTask = createTask($targetWorkspaceId, $toProjectId, $newTask);
			$newTaskId = $newTask["id"];
			
			if ($APPENGINE) {
				queueTask($targetWorkspaceId, $taskId, $newTaskId);
			}
			else {
				copyTask($targetWorkspaceId, $taskId, $newTaskId);
			}
		}

		notifyCreated($targetProject);
	}

?>
