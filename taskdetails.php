<?php 

function copyHistory($taskId, $newTaskId) {

	$result = asanaRequest("tasks/$taskId/stories");
	if (isError($result))
	{
        pre($result, "Failed to copy history from source task!", 'danger');
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
	$result = asanaRequest("tasks/$newTaskId/stories", 'POST', $data);

}

function copyTags ($taskId, $newTaskId, $newworkspaceId) {

    // GET Tags
    $result = asanaRequest("tasks/$taskId/tags");

    if (!isError($result))
    { 	// are there any tags?
        $tags = $result["data"];
		$result = asanaRequest("workspaces/$newworkspaceId/tags");
		if (isError($result))
		{
	        pre($result, "Failed to list tags in target workspace!", 'danger');
			return;
		}
		$existingtags = $result["data"];
		
        for ($i = count ($tags) - 1; $i >= 0; $i--) {

            $tag = $tags[$i];
            $tagName = $tag["name"];

            // does tag exist?
            $tagkey = "$newworkspaceId:tag:$tagName";
            $existingtag = getCached($tagkey);
            $tagisset = $existingtag != null;
            if (!$tagisset) {
	            for($j = count($existingtags) - 1; $j >= 0; $j--) {
	                $existingtag = $existingtags[$j];

	                if (strcmp($tagName,$existingtag["name"]) == 0) {
	                    $tagisset = true;
	                    $tagId = $existingtag["id"];
	                    break;
	                }
	            }
	        } else {
	        	$tagId = $existingtag["id"];
	        }

            if (!$tagisset) {

                p("tag does not exist in workspace");
                unset($tag['created_at']);
                unset($tag['followers']);
                $tag['workspace'] = $newworkspaceId;

                $data = array('data' => $tag);
                $result = asanaRequest("tags", "POST", $data);
                if (isError($result))
				{
			        pre($result, "Failed to create tag in target workspace!", 'danger');
					return;
				}
                $tagId = $result["data"]["id"];

				// Cache new tag
				cache($tagkey, $result["data"]);
            }

            $data = array("data" => array("tag" => $tagId));
            $result = asanaRequest("tasks/$newTaskId/addTag", "POST", $data);
            if (isError($result))
			{
		        pre($result, "Failed to add tag to task!", 'danger');
				return;
			}
        }
    }
}