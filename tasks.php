<?php

function queueTask($targetWorkspaceId, $taskId, $newTask, $copyTags = true, $copyAttachments = true) {
	global $channel;
	global $authToken;
	global $DEBUG;

	// Start task
	require_once("google/appengine/api/taskqueue/PushTask.php");

	$params = [
		'channel' => $channel,
		'authToken' => $authToken,
		'copy' => 'task',
		'workspaceId' => $targetWorkspaceId,
		'taskId' => $taskId,
		'newTask' => $newTask,
		'debug' => $DEBUG,
		'copyTags' => $copyTags
	];
	$job = new \google\appengine\api\taskqueue\PushTask('/process/task', $params);
	$task_name = $job->add();
}

function copyTask($targetWorkspaceId, $taskId, $newTask, $copyTags = true, $copyAttachments = true) {

    $newTaskId=$newTask['id'];
	copyHistory($taskId, $newTaskId);
	if ($copyAttachments) {
		copyAttachments($taskId, $newTaskId, $targetWorkspaceId);
	}

    $depth = 0;
    copySubtasks($taskId, $newTaskId, $depth, $targetWorkspaceId, $copyTags, $copyAttachments);
}

function cleanTask($task) {
	global $DEBUG;
	if ($DEBUG) pre($task, "Cleaning task");
	// "message": ".assignee_status: Schedule status shouldn't be set for unassigned tasks"
	if (!isset($task['assignee'])) {
		unset($task['assignee_status']);
		if ($DEBUG) pre($task, "Removed Assignee Status ('Schedule status shouldn't be set for unassigned tasks')", 'warn');
	}
	if (isset($task['due_at'])) {
		unset($task['due_on']);
		if ($DEBUG) pre($task, "Removed double due time ('You may only provide one of due_on or due_at!')", 'warn');
	}
	
	return $task;	
}

function addTaskToProject($task, $projectId) {

	// Set projects
	$task['projects'] = array($projectId);
	$taskId = $task['id'];
	$data = array('data' => array(
		'project' => $projectId,
		'insert_before' => null,
	));
	$result = asanaRequest("tasks/$taskId/addProject", 'POST', $data);
}

function addTaskToSection($task, $projectId, $sectionId) {

	// Set projects
	$task['projects'] = array($projectId);
	$taskId = $task['id'];
	$data = array('data' => array(
		'project' => $projectId,
		'section' => $sectionId,
	));
	$result = asanaRequest("tasks/$taskId/addProject", 'POST', $data);
}

function createTask($workspaceId, $task)
{
	// Unset projects
	unset($task['projects']);
	unset($task['memberships']);

	// Validate task data
	$task = cleanTask($task);
	
	// Create new task
	$data = array('data' => $task);
	$result = asanaRequest("workspaces/$workspaceId/tasks", 'POST', $data);

	// Try to remove assignee if an error is returned
	// TODO check assignee exists before submitting the request
	if (isError($result) && isset($task['assignee'])) {
		unset($task['assignee']);
		
		// Validate task data
		$task = cleanTask($task);
	
		$data = array('data' => $task);
		$result = asanaRequest("workspaces/$workspaceId/tasks", 'POST', $data);
	}

	// Check result
	if (!isError($result))
	{
		// Display result
		global $DEBUG;
		if ($DEBUG) pre($result);

		$newTask = $result['data'];
		return $newTask;
	}
	else {
		pre($result, "Error creating task", 'danger');
	}

	return $result;
}

