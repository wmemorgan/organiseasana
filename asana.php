<?php

/**
 * This is based on the implementation here:
 * https://gist.github.com/AWeg/5814427
 */

function asanaRequest($methodPath, $httpMethod = 'GET', $body = null)
{
	global $apiKey;
	global $DEBUG;

	$url = "https://app.asana.com/api/1.0/$methodPath";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ; 
	curl_setopt($ch, CURLOPT_USERPWD, $apiKey); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
    	
    // SSL cert of Asana is selfmade
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
	
	$jbody = $body;
	if ($jbody)
	{
		if (!is_string($jbody))
		{
			$jbody = json_encode($body);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jbody);
	}
	
	$data = curl_exec($ch);
	$error = curl_error($ch);
	curl_close($ch);
    
	$result = json_decode($data, true);

	if ($DEBUG >= 2) {
		pre(array('request' => $body, 'response' => $result), "$httpMethod " . $url);
	}
	return $result;
}

function p($text) {
	print "<p>" . $text . "</p>\n";
	flush();
}

function pre($o, $title = false, $style = 'info') {
	print '<div class="bs-callout bs-callout-' . $style . '">';
	if ($title)
		print "<h4>$title</h4>";
	print "<pre>";
	print(json_encode($o, JSON_PRETTY_PRINT));
	print "</pre></div>\n";
	flush();
}

function isError($result) {
	return isset($result['errors']) || !isset($result['data']);
}
 
function createTag($tagName, $workspaceId, $newTaskId) {
 	p("Creating tag: " . $tagName);

 	// Create new tag
    $data = array('data' => array('name' => $tagName));
    $result = asanaRequest("workspaces/$workspaceId/tags", "POST", $data);
 
 	// Assign tag to task
    $data = array("data" => array("tag" => $result["data"]["id"]));
    $result = asanaRequest("tasks/$newTaskId/addTag", "POST", $data);
 
}
 
