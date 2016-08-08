<?php

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

function createProject($workspaceId, $name, $teamId, $notes)
{
	p("Creating project: " . $name);
	$data = array('data' => array('name' => $name));
	if ($workspaceId)
		$data['data']['workspace'] = $workspaceId;
	if ($notes)
		$data['data']['notes'] = $notes;
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
