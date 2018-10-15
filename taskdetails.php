<?php 

function copyHistory($taskId, $newTaskId) {

	$result = asanaRequest("tasks/$taskId/stories");
	if (isUnrecognisedTaskError($result)) {
		progress("Unable to copy history for task $taskId: ID not recognised");
		return;
	}
	if (isError($result))
	{
        pre($result, "Failed to copy history from source task", 'danger');
		return;
	}

	$comments = array();
	foreach ($result['data'] as $story) {
		// Skip stories about attachments or other system events that may not be relevant in the new workspace
		if ($story['type'] === 'system' && substr($story['text'], 0, 9) === 'attached ') {
			continue;
		}
		if ($story['type'] === 'system' && substr($story['text'], 0, 9) === 'added to ') {
			continue;
		}
		if ($story['type'] === 'system' && substr($story['text'], 0, 12) === 'assigned to ') {
			continue;
		}
		$date = date('l M d, Y h:i A', strtotime($story['created_at']));
		$comment = " ­\n" . $story['created_by']['name'] . ' on ' . $date . ":\n" . $story['text'];
		$comments[] = $comment;
	}
	$comment = implode("\n----------------------", $comments);
	$data = array('data' => array('text' => $comment));
	if ($comment) {
		$result = asanaRequest("tasks/$newTaskId/stories", 'POST', $data);
	}
}

function getTargetTags($task, $targetWorkspaceId) {

    $tags = $task['tags'];
	$targetTags = array();

	// Do the tags exist in the target workspace?
	$tagKey = "tags:$targetWorkspaceId";
	$existingTags = getCached($tagKey);
	$tagsModified = false;
	if (!$existingTags) {
		$existingTags = getAllTags($targetWorkspaceId);
		$tagsModified = true;
	}
	foreach ($tags as $tag) {

		$tagName = $tag["name"];

		// does tag exist?
		$tagId = false;
		foreach ($existingTags as $existingTag) {
			if ($existingTag['name'] === $tagName) {
				$tagId = $existingTag["id"];
			}
		}

		if (!$tagId) {
			progress("Creating tag '$tagName' in target workspace");
			unset($tag['created_at']);
			unset($tag['followers']);
            unset($tag['resource_type']);
			$tag['workspace'] = $targetWorkspaceId;

			$data = array('data' => $tag);
			$result = asanaRequest("tags", "POST", $data);
			if (isError($result))
			{
				pre($result, "Failed to create tag in target workspace", 'danger');
				return;
			}
			$newTag = $result["data"];
			$tagId = $newTag["id"];
			$existingTags[] = $newTag;
			$tagsModified = true;
		}

		$targetTags[] = $tagId;
	}

	// Cache updated tags
	if (count($existingTags) && $tagsModified) {
		cache($tagkey, $existingTags);
	}

	// Return tag IDs for task creation
	return $targetTags;
}