<?php

function getAllTags($workspaceId) {
	$cursor = null;
	$limit = 100;

	$tags = array();
	do {
		$page = getTags($workspaceId, $cursor, $limit);
		if ($page) {
			$tags = array_merge($tags, $page);
		}
	} while ($cursor);

	return $tags;
}


function getTags($workspaceId, &$cursor, $limit = 10) {
	$path = "workspaces/$workspaceId/tags?";
	if ($limit) {
		$path .= "&limit=$limit";
	}
	if ($cursor) {
		$path .= "&offset=$cursor";
	}

	$result = asanaRequest($path);
	if (isError($result))
	{
        pre($result, "Error loading tags", 'danger');
		return;
	}

	if ($result['next_page']) {
		$cursor = $result['next_page']['offset'];
	} else {
		$cursor = false;
	}

	return $result['data'];
}