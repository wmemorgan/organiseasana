<?php



function copyAttachments($taskId, $newTaskId) {
	$result = asanaRequest("tasks/$taskId/attachments");
	if (isError($result))
	{
        pre($result, "Failed to copy attachments from source task!", 'danger');
		return;
	}

	global $APPENGINE;

	foreach ($result['data'] as $attachment){

		if ($APPENGINE) {
			queueAttachment($taskId, $newTaskId, $attachment["id"], $attachment["name"]);
		}
		else {
			copyAttachment($taskId, $newTaskId, $attachment["id"], $attachment["name"]);
		}
	}
}


function queueAttachment($taskId, $newTaskId, $attachmentId, $attachmentName) {
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

function copyAttachment($taskId, $newTaskId, $attachmentId, $attachmentName, $wait=true) {

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
    global $attachmentData;
    global $DEBUG;

    $downloadUrl = $result['data']['download_url'];
    $fileName = $result['data']['name'];
    $attachmentType = "text/plain";
    $attachmentLength = -1;

    // Get file headers
    $headers = get_headers($downloadUrl, 1);

	if ($DEBUG >= 2) {
		pre($headers, "Headers for $downloadUrl");
	}

    if (isset($headers['Content-Length'])) {
	    $attachmentLength = $headers['Content-Length'];
    } else {
    	if (isset($headers['content-length'])) {
		    $attachmentLength = $headers['content-length'];
		}
	}
	if (isset($headers['Content-Type'])) {
	    $attachmentType = $headers['Content-Type'];
    } else if (isset($headers['content-type'])) {
    	$attachmentType = $headers['content-type'];
	}

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
	
    // Set up cURL
	$access_token = getAccessToken();
	$ratelimit = getRateLimit();
    $url = "https://app.asana.com/api/1.0/tasks/$newTaskId/attachments";
	$headers = array(
		"Content-Type: multipart/form-data; boundary=$boundary",
		"Authorization: Bearer $access_token"
	);

	$attachmentData = false;
	if ($attachmentLength < 0) {
    	p("Unable to determine attachment size for $fileName, forced to download first.");

		// Maximum attachment size is 100 MiB
		$maxBuffer = 10 * 1024 * 1024;
		$maxSize = 100 * 1024 * 1024;
		$attachmentData = "";
		$attachmentLength = 0;
		$finished = false;

		while ($attachmentLength < $maxBuffer) {
			$chunk = fread($content, $maxBuffer-$attachmentLength);
			if (!$chunk || strlen($chunk) == 0) {
				$finished = true;
				break;
			}
			$attachmentData .= $chunk;
			$attachmentLength += strlen($chunk);
			if ($DEBUG >= 1) {
				p("Downloaded " . $attachmentLength/1024 . " kb");
			}
		}

		if (!$finished) {
			// Discard data and just work out the size of the attachment
			$attachmentData = false;

    		p("Attachment $fileName > 10 Mib, requires full re-download");
			while ($attachmentLength < $maxSize) {
				$chunk = fread($content, 1024 * 1024);
				if (!$chunk || strlen($chunk) == 0) {
					$finished = true;
					break;
				}
				$attachmentLength += strlen($chunk);
				if ($DEBUG >= 1) {
					p("Downloaded " . $attachmentLength/1024 . " kb");
				}
			}

			fclose($content);

			// Did we succeed?
			if (!$finished) {
				p("Attachment is too large to attach!");
				// TODO add a message to task
				return;
			}

			// Re-open the stream
    		$content = fopen($downloadUrl, 'r');
		}
	}

	$contentLength = strlen($attachmentHeader) + $attachmentLength + strlen($attachmentFooter);
	$headers[] = "Content-Length: $contentLength";
    p("Attachment size: $attachmentLength bytes\n");
	
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
	fclose($content);

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
	p("Finished uploading $fileName");
}

// Thanks to http://zingaburga.com/2011/02/streaming-post-data-through-php-curl-using-curlopt_readfunction/
function uploadAttachment($ch, $fp, $len) {
	static $header=true;
	static $footer=false;
	static $pos=0; // keep track of position

    global $attachmentLength;
    global $attachmentHeader;
    global $attachmentFooter;
    global $attachmentData;
    global $DEBUG;

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
    	$data = false;
    	if ($attachmentData) {
    		$data = substr($attachmentData, $pos, $len);
		} else {
			$data = fread($fp, $len);
		}

		// increment $pos
		$pos += strlen($data);

		if ($DEBUG >= 1) {
			p("Uploaded " . $pos/1024 . " kb");
		}

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