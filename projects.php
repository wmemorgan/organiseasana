<?php

function getAllProjects($workspaceId) {
	$cursor = 0;
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
        pre($result, "Error Loading Projects!", 'danger');
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
        pre($result, "Error Loading Project!", 'danger');
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
		pre($result, "Error creating project!", 'danger');
	}

	return $result;
}

function getProjectTasks($projectId, &$cursor, $limit = 20) {
	$url = "projects/$projectId/tasks?opt_fields=assignee,assignee_status,completed,due_on,due_at,hearted,name,notes";
	if ($nextPageOffset != null) {
		$url .= "&offset=$nextPageOffset";
	}
	if ($limit) {
		$url .= "&limit=$limit";
	}
	if (isError($result))
	{
        pre($result, "Error Loading Projects!", 'danger');
		return;
	}

	if ($result['next_page']) {
		$cursor = $result['next_page']['offset'];
	} else {
		$cursor = false;
	}

	return $result['data'];
}