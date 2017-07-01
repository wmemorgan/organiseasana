<?php

function getAllProjects($workspaceId) {
	$cursor = null;
	$limit = 100;

	$projects = array();
	do {
		$page = getProjects($workspaceId, $cursor, $limit);
		if ($page) {
			$projects = array_merge($projects, $page);
		}
	} while ($cursor);

	return $projects;
}

function getProjects($workspaceId, &$cursor, $limit = 10) {
	$path = "workspaces/$workspaceId/projects?";
	if ($limit) {
		$path .= "&limit=$limit";
	}
	if ($cursor) {
		$path .= "&offset=$cursor";
	}

	$result = asanaRequest($path);
	if (isError($result))
	{
        pre($result, "Error loading projects", 'danger');
		return;
	}

	if ($result['next_page']) {
		$cursor = $result['next_page']['offset'];
	} else {
		$cursor = false;
	}

	return $result['data'];
}

function getProject($projectId) {
	$result = asanaRequest("projects/$projectId");
	if (isError($result))
	{
        pre($result, "Error loading project", 'danger');
		return;
	}

	return $result['data'];
}

function createProject($workspaceId, $name, $teamId, $project)
{
	p("Creating project: " . $name);
	$data = array('data' => array(
		'name' => $name,
		'workspace' => $workspaceId,
		'layout' => $project['layout']
	));
	if ($teamId)
		$data['data']['team'] = $teamId;
	if ($project['notes'])
		$data['data']['notes'] = $project['notes'];

	$result = asanaRequest("projects", 'POST', $data);

	if (!isError($result))
	{
		$newProject = $result['data'];
		return $newProject;
	}
	else {
		pre($result, "Error creating project", 'danger');
	}

	return $result;
}

function getProjectTasks($projectId, &$cursor, $limit = 20, $lastTaskId = null) {
	return getTasks("projects/$projectId", $cursor, $limit, $lastTaskId);
}