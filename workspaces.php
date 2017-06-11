<?php



function isOrganisation($workspace) {
	$org = isset($workspace['is_organization']) && $workspace['is_organization'];
	if ($workspace['name'] == 'Personal Projects')
		$org = false;
		
	return $org;
}

function getWorkspaces() {
	$result = asanaRequest("workspaces");
	if (isError($result))
	{
        pre($result, "Error Loading Workspaces!", 'danger');
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

function getTeams($organizationId, &$cursor, $limit = 10) {
	$path = "organizations/$organizationId/teams?";
	if ($limit) {
		$path .= "&limit=$limit";
	}
	if ($cursor) {
		$path .= "&offset=$cursor";
	}

	$result = asanaRequest($path);
	if (isError($result))
	{
        pre($result, "Error Loading Teams!", 'danger');
		return;
	}

	if ($result['next_page']) {
		$cursor = $result['next_page']['offset'];
	} else {
		$cursor = false;
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