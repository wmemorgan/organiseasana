<?php

/**
 * This is based on the implementation here:
 * https://gist.github.com/AWeg/5814427
 */

function asanaRequest($methodPath, $httpMethod = 'GET', $body = null, $cached = true, $wait = true) {
    global $authToken;
    global $DEBUG;
    global $ratelimit;

    $key = sha1($authToken['refresh_token']) . ":" . $methodPath;

    $data = false;
    $cached = $cached && $httpMethod == 'GET';
    if ($cached) {
        $data = getCached($key);

        if ($DEBUG >= 2) {
            pre(array('request' => $body, 'response' => $data), "Memcache: " . $methodPath);
        }

        if ($data != false) {
            return $data;
        }
    }

    $access_token = getAccessToken();
    $ratelimit = getRateLimit();

    $url = "https://app.asana.com/api/1.0/$methodPath";
    $headers = array(
        "Content-type: application/json",
        "Authorization: Bearer $access_token",
        "Asana-Fast-Api: true"
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $jbody = $body;
    if ($jbody) {
        if (!is_string($jbody)) {
            $jbody = json_encode($body);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jbody);
    }

    $error = null;
    for ($i = 0; $i < 10; $i++) {
        if ($ratelimit > time()) {
            if ($wait) {
                echo("Waiting for rate limit (". ($ratelimit - time()) . "s)");
                time_sleep_until($ratelimit);
            } else {
                die("Rate limit reached: retry in " . ($ratelimit - time()) . "s");
            }
        }

        $start = microtime(true);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        $elapsed = microtime(true) - $start;
        if ($elapsed > 0.7) {
            logMessage("Slow request to Asana ($elapsed sec): $url");
        }

        $result = parseAsanaResponse($data);

        if (isset($result['retry_after'])) {
            $ratelimit = time() + $result['retry_after'];
            setRateLimit($ratelimit);

            if ($wait) {
                continue;
            }
        }
        break;
    }

    curl_close($ch);

    if ($cached) {
        cache($key, $result);
    }

    if ($DEBUG >= 2) {
        pre(array('request' => $body, 'response' => $result, 'error' => $error), "$httpMethod " . $url);
    }
    return $result;
}

function parseAsanaResponse($data) {
    $result = null;

    // See http://stackoverflow.com/a/27909889/37416
    if (version_compare(PHP_VERSION, '5.4.0', '>=') && !(defined('JSON_C_VERSION') && PHP_INT_SIZE > 4)) {
        /** In PHP >=5.4.0, json_decode() accepts an options parameter, that allows you
         * to specify that large ints (like Steam Transaction IDs) should be treated as
         * strings, rather than the PHP default behaviour of converting them to floats.
         */
        $result = json_decode($data, true, 512, JSON_BIGINT_AS_STRING);
    } else {
        /** Not all servers will support that, however, so for older versions we must
         * manually detect large ints in the JSON string and quote them (thus converting
         *them to strings) before decoding, hence the preg_replace() call.
         */
        $max_int_length = strlen((string) PHP_INT_MAX) - 1;
        $json_without_bigints = preg_replace('/:\s*(-?\d{'.$max_int_length.',})/', ': "$1"', $data);
        $result = json_decode($json_without_bigints, true);
    }
    // $result = json_decode($data, true);

    return $result;
}

function getAccessToken() {
    global $authToken;
    $access_token = $authToken['access_token'];
    // Check for expiry
    $refresh = time() + 600;
    if (!$access_token || $authToken['expires'] < $refresh) {
        // Look for an already-refreshed token
        $tokenKey = sha1($authToken['refresh_token']) . ":access_token";
        $refreshed_token = getCached($tokenKey);
        
        if ($refreshed_token && $refreshed_token['expires'] > $refresh) {
            $access_token = $refreshed_token['access_token'];
            $authToken['access_token'] = $refreshed_token['access_token'];
            $authToken['expires'] = $refreshed_token['expires'];
        } else {
            // Refresh the token
            global $asana_app;
            $asana_config = $asana_app[$authToken['host']];
            $client = Asana\Client::oauth(array(
                'client_id' => $asana_config['key'],
                'client_secret' => $asana_config['secret'],
                'redirect_uri' => $asana_config['redirect'],
                'refresh_token' => $authToken['refresh_token']
            ));
            $access_token = $client->dispatcher->refreshAccessToken();
            $authToken['access_token'] = $access_token;
            $authToken['expires'] = time() + $client->dispatcher->expiresIn;

            cache($tokenKey, $authToken);
        }
    }

    return $access_token;
}

function cache($key, $result) {
    if ($key) {
        try {
            getMemcache()->set($key, $result, false, 120);
        } catch (Exception $e) {
        }
    }
}

function getCached($key) {
    try {
        $data = getMemcache()->get($key);
        return $data;
    } catch (Exception $e) {
    }
    return null;
}

function getMemcache() {
    global $memcache;
    if (!$memcache) {
        $memcache = new Memcache;
    }
    return $memcache;
}

function isCancelled($channel) {
    global $authToken;
    $key = sha1($authToken['refresh_token']) . ":$channel:cancelled";
    $cancelled = getMemcache()->get($key);
    return $cancelled;
}

function cancel($channel) {
    global $authToken;
    $key = sha1($authToken['refresh_token']) . ":$channel:cancelled";
    $cancelled = getMemcache()->set($key, true);
    return $cancelled;
}

function getPendingRequests() {
    global $authToken;
    $key = sha1($authToken['refresh_token']) . ":issuedRequests:" . floor(time()/60);
    $pending = getMemcache()->get($key);
    return $pending;
}

function incrementRequests($value = 1) {
    global $authToken;
    $key = sha1($authToken['refresh_token']) . ":issuedRequests:" . floor(time()/60);
    $pending = getMemcache()->increment($key, $value);
    if (!$pending) {
        getMemcache()->set($key, $value, false, 120);
        return $value;
    }
    return $pending;
}

function getRateLimit() {
    global $authToken;
    $key = sha1($authToken['refresh_token']) . ":ratelimit";
    $ratelimit = getMemcache()->get($key);
    return $ratelimit;
}

function setRateLimit($ratelimit) {
    global $authToken;
    $key = sha1($authToken['refresh_token']) . ":ratelimit";
    getMemcache()->set($key, $ratelimit);
    return $ratelimit;
}

function notifyCreated($project) {
    global $pusher;
    global $channel;
    if ($pusher) {
        $pusher->trigger($channel, 'created', $project);
    }
}

$messages = array();

function progress($text) {
    global $messages;

    $messages[] = $text;
    logMessage($text);
}

function logMessage($text) {
    global $pusher;

    if ($pusher) {
        print $text;
    } else {
        error_log($text);
    }
}

function flushProgress() {
    global $messages;
    global $pusher;
    global $channel;
    if (count($messages) == 0) {
        return;
    }

    $body = array('messages' => $messages);
    $pusher->trigger($channel, 'progress', $body);

    $messages = array();
}

function error($body, $title, $style) {
    global $pusher;
    global $channel;
    flushProgress();
    if ($pusher) {
        $error = array('error' => $title, 'api_response' => $body);
        print_r($error, true);
        $pusher->trigger($channel, 'error', $error);
        if (strcmp($style, 'danger') == 0) {
            throw new Exception(json_encode($error, JSON_PRETTY_PRINT));
        }
    } else {
        print '<div class="bs-callout bs-callout-' . $style . '">';
        if ($title) {
            print "<h4>$title</h4>";
        }
        if ($body) {
            print "<pre>";
            print(htmlspecialchars(json_encode($body, JSON_PRETTY_PRINT)));
            print "</pre>";
        }
        print "</div>\n";
        flush();
    }
}

function fatal($body, $title) {
    global $channel;
    cancel($channel);

    error($body, "Job aborted: " + $title, 'danger');
}

function p($text) {
    progress($text);
}

function pre($o, $title = false, $style = 'info') {
    error($o, $title, $style);
}

function isError($result) {
    return isset($result['errors']) || !isset($result['data']);
}

function isPaginationError($result) {
    return isset($result['errors']) && $result['errors']['message'] === "offset: Your pagination token has expired.";
}

function hasPrefix($string, $prefix) {
    return substr($string, 0, strlen($prefix)) === $prefix;
}

function isUnrecognisedTaskError($result) {
    return isset($result['errors']) && hasPrefix($result['errors']['message'], "task: Not a recognized ID:");
}

require_once 'workspaces.php';
require_once 'projects.php';
require_once 'sections.php';
require_once 'tasks.php';
require_once 'tags.php';
require_once 'taskdetails.php';
require_once 'attachments.php';
require_once 'fields.php';
