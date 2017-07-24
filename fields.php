<?php

function getAllCustomFields($workspaceId) {
    $cursor = null;
    $limit = 100;

    $fields = array();
    do {
        $page = getCustomFields($workspaceId, $cursor, $limit);
        if ($page) {
            $fields = array_merge($fields, $page);
        }
    } while ($cursor);

    return $fields;
}

function getCustomFields($workspaceId, &$cursor, $limit = 10) {
    $path = "workspaces/$workspaceId/custom_fields?";
    if ($limit) {
        $path .= "&limit=$limit";
    }
    if ($cursor) {
        $path .= "&offset=$cursor";
    }

    $result = asanaRequest($path);
    if (isError($result)) {
        if ($result["errors"][0]["message"] == "Custom Fields are not available for free users or guests.") {
            return array();
        } else {
            pre($result["errors"][0], "Error loading custom fields", 'danger');
            return;
        }
    }

    if ($result['next_page']) {
        $cursor = $result['next_page']['offset'];
    } else {
        $cursor = false;
    }

    return $result['data'];
}

function getCustomField($fieldId) {
    $result = asanaRequest("custom_fields/$fieldId");
    if (isError($result)) {
        pre($result, "Error loading custom field", 'danger');
        return;
    }

    return $result['data'];
}


function getAllCustomFieldSettings($projectIds) {
    $limit = 100;

    if ($projectIds == null) {
        return null;
    }
    
    if (!is_array($projectIds)) {
        $projectIds = array($projectIds);
    }

    foreach ($projectIds as $projectId) {
        $cursor = null;

        $settings = array();
        do {
            $page = getCustomFieldSettings($projectId, $cursor, $limit);
            if ($page) {
                foreach ($page as $setting) {
                    $settings[$setting["id"]] = $setting;
                }
            }
        } while ($cursor);
    }

    return $settings;
}

function getCustomFieldSettings($projectId, &$cursor, $limit = 10) {
    $path = "projects/$projectId/custom_field_settings?expand=custom_field";
    if ($limit) {
        $path .= "&limit=$limit";
    }
    if ($cursor) {
        $path .= "&offset=$cursor";
    }

    $result = asanaRequest($path);
    if (isError($result)) {
        if ($result["errors"][0]["message"] == "Custom Field Settings are not available for free users.") {
            return array();
        } else {
            pre($result["errors"][0], "Error loading custom field settings", 'danger');
            return;
        }
    }

    if ($result['next_page']) {
        $cursor = $result['next_page']['offset'];
    } else {
        $cursor = false;
    }

    return $result['data'];
}

function addCustomFieldSetting($projectId, $customField, $isImportant = false, $insertBefore = null) {
    $data = array('data' => array(
        'custom_field' => $customField,
        'is_important' => $isImportant,
        'insert_before' => $insertBefore,
    ));
    $result = asanaRequest("projects/$projectId/addCustomFieldSetting", 'POST', $data);
}

// Given some source projects and a target workspace, determines the mapping between
// custom fields
function mapCustomFields($projectIds, $workspaceId, &$invalid) {
    if (!$projectIds || !count($projectIds)) {
        return null;
    }
    if (!$workspaceId) {
        return null;
    }

    // Get source project custom fields
    $sourceFields = getAllCustomFieldSettings($projectIds);
    $workspaceFields = getAllCustomFields($workspaceId);
    $invalid = array();

    $targetFields = array();
    foreach ($workspaceFields as $field) {
        $targetFields[$field["name"]] = $field;
    }
    $fieldMapping = array();
        
    foreach ($sourceFields as $sourceField) {
        $customField = $sourceField["custom_field"];
        $workspaceField = $targetFields[$customField["name"]];
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
                        $invalid[] = $option["name"]." option missing from target enum " + $customField["name"];
                        $allMatch = false;
                    }
                    if (!$allMatch) {
                        $invalid[] = "Available options: ". implode(", ", array_keys($optionMap));
                    }
                    $fieldMapping[$customField["id"]] = array("id" => $workspaceField["id"], "options" => $fieldOptionMapping);
                } else {
                    $fieldMapping[$customField["id"]] = array("id" => $workspaceField["id"]);
                }
            } else {
                $invalid[] = "Found field ".$customField["name"]." with type " . $workspaceField["type"]
                    . ", but should be ".$customField["type"];
            }
        } else {
            $invalid[] = "Did not match field ".$customField["name"];
        }
    }

    return $fieldMapping;
}

// Using the field mapping, add the mapped field settings to the target project
function addProjectFields($sourceProject, $targetProjectId, $customFieldMapping) {
    // Get settings
    $customFieldSettings = $sourceProject["custom_field_settings"];
    if (!$customFieldSettings) {
        return;
    }

    foreach ($customFieldSettings as $setting) {
        $targetFieldMapping = $customFieldMapping[$setting["custom_field"]["id"]];
        if (!$targetFieldMapping) {
            continue;
        }

        addCustomFieldSetting($targetProjectId, $targetFieldMapping["id"], $setting["is_important"]);
    }
}

function remapCustomFields(&$task, $customFieldMapping) {
    if (!$customFieldMapping) {
        unset($task["custom_fields"]);
        return;
    }

    $newFields = array();
    foreach ($task["custom_fields"] as $field) {
        $targetFieldMapping = $customFieldMapping[$field["id"]];
        if (!$targetFieldMapping) {
            continue;
        }

        $targetFieldId = $targetFieldMapping["id"];
        if ($field["type"] == "enum" && $field["enum_value"]) {
            $targetValue = $targetFieldMapping["options"][$field["enum_value"]["id"]];
            if ($targetValue) {
                $newFields[$targetFieldId] = $targetValue;
            }
        } elseif ($field["type"] == "number") {
            $newFields[$targetFieldId] = $field["number_value"];
        } elseif ($field["type"] == "text") {
            $newFields[$targetFieldId] = $field["text_value"];
        }
    }

    $task["custom_fields"] = $newFields;
    return $newFields;
}

function setCustomFields($taskId, $newFields) {
    if (!$newFields || !count($newFields)) {
        return;
    }
    
    $result = asanaRequest("tasks/$taskId", "PUT", array("data" => array("custom_fields" => $newFields)));
    if (isError($result)) {
        pre($result, "Error setting custom fields", 'danger');
        return;
    }

    return $result['data'];
}
