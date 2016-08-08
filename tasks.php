<?php

function queueTasks($fromProjectId, $toProjectId, $offset = 0) {
	global $channel;
	global $authToken;
	global $DEBUG;

	// Start task
	require_once("google/appengine/api/taskqueue/PushTask.php");

	$params = [
		'channel' => $channel,
		'authToken' => $authToken,
		'fromProjectId' => $fromProjectId,
		'toProjectId' => $toProjectId,
		'offset' => $offset,
		'debug' => $DEBUG
	];
	$job = new \google\appengine\api\taskqueue\PushTask('/process/tasks', $params);
	$task_name = $job->add();
}

function copyTasks($fromProjectId, $toProjectId, $offset = 0)
{
    // GET Project
	$result = asanaRequest("projects/$toProjectId");
	if (isError($result))
	{
        pre($result, "Error Loading Project!", 'danger');
		return;
	}
    $workspaceId = $result['data']['workspace']['id'];

    // GET Project tasks
    // TODO once OAuth is used, add support for external field
    $result = asanaRequest("projects/$fromProjectId/tasks?opt_fields=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes");
	$tasks = $result['data'];

	global $APPENGINE;

    // copy Tasks
    // TODO check timing and re-queue after 5mins
    for ($i = count($tasks) - 1; $i >= 0; $i--)
	{
		$task = $tasks[$i];

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

		$newTask = createTask($workspaceId, $toProjectId, $newTask);
		
		if ($APPENGINE) {
			queueTask($workspaceId, $taskId, $newTask);
		}
		else {
			copyTask($workspaceId, $taskId, $newTask);
		}
	}
}

function queueTask($workspaceId, $taskId, $newTask) {
	global $channel;
	global $authToken;
	global $DEBUG;

	// Start task
	require_once("google/appengine/api/taskqueue/PushTask.php");

	$params = [
		'channel' => $channel,
		'authToken' => $authToken,
		'copy' => 'task',
		'workspaceId' => $workspaceId,
		'taskId' => $taskId,
		'newTask' => $newTask,
		'debug' => $DEBUG
	];
	$job = new \google\appengine\api\taskqueue\PushTask('/process/task', $params);
	$task_name = $job->add();
}

function copyTask($workspaceId, $taskId, $newTask) {

    $newTaskId=$newTask['id'];
	copyHistory($taskId, $newTaskId);
    copyTags($taskId, $newTaskId, $workspaceId);
    copyAttachments($taskId, $newTaskId, $workspaceId);

    $depth = 0;
    copySubtasks($taskId, $newTaskId, $depth, $workspaceId);
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

function createTask($workspaceId, $projectId, $task)
{
	// Set projects
	$task['projects'] = array($projectId);

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

function copySubtasks($taskId, $newTaskId, $depth, $workspaceId) {
    $depth++;
    if ($depth > 10) {
        return FALSE;
    }

    // GET subtasks of task
    $result = asanaRequest("tasks/$taskId/subtasks");
    if (isError($result)) {
		pre($result, "Error getting subtasks", 'danger');
	}
    $subtasks = $result["data"];


    if ($subtasks){     // does subtask exist?
        for ($i= count($subtasks) - 1; $i >= 0; $i--) {

            $subtask = $subtasks[$i];

            $subtaskId = $subtask['id'];

		    // get data for subtask
		    // TODO external field
		    $result = asanaRequest("tasks/$subtaskId?opt_fields=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes");
		    $task = $result['data'];
		    unset($task["id"]);
		    
			p(str_repeat("&nbsp;", $depth) . "Creating subtask: " . $task['name']);
		    
		    if (isset($task["assignee"]))
		        $task["assignee"] = $task["assignee"]["id"];

		    // create Subtask
		    $data = array('data' => cleanTask($task));
		    $result = asanaRequest("tasks/$newTaskId/subtasks", 'POST', $data);
		    
		    // Try to remove assignee if an error is returned
			// TODO check assignee exists before submitting the request
			if (isError($result) && isset($task['assignee'])) {
				unset($task['assignee']);
				$data = array('data' => cleanTask($task));
				$result = asanaRequest("tasks/$newTaskId/subtasks", 'POST', $data);
			}

			if (isError($result)) {
				pre($result, "Failed to create subtask!", 'danger');
				return;
			}

			$newsubtask = $result["data"];
			$newSubId = $newsubtask['id'];
            
            global $APPENGINE;
            if ($APPENGINE) {
            	queueSubtask($subtaskId, $newSubId, $workspaceId, $depth);
            }
            else {
            	copySubtask($subtaskId, $newSubId, $workspaceId, $depth);
            }

        }
    }
}

function queueSubtask($subtaskId, $newSubId, $workspaceId, $depth) {
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
		'newSubId' => $newSubId,
		'workspaceId' => $workspaceId,
		'depth' => $depth,
		'debug' => $DEBUG
	];
	$job = new \google\appengine\api\taskqueue\PushTask('/process/subtask', $params);
	$task_name = $job->add();
}

function copySubtask($subtaskId, $newSubId, $workspaceId, $depth) {

    copyHistory($subtaskId, $newSubId);
    copyTags($subtaskId, $newSubId, $workspaceId);
    copyAttachments($subtaskId, $newSubId, $workspaceId);

    copySubtasks($subtaskId, $newSubId, $depth, $workspaceId);
}