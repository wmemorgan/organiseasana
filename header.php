<html>

<head>
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
			<h1>Organise Asana Projects
				<small>
					<a href="http://kothar.net/projects/organise-asana.html">Help!</a>
				</small>
			</h1>
		</div>
		<p class="lead">
			Copy
			<a href="https://asana.com" target="asana">Asana</a> projects from one workspace to another.
		</p>

		<div class="row">
			<div class="col-md-6">
				<h3>Usage</h3>
				<p>
					Before you start, you may need to set up a few things that the tool can't do on its own though the API.
				</p>
				<ol>
					<li>Add your new user account (for your destination workspace) to your old workspace, if not already using the same email
						address</li>
					<li>Log in to Asana with your new account</li>
					<li>Add any user accounts you want to keep task assignments for to the new workspace</li>
					<li>Add any custom fields you want to keep to the new workspace (pro workspaces)</li>
					<li>Log in to the tool</li>
					<li>Choose the source workspace and project you’d like to copy</li>
					<li>Choose where you’d like to copy to</li>
					<li>Hit Go!</li>
				</ol>
			</div>

			<div class="col-md-6">
				<h3>Updates</h3>
				<ul>
					<li>
						<b>30th June 2017:</b> Added support for board-style projects.
					</li>
					<li>
						<b>20th August 2017:</b> Added support for custom fields. Made possible by generous support from
						<a href="http://www.gauge.com.br/">Gauge&deg;</a>
					</li>
					<li>
						<b>10th May 2018:</b> Added simple usage instructions - thank you to everyone who's suggested this, it should have been
						there from the start!</li>
                    <li>
						<b>15 October 2018:</b> Several bugs fixed preventing tasks and tags from being created.</li>
				</ul>
			</div>
		</div>