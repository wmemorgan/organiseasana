<?php

include "init.php";
include "asana.php";

$projectIds = explode(',', $_GET['projectIds']);
$workspaceId = $_GET['workspaceId'];
?>
	<style>
		th {
			text-align: left;
		}
	</style>
	<form>
		<p>
			<label>Source project IDs <input type="text" name="projectIds" value="<?php echo implode(',', $projectIds) ?>"></label>
		</p>
		<p>
			<label>Destination workspace ID <input type="text" name="workspaceId" value="<?php echo $workspaceId ?>"></label>
		</p>
		<input type="submit">
	</form>

	<?php

    if ($projectIds) {
        $projectId = $projectIds[0];
        $project = getProject($projectId);
        echo "<p>Project <strong>".$project['name']."</strong> is part of Workspace <strong>".$project['workspace']['name']."</strong></p>";
        $workspace1 = $project['workspace']['id'];
        
        if ($workspaceId) {
            $workspaceFields = getAllCustomFields($workspaceId);
            $fieldMap = array();
            foreach ($workspaceFields as $field) {
                $fieldMap[$field["id"]] = $field;
            }

            $projectFieldMapping = array();
        } ?>

		<h2>Custom fields</h2>
		<table cellpadding="5">
			<thead>
				<tr>
					<th style="width: 20%;">Source project</th>
					<th>Target workspace</th>
				</tr>
			</thead>
			<tbody>
				<?php

            if ($workspace1) {
                $projectFields = getAllCustomFieldSettings($projectIds);
                foreach ($projectFields as $projectField) {
                    $customField = $projectField["custom_field"]; ?>

					<tr>
						<td>
							<?php echo $customField["name"]?> (
							<?php echo $customField["type"]?> )
						</td>
						<td>
							<?php 
                    $workspaceField = $fieldMap[$customField["id"]];
                    if ($workspaceField) {
                        if ($workspaceField["type"] == $customField["type"]) {
                            if ($customField["type"] == "enum") {
                                $optionMap = array();
                                foreach ($workspaceField["enum_options"] as $option) {
                                    $optionMap[$option["name"]] = $option;
                                }
                                $fieldOptionMapping = array();

                                $allMatch = true;
                                foreach ($customField["enum_options"] as $option) {
                                    $targetOption = $optionMap[$option["name"]];
                                    if ($targetOption) {
                                        $fieldOptionMapping[$option["id"]] = $targetOption["id"];
                                        continue;
                                    }
                                    echo "<div>".$option["name"]." option missing from target enum</div>";
                                    $allMatch = false;
                                }
                                if ($allMatch) {
                                    echo "Matches all enum options";
                                } else {
                                    echo "Available options:<br>". implode(", ", array_keys($optionMap));
                                }
                                $projectFieldMapping[$customField["id"]] = array("id" => $workspaceField["id"], "options" => $fieldOptionMapping);
                            } else {
                                echo "Matches target field type";
                                $projectFieldMapping[$customField["id"]] = array("id" => $workspaceField["id"]);
                            }
                        } else {
                            echo "Found field, but type does not match";
                        }
                    } else {
                        echo "Not matched";
                    } ?>
						</td>
					</tr>
					<?php
                }
            } ?>
			</tbody>
		</table>
		<?php
    }

    echo '<h2>Field mapping</h2>';
    echo '<pre>'.print_r($projectFieldMapping, true)."</pre>";
    ?>