function createTask($workspaceId, $projectId, $task)
{
	p("Creating task: " . $task['name']);

	// Set projects
	$task['projects'] = array($projectId);

	// Create new task
	$data = array('data' => $task);
	$result = asanaRequest("workspaces/$workspaceId/tasks", 'POST', $data);

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

function createProject($workspaceId, $name, $teamId)
{
	p("Creating project: " . $name);
	$data = array('data' => array('name' => $name));
	$data['data']['workspace'] = $workspaceId;
	if ($teamId)
		$data['data']['team'] = $teamId;
	
	$result = asanaRequest("projects", 'POST', $data);

	if (!isError($result))
	{
		$newProject = $result['data'];
		return $newProject;
	}
	else {
		pre($result, "Error creating project!", 'danger');
	}
	
	return $result;
}
 
function copySubtasks($taskId, $newTaskId, $failsafe) {
 
    $failsafe++;
    if ($failsafe > 10) {
        return FALSE;
    }
    
    // GET subtasks of task
    $result = asanaRequest("tasks/$taskId/subtasks");
    $subtasks = $result["data"];
 
    
    if ($subtasks){     // does subtask exist?
        for ($i= count($subtasks) - 1; $i >= 0; $i--) {
            
            $subtask = $subtasks[$i];
            $subtaskId = $subtask['id'];
 
            // get data for subtask
            $result = asanaRequest("tasks/$subtaskId?opt_fields=assignee,assignee_status,completed,due_on,name,notes");
            $newSubtask = $result['data'];
            unset($newSubtask["id"]);
            $newSubtask["assignee"] = $newSubtask["assignee"]["id"];
            
            // create Subtask
            $data = array('data' => $newSubtask );
            $result = asanaRequest("tasks/$newTaskId/subtasks", 'POST', $data);
 
            // add History
            $newSubId = $result["data"]["id"];
            copyHistory($subtaskId, $newSubId);
 
            // subtask of subtask?
            copySubtasks($subtaskId, $result["data"]["id"], $failsafe);
 
        }    
    }
            
    
}
 
function copyHistory($taskId, $newTaskId) {
 
	$result = asanaRequest("tasks/$taskId/stories");
	$comments = array();
	foreach ($result['data'] as $story){
		$date = date('l M d, Y h:i A', strtotime($story['created_at']));
		$comment = " ­\n" . $story['created_by']['name'] . ' on ' . $date . ":\n" . $story['text'];
		$comments[] = $comment;
	}
	$comment = implode("\n----------------------", $comments);
	$data = array('data' => array('text' => $comment));
	$result = asanaRequest("tasks/$newTaskId/stories", 'POST', $data);
        
}
 
function copyTags ($taskId, $newTaskId, $newworkspaceId) {
    
    // GET Tags
    $result = asanaRequest("tasks/$taskId/tags");
    
    if (!isError($result)) 
    { 	// are there any tags?
        $tags = $result["data"];
        for ($i = count ($tags) - 1; $i >= 0; $i--) {
           
            $tag = $tags[$i];
            $tagName = $tag["name"];
 
            // does tag exist?
            $result = asanaRequest("workspaces/$newworkspaceId/tags");
            $tagisset = false;
            $existingtags = $result["data"];
            for($j = count($existingtags) - 1; $j >= 0; $j--) {
                $existingtag = $existingtags[$j];
                
                if ($tagName == $existingtag["name"]) {
                    $tagisset = true;
                    $tagId = $existingtag["id"];
                    break;
                }
            }
 
            if (!$tagisset) {
     
                p("tag does not exist in workspace");
                $data = array('data' => array('name' => $tagName));
                $result = asanaRequest("workspaces/$newworkspaceId/tags", "POST", $data);
                $tagId = $result["data"]["id"];
 
            }
 
            $data = array("data" => array("tag" => $tagId));
            $result = asanaRequest("tasks/$newTaskId/addTag", "POST", $data);
           
        }
    }
}

function getWorkspaces() {
	$result = asanaRequest("workspaces");
	if (isError($result))
	{
        pre($result, "Error Loading Workspaces!", danger);
		return;
	}

	return $result['data'];
}

function getWorkspace($workspaceId) {
	$result = asanaRequest("workspaces/$workspaceId");
	if (isError($result))
	{
        pre($result, "Error Loading Workspace!", 'danger');
		return;
	}

	return $result['data'];
}

function getTeams($organizationId) {
	$result = asanaRequest("organizations/$organizationId/teams");
	if (isError($result))
	{
        pre($result, "Error Loading Teams!", 'danger');
		return;
	}

	return $result['data'];
}

function getTeam($organizationId, $teamId) {
	$result = asanaRequest("teams/$teamId");
	if (isError($result))
	{
        pre($result, "Error Loading Team!", 'danger');
		return;
	}

	return $result['data'];
}

function getProjects($workspaceId) {
	$result = asanaRequest("workspaces/$workspaceId/projects");
	if (isError($result))
	{
        pre($result, "Error Loading Projects!", 'danger');
		return;
	}

	return $result['data'];
}

function getProject($projectId) {
	$result = asanaRequest("projects/$projectId");
	if (isError($result))
	{
        pre($result, "Error Loading Project!", 'danger');
		return;
	}

	return $result['data'];
}
 
function copyTasks($fromProjectId, $toProjectId)
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
    $result = asanaRequest("projects/$fromProjectId/tasks?opt_fields=assignee,assignee_status,completed,due_on,name,notes");
	$tasks = $result['data'];
	
    // copy Tasks
    for ($i = count($tasks) - 1; $i >= 0; $i--)
	{
		$task = $tasks[$i];
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
        
		if ($newTask['id'])
		{
            
            //copy history
			$taskId = $task['id'];
            $newTaskId = $newTask['id'];
			copyHistory($taskId, $newTaskId);
            
            //copy tags
            copyTags($taskId, $newTaskId, $workspaceId);
 
            //implement copying of subtasks
            $failsafe = 0;
            copySubtasks($taskId, $newTaskId, $failsafe);
 
		}
	}
}