<?php

function getProjects($workspaceId, &$cursor, $limit = 20) {
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
		'workspace' => $workspaceId
		// 'layout' => $project['layout']
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