function copySubtasks($taskId, $newTaskId, $depth, $targetWorkspaceId, $copyTags = true, $copyAttachments = true) {
    $depth++;
    if ($depth > 10) {
        return FALSE;
    }

    // GET subtasks of task
    $result = asanaRequest("tasks/$taskId/subtasks?opt_expand=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes,memberships,tags");
    if (isError($result)) {
		pre($result, "Error getting subtasks", 'danger');
	}
    $subtasks = $result["data"];

	// does subtask exist?
    if ($subtasks){
        for ($i= count($subtasks) - 1; $i >= 0; $i--) {

            $subtask = $subtasks[$i];
            $subtaskId = $subtask['id'];

		    // TODO external field
		    unset($subtask["id"]);
		    
			p(str_repeat("&nbsp;", $depth) . "Creating subtask: " . $subtask['name']);
		    
		    if (isset($subtask["assignee"]))
		        $subtask["assignee"] = $subtask["assignee"]["id"];
			if ($copyTags && $subtask['tags']) {
				$subtask['tags'] = getTargetTags($subtask, $targetWorkspaceId);
			} else {
				unset($subtask['tags']);
			}

		    // create Subtask
		    $data = array('data' => cleanTask($subtask));
		    $result = asanaRequest("tasks/$newTaskId/subtasks", 'POST', $data);
		    
		    // Try to remove assignee if an error is returned
			// TODO check assignee exists before submitting the request
			if (isError($result) && isset($subtask['assignee'])) {
				unset($subtask['assignee']);
				$data = array('data' => cleanTask($subtask));
				$result = asanaRequest("tasks/$newTaskId/subtasks", 'POST', $data);
			}

			if (isError($result)) {
				pre($result, "Failed to create subtask", 'danger');
				return;
			}

			$newsubtask = $result["data"];
			$newSubId = $newsubtask['id'];
            
			queueSubtask($subtask, $subtaskId, $newSubId, $targetWorkspaceId, $depth, $copyTags, $copyAttachments);
        }
    }
}

function queueSubtask($subtask, $subtaskId, $newSubId, $targetWorkspaceId, $depth, $copyTags = true, $copyAttachments = true) {
	global $channel;
	global $authToken;
	global $DEBUG;

	// Start task
	require_once("google/appengine/api/taskqueue/PushTask.php");

	$params = [
		'channel' => $channel,
		'authToken' => $authToken,
		'copy' => 'subtask',
		'subtaskId' => $subtaskId,
		'subtask' => $subtask,
		'newSubId' => $newSubId,
		'workspaceId' => $targetWorkspaceId,
		'depth' => $depth,
		'copyTags' => $copyTags,
		'debug' => $DEBUG
	];
	$job = new \google\appengine\api\taskqueue\PushTask('/process/subtask', $params);
	$task_name = $job->add();
}

function copySubtask($subtaskId, $newSubId, $targetWorkspaceId, $depth, $copyTags = true, $copyAttachments = true) {

    copyHistory($subtaskId, $newSubId);
	if ($copyAttachments) {
		copyAttachments($subtaskId, $newSubId, $targetWorkspaceId);
	}

    copySubtasks($subtaskId, $newSubId, $depth, $targetWorkspaceId, $copyTags, $copyAttachments);
}

function getTasks($parentPath, &$cursor, $limit = 20, $lastTaskId = null) {
	$baseUrl = "$parentPath/tasks?opt_expand=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes,memberships,tags";
	if ($limit) {
		$baseUrl .= "&limit=$limit";
	}
	$url = $baseUrl;
	if ($cursor) {
		$url .= "&offset=$cursor";
	}

	$result = asanaRequest($url);

	// Handle pagination token expiry
	if (isPaginationError($result) && $lastTaskId) {
		progress("Re-querying tasks - pagination token expired");
		$url = $baseUrl;
		$found = false;
		for (;;) {
			$result = asanaRequest($url);
			if ($found || isError($result)) {
				break;
			}

			// Update cursor
			if ($result['next_page']) {
				$cursor = $result['next_page']['offset'];
			} else {
				$cursor = false;
			}

			$tasks = $result['data'];
			for ($i = 0; $i < count($tasks); $i++) {
				$task = $tasks[$i];
				if ($task['id'] == $lastTaskId) {
					if ($i < count($tasks) - 1) {
						return array_slice($tasks, $i + 1);
					} else {
						$found = true;
					}
				}
			}

			if ($cursor) {
				$url = $baseUrl . "&offset=" . $cursor;
			} else {
				pre(null, "Unable to locate task $lastTaskId while re-paginating $parentPath", 'danger');
				return;
			}
		}
	}
	
	if (isError($result)) {
        pre($result, "Error loading tasks from $parentPath", 'danger');
		return;
	}

	if ($result['next_page']) {
		$cursor = $result['next_page']['offset'];
	} else {
		$cursor = false;
	}

	return $result['data'];
}