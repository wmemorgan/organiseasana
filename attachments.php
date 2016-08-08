<?php



function copyAttachments($taskId, $newTaskId, $workspaceId) {
	$result = asanaRequest("tasks/$taskId/attachments");
	if (isError($result))
	{
        pre($result, "Failed to copy attachments from source task!", 'danger');
		return;
	}

	global $APPENGINE;

	foreach ($result['data'] as $attachment){

		if ($APPENGINE) {
			queueAttachment($taskId, $newTaskId, $attachment["id"], $attachment["name"], $workspaceId);
		}
		else {
			copyAttachment($taskId, $newTaskId, $attachment["id"], $attachment["name"], $workspaceId);
		}
	}
}


function queueAttachment($taskId, $newTaskId, $attachmentId, $attachmentName, $workspaceId) {
	global $channel;
	global $authToken;
	global $DEBUG;

	// Start task
	require_once("google/appengine/api/taskqueue/PushTask.php");

	$params = [
		'channel' => $channel,
		'authToken' => $authToken,
		'taskId' => $taskId,
		'newTaskId' => $newTaskId,
		'attachmentId' => $attachmentId,
		'attachmentName' => $attachmentName,
		'debug' => $DEBUG
	];
	$job = new \google\appengine\api\taskqueue\PushTask('/process/attachment', $params);
	$task_name = $job->add('attachments');
}

function copyAttachment($taskId, $newTaskId, $attachmentId, $attachmentName, $workspaceId, $wait=true) {

	// Get attachment details
	$result = asanaRequest("attachments/$attachmentId");
	if (isError($result))
	{
        pre($result, "Failed to load attachment details!", 'danger');
		return;
	}

    $downloadUrl = $result['data']['download_url'];
    $fileName = $result['data']['name'];

    // Get file headers
    $headers = get_headers($downloadUrl, 1);
    $contentLength = $headers['Content-Length'];

    // Get attachment content stream
    $content = fopen($downloadUrl, 'r');

    // Set up cURL
	$access_token = getAccessToken();
	$ratelimit = getRateLimit();
    $url = "https://app.asana.com/api/1.0/tasks/$newTaskId/attachments";
	$headers = array(
		"Content-type: multipart/form-data",
		"Authorization: Bearer $access_token"
	);
	$fields = array(
		"file" => $fileName
	);
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

	curl_setopt($ch, CURLOPT_UPLOAD, true);
	curl_setopt($ch, CURLOPT_INFILE, $content);
	curl_setopt($ch, CURLOPT_INFILESIZE, $contentLength);
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
	curl_setopt($ch, CURLOPT_VERBOSE, true);

    // SSL cert of Asana is selfmade
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    // curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

	// Upload to destination task
	if ($ratelimit > time()) {
		if ($wait) {
			echo("Waiting for rate limit (". ($ratelimit - time()) . "s)");
			time_sleep_until($ratelimit);
		} else {
			die ("Rate limit reached: retry in " . ($ratelimit - time()) . "s");
		}
	}

	$data = curl_exec($ch);
	$error = curl_error($ch);
	$result = parseAsanaResponse($data);

	if(isset($result['retry_after'])) {
		$ratelimit = time() + $result['retry_after'];
		setRateLimit($ratelimit);

		error("Rate limit exceeded");
	}
	if (isError($result))
	{
        pre($result, "Failed to upload attachment", 'danger');
		return;
	}

	curl_close($ch);
}