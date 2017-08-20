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

    // Get the custom fields which need mapping
    $sourceFieldAssociations = getAllCustomFieldSettings($projectIds);
    $workspaceFields = getAllCustomFields($workspaceId);

    // Index target fields by name
    $targetFields = array();
    foreach ($workspaceFields as $field) {
        $targetFields[$field["name"]] = $field;
    }
    
    // Locate the correct target field for each source field
    $fieldMapping = array();
    $invalid = array();
    foreach ($sourceFieldAssociations as $sourceFieldAssociation) {
        // The source field type of each project field association
        $sourceField = $sourceFieldAssociation["custom_field"];
        $targetField = $targetFields[$sourceField["name"]];

        if ($targetField) {
            // Source and target must have the same field type
            if ($targetField["type"] == $sourceField["type"]) {

                // Enums
                if ($sourceField["type"] == "enum") {

                    // Index target enum options by name
                    $optionMap = array();
                    foreach ($targetField["enum_options"] as $option) {
                        if ($option["enabled"]) {
                            $optionMap[$option["name"]] = $option;
                        }
                    }

                    // Find corresponding enum option for each source option
                    $fieldOptionMapping = array();
                    $allMatch = true;
                    foreach ($sourceField["enum_options"] as $sourceOption) {
                        if (!$sourceOption['enabled']) {
                            continue;
                        }
                        $targetOption = $optionMap[$sourceOption["name"]];
                        if ($targetOption) {
                            $fieldOptionMapping[$sourceOption["id"]] = $targetOption["id"];
                        } else {
                            $invalid[] = $sourceOption["name"]." option missing from target enum " . $targetField["name"];
                            $allMatch = false;
                        }
                    }
                    if (!$allMatch) {
                        $invalid[] = "Available options: ". implode(", ", array_keys($optionMap));
                    }
                    $fieldMapping[$sourceField["id"]] = array("id" => $targetField["id"], "options" => $fieldOptionMapping);
                }
                
                // Other field types
                else {
                    $fieldMapping[$sourceField["id"]] = array("id" => $targetField["id"]);
                }
            } else {
                $invalid[] = "Found field ".$sourceField["name"]." with type " . $targetField["type"]
                    . ", but should be ".$sourceField["type"];
            }
        } else {
            $invalid[] = "Did not match field ".$sourceField["name"];
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
    // If we have no fields in the target workspace, we can't assign them
    if (!$customFieldMapping) {
        unset($task["custom_fields"]);
        return;
    }

    // Examine each custom field value and find mapped equivalent
    $newFields = array();
    foreach ($task["custom_fields"] as $sourceFieldValue) {
        $targetFieldMapping = $customFieldMapping[$sourceFieldValue["id"]];
        if (!$targetFieldMapping) {
            continue;
        }

        $targetFieldId = $targetFieldMapping["id"];
        $sourceType = $sourceFieldValue["type"];
        // Mapping depends on type of field
        if ($sourceType == "enum") {
            // Check if this field has a value set
            $enumValue = $sourceFieldValue["enum_value"];
            if (!$enumValue) {
                continue;
            }

            // Find the target mapping for this enum value
            $targetValue = $targetFieldMapping["options"][$enumValue["id"]];
            if (!$targetValue) {
                continue;
            }
            $newFields[$targetFieldId] = $targetValue;
        } elseif ($sourceType == "number") {
            $newFields[$targetFieldId] = $sourceFieldValue["number_value"];
        } elseif ($sourceType == "text") {
            $newFields[$targetFieldId] = $sourceFieldValue["text_value"];
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
