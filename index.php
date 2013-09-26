<?php 


global $apiKey;
$apiKey = "";

if ($_POST["apiKey"])
	$apiKey = $_POST["apiKey"];
if ($_POST["workspace"])
	$workspaceId = $_POST["workspace"];
if ($_POST["copyTo"])
	$copyTo = $_POST["copyTo"];
if ($_POST["projects"])
	$projects = $_POST["projects"];

include "asana.php";

?>
<html>
	<head>
		<title>Organise Asana Projects</title>
		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap.min.css">

		<!-- Optional theme -->
		<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.0.0/css/bootstrap-theme.min.css">

		<!-- Latest compiled and minified JavaScript -->
		<script src="//netdna.bootstrapcdn.com/bootstrap/3.0.0/js/bootstrap.min.js"></script>

		<style>
			form input[type=text] { width: 500px; }
		</style>
	</head>
	<body>
		<div class="container">
			<div class="page-header">
				<h1>Organise Asana Projects <small><a href="http://kothar.net/projects/organise-asana.html">Help!</a></small></h1>
			</div>
			<p class="lead">
				Copy <a href="https://asana.com">Asana</a> projects from one workspace to another.
			</p>
			<form method="POST">
				<p>
					<label>API Key: <input type="text" name="apiKey" value="<?php echo $apiKey ?>"></label>
					<button type="submit">Submit</button>
				</p>

				<?php if ($apiKey) {

					// Run the copy operation
					if ($copyTo && $projects) {
						$workspace = getWorkspace($copyTo);
						echo '<h2>Copying Projects to '. $workspace['name'] . '</h2>';

						for ($i = count($projects) - 1; $i >= 0; $i--) {
							$project = getProject($projects[$i]);
							$targetProjectName = $project['name'];

							// Check for an existing project in the target workspace
							$targetProjects = getProjects($copyTo);
							$count = 2;
							for ($j = count($targetProjects) - 1; $j >= 0; $j--)
							{
								if (strcmp($targetProjects[$j]['name'], $targetProjectName) == 0) {
									$targetProjectName = $project['name'] . $count++;
									$j = count($projects);
								}
							}

							// Create target project
							$targetProject = createProject($copyTo, $targetProjectName);
							
							echo '<p>Copying ' . $project['name'] . ' to ' . $workspace['name'] . '/' . $targetProjectName . '</p>';
							flush();

							// Run copy
							copyTasks($project['id'], $targetProject['id']);
						}

						echo '<b>Done</b>';
					}

					// Display copy options
					else {
						echo '<h2>Browse workspace</h2>';
						echo '<p>';
						$workspaces = getWorkspaces();
						for ($i = count($workspaces) - 1; $i >= 0; $i--)
						{
							$workspace = $workspaces[$i];
							echo '<button type="submit" name="workspace" value="' . $workspace['id'] . '">' . $workspace['name'] . '</button>';
						}
						echo '</p>';

						if ($workspaceId) {
							echo '<table><tr><td valign="top">';
							echo '<h2>Copy Projects -></h2>';
							$projects = getProjects($workspaceId);
							for ($i = count($projects) - 1; $i >= 0; $i--)
							{
								$project = $projects[$i];
								echo '<input type="checkbox" name="projects[]" value="' . $project['id'] . '">' . $project['name'] . '</input><br>';
							}
							echo '</td><td valign="top">';
							echo '<h2>to Workspace</h2>';
							for ($i = count($workspaces) - 1; $i >= 0; $i--)
							{
								$workspace = $workspaces[$i];
								echo '<button type="submit" name="copyTo" value="' . $workspace['id'] . '">' . $workspace['name'] . '</button><br>';
							}
							echo '</td></tr></table>';
						}
					}

				} ?>
			</form>

			<div class="alert alert-info">
				<h3>Source code</h3>
				<p>Source code for this tool can be found at <a href="https://bitbucket.org/mikehouston/organiseasana">https://bitbucket.org/mikehouston/organiseasana</a></p>
				<p>The implementation of the copy operation is based on <a href="https://gist.github.com/AWeg/5814427">https://gist.github.com/AWeg/5814427</a></p>
				<h3>Privacy</h3>
				<p>No data is stored on the server - the API key is not retained between calls. No cookies are stored.</p>
				<h3>No Warranty</h3>
				<p>This tool does not delete any data, and will not modifiy any existing projects (a new copy is made each time)</p>
				<p>No warranty is made however - use at your own risk</p>
			</div>
		</div>
	</body>
</html>