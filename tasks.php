<?php

function queueTask($targetWorkspaceId, $taskId, $newTask, $copyTags, $copyAttachments, $customFieldMapping, $projectId) {
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
        'copyTags' => $copyTags,
        'copyAttachments' => $copyAttachments,
        'customFieldMapping' => $customFieldMapping,
        'projectId' => $projectId
    ];
    $job = new \google\appengine\api\taskqueue\PushTask('/process/task', $params);
    $task_name = $job->add();
}

function copyTask($targetWorkspaceId, $taskId, $newTask, $copyTags, $copyAttachments, $customFieldMapping, $projectId) {
    $newTaskId=$newTask['id'];
    copyHistory($taskId, $newTaskId);
    if ($copyAttachments) {
        copyAttachments($taskId, $newTaskId, $targetWorkspaceId);
    }

    $depth = 0;
    copySubtasks($taskId, $newTaskId, $depth, $targetWorkspaceId, $copyTags, $copyAttachments, $customFieldMapping, $projectId);
}

function cleanTask($task) {
    global $DEBUG;
    if ($DEBUG) {
        pre($task, "Cleaning task");
    }
    // "message": ".assignee_status: Schedule status shouldn't be set for unassigned tasks"
    if (!isset($task['assignee'])) {
        unset($task['assignee_status']);
        if ($DEBUG) {
            pre($task, "Removed Assignee Status ('Schedule status shouldn't be set for unassigned tasks')", 'warn');
        }
    }
    if (isset($task['due_at'])) {
        unset($task['due_on']);
        if ($DEBUG) {
            pre($task, "Removed double due time ('You may only provide one of due_on or due_at!')", 'warn');
        }
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

function createTask($workspaceId, $task, $customFieldMapping) {
    // Unset projects
    unset($task['projects']);
    unset($task['memberships']);

    // Validate task data
    $task = cleanTask($task);

    $newFields = remapCustomFields($task, $customFieldMapping);
    unset($task['custom_fields']);
    
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
    if (!isError($result)) {
        // Display result
        global $DEBUG;
        if ($DEBUG) {
            pre($result);
        }

        $newTask = $result['data'];
    } else {
        pre($result, "Error creating task", 'danger');
        return null;
    }

    if ($newFields && $customFieldMapping) {
        $newTask["custom_fields"] = $newFields;
    }

    return $newTask;
}

function copySubtasks($taskId, $newTaskId, $depth, $targetWorkspaceId, $copyTags, $copyAttachments, $customFieldMapping, $projectId) {
    $depth++;
    if ($depth > 10) {
        return false;
    }

    // GET subtasks of task
    $result = asanaRequest("tasks/$taskId/subtasks?opt_expand=assignee,assignee_status,completed,custom_fields,due_on,due_at,hearted,name,notes,memberships,tags");
    if (isError($result)) {
        pre($result, "Error getting subtasks", 'danger');
    }
    $subtasks = $result["data"];

    // does subtask exist?
    if ($subtasks) {
        for ($i= count($subtasks) - 1; $i >= 0; $i--) {
            $subtask = $subtasks[$i];
            $subtaskId = $subtask['id'];

            // TODO external field
            unset($subtask["id"]);
            unset($subtask["memberships"]);

            p(str_repeat("&nbsp;", $depth) . "Creating subtask: " . $subtask['name']);

            // Remap custom fields
            $newFields = remapCustomFields($subtask, $customFieldMapping);
            
            if (isset($subtask["assignee"])) {
                $subtask["assignee"] = $subtask["assignee"]["id"];
            }
            if ($copyTags && $subtask['tags']) {
                $subtask['tags'] = getTargetTags($subtask, $targetWorkspaceId);
            } else {
                unset($subtask['tags']);
            }

            // create Subtask
            if ($newFields) {
                error_log("Creating subtask as top-level task");
                $subtask["projects"] = array($projectId);
                $data = array('data' => cleanTask($subtask));
                $result = asanaRequest("tasks", 'POST', $data);

                // Try to remove assignee if an error is returned
                // TODO check assignee exists before submitting the request
                if (isError($result) && isset($subtask['assignee'])) {
                    unset($subtask['assignee']);
                    $data = array('data' => cleanTask($subtask));
                    $result = asanaRequest("tasks", 'POST', $data);
                }

                if (isError($result)) {
                    pre($result, "Failed to create subtask", 'danger');
                    return;
                }

                $newsubtask = $result["data"];
                $newSubId = $newsubtask['id'];

                // Move to subtask
                $data = array('data' => array('parent' => $newTaskId));
                $result = asanaRequest("tasks/$newSubId/setParent", 'POST', $data);

                if (isError($result)) {
                    pre($result, "Failed to move subtask", 'danger');
                    return;
                }
                
                $data = array('data' => array('project' => $projectId));
                $result = asanaRequest("tasks/$newSubId/removeProject", 'POST', $data);

                if (isError($result)) {
                    pre($result, "Failed to move subtask", 'danger');
                    return;
                }
            } else {
                error_log("Creating subtask directly");
                unset($subtask['custom_fields']);
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
            }
            
            queueSubtask($subtask, $subtaskId, $newSubId, $targetWorkspaceId, $depth, $copyTags, $copyAttachments, $customFieldMapping, $projectId);
        }
    }
}

function queueSubtask($subtask, $subtaskId, $newSubId, $targetWorkspaceId, $depth, $copyTags, $copyAttachments, $customFieldMapping, $projectId) {
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
        'copyAttachments' => $copyAttachments,
        'customFieldMapping' => $customFieldMapping,
        'projectId' => $projectId,
        'debug' => $DEBUG
    ];

    $job = new \google\appengine\api\taskqueue\PushTask('/process/subtask', $params);
    $task_name = $job->add();
}

function copySubtask($subtaskId, $newSubId, $targetWorkspaceId, $depth, $copyTags, $copyAttachments, $customFieldMapping, $projectId) {
    copyHistory($subtaskId, $newSubId);
    if ($copyAttachments) {
        copyAttachments($subtaskId, $newSubId, $targetWorkspaceId);
    }

    copySubtasks($subtaskId, $newSubId, $depth, $targetWorkspaceId, $copyTags, $copyAttachments, $customFieldMapping, $projectId);
}

function getTasks($parentPath, &$cursor, $limit = 20, $lastTaskId = null) {
    $baseUrl = "$parentPath/tasks?opt_expand=assignee,assignee_status,completed,custom_fields,due_on,due_at,hearted,name,notes,memberships,tags";
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
