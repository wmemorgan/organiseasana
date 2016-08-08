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

    // Set up variables for upload
    global $attachmentLength;
    global $attachmentHeader;
    global $attachmentFooter;

    $downloadUrl = $result['data']['download_url'];
    $fileName = $result['data']['name'];

    // Get file headers
    $headers = get_headers($downloadUrl, 1);
    $attachmentLength = $headers['content-length'];
    $attachmentType = $headers['content-type'];
    printf("Attachment size: %d bytes\n", $attachmentLength);

    // Get attachment content stream
    $content = fopen($downloadUrl, 'r');

    // Prepare upload
    $boundary = md5(rand());
    $attachmentHeader = 
		"--$boundary\r\n".
		"Content-Disposition: form-data; name=\"file\"; filename=\"$fileName\"\r\n".
		"Content-Type: $attachmentType\r\n".
		"Content-Transfer-Encoding: binary\r\n\r\n";
	$attachmentFooter =
		"\r\n--$boundary--\r\n";
	$contentLength = strlen($attachmentHeader) + $attachmentLength + strlen($attachmentFooter);

    // Set up cURL
	$access_token = getAccessToken();
	$ratelimit = getRateLimit();
    $url = "https://app.asana.com/api/1.0/tasks/$newTaskId/attachments";
	$headers = array(
		"Content-Type: multipart/form-data; boundary=$boundary",
		"Content-Length: $contentLength",
		"Authorization: Bearer $access_token"
	);
	
	$ch = curl_init($url);
	$options = array(
		CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_POST => true,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_INFILE => $content,
		CURLOPT_INFILESIZE => $content,
		CURLOPT_READFUNCTION => 'uploadAttachment'
	);
	curl_setopt_array($ch, $options);

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
	$curlError = curl_error($ch);
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

// Thanks to http://zingaburga.com/2011/02/streaming-post-data-through-php-curl-using-curlopt_readfunction/
function uploadAttachment($ch, $fp, $len) {
	static $header=true;
	static $footer=false;
	static $pos=0; // keep track of position

    global $attachmentLength;
    global $attachmentHeader;
    global $attachmentFooter;

    // Send header
    if ($header) {
		// set data
		$data = substr($attachmentHeader, $pos, $len);

		// increment $pos
		$pos += strlen($data);

		// Check for end of header
    	if ($pos >= strlen($attachmentHeader)) {
    		$header = false;
    		$pos = 0;
    	}

		// return the data to send in the request
		return $data;
    } 

    // Send body
    if (!$footer) {
    	// set data
		$data = fread($fp, $len);

		// increment $pos
		$pos += strlen($data);

		// Check for end of header
    	if ($pos >= $attachmentLength) {
    		$footer = true;
    		$pos = 0;
    	}

		// return the data to send in the request
		return $data;
    }

    // Send footer
    // set data
	$data = substr($attachmentFooter, $pos, $len);

	// increment $pos
	$pos += strlen($data);

	// return the data to send in the request
	return $data;
}