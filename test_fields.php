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
		
		del {
			color: red;
			text-decoration: none;
		}
		
		ins {
			color: rgb(0, 200, 0);
			text-decoration: none;
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

    if ($workspaceId) {
        $workspace = getWorkspace($workspaceId);
        echo '<h1>Destination workspace: '.$workspace['name'].'</h1>';
        $workspaceFields = getAllCustomFields($workspaceId);
        $fieldMap = array();
        foreach ($workspaceFields as $field) {
            $fieldName = $field["name"];
            if (isset($fieldMap[$fieldName])) {
                echo "<p><del>Duplicate field found: $fieldName</del></p>";
            }
            $fieldMap[$fieldName] = $field;
        }

        echo "<p>Found " . sizeof($fieldMap) . " fields</p>";
    }

    $projectFieldMapping = array();
    if ($projectIds) {
        foreach ($projectIds as $projectId) {
            $project = getProject($projectId);
            echo "<h1>".$project['name']."</h1>";
            echo "<p>Workspace <strong>".$project['workspace']['name']."</strong></p>";
            $workspace1 = $project['workspace']['id']; ?>

		<h2>Custom fields</h2>
		<table cellpadding="5" border="1">
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
							<br><small><?php echo $customField["id"]?></small>
						</td>
						<td>
							<?php 
                    $workspaceField = $fieldMap[$customField["name"]];
                    if ($workspaceField) {
                        $targetFieldId = $workspaceField["id"];
                        if ($workspaceField["type"] == $customField["type"]) {
                            if ($customField["type"] == "enum") {
                                $optionMap = array();
                                foreach ($workspaceField["enum_options"] as $option) {
                                    if (!$option['enabled']) {
                                        continue;
                                    }
                                    $optionName = $option["name"];
                                    if (isset($optionMap[$optionName])) {
                                        echo "<p><del>Duplicate field option found: $optionName</del></p>";
                                    }
                                    $optionMap[$optionName] = $option;
                                }
                                $fieldOptionMapping = array();

                                $allMatch = true;
                                foreach ($customField["enum_options"] as $option) {
                                    $optionName = $option["name"];
                                    $optionId = $option["id"];

                                    $targetOption = $optionMap[$optionName];
                                    if ($targetOption) {
                                        $targetOptionId = $targetOption["id"];
                                        $fieldOptionMapping[$optionId] = $targetOptionId;
                                        echo "<div>$optionName option <ins>matched</ins> &nbsp;&nbsp;&nbsp;&nbsp;<small>($optionId => $targetOptionId)</small></div>";
                                    } else {
                                        echo "<div>$optionName option <del>missing from target enum</del> &nbsp;&nbsp;&nbsp;&nbsp;<small>($optionId => ?)</small></div>";
                                        $allMatch = false;
                                    }
                                }
                                if ($allMatch) {
                                    echo "<p><ins>Matches all enum options</ins></p>";
                                } else {
                                    echo "<p><strong>Available options:</strong></p><ul><li>". implode("</li><li>", array_keys($optionMap)) . '</li></ul>';
                                }
                                $projectFieldMapping[$customField["id"]] = array("id" => $targetFieldId, "options" => $fieldOptionMapping);
                            } else {
                                echo "<ins>Matches target field type</ins>";
                                $projectFieldMapping[$customField["id"]] = array("id" => $targetFieldId);
                            }
                        } else {
                            echo "Found field, but <del>type does not match</del>";
                        }
                        echo " &nbsp;&nbsp;&nbsp;&nbsp;<small>($targetFieldId)</small>";
                    } else {
                        echo "<del>Not matched</del>";
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
    }

    echo '<h2>Field mapping</h2>';
    echo '<pre>'.print_r($projectFieldMapping, true)."</pre>";
    ?>