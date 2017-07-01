<?php

	include "init.php";
	include "asana.php";

	global $pusher;
	$pusher = false;

	// Input parameters
	$storeKey = null;
	$targetWorkspaceId = null;
	$copy = null;
	$workspaceId = null;
	$projects = null;
	$teamId = null;
	$refresh = false;
	$projectCursor = false;
	$teamCursor = false;

	// Has the job been cancelled?
	if (isset($_POST["cancel"]) && $channel) {
		cancel($channel);
		return;
	}

	// Read parameters
	if (isset($_POST["storeKey"]))
		$storeKey = $_POST["storeKey"];
	if (isset($_POST["refresh"]))
		$channel = $_POST['refresh'];
	if (isset($_POST["projectCursor"]))
		$projectCursor = $_POST["projectCursor"];
	if (isset($_POST["setProjectCursor"]))
		$projectCursor = $_POST["setProjectCursor"];
	if (isset($_POST["teamCursor"]))
		$teamCursor = $_POST["teamCursor"];
	if (isset($_POST["setTeamCursor"]))
		$teamCursor = $_POST["setTeamCursor"];

	if (isset($_POST["new_workspace"])) {
		$workspaceId = $_POST["new_workspace"];
	}
	else {
		if (isset($_POST["workspace"]))
			$workspaceId = $_POST["workspace"];

		if (isset($_POST["projects"])) {
			$projects = array_unique($_POST["projects"]);
		}

		if (isset($_POST["new_targetWorkspace"])) {
			$targetWorkspaceId = $_POST["new_targetWorkspace"];
		}
		else {
			if (isset($_POST["targetWorkspace"]))
				$targetWorkspaceId = $_POST["targetWorkspace"];
			if (isset($_POST["copy"]))
				$copy = $_POST["copy"];
			if (isset($_POST["team"]))
				$teamId = $_POST["team"];
		}
	}

	if ($authToken) {

		$targetWorkspace = null;
		$team = null;

		if ($targetWorkspaceId) {
			$targetWorkspace = getWorkspace($targetWorkspaceId);
		}

		if ($DEBUG) pre($targetWorkspace, "Target Workspace");

		// Do we have what we need to run a copy?
		if ($targetWorkspaceId && $projects) {
			if (isOrganisation($targetWorkspace)) {
				if ($teamId) {
					$team = getTeam($targetWorkspaceId, $teamId);
					if ($DEBUG) pre($team, "Team");
					if ($team)
						$copy = true;
				}
			}
		}

		// Run the copy operation
		if ($copy) {
			// Create a pusher channel
			$channel = sha1(openssl_random_pseudo_bytes(30));

			// Start task
			require_once("google/appengine/api/taskqueue/PushTask.php");

			$params = [
				'channel' => $channel,
				'authToken' => $authToken,
				'debug' => $DEBUG,
				'targetWorkspaceId' => $targetWorkspaceId,
				'copy' => 'projects',
				'workspaceId' => $workspaceId,
				'projects' => $projects,
				'teamId' => $teamId
			];
			$task = new \google\appengine\api\taskqueue\PushTask('/process/project', $params);
			$task_name = $task->add();

			$host = $_SERVER['HTTP_HOST'];
			$url = 'https://' . $host . "?channel=$channel&debug=$DEBUG";
			header('Location: ' . $url, true, 303);
			die();
		}
	}

if($DEBUG >= 1) {
?>
<div class="bs-callout bs-callout-info">
	<h4>DEBUG Mode is ON</h4>
	<a href="?debug=0" class="btn btn-warning">Disable</a>

	<div class="btn-group">
		<a href="?debug=1" class="btn btn-default">Level 1</a>
		<a href="?debug=2" class="btn btn-default">Level 2 (show API calls)</a>
	</div>
</div> <?php 
	if($DEBUG >= 3) { ?>
<div class="bs-callout bs-callout-info">
	<?php 
	print "<h4>Parameters</h4>";
	print "<pre>";
	print(json_encode(array(
		"DEBUG" => $DEBUG,
		"channel" => $channel,
		"authToken" => $authToken
		), JSON_PRETTY_PRINT));
	print "</pre>\n";
	flush(); ?>
</div>
<?php
	}
}

include "header.php";
?>

