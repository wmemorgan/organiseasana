<hr>
<div class="bs-callout bs-callout-info">
    Due to the rate limits imposed by the Asana API,
    the copy may take some time (approximately 10-15 tasks per minute). Please be patient.
</div>
<h3 id="progress">Progress: 
    <input type="hidden" name="channel" value="<?php echo $channel; ?>">
    <input type="hidden" name="cancel" value="1">
    <input class="btn btn-danger pull-right" type="submit" value="Cancel" >
</h3>

<div class="well" id="log">
    Waiting for status...<br>
</div>
<h3>New projects:</h3>
<div id="projects"></div>
<hr>
<script src="//js.pusher.com/2.2/pusher.min.js"></script>
<script>
    var startTime = new Date().getTime();
    var pusher = new Pusher("<?php echo $config['pusher_key']; ?>");
    var channel = pusher.subscribe("<?php echo $channel; ?>");
    channel.bind('progress', function(data) {
        var message = data.message;
        var now = new Date();
        var elapsed = now.getTime() - startTime;
        var seconds = elapsed / 1000;
        var minutes = Math.floor(seconds / 60);
        seconds = Math.floor(seconds % 60);

        $('#log').append(now + " (" + minutes + "m " + seconds + "s) - ");
        $('#log').append(message + "<br>");
        $('#log').scrollTop(10000000);
    });
    channel.bind('created', function(project) {
        $('#projects').append('<a class="btn btn-success btn-xs" target="asana" href="https://app.asana.com/0/' + project['id'] + '">' + project['name'] + '</a> ');
    });
    channel.bind('done', function(data) {
        $('#projects').append("<hr>Done.");
        pusher.unsubscribe("<?php echo $channel; ?>");
    });
    channel.bind('error', function(data) {
        var message = JSON.stringify(data.api_response, null, 2);
        $('#log').append('<h2>' + data.error + '</h2><pre class="text-danger">' + message + "</pre><br>");
        $('#log').scrollTop(10000000);
    });

    $(function() { 
        $('#mainForm').ajaxForm(function() { 
            $('#log').append('<p class="text-danger">Cancelling job...</p><br>');
            $('#log').scrollTop(10000000);
        }); 
    }); 
</script>