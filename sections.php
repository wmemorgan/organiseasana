<?php

function getSections($projectId, &$cursor, $limit = 10, $lastSectionId = null) {
	$path = "projects/$projectId/sections?";
	if ($limit) {
		$path .= "&limit=$limit";
	}
	if ($cursor) {
		$path .= "&offset=$cursor";
	}

	$result = asanaRequest($path);
	if (isError($result))
	{
        pre($result, "Error loading sections", 'danger');
		return;
	}

	if ($result['next_page']) {
		$cursor = $result['next_page']['offset'];
	} else {
		$cursor = false;
	}

	return $result['data'];
}

function getSection($sectionId) {
	$result = asanaRequest("sections/$sectionId");
	if (isError($result))
	{
        pre($result, "Error loading section", 'danger');
		return;
	}

	return $result['data'];
}

function createSection($projectId, $name)
{
	p("Creating section: " . $name);
	$data = array('data' => array(
		'name' => $name
	));

	$result = asanaRequest("projects/$projectId/sections", 'POST', $data);

	if (!isError($result))
	{
		$newSection = $result['data'];
		return $newSection;
	}
	else {
		pre($result, "Error creating section", 'danger');
	}

	return $result;
}

function getSectionTasks($sectionId, &$cursor, $limit = 20, $lastTaskId = null) {
	return getTasks("sections/$sectionId", $cursor, $limit, $lastTaskId);
}