<form id="mainForm" role="form" method="POST">
	<div class="row">
		<div class="col-sm-8">
			<?php if ($authToken) { ?>
				<a href="/deauth" class="btn btn-danger">Log out</a>
			<?php } else { ?>
				<a href="/auth" class="btn btn-primary">Login with Asana</a>
			<?php } ?>
		</div>
	</div>

	<?php 

	if ($channel) {
		include "progress.php";
	}

	// Display copy options
	else if ($enabled && $authToken) {
		echo '<h2>Browse workspace</h2>';
		echo '<div class="btn-group">';
		$workspaces = getWorkspaces();
		for ($i = count($workspaces) - 1; $i >= 0; $i--)
		{
			$workspace = $workspaces[$i];
			$active = '';
			if ($workspace['id'] == $workspaceId)
				$active = ' active';
			echo '<button class="btn btn-default' . $active . '" type="submit" name="new_workspace" value="' . $workspace['id'] . '">' . $workspace['name'] . '</button>';
		}
		echo '</div>';

		if ($workspaceId) {
			echo '<input type="hidden" name="workspace" value="' . $workspaceId . '"></input>';
			echo '<input type="hidden" name="projectCursor" value="' . $projectCursor . '"></input>';

			// Select projects
			echo '<div class="row">';
			echo '<div class="col-sm-4">';
			echo '<h2>Copy Projects -></h2>';

			$nextProjectCursor = $projectCursor;
			$workspaceProjects = getProjects($workspaceId, $nextProjectCursor);
			$names = function($value) { return $value['name']; };

			echo '<div class="btn-group-vertical" data-toggle="buttons">';
			$remainingProjects = $projects;
			for ($i = count($workspaceProjects) - 1; $i >= 0; $i--)
			{
				$project = $workspaceProjects[$i];
				$checked = '';
				$active = '';
				$id = $project['id'];
				if ($projects && in_array($id, $projects)) {
					$checked = ' checked';
					$active = ' active';
					$remainingProjects = array_diff($remainingProjects, [$id]);
				}

				echo '<label class="btn btn-default' . $active . '"><input type="checkbox" name="projects[]" value="'
						. $project['id'] . '"' . $checked . '> ' . $project['name'] . '</label>';
			}
			echo '</div>';

			$other = count($remainingProjects);
			if ($other) {
				echo '<p>' . $other . " other projects selected</p>";
				foreach($remainingProjects as $project) {
					echo '<input type="hidden" name="projects[]" value="' . $project . '">';
				}
			}

			echo '<div style="padding: 10px;">';
			if ($projectCursor) {
				echo '<button class="btn btn-sm btn-primary" type="submit" name="setProjectCursor" value="0">Reset</button> ';
			}
			if ($nextProjectCursor) {
				echo '<button class="btn btn-sm btn-primary" type="submit" name="setProjectCursor" value="' . $nextProjectCursor . '">More</button>';
			}
			echo '</div>';

			if ($DEBUG) pre($projects, "Selected projects");
			if ($DEBUG) pre($workspaceProjects, "Workspace projects");
			echo '</div>';

			// Select workspace
			echo '<div class="col-sm-4">';
			echo '<h2>to Workspace</h2>';

			echo '<div class="btn-group-vertical">';
			for ($i = count($workspaces) - 1; $i >= 0; $i--)
			{
				$workspace = $workspaces[$i];

				$type = ' btn-default';
				if (isOrganisation($workspace))
					$type = ' btn-warning';

				$active = '';
				if ($targetWorkspaceId == $workspace['id'])
					$active = ' active';
				echo '<button class="btn' . $type . $active . '" type="submit" name="new_targetWorkspace" value="' . $workspace['id'] . '">' . $workspace['name'] . '</button>';
			}
			echo '</div>';

			if ($DEBUG) pre($workspaces, "Target workspaces");
			echo '</div>';

			// Select team
			if ($targetWorkspaceId) {
				echo '<div class="col-sm-4">';
				echo '<input type="hidden" name="targetWorkspace" value="' . $targetWorkspaceId . '"></input>';
				$showTeams = isOrganisation($targetWorkspace);

				// Handle Personal Projects
				if ($showTeams) {
					echo '<input type="hidden" name="teamCursor" value="' . $teamCursor . '"></input>';
					$nextTeamCursor = $teamCursor;
					$teams = getTeams($targetWorkspaceId, $nextTeamCursor);
					
					echo '<h2>for team</h2>';

					echo '<div class="btn-group-vertical">';
					for ($i = count($teams) - 1; $i >= 0; $i--)
					{
						$team = $teams[$i];

						echo '<button class="btn btn-success" type="submit" name="team" value="' . $team['id'] . '">' . $team['name'] . '</button>';
					}
					echo '</div>';

					echo '<div style="padding: 10px;">';
					if ($teamCursor) {
						echo '<button class="btn btn-sm btn-primary" type="submit" name="setTeamCursor" value="0">Reset</button> ';
					}
					if ($nextTeamCursor) {
						echo '<button class="btn btn-sm btn-primary" type="submit" name="setTeamCursor" value="' . $nextTeamCursor . '">More</button>';
					}
					echo '</div>';

					if ($DEBUG) pre($teams, "Available teams");
				}
				else {
					// GO button

					echo '<h2>Ready!</h2>';
					echo '<button class="btn btn-success" type="submit" name="copy" value="go">Go!</button>';

				}
				echo '</div>';
			}

			echo '</div>';
		}

	} ?>
</form>

<?php
include "footer.php";
