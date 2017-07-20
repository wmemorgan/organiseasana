<?php

include "init.php";
include "asana.php";

$workspaceId = $_GET['workspaceId'];

?>
	<form>
		<label>Workspace ID <input type="text" name="workspaceId" value="<?php echo $workspaceId ?>"></label>
	</form>

	<?php
if ($workspaceId) {
    $fields = getAllCustomFields($workspaceId);
    print_r($fields);
}
