<html>

<head>
	<!-- Global Site Tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-40871968-3"></script>
	<script>
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());
		gtag('config', '<?php echo $config["ga_ua"] ?>');
	</script>

	<title>Organise Asana Projects</title>
	<script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
	<script src="/jquery.form.js"></script>

	<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="/theme.min.css">
	<script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
	<link rel="shortcut icon" href="/favicon.ico">
	<style>
		form input[type=text] {
			width: 500px;
		}
		
		.bs-callout h4 {
			margin-top: 0;
			margin-bottom: 5px;
		}
		
		.bs-callout-info h4 {
			color: #5bc0de;
		}
		
		.bs-callout-warning h4 {
			color: #f0ad4e;
		}
		
		.bs-callout-danger h4 {
			color: #d9534f;
		}
		
		.bs-callout {
			margin: 20px 0;
			padding: 20px;
			border-left: 3px solid #eee;
		}
		
		.bs-callout-info {
			background-color: #f4f8fa;
			border-color: #5bc0de;
		}
		
		.bs-callout-warning {
			background-color: #fcf8f2;
			border-color: #f0ad4e;
		}
		
		.bs-callout-danger {
			background-color: #fdf7f7;
			border-color: #d9534f;
		}
		
		#log {
			height: 400px;
			max-height: 400px;
			overflow-y: auto;
		}
	</style>
</head>

<body>
	<div class="container">
		<div class="page-header">
			<h1>Organise Asana Projects <small><a href="http://kothar.net/projects/organise-asana.html">Help!</a></small></h1>
		</div>
		<p class="lead">
			Copy <a href="https://asana.com" target="asana">Asana</a> projects from one workspace to another.
		</p>

		<h3>Updates</h3>
		<ul>
			<li>
				<b>21st Feb 2017:</b> Copying large projects now works more reliably.
			</li>
			<li>
				<b>1st March 2017:</b> Bug fix for task description not being copied.
			</li>
			<li>
				<b>9th March 2017:</b> Bug fix for duplicate tasks being copied, and errors when copying projects with tags to a personal
				workspace.
			</li>
			<li>
				<b>30th June 2017:</b> Added support for board-style projects.
			</li>
			<li>
				<b>20th August 2017:</b> Added support for custom fields. Made possible by generous support from <a href="http://www.gauge.com.br/">Gauge&deg;</a>
			</li>
		</ul>