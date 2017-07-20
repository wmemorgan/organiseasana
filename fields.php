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
        pre($result, "Error loading custom fields", 'danger');
        return;
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


function getAllCustomFieldSettings($projectId) {
    $cursor = null;
    $limit = 100;

    $settings = array();
    do {
        $page = getCustomFieldSettings($projectId, $cursor, $limit);
        if ($page) {
            $settings = array_merge($settings, $page);
        }
    } while ($cursor);

    return $settings;
}

function getCustomFieldSettings($projectId, &$cursor, $limit = 10) {
    $path = "projects/$projectId/custom_field_settings?";
    if ($limit) {
        $path .= "&limit=$limit";
    }
    if ($cursor) {
        $path .= "&offset=$cursor";
    }

    $result = asanaRequest($path);
    if (isError($result)) {
        pre($result, "Error loading custom field settings", 'danger');
        return;
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
