<?php 
	/*
	 * On app engine, processes a task queue
	 */

	include "init.php";
	include "asana.php";

	if (isCancelled($channel)) {
		pre("User cancelled job", null, "info");
		return;
	}

	// Read task parameters
	$targetWorkspaceId = $_POST['targetWorkspace'];
	$workspaceId = $_POST['workspace'];
	$projects = $_POST['projects'];

	$teamId = false;
	if (isset($_POST['team']))
		$teamId = $_POST['team'];

	$projectOffset = 0;
	$currentProject = null;
	$nextPageOffset = null;
	if (isset($_POST['projectOffset'])) {
		$projectOffset = $_POST['projectOffset'];
		$nextPageOffset = $_POST['nextPageOffset'];
		$currentProject = $_POST['currentProject'];
	}

	$taskOffset = -1;
	if (isset($_POST['taskOffset']))
		$taskOffset = $_POST['taskOffset'];

	// Get some info
	$team = null;
	$copyTags = false;
	$targetWorkspace = getWorkspace($targetWorkspaceId);
	if ($targetWorkspaceId && $projects) {
		if (isOrganisation($targetWorkspace)) {
			$copyTags = true;
			if ($teamId) {
				$team = getTeam($targetWorkspaceId, $teamId);
			}
		}
	}

	$teamName = '';
	if ($team) {
		$teamName = '/' . $team['name'];
	}

	if ($taskOffset < 0) {
		progress('Copying projects to '. $targetWorkspace['name'] . $teamName);
	}

	for ($i = $projectOffset; $i < count($projects); $i++) {
		$project = getProject($projects[$i], false);

		// Create a new project if we're not in the middle of an existing copy
		if ($currentProject) {
			$targetProject = $currentProject;
			$currentProject = null;
			$targetProjectName = $targetProject['name'];
			progress('Continuing copy to ' . $targetProjectName);
		} else {
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
			p('Copying ' . $project['name'] . ' to ' . $targetWorkspace['name'] . $teamName . '/' . $targetProjectName);
			$targetProject = createProject($targetWorkspaceId, $targetProjectName, $teamId, $project);
			notifyCreated($targetProject);
		}

		// Run copy
		
	    // GET Project tasks
	    // TODO once OAuth is used, add support for external field
	    $fromProjectId = $project['id'];
	    $toProjectId = $targetProject['id'];
	    incrementRequests(1);
	    $url = "projects/$fromProjectId/tasks?opt_fields=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes&limit=10";
		if ($nextPageOffset != null) {
			$url .= "&offset=$nextPageOffset";
		}
		$result = asanaRequest($url);
		$tasks = $result['data'];
		$nextPage = $result['next_page'];

		global $APPENGINE;

	    // copy Tasks
	    // TODO check timing and re-queue after 5mins
	    $start = 0;
	    if ($taskOffset >= 0) {
	    	$start = $taskOffset;
	    }
	    for ($j = $start; $j < count($tasks); $j++)
		{

			// Potential calls to the API
			// createTask: 2
			// addToProject: 1
			// copyHistory: 2
			// copyTags: 1 + 3N tags
			// copySubtasks: 1 + 2N subtasks + descendants

			// Allow for minimum (7), rest will be deferred if not enough allowance
			$pending = 0;
			if ($copyTags) {
				$pending = incrementRequests(7);
			} else {
				$pending = incrementRequests(6);
			}
			$rateLimit = getRateLimit();

			if (isCancelled($channel)) {
				pre("User cancelled job", null, "info");
				return;
			}

			// Are there too many pending requests?
			if ($pending > 90 || $rateLimit > time()) {
				// Re-queue task creation at the current point
				$params = [
					'channel' => $channel,
					'authToken' => $authToken,
					'targetWorkspace' => $targetWorkspaceId,
					'workspace' => $workspaceId,
					'projects' => $projects,
					'team' => $teamId,
					'projectOffset' => $i,
					'taskOffset' => $j,
					'nextPageOffset' => $nextPageOffset,
					'currentProject' => $targetProject,
					'copyTags' => $copyTags
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

			$newTask = createTask($targetWorkspaceId, $newTask);
			addTaskToProject($newTask, $toProjectId);
			
			if ($APPENGINE) {
				queueTask($targetWorkspaceId, $taskId, $newTask, $copyTags);
			}
			else {
				copyTask($targetWorkspaceId, $taskId, $newTask, $copyTags);
			}
		}

		if (!empty($nextPage)) {
			// Re-queue task creation at the start of the next page
			$params = [
				'channel' => $channel,
				'authToken' => $authToken,
				'targetWorkspace' => $targetWorkspaceId,
				'workspace' => $workspaceId,
				'projects' => $projects,
				'team' => $teamId,
				'projectOffset' => $i,
				'taskOffset' => 0,
				'nextPageOffset' => $nextPage['offset'],
				'currentProject' => $targetProject,
				'copyTags' => $copyTags
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

		$params = [
			'channel' => $channel,
			'authToken' => $authToken
		];
		$delay = 60;
		$options = ['delay_seconds' => $delay];
		$task = new \google\appengine\api\taskqueue\PushTask('/process/complete', $params, $options);
		$task_name = $task->add();

	}

?>
